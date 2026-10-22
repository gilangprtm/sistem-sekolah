<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTeacherAgendaRequest;
use App\Models\AcademicPeriod;
use App\Models\Teacher;
use App\Models\TeacherAgenda;
use App\Services\StudentAttendanceService;
use App\Services\TeacherAgendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function index(Request $request, TeacherAgendaService $service): Response
    {
        $date = $this->date($request);
        $pagination = $request->validate(['per_page' => ['sometimes', 'integer', Rule::in([10, 20, 30, 40, 50])]]);
        $teacher = $request->user()->teacher;
        $period = $service->activePeriod();
        $saved = $teacher === null ? collect() : TeacherAgenda::query()
            ->where('teacher_id', $teacher->id)->whereDate('agenda_date', $date)->get();
        $blocks = $teacher === null || $period === null ? [] : array_map(
            fn (array $block): array => [
                ...$block,
                'agenda_id' => $saved->firstWhere('block_key', $block['block_key'])?->id,
            ], $service->blocks($teacher, $period, $date),
        );

        return Inertia::render('teacher/agenda/index', [
            'date' => $date,
            'hasTeacherProfile' => $teacher !== null,
            'hasActivePeriod' => $period !== null,
            'canManage' => $request->user()->can('teacher.agenda.manage'),
            'blocks' => $blocks,
            'history' => $teacher === null ? null : TeacherAgenda::query()
                ->where('teacher_id', $teacher->id)
                ->orderByDesc('agenda_date')->orderByDesc('id')
                ->paginate((int) ($pagination['per_page'] ?? 10))->withQueryString()
                ->through(fn (TeacherAgenda $agenda): array => [
                    'id' => $agenda->id,
                    'date' => $agenda->agenda_date->format('Y-m-d'),
                    'snapshot' => $agenda->schedule_snapshot,
                    'material' => $agenda->material,
                ]),
        ]);
    }

    public function create(Request $request, TeacherAgendaService $service, StudentAttendanceService $attendance): Response|RedirectResponse
    {
        $date = $this->date($request);
        $data = $request->validate(['block_key' => ['required', 'string', 'size:64']]);
        $teacher = $this->teacher($request);
        $saved = TeacherAgenda::query()->where('teacher_id', $teacher->id)
            ->whereDate('agenda_date', $date)->where('block_key', $data['block_key'])->first();
        if ($saved !== null) {
            return to_route('teacher.agenda.edit', $saved);
        }
        $period = $service->activePeriod();
        abort_if($period === null, 422, 'Semester aktif belum tersedia.');
        $context = $service->context($teacher, $period, $date, $data['block_key']);

        return Inertia::render('teacher/agenda/form', [
            'agenda' => [
                'id' => null,
                'date' => $date,
                ...$context,
                'material' => '',
                'attendance' => $attendance->rows($period->academic_year_id, $date, $context['students']),
                'canManageAttendance' => $request->user()->can('teacher.attendance.manage'),
            ],
        ]);
    }

    public function store(SaveTeacherAgendaRequest $request, TeacherAgendaService $service): RedirectResponse
    {
        $data = $request->validated();
        $teacher = $this->teacher($request);
        $agenda = DB::transaction(function () use ($teacher, $data, $service): TeacherAgenda {
            $period = $service->activePeriod();
            abort_if($period === null, 422, 'Semester aktif belum tersedia.');
            $period = AcademicPeriod::query()->lockForUpdate()->findOrFail($period->id);
            Teacher::query()->whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $context = $service->context($teacher, $period, $data['agenda_date'], $data['block_key']);
            if (! hash_equals($context['context_token'], $data['context_token'])) {
                throw ValidationException::withMessages(['context_token' => 'Jadwal atau daftar siswa berubah. Muat ulang sebelum menyimpan.']);
            }
            if (TeacherAgenda::query()->where('teacher_id', $teacher->id)
                ->whereDate('agenda_date', $data['agenda_date'])->where('block_key', $data['block_key'])->exists()) {
                throw ValidationException::withMessages(['block_key' => 'Agenda blok ini sudah tersimpan. Buka melalui riwayat untuk mengedit.']);
            }

            if ($context['students'] === []) {
                throw ValidationException::withMessages(['attendance' => 'Belum ada siswa aktif pada kelas ini. Lengkapi penempatan siswa sebelum membuat agenda.']);
            }

            return TeacherAgenda::query()->create([
                'teacher_id' => $teacher->id,
                'academic_period_id' => $period->id,
                'agenda_date' => $data['agenda_date'],
                'block_key' => $data['block_key'],
                'schedule_snapshot' => $context['snapshot'],
                'material' => $data['material'],
                'attendance' => $context['students'],
            ]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agenda Guru berhasil disimpan.']);

        return to_route('teacher.agenda.edit', $agenda);
    }

    public function edit(Request $request, TeacherAgenda $agenda, StudentAttendanceService $attendance): Response
    {
        $this->ensureOwner($request, $agenda);

        return Inertia::render('teacher/agenda/form', [
            'agenda' => [
                'id' => $agenda->id,
                'date' => $agenda->agenda_date->format('Y-m-d'),
                'block_key' => $agenda->block_key,
                'context_token' => $this->editToken($agenda),
                'snapshot' => $agenda->schedule_snapshot,
                'material' => $agenda->material,
                'attendance' => $attendance->rows(AcademicPeriod::query()->findOrFail($agenda->academic_period_id)->academic_year_id, $agenda->agenda_date->toDateString(), $agenda->attendance),
                'canManageAttendance' => $request->user()->can('teacher.attendance.manage'),
            ],
        ]);
    }

    public function update(SaveTeacherAgendaRequest $request, TeacherAgenda $agenda): RedirectResponse
    {
        $this->ensureOwner($request, $agenda);
        $data = $request->validated();
        DB::transaction(function () use ($agenda, $data): void {
            $current = TeacherAgenda::query()->lockForUpdate()->findOrFail($agenda->id);
            if ($data['agenda_date'] !== $current->agenda_date->format('Y-m-d') || $data['block_key'] !== $current->block_key) {
                throw ValidationException::withMessages(['agenda_date' => 'Tanggal dan blok agenda tersimpan tidak dapat diganti.']);
            }
            if (! hash_equals($this->editToken($current), $data['context_token'])) {
                throw ValidationException::withMessages(['context_token' => 'Agenda telah diperbarui. Muat ulang agar perubahan terbaru tidak tertimpa.']);
            }
            $current->update([
                'material' => $data['material'],
            ]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agenda Guru berhasil diperbarui.']);

        return to_route('teacher.agenda.edit', $agenda);
    }

    private function date(Request $request): string
    {
        return $request->validate(['date' => ['sometimes', 'required', 'date_format:Y-m-d']])['date']
            ?? now('Asia/Makassar')->toDateString();
    }

    private function teacher(Request $request): Teacher
    {
        $teacher = $request->user()->teacher;
        abort_if($teacher === null, 403, 'Akun belum terhubung dengan profil guru.');

        return $teacher;
    }

    private function ensureOwner(Request $request, TeacherAgenda $agenda): void
    {
        abort_unless($request->user()->teacher?->id === $agenda->teacher_id, 404);
    }

    private function editToken(TeacherAgenda $agenda): string
    {
        return hash('sha256', json_encode([
            $agenda->id, $agenda->material, $agenda->attendance, $agenda->updated_at?->toISOString(),
        ], JSON_THROW_ON_ERROR));
    }
}
