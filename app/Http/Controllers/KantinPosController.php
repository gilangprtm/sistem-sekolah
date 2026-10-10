<?php

namespace App\Http\Controllers;

use App\Models\KantinBarang;
use App\Models\KantinCategory;
use App\Models\Student;
use App\Services\KantinPosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class KantinPosController extends Controller
{
    private const VERIFICATION_TOKEN_TTL_MINUTES = 10;

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

        $request->session()->put('pos_student', $student->id);
        $verificationToken = bin2hex(random_bytes(32));
        Cache::put(
            $this->verificationTokenKey($verificationToken),
            $student->id,
            now()->addMinutes(self::VERIFICATION_TOKEN_TTL_MINUTES),
        );

        return response()->json([
            'verification_token' => $verificationToken,
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name,
                'nis' => $student->nis,
                'balance' => $student->kantinSaldo?->saldo ?? '0.00',
            ],
        ]);
    }

    public function checkout(Request $request, KantinPosService $service): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'student_id' => ['prohibited'],
            'verification_token' => ['nullable', 'string', 'size:64'],
        ]);

        $sessionStudentId = $request->session()->get('pos_student');
        $tokenStudentId = null;
        if ($data['verification_token'] ?? null) {
            $tokenStudentId = Cache::get($this->verificationTokenKey($data['verification_token']));
        }

        if ($sessionStudentId && $tokenStudentId && (int) $sessionStudentId !== (int) $tokenStudentId) {
            return response()->json(['message' => 'Sesi kartu tidak valid. Silakan scan ulang.'], 403);
        }

        $id = $sessionStudentId ?? $tokenStudentId;
        if (! $id) {
            return response()->json(['message' => 'Scan kartu terlebih dahulu.'], 403);
        }

        $student = Student::query()->whereKey($id)->where('status', 'active')->firstOrFail();
        $result = $service->checkout($student, $data['items']);
        if ($data['verification_token'] ?? null) {
            Cache::forget($this->verificationTokenKey($data['verification_token']));
        }
        $request->session()->forget('pos_student');

        return response()->json(['message' => 'Pembayaran berhasil.', ...$result]);
    }

    public function exit(Request $request): JsonResponse
    {
        $request->session()->forget('pos_student');

        return response()->json(['message' => 'Sesi ditutup.']);
    }

    private function verificationTokenKey(string $token): string
    {
        return 'kantin-pos-verification:'.hash('sha256', $token);
    }

    public function index(): Response
    {
        return Inertia::render('kantin/pos/index', [
            'categories' => KantinCategory::query()->orderBy('name')->get(['id', 'name']),
            'products' => KantinBarang::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'kantin_kategori_id', 'name', 'brand', 'satuan', 'harga', 'image_path'])
                ->map(function (KantinBarang $product): array {
                    $data = $product->toArray();
                    unset($data['image_path']);

                    return [
                        ...$data,
                        'image_url' => $product->imageUrl(),
                    ];
                }),
        ]);
    }
}
