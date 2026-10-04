<?php

namespace App\Http\Controllers;

use App\Http\Requests\RombelRequest;
use App\Models\Rombel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RombelController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $perPage = min(max($request->integer('per_page', 25), 1), 50);
        $rombels = Rombel::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('grade_level')->orderBy('parallel_code')->orderBy('id')
            ->paginate($perPage)->withQueryString();

        return Inertia::render('rombels/index', [
            'rombels' => $rombels,
            'filters' => $request->only(['search', 'status', 'per_page']),
            'filterOptions' => ['statuses' => [['value' => 'active', 'label' => 'Aktif'], ['value' => 'inactive', 'label' => 'Arsip']]],
        ]);
    }

    public function store(RombelRequest $request): RedirectResponse
    {
        try {
            Rombel::query()->create(array_merge($request->safe()->except(['code']), ['code' => $request->validated('grade_level').'-'.$request->validated('parallel_code'), 'status' => $request->validated('status', 'active')]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['parallel_code' => 'Kode Rombel sudah digunakan.']);
        }

        return redirect()->route('rombels.index')->with('success', 'Rombel berhasil dibuat.');
    }

    public function update(RombelRequest $request, Rombel $rombel): RedirectResponse
    {
        try {
            $rombel->update(array_merge($request->safe()->except(['code']), ['code' => $request->validated('grade_level').'-'.$request->validated('parallel_code'), 'status' => $request->validated('status', $rombel->status)]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['parallel_code' => 'Kode Rombel sudah digunakan.']);
        }

        return redirect()->route('rombels.index')->with('success', 'Rombel berhasil diperbarui.');
    }

    public function archive(Rombel $rombel): RedirectResponse
    {
        $rombel->update(['status' => 'inactive']);

        return redirect()->route('rombels.index')->with('success', 'Rombel diarsipkan.');
    }
}
