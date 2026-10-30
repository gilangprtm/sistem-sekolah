<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\HomeroomSeat;
use App\Models\StudentPlacement;
use App\Models\UasLink;
use App\Models\YearClass;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $years = AcademicYear::query()
            ->with('periods')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('year', 'like', "%{$search}%")
                        ->orWhereHas('periods', function ($periodQuery) use ($search): void {
                            $periodQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('year')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('academic-years/index', [
            'years' => $years,
            'canDelete' => $request->user()->can('curriculum.delete'),
            'filters' => $request->only(['search', 'status', 'per_page']),
            'filterOptions' => [
                'statuses' => [
                    ['value' => 'active', 'label' => 'Aktif'],
                    ['value' => 'inactive', 'label' => 'Tidak aktif'],
                ],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['year' => ['required', 'regex:/^[0-9]{4}\/[0-9]{4}$/', 'unique:m_academic_years,year'], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        DB::transaction(function () use ($data): void {
            $year = AcademicYear::query()->create([
                'year' => $data['year'],
                'status' => 'inactive',
            ]);
            if ($data['status'] === 'active') {
                $year->activate();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tahun Ajaran berhasil dibuat.']);

        return back();
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $request->validate(['year' => ['required', 'regex:/^[0-9]{4}\/[0-9]{4}$/', Rule::unique('m_academic_years', 'year')->ignore($academicYear->id)], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        DB::transaction(function () use ($data, $academicYear): void {
            $academicYear->update(['year' => $data['year']]);
            $data['status'] === 'active' ? $academicYear->activate() : $academicYear->deactivate();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tahun Ajaran diperbarui.']);

        return back();
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        $usedMessage = 'Tahun Ajaran sudah digunakan oleh data akademik dan tidak dapat dihapus.';

        try {
            DB::transaction(function () use ($academicYear, $usedMessage): void {
                $year = AcademicYear::query()->lockForUpdate()->findOrFail($academicYear->id);

                if ($year->status === 'active') {
                    throw ValidationException::withMessages([
                        'year' => 'Tahun Ajaran aktif tidak dapat dihapus. Nonaktifkan terlebih dahulu.',
                    ]);
                }

                $periodIds = $year->periods()->lockForUpdate()->pluck('id');

                foreach ([YearClass::class, StudentPlacement::class, HomeroomAssignment::class, HomeroomSeat::class] as $model) {
                    if ($model::query()->where('academic_year_id', $year->id)->exists()) {
                        throw ValidationException::withMessages(['year' => $usedMessage]);
                    }
                }

                if (UasLink::query()->where('academic_year_id', $year->id)->orWhereIn('academic_period_id', $periodIds)->exists()) {
                    throw ValidationException::withMessages(['year' => $usedMessage]);
                }

                $year->delete();
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23503' && ! str_contains(strtolower($exception->getMessage()), 'foreign key constraint failed')) {
                throw $exception;
            }

            throw ValidationException::withMessages(['year' => $usedMessage]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tahun Ajaran berhasil dihapus.']);

        return back();
    }

    public function storePeriod(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $request->validate([
            'code' => [
                'required',
                Rule::in(['ganjil', 'genap']),
                Rule::unique('m_academic_periods', 'code')->where('academic_year_id', $academicYear->id),
            ],
            'name' => ['required', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($data['status'] === 'active' && $academicYear->status !== 'active') {
            throw ValidationException::withMessages(['status' => 'Semester hanya dapat diaktifkan pada Tahun Ajaran aktif.']);
        }

        try {
            DB::transaction(function () use ($academicYear, $data): void {
                $period = $academicYear->periods()->create(['code' => $data['code'], 'name' => $data['name'], 'status' => 'inactive']);
                if ($data['status'] === 'active') {
                    $period->activate();
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Semester dengan kode tersebut sudah ada pada Tahun Ajaran ini.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Semester berhasil dibuat.']);

        return back();
    }

    public function updatePeriod(Request $request, AcademicPeriod $academicPeriod): RedirectResponse
    {
        $data = $request->validate([
            'code' => [
                'required',
                Rule::in(['ganjil', 'genap']),
                Rule::unique('m_academic_periods', 'code')
                    ->where('academic_year_id', $academicPeriod->academic_year_id)
                    ->ignore($academicPeriod->id),
            ],
            'name' => ['required', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($data['status'] === 'active' && $academicPeriod->academicYear->status !== 'active') {
            throw ValidationException::withMessages(['status' => 'Semester hanya dapat diaktifkan pada Tahun Ajaran aktif.']);
        }

        try {
            DB::transaction(function () use ($academicPeriod, $data): void {
                $academicPeriod->update(['code' => $data['code'], 'name' => $data['name']]);
                $data['status'] === 'active' ? $academicPeriod->activate() : $academicPeriod->deactivate();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Semester dengan kode tersebut sudah ada pada Tahun Ajaran ini.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Semester diperbarui.']);

        return back();
    }
}
