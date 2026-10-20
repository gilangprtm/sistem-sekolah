<?php

namespace App\Http\Controllers;

use App\Models\KantinBarang;
use App\Models\KantinCategory;
use App\Models\Student;
use App\Services\KantinPosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class KantinPosController extends Controller
{
    private const VERIFICATION_TOKEN_TTL_MINUTES = 10;

    private const PIN_CHALLENGE_TTL_MINUTES = 5;

    private const MAX_PIN_ATTEMPTS = 3;

    public function identify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr' => ['required_without:nisn', 'prohibits:nisn', 'nullable', 'string', 'email', 'max:255'],
            'nisn' => ['required_without:qr', 'prohibits:qr', 'nullable', 'string', 'max:20'],
        ]);
        $studentQuery = Student::query()->where('status', 'active');

        if (filled($validated['nisn'] ?? null)) {
            $studentQuery->where('nisn', $validated['nisn'])->has('user');
        } else {
            $studentQuery->whereHas('user', fn ($query) => $query->where('email', $validated['qr']));
        }

        $student = $studentQuery->with('kantinSaldo:id,student_id,saldo')->first();

        if ($student === null) {
            return response()->json(['message' => 'Kartu pelajar tidak ditemukan atau siswa tidak aktif.'], 404);
        }

        if ($student->kantin_pin_hash === null) {
            return response()->json(['message' => 'Buat PIN terlebih dulu di aplikasi Portal Siswa'], 409);
        }

        $challengeToken = bin2hex(random_bytes(32));
        Cache::put(
            $this->pinChallengeKey($challengeToken),
            [
                'student_id' => $student->id,
                'session_binding' => $this->sessionBinding($request),
                'attempts' => 0,
            ],
            now()->addMinutes(self::PIN_CHALLENGE_TTL_MINUTES),
        );

        return response()->json([
            'pin_required' => true,
            'challenge_token' => $challengeToken,
        ]);
    }

    public function verifyPin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string', 'size:64'],
            'pin' => ['required', 'digits:6'],
        ]);
        $key = $this->pinChallengeKey($data['challenge_token']);
        $challenge = Cache::get($key);

        if (! is_array($challenge) || ($challenge['session_binding'] ?? null) !== $this->sessionBinding($request)) {
            return response()->json(['message' => 'Sesi PIN tidak valid. Silakan scan ulang.'], 403);
        }

        $student = Student::query()->with('kantinSaldo:id,student_id,saldo')->whereKey($challenge['student_id'] ?? 0)->where('status', 'active')->first();
        if ($student === null || $student->kantin_pin_hash === null) {
            Cache::forget($key);

            return response()->json(['message' => 'Sesi PIN tidak valid. Silakan scan ulang.'], 403);
        }

        if (! Hash::check($data['pin'], $student->kantin_pin_hash)) {
            $attempts = ((int) ($challenge['attempts'] ?? 0)) + 1;

            if ($attempts >= self::MAX_PIN_ATTEMPTS) {
                Cache::forget($key);
                $request->session()->forget('pos_student');

                return response()->json(['message' => 'PIN salah 3 kali. Silakan scan ulang.'], 429);
            }

            $challenge['attempts'] = $attempts;
            Cache::put($key, $challenge, now()->addMinutes(self::PIN_CHALLENGE_TTL_MINUTES));

            return response()->json(['message' => 'PIN salah. Silakan coba lagi.', 'attempts_remaining' => self::MAX_PIN_ATTEMPTS - $attempts], 422);
        }

        Cache::forget($key);
        $request->session()->put('pos_student', $student->id);
        $verificationToken = bin2hex(random_bytes(32));
        Cache::put(
            $this->verificationTokenKey($verificationToken),
            ['student_id' => $student->id, 'session_binding' => $this->sessionBinding($request)],
            now()->addMinutes(self::VERIFICATION_TOKEN_TTL_MINUTES),
        );

        return response()->json([
            'verification_token' => $verificationToken,
            'student' => $this->studentPayload($student),
        ]);
    }

    public function checkout(Request $request, KantinPosService $service): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'student_id' => ['prohibited'],
            'verification_token' => ['required', 'string', 'size:64'],
        ]);
        $token = Cache::get($this->verificationTokenKey($data['verification_token']));
        $sessionStudentId = $request->session()->get('pos_student');

        if (! is_array($token) || ($token['session_binding'] ?? null) !== $this->sessionBinding($request) || ! $sessionStudentId || (int) $sessionStudentId !== (int) ($token['student_id'] ?? 0)) {
            return response()->json(['message' => 'Sesi kartu tidak valid. Silakan scan ulang.'], 403);
        }

        $student = Student::query()->whereKey($token['student_id'])->where('status', 'active')->firstOrFail();
        $result = $service->checkout($student, $data['items']);
        Cache::forget($this->verificationTokenKey($data['verification_token']));
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

    private function pinChallengeKey(string $token): string
    {
        return 'kantin-pos-pin-challenge:'.hash('sha256', $token);
    }

    private function sessionBinding(Request $request): string
    {
        $binding = $request->session()->get('pos_pin_binding');

        if (! is_string($binding)) {
            $binding = bin2hex(random_bytes(32));
            $request->session()->put('pos_pin_binding', $binding);
        }

        return hash('sha256', $binding);
    }

    /**
     * @return array{id: int, name: string, nis: string|null, balance: string}
     */
    private function studentPayload(Student $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->full_name,
            'nis' => $student->nis,
            'balance' => $student->kantinSaldo->saldo ?? '0.00',
        ];
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
