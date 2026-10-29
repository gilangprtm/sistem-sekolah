<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleActivityRequest;
use App\Http\Requests\ScheduleMoveRequest;
use App\Http\Requests\SchedulePlaceRequest;
use App\Http\Requests\ScheduleSaveRequest;
use App\Models\AcademicPeriod;
use App\Models\LmsCourseRun;
use App\Models\Rombel;
use App\Models\ScheduleCustomSlot;
use App\Models\ScheduleEntry;
use App\Models\SchedulePlan;
use App\Models\ScheduleTeachingAssignment;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Services\ScheduleReadService;
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
        $readService = app(ScheduleReadService::class);
        $readPlan = $selectedPeriod === null
            ? null
            : $readService->publishedPlanForGrade($selectedPeriod->id, $grade);
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
            'entries' => $readService->entriesForWeb($selectedPeriod?->id, $selectedRombelIds, $readPlan?->id),
            'editablePlanId' => $readPlan?->status === 'published' ? $readPlan->id : null,
            'canManageSchedule' => $request->user()?->can('curriculum.schedule.manage') ?? false,
            'placementOptions' => $request->user()?->can('curriculum.schedule.manage') && $readPlan?->status === 'published'
                ? $this->placementOptions($readPlan, $selectedRombels)
                : [],
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
            $hasCourseRuns = $sourcePlan !== null && LmsCourseRun::query()
                ->whereIn('schedule_teaching_assignment_id', $sourcePlan->teachingAssignments()->select('id'))->exists();
            $inPlaceRegeneration = $sourcePlan !== null
                && $sourcePlan->status === 'published'
                && $publishedPlan?->id === $sourcePlan->id
                && ! $hasCourseRuns;

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
                if ($hasCourseRuns && $sourcePlan->status === 'published') {
                    $sourcePlan->update(['status' => 'archived', 'archived_at' => now()]);
                }
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
            $automaticAssignments = [];
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
                    if ($manualPlacement !== null) {
                        $this->createEntriesForAssignment($plan, $teachingAssignment, $manualPlacement);
                    } else {
                        $automaticAssignments[] = $teachingAssignment;
                    }
                }
            }

            foreach ($automaticAssignments as $teachingAssignment) {
                $this->createDraftEntries($plan, $teachingAssignment);
            }

            $plan->update(['published_at' => now()]);

            return $plan;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal berhasil disimpan.']);

        return redirect()->route('schedule.index', [
            'academic_period_id' => $plan->academic_period_id,
            'grade' => $data['grade'] ?? null,
            'rombel_ids' => $selectedRombelIds,
        ]);
    }

    public function place(SchedulePlaceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $plan = DB::transaction(function () use ($data): SchedulePlan {
            $planPeriodId = SchedulePlan::query()->whereKey($data['schedule_plan_id'])->value('academic_period_id');
            $period = AcademicPeriod::query()->with('academicYear')->lockForUpdate()->whereKey($planPeriodId)->firstOrFail();
            if ($period->status !== 'active' || $period->academicYear->status !== 'active') {
                throw ValidationException::withMessages(['schedule' => 'Periode atau Tahun Ajaran tidak aktif.']);
            }
            $plan = SchedulePlan::query()->lockForUpdate()->whereKey($data['schedule_plan_id'])->firstOrFail();
            if ($plan->status !== 'published') {
                throw ValidationException::withMessages(['schedule' => 'Jadwal telah berubah. Muat ulang sebelum mengisi slot.']);
            }
            $rombel = Rombel::query()->where('status', 'active')->whereKey($data['rombel_id'])->firstOrFail();
            $selectedPlanRombelIds = $plan->getAttribute('selected_rombel_ids');
            $planRombelIds = array_values(array_unique(array_map('intval', [
                ...(is_array($selectedPlanRombelIds) ? $selectedPlanRombelIds : []),
                ...$plan->teachingAssignments()->pluck('rombel_id')->all(),
            ])));
            if ($rombel->grade_level !== $plan->grade_level || ! in_array($rombel->id, $planRombelIds, true)) {
                throw ValidationException::withMessages(['schedule' => 'Rombel tidak termasuk dalam jadwal jenjang ini.']);
            }
            $relation = TeacherSubject::query()->with(['subject', 'teacher'])
                ->whereHas('subject', fn ($query) => $query->where('status', 'active'))
                ->whereHas('teacher', fn ($query) => $query->where('status', 'active')->where('staff_type', 'guru'))
                ->whereKey($data['teacher_subject_id'])->first();
            if ($relation === null) {
                throw ValidationException::withMessages(['teacher_subject_id' => 'Relasi Guru Mata Pelajaran tidak aktif.']);
            }
            $assignment = $plan->teachingAssignments()->where('rombel_id', $rombel->id)->where('subject_id', $relation->subject_id)->first();
            if ($assignment !== null && $assignment->teacher_subject_id !== $relation->id) {
                throw ValidationException::withMessages(['teacher_subject_id' => 'Mata pelajaran ini sudah memiliki guru lain pada rombel tersebut.']);
            }
            $assignment ??= $plan->teachingAssignments()->create([
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
            $existingEntryCount = $plan->entries()->where('teaching_assignment_id', $assignment->id)->count();
            $masterWeeklyJp = (int) $relation->subject->jp_per_class;
            if ($existingEntryCount > $masterWeeklyJp) {
                throw $this->assignmentValidationException(
                    $assignment,
                    "Jadwal yang sudah ada melebihi {$masterWeeklyJp} JP per minggu dari Master Mata Pelajaran. Periksa jadwal sebelum menambah JP.",
                );
            }
            if ($assignment->weekly_jp !== $masterWeeklyJp) {
                $assignment->update(['weekly_jp' => $masterWeeklyJp]);
                $assignment->refresh();
            }
            $this->createDraftEntries($plan, $assignment, [
                'day' => $data['day'],
                'lesson_number' => (int) $data['lesson_number'],
            ]);
            $this->refreshPlanPayload($plan);

            return $plan;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'JP mingguan berhasil dijadwalkan.']);

        return redirect()->route('schedule.index', [
            'academic_period_id' => $plan->academic_period_id,
            'grade' => $plan->grade_level,
            'rombel_ids' => $data['selected_rombel_ids'] ?? $plan->selected_rombel_ids ?? [],
        ]);
    }

    /**
     * @param  Collection<int, Rombel>  $rombels
     * @return array<int, array{rombel_id: int, subject_id: int, subject_name: string, teacher_subject_id: int, label: string, weekly_jp: int, remaining_jp: int}>
     */
    private function placementOptions(SchedulePlan $plan, Collection $rombels): array
    {
        $assignments = $plan->teachingAssignments()->get()->keyBy(fn (ScheduleTeachingAssignment $assignment): string => $assignment->rombel_id.':'.$assignment->subject_id);
        $counts = $plan->entries()->get(['teaching_assignment_id'])->countBy('teaching_assignment_id');
        $relations = TeacherSubject::query()->with(['subject', 'teacher'])
            ->whereHas('subject', fn ($query) => $query->where('status', 'active'))
            ->whereHas('teacher', fn ($query) => $query->where('status', 'active')->where('staff_type', 'guru'))
            ->orderBy('subject_id')->orderBy('teacher_id')->get();
        $options = [];
        foreach ($rombels as $rombel) {
            foreach ($relations as $relation) {
                $assignment = $assignments->get($rombel->id.':'.$relation->subject_id);
                if ($assignment !== null && $assignment->teacher_subject_id !== $relation->id) {
                    continue;
                }
                $weeklyJp = (int) $relation->subject->jp_per_class;
                $remaining = $weeklyJp - ($assignment === null ? 0 : $counts->get($assignment->id, 0));
                if ($remaining > 0) {
                    $options[] = [
                        'rombel_id' => $rombel->id,
                        'subject_id' => $relation->subject_id,
                        'subject_name' => $relation->subject->name,
                        'teacher_subject_id' => $relation->id,
                        'label' => $relation->code.' - '.$relation->teacher->full_name,
                        'weekly_jp' => $weeklyJp,
                        'remaining_jp' => $remaining,
                    ];
                }
            }
        }

        return $options;
    }

    public function saveActivity(ScheduleActivityRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $activity = trim($data['label']);
        $plan = DB::transaction(function () use ($data, $activity): SchedulePlan {
            $planPeriodId = SchedulePlan::query()->whereKey($data['schedule_plan_id'])->value('academic_period_id');
            $period = AcademicPeriod::query()->with('academicYear')->lockForUpdate()->whereKey($planPeriodId)->firstOrFail();
            $plan = SchedulePlan::query()->lockForUpdate()->whereKey($data['schedule_plan_id'])->firstOrFail();
            if ($plan->status !== 'published') {
                throw ValidationException::withMessages([
                    'activity' => 'Jadwal telah berubah. Muat ulang sebelum menambahkan kegiatan.',
                ]);
            }
            if ($period->status !== 'active' || $period->academicYear->status !== 'active') {
                throw ValidationException::withMessages([
                    'activity' => 'Periode atau Tahun Ajaran tidak aktif.',
                ]);
            }
            $slot = collect($this->lessonSlotsByDay()[$data['day']] ?? [])
                ->firstWhere('number', (int) $data['lesson_number']);
            if ($slot === null) {
                throw ValidationException::withMessages([
                    'activity' => 'Slot kegiatan tidak sesuai konfigurasi jadwal.',
                ]);
            }
            $customSlot = $plan->customSlots()->where('day', $data['day'])
                ->where('lesson_number', $data['lesson_number'])->lockForUpdate()->first();
            $entries = $plan->entries()->where('day', $data['day'])
                ->where('lesson_number', $data['lesson_number'])->lockForUpdate()->get();
            if ($entries->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'activity' => 'Masih ada mata pelajaran pada baris ini. Hapus atau pindahkan jadwal pelajaran sebelum menambahkan kegiatan.',
                ]);
            }

            if ($customSlot === null) {
                $plan->customSlots()->create([
                    'academic_period_id' => $period->id,
                    'day' => $data['day'],
                    'lesson_number' => (int) $data['lesson_number'],
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                    'label' => $activity,
                ]);
            } else {
                $customSlot->update(['label' => $activity]);
            }

            $this->refreshPlanPayload($plan);

            return $plan;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kegiatan jadwal berhasil disimpan.']);

        return redirect()->route('schedule.index', [
            'academic_period_id' => $plan->academic_period_id,
            'grade' => $plan->grade_level,
            'rombel_ids' => $data['selected_rombel_ids'] ?? $plan->selected_rombel_ids ?? [],
        ]);
    }

    private function refreshPlanPayload(SchedulePlan $plan): void
    {
        $assignments = $plan->teachingAssignments()->get();
        $entriesByAssignment = $plan->entries()->get()->groupBy('teaching_assignment_id');
        $canonicalAssignments = $this->canonicalAssignments($assignments->map(fn (ScheduleTeachingAssignment $item): array => [
            'teacher_subject_id' => $item->teacher_subject_id,
            'rombel_ids' => [$item->rombel_id],
        ])->all());
        $manualPlacements = $this->canonicalManualPlacements($assignments->map(fn (ScheduleTeachingAssignment $item): array => [
            'teacher_subject_id' => $item->teacher_subject_id,
            'rombel_id' => $item->rombel_id,
            'slots' => $entriesByAssignment->get($item->id, collect())->map(fn (ScheduleEntry $entry): array => [
                'day' => $entry->day,
                'lesson_number' => $entry->lesson_number,
            ])->all(),
        ])->all());
        $customSlots = $this->canonicalCustomSlots($plan->customSlots()->get(['day', 'lesson_number', 'label'])->toArray());
        $plan->update([
            'payload_hash' => $this->schedulePayloadHash($canonicalAssignments, $customSlots, $manualPlacements),
            'revision' => $plan->revision + 1,
        ]);
    }

    public function move(ScheduleMoveRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $periodId = DB::transaction(function () use ($data): int {
            $planPeriodId = SchedulePlan::query()->whereKey($data['schedule_plan_id'])->value('academic_period_id');
            $period = AcademicPeriod::query()->with('academicYear')->lockForUpdate()->whereKey($planPeriodId)->firstOrFail();
            $plan = SchedulePlan::query()->lockForUpdate()->whereKey($data['schedule_plan_id'])->firstOrFail();
            /** @var ScheduleEntry $entry */
            $entry = ScheduleEntry::query()
                ->lockForUpdate()
                ->whereKey($data['schedule_entry_id'])
                ->where('schedule_plan_id', $data['schedule_plan_id'])
                ->firstOrFail();
            if ($plan->status !== 'published') {
                throw ValidationException::withMessages([
                    'move' => 'Jadwal yang dipindahkan bukan jadwal terbit.',
                ]);
            }

            if (isset($data['target_rombel_id']) && (int) $data['target_rombel_id'] !== $entry->rombel_id) {
                throw ValidationException::withMessages([
                    'move' => 'Jadwal hanya dapat dipindahkan atau ditukar dalam rombel yang sama.',
                ]);
            }

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
            $target = $plan->entries()
                ->where('rombel_id', $entry->rombel_id)
                ->where('day', $data['day'])
                ->where('lesson_number', $data['lesson_number'])
                ->lockForUpdate()->first();

            if ($target?->id === $entry->id) {
                return $period->id;
            }

            $sourcePosition = $entry->only(['day', 'lesson_number', 'start_time', 'end_time']);
            $positions = [[
                'teacher_id' => $entry->teacher_id,
                'subject_id' => $entry->subject_id,
                'day' => $data['day'],
                'lesson_number' => (int) $data['lesson_number'],
            ]];
            if ($target !== null) {
                $sourceSlot = collect($this->lessonSlotsByDay()[$entry->day] ?? [])->firstWhere('number', $entry->lesson_number);
                if ($sourceSlot === null || $plan->customSlots()->where('day', $entry->day)->where('lesson_number', $entry->lesson_number)->exists()) {
                    throw ValidationException::withMessages(['move' => 'Slot jadwal tidak sesuai konfigurasi hari.']);
                }
                $sourcePosition['start_time'] = $sourceSlot['start'];
                $sourcePosition['end_time'] = $sourceSlot['end'];
                $positions[] = [
                    'teacher_id' => $target->teacher_id,
                    'subject_id' => $target->subject_id,
                    'day' => $entry->day,
                    'lesson_number' => $entry->lesson_number,
                ];
            }

            foreach ($positions as $position) {
                $hasConflict = ScheduleEntry::query()
                    ->where('academic_period_id', $period->id)
                    ->whereHas('schedulePlan', fn ($query) => $query->where('status', 'published'))
                    ->whereNotIn('id', array_filter([$entry->id, $target?->id]))
                    ->where('day', $position['day'])
                    ->where('lesson_number', $position['lesson_number'])
                    ->conflictingWith($position['teacher_id'], $position['subject_id'], $entry->rombel_id)
                    ->exists();
                if ($hasConflict) {
                    throw ValidationException::withMessages([
                        'move' => 'Slot tujuan bertabrakan dengan jadwal lain.',
                    ]);
                }
            }

            if ($target !== null) {
                // Free the unique slot inside this transaction without deleting either entry.
                $entry->update(['day' => '__swap__']);
                $target->update($sourcePosition);
            }
            $entry->update([
                'day' => $data['day'],
                'lesson_number' => $data['lesson_number'],
                'start_time' => $configuredSlot['start'],
                'end_time' => $configuredSlot['end'],
            ]);

            return $period->id;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Posisi jadwal berhasil diperbarui.']);

        return redirect()->route('schedule.index', [
            'academic_period_id' => $periodId,
            'rombel_ids' => array_values(array_unique(array_map('intval', $data['selected_rombel_ids'] ?? []))),
        ]);
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
                ->where('status', 'published')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($plan === null) {
                return;
            }

            if (LmsCourseRun::query()->whereIn('schedule_teaching_assignment_id', $plan->teachingAssignments()->select('id'))->exists()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Jadwal memiliki hasil Course tersimpan dan tidak dapat dihapus.',
                ]);
            }

            if (SchedulePlan::query()->where('source_plan_id', $plan->id)->exists()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Jadwal memiliki versi turunan dan tidak dapat dihapus.',
                ]);
            }

            $plan->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal jenjang berhasil dihapus.']);

        return redirect()->route('schedule.index', [
            'academic_period_id' => $validated['academic_period_id'],
            'grade' => $validated['grade'],
        ]);
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
        if ($selectedRombelIds === [] && $draftPlan !== null) {
            $persistedRombelIds = $draftPlan->getAttribute('selected_rombel_ids');
            $selectedRombelIds = is_array($persistedRombelIds)
                ? array_map('intval', $persistedRombelIds)
                : [];
        }
        $draftRombelIds = $draftPlan?->teachingAssignments->pluck('rombel_id')->map(fn (int $id): int => $id)->all() ?? [];
        $selectedRombelIds = array_values(array_intersect(
            array_unique([...$selectedRombelIds, ...$draftRombelIds]),
            $periodRombels->pluck('id')->all(),
        ));
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
            'combined_subjects' => TeacherSubject::query()
                ->whereIn('id', array_column($assignments, 'teacher_subject_id'))
                ->whereHas('subject', fn ($query) => $query->where('allow_combined', true))
                ->orderBy('id')->pluck('id')->all(),
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
        if ($keys->unique()->count() !== $keys->count()) {
            throw ValidationException::withMessages([
                'custom_slots' => 'Satu slot hanya dapat memiliki satu kegiatan khusus.',
            ]);
        }

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
            if ($slot === null) {
                throw ValidationException::withMessages([
                    'custom_slots' => 'Slot kegiatan khusus tidak sesuai konfigurasi jadwal.',
                ]);
            }

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

    /**
     * @param  array{teacher_subject_id: int, rombel_id: int, slots: array<int, array{day: string, lesson_number: int}>}|null  $manualPlacement
     */
    private function createEntriesForAssignment(SchedulePlan $plan, ScheduleTeachingAssignment $assignment, ?array $manualPlacement): void
    {
        if ($manualPlacement === null) {
            $this->createDraftEntries($plan, $assignment);

            return;
        }

        if (count($manualPlacement['slots']) !== $assignment->weekly_jp) {
            throw $this->assignmentValidationException($assignment, 'Jumlah slot manual tidak sesuai JP mingguan.');
        }
        $configuredSlots = $this->lessonSlotsByDay();
        $customSlotKeys = $plan->customSlots()
            ->get(['day', 'lesson_number'])
            ->mapWithKeys(fn (ScheduleCustomSlot $slot): array => [$slot->day.':'.$slot->lesson_number => true]);
        $seenSlots = [];
        foreach ($manualPlacement['slots'] as $manualSlot) {
            $key = $manualSlot['day'].':'.$manualSlot['lesson_number'];
            $slot = collect($configuredSlots[$manualSlot['day']] ?? [])
                ->firstWhere('number', $manualSlot['lesson_number']);
            if ($slot === null) {
                throw $this->assignmentValidationException($assignment, 'Slot manual tidak sesuai konfigurasi jadwal.');
            }
            if ($customSlotKeys->has($key)) {
                throw $this->assignmentValidationException($assignment, 'Slot manual bertabrakan dengan kegiatan khusus. Pindahkan posisi pelajaran atau ubah slot kegiatan.');
            }
            if (isset($seenSlots[$key])) {
                throw $this->assignmentValidationException($assignment, 'Slot manual duplikat.');
            }
            $hasConflict = ScheduleEntry::query()
                ->where(function ($query) use ($plan, $assignment): void {
                    $query->where('schedule_plan_id', $plan->id)
                        ->orWhere(function ($query) use ($assignment): void {
                            $query->where('academic_period_id', $assignment->academic_period_id)
                                ->whereHas('schedulePlan', fn ($query) => $query->where('status', 'published'));
                        });
                })
                ->where('day', $manualSlot['day'])
                ->where('lesson_number', $manualSlot['lesson_number'])
                ->conflictingWith($assignment->teacher_id, $assignment->subject_id, $assignment->rombel_id)
                ->exists();
            if ($hasConflict) {
                throw $this->assignmentValidationException($assignment, 'Slot manual bertabrakan dengan jadwal lain.');
            }
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

    /** @param array{day: string, lesson_number: int}|null $preferredSlot */
    private function createDraftEntries(SchedulePlan $plan, ScheduleTeachingAssignment $assignment, ?array $preferredSlot = null): void
    {
        $slotsByDay = $this->lessonSlotsByDay();
        $dayOrder = array_keys($slotsByDay);
        $existingEntries = $plan->entries()->where('teaching_assignment_id', $assignment->id)->get(['day']);
        $remaining = $assignment->weekly_jp - $existingEntries->count();
        $dailyCounts = array_replace(array_fill_keys($dayOrder, 0), $existingEntries->countBy('day')->all());
        $anchorPending = $preferredSlot !== null;
        if ($anchorPending && $remaining <= 0) {
            throw $this->assignmentValidationException($assignment, 'Seluruh JP mingguan sudah terjadwal.');
        }
        if ($anchorPending && ($dailyCounts[$preferredSlot['day']] ?? 0) >= 3) {
            throw $this->assignmentValidationException($assignment, 'Batas maksimal 3 JP pada hari yang dipilih sudah terpenuhi.');
        }
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
                            ->where(function ($query) use ($plan): void {
                                $query->where('schedule_plan_id', $plan->id)
                                    ->orWhere(function ($query) use ($plan): void {
                                        $query->where('academic_period_id', $plan->academic_period_id)
                                            ->whereHas('schedulePlan', fn ($query) => $query->where('status', 'published'));
                                    });
                            })
                            ->where('day', $day)
                            ->where('lesson_number', $candidate['number'])
                            ->conflictingWith($assignment->teacher_id, $assignment->subject_id, $assignment->rombel_id)
                            ->exists();
                    })
                    ->values();

                foreach ($availableSlots as $startIndex => $startSlot) {
                    if ($anchorPending && ($day !== $preferredSlot['day'] || $startSlot['number'] !== $preferredSlot['lesson_number'])) {
                        continue;
                    }
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

            usort($candidates, function (array $left, array $right) use ($preferredSlot): int {
                $leftPreferred = $preferredSlot !== null && $left['day'] === $preferredSlot['day'] ? 0 : 1;
                $rightPreferred = $preferredSlot !== null && $right['day'] === $preferredSlot['day'] ? 0 : 1;

                return [$leftPreferred, $right['length'], $left['teacher_load'], $left['day_index'], $left['slots'][0]['number']]
                    <=> [$rightPreferred, $left['length'], $right['teacher_load'], $right['day_index'], $right['slots'][0]['number']];
            });
            $selected = $candidates[0] ?? null;
            if ($selected === null) {
                if ($anchorPending) {
                    throw $this->assignmentValidationException($assignment, 'Slot yang dipilih tidak tersedia, bertabrakan dengan jadwal lain, atau merupakan kegiatan khusus.');
                }
                throw $this->assignmentValidationException(
                    $assignment,
                    "Penempatan otomatis belum menemukan slot untuk {$remaining} JP tersisa. Periksa beban Guru/Rombel, posisi manual, dan kegiatan khusus.",
                );
            }

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

            $anchorPending = false;
            $dailyCounts[$selected['day']] += $selected['length'];
            $remaining -= $selected['length'];
        }
    }

    private function assignmentValidationException(ScheduleTeachingAssignment $assignment, string $reason): ValidationException
    {
        return ValidationException::withMessages([
            'schedule' => "{$assignment->subject_name} — {$assignment->teacher_name} — {$assignment->rombel_code}: {$reason}",
        ]);
    }

    /**
     * @return array<string, array<int, array{number: int, start: string, end: string}>>
     */
    private function lessonSlotsByDay(): array
    {
        return app(ScheduleReadService::class)->lessonSlotsByDay();
    }

    /**
     * @param  array<int, int>  $rombelIds
     * @return Collection<int, ScheduleEntry>
     */
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
        $readService = app(ScheduleReadService::class);
        $rowsByDay = $readService->scheduleRows($customSlots);

        return collect($readService->days())
            ->map(fn (array $day): array => [
                'key' => $day['key'],
                'label' => $day['label'],
                'rows' => $rowsByDay[$day['key']],
            ])
            ->all();
    }
}
