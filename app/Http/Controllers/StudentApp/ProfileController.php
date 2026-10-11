<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\StudentKantinPinRequest;
use App\Http\Requests\StudentKantinPinResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user()->load('student');
        $student = $user->student;

        return Inertia::render('student-app/profile', [
            'user' => $user->only(['name', 'email']),
            'student' => $student === null ? null : array_merge(
                $student->only([
                    'nis',
                    'nisn',
                    'tahun_angkatan',
                    'full_name',
                    'gender',
                    'birth_place',
                    'birth_date',
                    'address',
                    'status',
                ]),
                [
                    'photo_url' => $this->photoUrl($student->photo_path),
                    'has_kantin_pin' => $student->kantin_pin_hash !== null,
                ],
            ),
        ]);
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password berhasil diperbarui.']);

        return back();
    }

    public function updateKantinPin(StudentKantinPinRequest $request): RedirectResponse
    {
        $student = $request->user()->student;

        if ($student === null) {
            return back()->withErrors(['pin' => 'Akun ini belum terhubung dengan data siswa.']);
        }

        $hadPin = $student->kantin_pin_hash !== null;
        $student->update(['kantin_pin_hash' => Hash::make($request->string('pin')->toString())]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $hadPin ? 'PIN Kantin berhasil diganti.' : 'PIN Kantin berhasil dibuat.',
        ]);

        return back();
    }

    public function resetKantinPin(StudentKantinPinResetRequest $request): RedirectResponse
    {
        $student = $request->user()->student;

        if ($student === null) {
            return back()->withErrors(['current_password' => 'Akun ini belum terhubung dengan data siswa.']);
        }

        $student->update(['kantin_pin_hash' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'PIN Kantin berhasil direset.']);

        return back();
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:1024'],
        ]);

        $student = $request->user()->student;

        if ($student === null) {
            return back()->withErrors([
                'photo' => 'Akun ini belum terhubung dengan data siswa.',
            ]);
        }

        $photo = $request->file('photo');

        if (! $photo instanceof UploadedFile) {
            return back()->withErrors([
                'photo' => 'Foto tidak dapat dibaca. Silakan pilih ulang foto.',
            ]);
        }

        $oldPhotoPath = $student->photo_path;
        $newPhotoPath = $photo->store("students/{$student->id}", 'public');

        if (! is_string($newPhotoPath)) {
            return back()->withErrors([
                'photo' => 'Foto tidak dapat disimpan. Silakan coba lagi.',
            ]);
        }

        $student->update(['photo_path' => $newPhotoPath]);

        if ($oldPhotoPath !== null) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto profil berhasil diperbarui.']);

        return back();
    }

    private function photoUrl(?string $photoPath): ?string
    {
        return $photoPath === null
            ? null
            : Storage::disk('public')->url($photoPath);
    }
}
