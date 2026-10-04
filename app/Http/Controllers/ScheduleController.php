<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleMoveRequest;
use App\Http\Requests\ScheduleSaveRequest;
use App\Models\AcademicPeriod;
use App\Models\Rombel;
use App\Models\ScheduleCustomSlot;
use App\Models\ScheduleEntry;
use App\Models\SchedulePlan;
use App\Models\ScheduleTeachingAssignment;
use App\Models\Subject;
use App\Models\TeacherSubject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $grade = $request->string('grade')->trim()->toString() ?: 'VII';
        $validated = $request->validate([
            'academic_period_id' => ['nullable', 'integer'],
            'grade' => ['nullable', Rule::in(['VII', 'VIII', 'IX'])],
            'rombel_ids' => ['nullable', 'array', 'max:50'],
            'rombel_ids.*' => [
                'integer',
                Rule::exists('m_rombels', 'id')->where(
                    fn ($query) => $query
                        ->where('status', 'active')
                        ->where('grade_level', $grade),
                ),
            ],
        ]);
        $periods = $this->activePeriods();
        $selectedPeriod = $periods->firstWhere('id', $validated['academic_period_id'] ?? null) ?? $periods->first();
        $grade = $validated['grade'] ?? 'VII';
        $selectedRombelIds = array_map('intval', Arr::wrap($validated['rombel_ids'] ?? []));
        $publishedPlan = $selectedPeriod === null
            ? null
            : SchedulePlan::query()
                ->with('customSlots')
                ->where('academic_period_id', $selectedPeriod->id)
                ->where('grade_level', $grade)
                ->where('status', 'published')
                ->latest('id')
                ->first();
        $legacyPlan = $publishedPlan === null && $selectedPeriod !== null
            ? $this->legacyPlanForGrade($selectedPeriod->id, $grade)
            : null;
        $readPlan = $publishedPlan ?? $legacyPlan;
        if ($selectedRombelIds === [] && $readPlan !== null) {
            $persistedRombelIds = $readPlan->getAttribute('selected_rombel_ids');
            $selectedRombelIds = is_array($persistedRombelIds) && $persistedRombelIds !== []
                ? array_map('intval', $persistedRombelIds)
                : $readPlan->teachingAssignments()
                    ->pluck('rombel_id')
                    ->unique()
                    ->map(fn (int $id): int => $id)
                    ->values()
                    ->all();
        }
        $selectedRombels = $this->selectedRombels($selectedRombelIds, $grade);
        $customSlots = $readPlan === null ? collect() : $readPlan->customSlots;
        $days = $this->scheduleDays($customSlots);

        return Inertia::render('schedules/index', [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'grade' => $grade,
            'selectedRombels' => $selectedRombels,
            'selectedRombelIds' => $selectedRombelIds,
            'days' => $days,
            'entries' => $this->publishedEntries($selectedPeriod?->id, $selectedRombelIds, $readPlan?->id),
            'scheduleMeta' => [
                'lessonDurationMinutes' => config('schedule.lesson_duration_minutes'),
                'breakDurationMinutes' => config('schedule.break_duration_minutes'),
                'startTime' => config('schedule.start_time'),
            ],
        ]);
    }

    public function save(ScheduleSaveRequest $request): RedirectResponse
    {
        /** @var array{academic_period_id: int, grade?: string|null, selected_rombel_ids?: array<int, int>, source_plan_id?: int|null, custom_slots?: array<int, array{day: string, lesson_number: int, label: string}>, manual_placements?: array<int, array{teacher_subject_id: int, rombel_id: int, slots: array<int, array{day: string, lesson_number: int}>}>, assignments: array<int, array{teacher_subject_id: int, rombel_ids: array<int, int>}>} $data */
        $data = $request->validated();
        $selectedRombelIds = array_values(array_unique(array_map('intval', $data['selected_rombel_ids'] ?? [])));
        $grade = $data['grade'] ?? null;
        $data['assignments'] = $this->canonicalAssignments($data['assignments']);
        $data['custom_slots'] = $this->canonicalCustomSlots($data['custom_slots'] ?? []);
        $data['manual_placements'] = $this->canonicalManualPlacements($data['manual_placements'] ?? []);
        $payloadHash = $this->schedulePayloadHash($data['assignments'], $data['custom_slots'], $data['manual_placements']);
        sort($selectedRombelIds, SORT_NUMERIC);
        $sourcePlanId = $data['source_plan_id'] ?? null;

        $plan = DB::transaction(function () use ($data, $payloadHash, $request, $sourcePlanId, $selectedRombelIds): SchedulePlan {
            /** @var AcademicPeriod $period */
            $period = AcademicPeriod::query()
                ->with('academicYear')
                ->lockForUpdate()
                ->findOrFail($data['academic_period_id']);
            $this->ensureActivePeriod($period);
            $grade = $this->gradeForAssignments($data['assignments']);
            if (($data['grade'] ?? null) !== null && $data['grade'] !== $grade) {
                abort(422, 'Jenjang tidak sesuai dengan Rombel yang dipilih.');
            }
            $this->validateSelectedRombelsForGrade($selectedRombelIds, $grade);

            $publishedPlan = SchedulePlan::query()
                ->where('academic_period_id', $period->id)
                ->where('grade_level', $grade)
                ->where('status', 'published')
                ->lockForUpdate()
                ->latest('id')
                ->first();
            $existingPlan = SchedulePlan::query()
                ->where('academic_period_id', $period->id)
                ->where('grade_level', $grade)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (($existingPlan ?? $publishedPlan)?->payload_hash === $payloadHash) {
                $unchangedPlan = $existingPlan ?? $publishedPlan;
                $unchangedPlan->update(['selected_rombel_ids' => $selectedRombelIds]);

                return $unchangedPlan;
            }

            $sourcePlan = null;
            if ($sourcePlanId !== null) {
                $sourcePlan = SchedulePlan::query()
                    ->whereKey($sourcePlanId)
                    ->where('academic_period_id', $period->id)
                    ->whereIn('status', ['draft', 'published'])
                    ->lockForUpdate()
                    ->first();
                abort_unless($sourcePlan !== null, 422, 'Rancangan sumber tidak sesuai periode.');
                abort_unless($sourcePlan->grade_level === null || $sourcePlan->grade_level === $grade, 422, 'Rancangan sumber tidak sesuai jenjang.');
                abort_unless($existingPlan === null || $sourcePlan->id === $existingPlan->id, 422, 'Rancangan sumber bukan versi tersimpan terbaru.');
            } elseif ($existingPlan !== null) {
                $sourcePlan = $existingPlan;
            } else {
                $sourcePlan = $publishedPlan;
            }

            if ($existingPlan !== null) {
                $existingPlan->update(['status' => 'archived', 'archived_at' => now()]);
            }

            $rombels = $this->activeRombelsForAssignments($data['assignments']);
            $relations = $this->eligibleTeacherSubjects($data['assignments']);
            $revision = $sourcePlan?->revision ? $sourcePlan->revision + 1 : 1;
            $inPlaceRegeneration = $sourcePlan !== null
                && $sourcePlan->status === 'published'
                && $publishedPlan?->id === $sourcePlan->id;

            if ($inPlaceRegeneration) {
                $sourcePlan->entries()->delete();
                $sourcePlan->teachingAssignments()->delete();
                $sourcePlan->customSlots()->delete();
                $sourcePlan->update([
                    'revision' => $revision,
                    'payload_hash' => $payloadHash,
                    'selected_rombel_ids' => $selectedRombelIds,
                    'published_at' => now(),
                ]);
                $plan = $sourcePlan;
            } else {
                $plan = SchedulePlan::query()->create([
                    'academic_period_id' => $period->id,
                    'grade_level' => $grade,
                    'source_plan_id' => $sourcePlan?->id,
                    'created_by' => $request->user()->id,
                    'status' => 'published',
                    'revision' => $revision,
                    'payload_hash' => $payloadHash,
                    'selected_rombel_ids' => $selectedRombelIds,
                ]);
            }

            foreach ($this->resolvedCustomSlots($data['custom_slots']) as $customSlot) {
                $plan->customSlots()->create([
                    'academic_period_id' => $period->id,
                    ...$customSlot,
                ]);
            }

            $seenAssignments = [];
            foreach ($data['assignments'] as $assignment) {
                $relation = $relations->get($assignment['teacher_subject_id']);
                abort_unless($relation !== null, 422, 'Relasi Guru Mata Pelajaran tidak aktif.');
                foreach ($assignment['rombel_ids'] as $rombelId) {
                    $rombel = $rombels->get($rombelId);
                    abort_unless($rombel !== null, 422, 'Rombel tidak aktif atau tidak ditemukan.');
                    $assignmentKey = $rombel->id.':'.$relation->subject_id;
                    abort_if(isset($seenAssignments[$assignmentKey]), 422, 'Satu Mata Pelajaran hanya dapat memiliki satu Guru per Rombel.');
                    $seenAssignments[$assignmentKey] = true;
                    $teachingAssignment = ScheduleTeachingAssignment::query()->create([
                        'schedule_plan_id' => $plan->id,
                        'academic_period_id' => $period->id,
                        'rombel_id' => $rombel->id,
                        'subject_id' => $relation->subject_id,
                        'teacher_subject_id' => $relation->id,
                        'teacher_id' => $relation->teacher_id,
                        'weekly_jp' => $relation->subject->jp_per_class,
                        'subject_code' => $relation->subject->code,
                        'subject_name' => $relation->subject->name,
                        'teacher_name' => $relation->teacher->full_name,
                        'rombel_code' => $rombel->code,
                        'status' => 'active',
                    ]);
                    $manualPlacement = collect($data['manual_placements'])->first(
                        fn (array $placement): bool => $placement['teacher_subject_id'] === $relation->id
                            && $placement['rombel_id'] === $rombel->id,
                    );
                    $this->createEntriesForAssignment($plan, $teachingAssignment, $manualPlacement);
                }
            }

            $plan->update(['published_at' => now()]);

            return $plan;
        });

        return redirect()->route('schedule.index', [
            'academic_period_id' => $plan->academic_period_id,
            'grade' => $data['grade'] ?? null,
            'rombel_ids' => $selectedRombelIds,
        ])
            ->with('success', 'Jadwal berhasil disimpan.');
    }

    public function move(ScheduleMoveRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $periodId = DB::transaction(function () use ($data): int {
            /** @var ScheduleEntry $entry */
            $entry = ScheduleEntry::query()
                ->with(['schedulePlan', 'teachingAssignment'])
                ->lockForUpdate()
                ->whereKey($data['schedule_entry_id'])
                ->where('schedule_plan_id', $data['schedule_plan_id'])
                ->firstOrFail();
            $plan = $entry->schedulePlan;
            if ($plan->status !== 'published') {
                throw ValidationException::withMessages([
                    'move' => 'Jadwal yang dipindahkan bukan jadwal terbit.',
                ]);
            }

            $period = $plan->academicPeriod()->with('academicYear')->firstOrFail();
            if ($period->status !== 'active' || $period->academicYear->status !== 'active') {
                throw ValidationException::withMessages([
                    'move' => 'Periode atau Tahun Ajaran tidak aktif.',
                ]);
            }

            $plan->update(['selected_rombel_ids' => array_values(array_unique(array_map('intval', $data['selected_rombel_ids'] ?? [])))]);
            $configuredSlot = collect($this->lessonSlotsByDay()[$data['day']] ?? [])
                ->firstWhere('number', (int) $data['lesson_number']);

            if ($configuredSlot === null) {
                throw ValidationException::withMessages([
                    'move' => 'Slot jadwal tidak sesuai konfigurasi hari.',
                ]);
            }

            if ($plan->customSlots()->where('day', $data['day'])->where('lesson_number', $data['lesson_number'])->exists()) {
                throw ValidationException::withMessages([
                    'move' => 'Slot tujuan merupakan kegiatan khusus.',
                ]);
            }
            $hasConflict = ScheduleEntry::query()
                ->where('schedule_plan_id', $plan->id)
                ->where('id', '<>', $entry->id)
                ->where(function ($query) use ($entry, $data): void {
                    $query->where(function ($query) use ($entry, $data): void {
                        $query->where('teacher_id', $entry->teacher_id)
                            ->where('day', $data['day'])
                            ->where('lesson_number', $data['lesson_number']);
                    })->orWhere(function ($query) use ($entry, $data): void {
                        $query->where('rombel_id', $entry->rombel_id)
                            ->where('day', $data['day'])
                            ->where('lesson_number', $data['lesson_number']);
                    });
                })
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'move' => 'Slot tujuan bertabrakan dengan jadwal lain.',
                ]);
            }
            $entry->update([
                'day' => $data['day'],
                'lesson_number' => $data['lesson_number'],
                'start_time' => $configuredSlot['start'],
                'end_time' => $configuredSlot['end'],
            ]);

            return $period->id;
        });

        return redirect()->route('schedule.index', [
            'academic_period_id' => $periodId,
            'rombel_ids' => array_values(array_unique(array_map('intval', $data['selected_rombel_ids'] ?? []))),
        ])->with('success', 'Posisi jadwal berhasil diperbarui.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_period_id' => ['required', 'integer', Rule::exists('m_academic_periods', 'id')],
            'grade' => ['required', Rule::in(['VII', 'VIII', 'IX'])],
        ]);

        DB::transaction(function () use ($validated): void {
            $plan = SchedulePlan::query()
                ->where('academic_period_id', $validated['academic_period_id'])
                ->where('grade_level', $validated['grade'])
                ->whereIn('status', ['published', 'draft', 'archived'])
                ->lockForUpdate()
                ->first();

            if ($plan === null) {
                return;
            }

            if (SchedulePlan::query()->where('source_plan_id', $plan->id)->exists()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Jadwal memiliki versi turunan dan tidak dapat dihapus.',
                ]);
            }

            $plan->delete();
        });

        return redirect()->route('schedule.index', [
            'academic_period_id' => $validated['academic_period_id'],
            'grade' => $validated['grade'],
        ])->with('success', 'Jadwal jenjang berhasil dihapus.');
    }

    public function publish(Request $request): RedirectResponse
    {
        abort(410, 'Publikasi manual sudah tidak tersedia. Gunakan Buat Jadwal.');
    }

    public function generate(Request $request): Response
    {
        $grade = $request->string('grade')->trim()->toString() ?: 'VII';
        $validated = $request->validate([
            'academic_period_id' => ['nullable', 'integer'],
            'grade' => ['nullable', Rule::in(['VII', 'VIII', 'IX'])],
            'search' => ['nullable', 'string', 'max:100'],
            'rombel_ids' => ['nullable', 'array', 'max:50'],
            'rombel_ids.*' => [
                'integer',
                Rule::exists('m_rombels', 'id')->where(
                    fn ($query) => $query
                        ->where('status', 'active')
                        ->where('grade_level', $grade),
                ),
            ],
        ]);
        $periods = $this->activePeriods();
        $selectedPeriod = $periods->firstWhere('id', $validated['academic_period_id'] ?? null) ?? $periods->first();
        $grade = $validated['grade'] ?? $grade;
        $search = trim($validated['search'] ?? '');
        $selectedRombelIds = array_map('intval', Arr::wrap($validated['rombel_ids'] ?? []));
        $periodRombels = Rombel::query()
            ->where('status', 'active')
            ->where('grade_level', $grade)
            ->orderBy('parallel_code')
            ->get(['id', 'code', 'name', 'grade_level', 'parallel_code']);
        $availableRombels = $periodRombels
            ->filter(fn (Rombel $rombel): bool => $search === ''
                || str_contains(mb_strtolower($rombel->code), mb_strtolower($search))
                || str_contains(mb_strtolower($rombel->name), mb_strtolower($search)))
            ->values();
        $selectedRombels = $periodRombels->whereIn('id', $selectedRombelIds)->values();
        $subjects = Subject::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'jp_per_class', 'color']);
        $teacherSubjects = TeacherSubject::query()
            ->with([
                'subject:id,code,name,color',
                'teacher:id,full_name,staff_type,status',
            ])
            ->whereHas('subject', fn ($query) => $query->where('status', 'active'))
            ->whereHas('teacher', fn ($query) => $query->where('status', 'active')->where('staff_type', 'guru'))
            ->orderBy('subject_id')
            ->orderBy('teacher_id')
            ->get(['id', 'teacher_id', 'subject_id', 'code'])
            ->map(fn (TeacherSubject $teacherSubject): array => [
                'id' => $teacherSubject->id,
                'teacher_id' => $teacherSubject->teacher_id,
                'teacher_name' => $teacherSubject->teacher->full_name,
                'subject_id' => $teacherSubject->subject_id,
                'subject_code' => $teacherSubject->subject->code,
                'subject_name' => $teacherSubject->subject->name,
                'subject_color' => $teacherSubject->subject->color,
                'code' => $teacherSubject->code,
            ])
            ->values();
        $draftPlan = $selectedPeriod === null
            ? null
            : SchedulePlan::query()
                ->with([
                    'teachingAssignments:id,schedule_plan_id,teacher_subject_id,rombel_id',
                    'entries:id,schedule_plan_id,teaching_assignment_id,rombel_id,day,lesson_number',
                    'customSlots:id,schedule_plan_id,day,lesson_number,start_time,end_time,label',
                ])
                ->where('academic_period_id', $selectedPeriod->id)
                ->where('grade_level', $grade)
                ->where('status', 'published')
                ->latest('id')
                ->first();
        if ($draftPlan !== null) {
            $draftPlan->setAttribute('manual_placements', $draftPlan->entries
                ->groupBy('teaching_assignment_id')
                ->map(function (Collection $entries, string $assignmentId) use ($draftPlan): array {
                    $assignment = $draftPlan->teachingAssignments->firstWhere('id', (int) $assignmentId);

                    return [
                        'teacher_subject_id' => $assignment?->teacher_subject_id,
                        'rombel_id' => $assignment?->rombel_id,
                        'slots' => $entries->map(fn (ScheduleEntry $entry): array => [
                            'day' => $entry->day,
                            'lesson_number' => $entry->lesson_number,
                        ])->values()->all(),
                    ];
                })
                ->filter(fn (array $placement): bool => $placement['teacher_subject_id'] !== null && $placement['rombel_id'] !== null)
                ->values()
                ->all());
        }
        $draftRombelIds = $draftPlan?->teachingAssignments->pluck('rombel_id')->map(fn (int $id): int => $id)->all() ?? [];
        $selectedRombelIds = array_values(array_unique([...$selectedRombelIds, ...$draftRombelIds]));
        $selectedRombels = $periodRombels->whereIn('id', $selectedRombelIds)->values();

        return Inertia::render('schedules/generate', [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'grade' => $grade,
            'search' => $search,
            'availableRombels' => $availableRombels,
            'selectedRombels' => $selectedRombels,
            'selectedRombelIds' => $selectedRombelIds,
            'subjects' => $subjects,
            'teacherSubjects' => $teacherSubjects,
            'scheduleSlotOptions' => $this->scheduleSlotOptions(),
            'draftPlan' => $draftPlan,
        ]);
    }

    public function legacyIndex(): RedirectResponse
    {
        return redirect()->route('schedule.index', request()->query());
    }

    public function legacyGenerate(): RedirectResponse
    {
        return redirect()->route('schedule.generate', request()->query());
    }

    /**
     * @param  array<int, array{teacher_subject_id: int, rombel_ids: array<int, int>}>  $assignments
     * @return array<int, array{teacher_subject_id: int, rombel_ids: array<int, int>}>
     */
    private function canonicalAssignments(array $assignments): array
    {
        $grouped = [];
        foreach ($assignments as $assignment) {
            $teacherSubjectId = (int) $assignment['teacher_subject_id'];
            $grouped[$teacherSubjectId] = array_values(array_unique(array_merge(
                $grouped[$teacherSubjectId] ?? [],
                array_map('intval', $assignment['rombel_ids']),
            )));
        }

        ksort($grouped, SORT_NUMERIC);
        $canonical = [];
        foreach ($grouped as $teacherSubjectId => $rombelIds) {
            sort($rombelIds, SORT_NUMERIC);
            $canonical[] = [
                'teacher_subject_id' => (int) $teacherSubjectId,
                'rombel_ids' => $rombelIds,
            ];
        }

        return $canonical;
    }

    /**
     * @param  array<int, array{teacher_subject_id: int, rombel_ids: array<int, int>}>  $assignments
     * @param  array<int, array{day: string, lesson_number: int, label: string}>  $customSlots
     * @param  array<int, array{teacher_subject_id: int, rombel_id: int, slots: array<int, array{day: string, lesson_number: int}>}>  $manualPlacements
     */
    private function schedulePayloadHash(array $assignments, array $customSlots, array $manualPlacements = []): string
    {
        return hash('sha256', json_encode([
            'assignments' => $assignments,
            'custom_slots' => $customSlots,
            'manual_placements' => $manualPlacements,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<int, array{teacher_subject_id: int, rombel_id: int, slots: array<int, array{day: string, lesson_number: int}>}>  $placements
     * @return array<int, array{teacher_subject_id: int, rombel_id: int, slots: array<int, array{day: string, lesson_number: int}>}>
     */
    private function canonicalManualPlacements(array $placements): array
    {
        return collect($placements)
            ->map(function (array $placement): array {
                $slots = collect($placement['slots'])
                    ->map(fn (array $slot): array => [
                        'day' => $slot['day'],
                        'lesson_number' => (int) $slot['lesson_number'],
                    ])
                    ->sortBy(['day', 'lesson_number'])
                    ->values()
                    ->all();

                return [
                    'teacher_subject_id' => (int) $placement['teacher_subject_id'],
                    'rombel_id' => (int) $placement['rombel_id'],
                    'slots' => $slots,
                ];
            })
            ->sortBy(['teacher_subject_id', 'rombel_id'])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{day: string, lesson_number: int, label: string}>  $customSlots
     * @return array<int, array{day: string, lesson_number: int, label: string}>
     */
    private function canonicalCustomSlots(array $customSlots): array
    {
        $canonical = collect($customSlots)
            ->map(fn (array $slot): array => [
                'day' => $slot['day'],
                'lesson_number' => (int) $slot['lesson_number'],
                'label' => trim($slot['label']),
            ])
            ->sortBy(['day', 'lesson_number'])
            ->values();

        $keys = $canonical->map(fn (array $slot): string => $slot['day'].':'.$slot['lesson_number']);
        abort_if($keys->unique()->count() !== $keys->count(), 422, 'Satu slot hanya dapat memiliki satu kegiatan khusus.');

        return $canonical->all();
    }

    /**
     * @param  array<int, array{day: string, lesson_number: int, label: string}>  $customSlots
     * @return array<int, array{day: string, lesson_number: int, start_time: string, end_time: string, label: string}>
     */
    private function resolvedCustomSlots(array $customSlots): array
    {
        $configuredSlots = $this->lessonSlotsByDay();

        return collect($customSlots)->map(function (array $customSlot) use ($configuredSlots): array {
            $slot = collect($configuredSlots[$customSlot['day']] ?? [])
                ->firstWhere('number', $customSlot['lesson_number']);
            abort_unless($slot !== null, 422, 'Slot kegiatan khusus tidak sesuai konfigurasi jadwal.');

            return [
                'day' => $customSlot['day'],
                'lesson_number' => $customSlot['lesson_number'],
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'label' => $customSlot['label'],
            ];
        })->all();
    }

    private function ensureActivePeriod(AcademicPeriod $period): void
    {
        abort_if($period->status !== 'active' || $period->academicYear->status !== 'active', 422, 'Periode atau Tahun Ajaran tidak aktif.');
    }

    /**
     * @param  array<int, array{teacher_subject_id: int, rombel_ids: array<int, int>}>  $assignments
     */
    private function gradeForAssignments(array $assignments): string
    {
        $gradeLevels = Rombel::query()
            ->whereIn('id', collect($assignments)->flatMap(fn (array $assignment): array => $assignment['rombel_ids'])->unique())
            ->pluck('grade_level')
            ->unique()
            ->values();
        abort_unless($gradeLevels->count() === 1, 422, 'Semua Rombel harus berada pada satu jenjang.');

        return (string) $gradeLevels->first();
    }

    /**
     * @param  array<int, int>  $rombelIds
     */
    private function validateSelectedRombelsForGrade(array $rombelIds, string $grade): void
    {
        abort_unless(
            Rombel::query()->whereIn('id', $rombelIds)->where('status', 'active')->where('grade_level', $grade)->count() === count($rombelIds),
            422,
            'Rombel terpilih tidak sesuai jenjang.',
        );
    }

    private function legacyPlanForGrade(int $periodId, string $grade): ?SchedulePlan
    {
        return SchedulePlan::query()
            ->with('customSlots')
            ->where('academic_period_id', $periodId)
            ->whereNull('grade_level')
            ->whereIn('status', ['published', 'archived'])
            ->whereHas('teachingAssignments', fn ($query) => $query->whereIn('rombel_id', Rombel::query()
                ->where('grade_level', $grade)
                ->select('id')))
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<int, array{teacher_subject_id: int, rombel_ids: array<int, int>}>  $assignments
     * @return Collection<int, Rombel>
     */
    private function activeRombelsForAssignments(array $assignments): Collection
    {
        $rombelIds = collect($assignments)->flatMap(fn (array $assignment): array => $assignment['rombel_ids'])->unique()->values();
        $rombels = Rombel::query()->whereIn('id', $rombelIds)->where('status', 'active')->get()->keyBy('id');
        abort_if($rombels->count() !== $rombelIds->count(), 422, 'Rombel tidak aktif atau tidak ditemukan.');

        return $rombels;
    }

    /**
     * @param  array<int, array{teacher_subject_id: int, rombel_ids: array<int, int>}>  $assignments
     * @return Collection<int, TeacherSubject>
     */
    private function eligibleTeacherSubjects(array $assignments): Collection
    {
        $ids = collect($assignments)->pluck('teacher_subject_id')->unique()->values();
        $relations = TeacherSubject::query()
            ->with(['subject', 'teacher'])
            ->whereIn('id', $ids)
            ->whereHas('subject', fn ($query) => $query->where('status', 'active'))
            ->whereHas('teacher', fn ($query) => $query->where('status', 'active')->where('staff_type', 'guru'))
            ->get()
            ->keyBy('id');
        abort_if($relations->count() !== $ids->count(), 422, 'Relasi Guru Mata Pelajaran tidak aktif.');

        return $relations;
    }

    private function validatePlanForPublish(SchedulePlan $plan, AcademicPeriod $period): void
    {
        $assignments = $plan->teachingAssignments()->lockForUpdate()->get();
        $entries = $plan->entries()->lockForUpdate()->get();
        $customSlots = $plan->customSlots()->lockForUpdate()->get();
        $configuredSlots = $this->lessonSlotsByDay();
        $customSlotKeys = [];
        foreach ($customSlots as $customSlot) {
            $slot = collect($configuredSlots[$customSlot->day] ?? [])
                ->firstWhere('number', $customSlot->lesson_number);
            abort_unless($slot !== null && $slot['start'] === $customSlot->start_time && $slot['end'] === $customSlot->end_time, 422, 'Slot kegiatan khusus tidak sesuai konfigurasi jadwal.');
            $customSlotKey = $customSlot->day.':'.$customSlot->lesson_number;
            abort_if(isset($customSlotKeys[$customSlotKey]), 422, 'Satu slot hanya dapat memiliki satu kegiatan khusus.');
            $customSlotKeys[$customSlotKey] = true;
        }
        $relations = TeacherSubject::query()
            ->with(['subject', 'teacher'])
            ->whereIn('id', $assignments->pluck('teacher_subject_id')->unique())
            ->get()
            ->keyBy('id');
        $rombels = Rombel::query()->whereIn('id', $assignments->pluck('rombel_id')->unique())->get()->keyBy('id');
        $slots = $this->lessonSlotsByDay();
        $seenTeacherSlots = [];
        $seenRombelSlots = [];

        $seenAssignmentIds = [];
        foreach ($assignments as $assignment) {
            $seenAssignmentIds[$assignment->id] = true;
            $relation = $relations->get($assignment->teacher_subject_id);
            $rombel = $rombels->get($assignment->rombel_id);
            abort_unless($assignment->status === 'active', 422, 'Tugas mengajar tidak aktif.');
            abort_unless($assignment->schedule_plan_id === $plan->id && $assignment->academic_period_id === $period->id, 422, 'Scope tugas mengajar tidak konsisten.');
            abort_unless($relation !== null && $relation->subject->status === 'active' && $relation->teacher->status === 'active' && $relation->teacher->staff_type === 'guru', 422, 'Anchor Guru Mata Pelajaran sudah tidak aktif.');
            abort_unless($rombel !== null && $rombel->status === 'active', 422, 'Rombel sudah tidak aktif.');
            abort_unless($assignment->subject_id === $relation->subject_id && $assignment->teacher_id === $relation->teacher_id && $assignment->weekly_jp === $relation->subject->jp_per_class, 422, 'Snapshot tugas mengajar sudah tidak konsisten.');
            abort_unless($assignment->subject_code === $relation->subject->code && $assignment->subject_name === $relation->subject->name && $assignment->teacher_name === $relation->teacher->full_name && $assignment->rombel_code === $rombel->code, 422, 'Label historis tugas mengajar sudah tidak konsisten.');

            $assignmentEntries = $entries->where('teaching_assignment_id', $assignment->id);
            abort_unless($assignmentEntries->count() <= $assignment->weekly_jp && $assignmentEntries->count() === $assignment->weekly_jp, 422, 'Jumlah slot tidak sesuai JP mingguan.');
            foreach ($assignmentEntries as $entry) {
                $slot = collect($slots[$entry->day] ?? [])->firstWhere('number', $entry->lesson_number);
                abort_unless($slot !== null && $slot['start'] === $entry->start_time && $slot['end'] === $entry->end_time, 422, 'Slot jadwal tidak sesuai konfigurasi hari.');
                abort_if(isset($customSlotKeys[$entry->day.':'.$entry->lesson_number]), 422, 'Guru tidak dapat menggunakan slot kegiatan khusus.');
                abort_unless($entry->schedule_plan_id === $plan->id && $entry->academic_period_id === $period->id && $entry->rombel_id === $assignment->rombel_id && $entry->subject_id === $assignment->subject_id && $entry->teacher_id === $assignment->teacher_id, 422, 'Scope entri jadwal tidak konsisten.');
                abort_unless($entry->subject_code === $assignment->subject_code && $entry->subject_name === $assignment->subject_name && $entry->teacher_name === $assignment->teacher_name && $entry->rombel_code === $assignment->rombel_code, 422, 'Snapshot label entri jadwal tidak konsisten.');
                $teacherKey = $entry->teacher_id.':'.$entry->day.':'.$entry->lesson_number;
                $rombelKey = $entry->rombel_id.':'.$entry->day.':'.$entry->lesson_number;
                abort_if(isset($seenTeacherSlots[$teacherKey]), 422, 'Guru memiliki benturan slot.');
                abort_if(isset($seenRombelSlots[$rombelKey]), 422, 'Rombel memiliki benturan slot.');
                $seenTeacherSlots[$teacherKey] = true;
                $seenRombelSlots[$rombelKey] = true;
            }
        }
        abort_unless($entries->every(fn (ScheduleEntry $entry): bool => isset($seenAssignmentIds[$entry->teaching_assignment_id])), 422, 'Entri jadwal tidak memiliki tugas mengajar yang valid.');
    }

    /**
     * @param  array{teacher_subject_id: int, rombel_id: int, slots: array<int, array{day: string, lesson_number: int}>}|null  $manualPlacement
     */
    private function createEntriesForAssignment(SchedulePlan $plan, ScheduleTeachingAssignment $assignment, ?array $manualPlacement): void
    {
        if ($manualPlacement === null) {
            $this->createDraftEntries($plan, $assignment);

            return;
        }

        abort_unless(count($manualPlacement['slots']) === $assignment->weekly_jp, 422, 'Jumlah slot manual tidak sesuai JP mingguan.');
        $configuredSlots = $this->lessonSlotsByDay();
        $customSlotKeys = $plan->customSlots()
            ->get(['day', 'lesson_number'])
            ->mapWithKeys(fn (ScheduleCustomSlot $slot): array => [$slot->day.':'.$slot->lesson_number => true]);
        $seenSlots = [];
        foreach ($manualPlacement['slots'] as $manualSlot) {
            $key = $manualSlot['day'].':'.$manualSlot['lesson_number'];
            $slot = collect($configuredSlots[$manualSlot['day']] ?? [])
                ->firstWhere('number', $manualSlot['lesson_number']);
            abort_unless($slot !== null, 422, 'Slot manual tidak sesuai konfigurasi jadwal.');
            abort_if($customSlotKeys->has($key), 422, 'Slot manual bertabrakan dengan kegiatan khusus.');
            abort_if(isset($seenSlots[$key]), 422, 'Slot manual duplikat.');
            $teacherBusy = ScheduleEntry::query()
                ->where('schedule_plan_id', $plan->id)
                ->where('teacher_id', $assignment->teacher_id)
                ->where('day', $manualSlot['day'])
                ->where('lesson_number', $manualSlot['lesson_number'])
                ->exists();
            $rombelBusy = ScheduleEntry::query()
                ->where('schedule_plan_id', $plan->id)
                ->where('rombel_id', $assignment->rombel_id)
                ->where('day', $manualSlot['day'])
                ->where('lesson_number', $manualSlot['lesson_number'])
                ->exists();
            abort_if($teacherBusy || $rombelBusy, 422, 'Slot manual bertabrakan dengan jadwal lain.');
            $seenSlots[$key] = true;
            ScheduleEntry::query()->create([
                'schedule_plan_id' => $plan->id,
                'teaching_assignment_id' => $assignment->id,
                'academic_period_id' => $assignment->academic_period_id,
                'rombel_id' => $assignment->rombel_id,
                'subject_id' => $assignment->subject_id,
                'teacher_id' => $assignment->teacher_id,
                'day' => $manualSlot['day'],
                'lesson_number' => $manualSlot['lesson_number'],
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'subject_code' => $assignment->subject_code,
                'subject_name' => $assignment->subject_name,
                'teacher_name' => $assignment->teacher_name,
                'rombel_code' => $assignment->rombel_code,
            ]);
        }
    }

    private function createDraftEntries(SchedulePlan $plan, ScheduleTeachingAssignment $assignment): void
    {
        $slotsByDay = $this->lessonSlotsByDay();
        $dayOrder = array_keys($slotsByDay);
        $remaining = $assignment->weekly_jp;
        $dailyCounts = array_fill_keys($dayOrder, 0);
        $customSlotKeys = $plan->customSlots()
            ->get(['day', 'lesson_number'])
            ->mapWithKeys(fn (ScheduleCustomSlot $slot): array => [$slot->day.':'.$slot->lesson_number => true]);

        while ($remaining > 0) {
            $candidates = [];
            foreach ($dayOrder as $dayIndex => $day) {
                $dailyCapacity = 3 - $dailyCounts[$day];
                if ($dailyCapacity <= 0) {
                    continue;
                }

                $availableSlots = collect($slotsByDay[$day])
                    ->filter(function (array $candidate) use ($plan, $assignment, $day, $customSlotKeys): bool {
                        if ($customSlotKeys->has($day.':'.$candidate['number'])) {
                            return false;
                        }

                        return ! ScheduleEntry::query()
                            ->where('schedule_plan_id', $plan->id)
                            ->where(function ($query) use ($assignment, $day, $candidate): void {
                                $query->where(function ($query) use ($assignment, $day, $candidate): void {
                                    $query->where('teacher_id', $assignment->teacher_id)
                                        ->where('day', $day)
                                        ->where('lesson_number', $candidate['number']);
                                })->orWhere(function ($query) use ($assignment, $day, $candidate): void {
                                    $query->where('rombel_id', $assignment->rombel_id)
                                        ->where('day', $day)
                                        ->where('lesson_number', $candidate['number']);
                                });
                            })
                            ->exists();
                    })
                    ->values();

                foreach ($availableSlots as $startIndex => $startSlot) {
                    $block = [$startSlot];
                    foreach ($availableSlots->slice($startIndex + 1) as $nextSlot) {
                        $previousSlot = $block[array_key_last($block)];
                        if ($previousSlot['end'] !== $nextSlot['start']) {
                            break;
                        }
                        $block[] = $nextSlot;
                    }

                    $blockLength = min($remaining, $dailyCapacity, count($block), 3);
                    $candidates[] = [
                        'day' => $day,
                        'day_index' => $dayIndex,
                        'slots' => array_slice($block, 0, $blockLength),
                        'length' => $blockLength,
                        'teacher_load' => ScheduleEntry::query()
                            ->where('schedule_plan_id', $plan->id)
                            ->where('teacher_id', $assignment->teacher_id)
                            ->where('day', $day)
                            ->count(),
                    ];
                }
            }

            usort($candidates, fn (array $left, array $right): int => [$right['length'], $left['teacher_load'], $left['day_index'], $left['slots'][0]['number']]
                <=>
                [$left['length'], $right['teacher_load'], $right['day_index'], $right['slots'][0]['number']]
            );
            $selected = $candidates[0] ?? null;
            abort_if($selected === null, 422, 'Kapasitas slot Guru atau Rombel tidak mencukupi.');

            foreach ($selected['slots'] as $slot) {
                ScheduleEntry::query()->create([
                    'schedule_plan_id' => $plan->id,
                    'teaching_assignment_id' => $assignment->id,
                    'academic_period_id' => $assignment->academic_period_id,
                    'rombel_id' => $assignment->rombel_id,
                    'subject_id' => $assignment->subject_id,
                    'teacher_id' => $assignment->teacher_id,
                    'day' => $selected['day'],
                    'lesson_number' => $slot['number'],
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                    'subject_code' => $assignment->subject_code,
                    'subject_name' => $assignment->subject_name,
                    'teacher_name' => $assignment->teacher_name,
                    'rombel_code' => $assignment->rombel_code,
                ]);
            }

            $dailyCounts[$selected['day']] += $selected['length'];
            $remaining -= $selected['length'];
        }
    }

    /**
     * @return array<string, array<int, array{number: int, start: string, end: string}>>
     */
    private function lessonSlotsByDay(): array
    {
        return collect([
            'monday' => config('schedule.weekdays.monday_thursday'),
            'tuesday' => config('schedule.weekdays.monday_thursday'),
            'wednesday' => config('schedule.weekdays.monday_thursday'),
            'thursday' => config('schedule.weekdays.monday_thursday'),
            'friday' => config('schedule.weekdays.friday'),
        ])->map(fn (array $rows): array => array_values(array_filter($rows, fn (array $row): bool => $row['type'] === 'lesson')))->all();
    }

    /**
     * @param  array<int, int>  $rombelIds
     * @return Collection<int, ScheduleEntry>
     */
    private function publishedEntries(?int $periodId, array $rombelIds, ?int $planId = null): Collection
    {
        if ($periodId === null || $rombelIds === []) {
            return collect();
        }

        return ScheduleEntry::query()
            ->with([
                'subject:id,color',
                'teachingAssignment.teacherSubject:id,code',
            ])
            ->where('academic_period_id', $periodId)
            ->whereIn('rombel_id', $rombelIds)
            ->when($planId !== null, fn ($query) => $query->where('schedule_plan_id', $planId))
            ->whereHas('schedulePlan', fn ($query) => $query->where('status', 'published'))
            ->orderBy('day')
            ->orderBy('lesson_number')
            ->get()
            ->each(function (ScheduleEntry $entry): void {
                $entry->setAttribute('schedule_entry_id', $entry->id);
                $entry->setAttribute('schedule_plan_id', $entry->schedule_plan_id);
                $entry->setAttribute('assignment_code', $entry->teachingAssignment->teacherSubject->code);
                $entry->setAttribute('subject_color', $entry->subject->color ?? '#F3F4F6');
                $entry->unsetRelation('subject');
                $entry->unsetRelation('teachingAssignment');
            });
    }

    /**
     * @return Collection<int, AcademicPeriod>
     */
    private function activePeriods(): Collection
    {
        return AcademicPeriod::query()
            ->with('academicYear:id,year')
            ->where('status', 'active')
            ->whereHas('academicYear', fn ($query) => $query->where('status', 'active'))
            ->orderByDesc('id')
            ->get(['id', 'academic_year_id', 'code', 'name']);
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Rombel>
     */
    private function selectedRombels(array $ids, string $grade): Collection
    {
        return Rombel::query()
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->where('grade_level', $grade)
            ->orderBy('parallel_code')
            ->get(['id', 'code', 'name', 'grade_level', 'parallel_code']);
    }

    /**
     * @return array<string, array<int, array{number: int, start: string, end: string}>>
     */
    private function scheduleSlotOptions(): array
    {
        return $this->lessonSlotsByDay();
    }

    /**
     * @param  Collection<int, ScheduleCustomSlot>  $customSlots
     * @return array<int, array{key: string, label: string, rows: array<int, array<string, mixed>>}>
     */
    private function scheduleDays(Collection $customSlots): array
    {
        $weekdayRows = config('schedule.weekdays.monday_thursday');
        $days = [
            ['key' => 'monday', 'label' => 'Senin', 'rows' => $weekdayRows],
            ['key' => 'tuesday', 'label' => 'Selasa', 'rows' => $weekdayRows],
            ['key' => 'wednesday', 'label' => 'Rabu', 'rows' => $weekdayRows],
            ['key' => 'thursday', 'label' => 'Kamis', 'rows' => $weekdayRows],
            ['key' => 'friday', 'label' => 'Jumat', 'rows' => config('schedule.weekdays.friday')],
        ];

        return collect($days)->map(function (array $day) use ($customSlots): array {
            $daySlots = $customSlots->where('day', $day['key'])->keyBy('lesson_number');
            $rows = [];
            foreach ($day['rows'] as $row) {
                if ($row['type'] === 'lesson' && $daySlots->has($row['number'])) {
                    $customSlot = $daySlots->get($row['number']);
                    $row = [
                        ...$row,
                        'type' => 'custom',
                        'label' => $customSlot->label,
                    ];
                }
                $rows[] = $row;
            }
            $day['rows'] = $rows;

            return $day;
        })->all();
    }
}
