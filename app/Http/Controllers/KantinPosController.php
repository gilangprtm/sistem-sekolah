<?php

namespace App\Http\Controllers;

use App\Models\KantinBarang;
use App\Models\KantinCategory;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KantinPosController extends Controller
{
    public function identify(Request $request): JsonResponse
    {
        $validated = $request->validate(['qr' => ['required', 'string', 'email', 'max:255']]);
        $student = Student::query()
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('email', $validated['qr']))
            ->with('kantinSaldo:id,student_id,saldo')
            ->first();

        if ($student === null) {
            return response()->json(['message' => 'Kartu pelajar tidak ditemukan atau siswa tidak aktif.'], 404);
        }

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name,
                'nis' => $student->nis,
                'balance' => $student->kantinSaldo?->saldo ?? '0.00',
            ],
        ]);
    }

    public function index(): Response
    {
        return Inertia::render('kantin/pos/index', [
            'categories' => KantinCategory::query()->orderBy('name')->get(['id', 'name']),
            'products' => KantinBarang::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'kantin_kategori_id', 'name', 'brand', 'satuan', 'harga']),
        ]);
    }
}
