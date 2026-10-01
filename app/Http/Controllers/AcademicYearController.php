<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
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

        return back()->with('success', 'Tahun Ajaran berhasil dibuat.');
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $request->validate(['year' => ['required', 'regex:/^[0-9]{4}\/[0-9]{4}$/', Rule::unique('m_academic_years', 'year')->ignore($academicYear->id)], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        DB::transaction(function () use ($data, $academicYear): void {
            $academicYear->update(['year' => $data['year']]);
            $data['status'] === 'active' ? $academicYear->activate() : $academicYear->deactivate();
        });

        return back()->with('success', 'Tahun Ajaran diperbarui.');
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

        return back()->with('success', 'Semester berhasil dibuat.');
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

        return back()->with('success', 'Semester diperbarui.');
    }
}
