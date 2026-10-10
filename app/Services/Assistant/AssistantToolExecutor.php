<?php

namespace App\Services\Assistant;

use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\KantinBarang;
use App\Models\StudentPlacement;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Models\YearClass;
use App\Services\InventoryCategoryService;
use App\Services\InventoryItemService;
use App\Services\InventoryRegisterService;
use App\Services\InventoryRoomService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssistantToolExecutor
{
    public function __construct(
        private readonly InventoryCategoryService $categoryService,
        private readonly InventoryItemService $itemService,
        private readonly InventoryRegisterService $registerService,
        private readonly InventoryRoomService $roomService,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int|string, mixed>
     */
    public function execute(User $user, string $name, array $arguments): array
    {
        if (! in_array($name, ['teacher_subjects', 'curriculum_class_query', 'kantin_catalog_query', 'kantin_insights_query'], true)) {
            abort_unless($user->can('inventory.view'), 403);
        }

        return match ($name) {
            'curriculum_class_query' => $this->queryClasses($arguments),
            'teacher_subjects' => $this->queryTeacherSubjects($arguments),
            'kantin_catalog_query' => $this->queryKantinCatalog($arguments),
            'kantin_insights_query' => $this->queryKantinInsights($arguments),
            'inventory_items' => $this->queryInventoryItemsResource($arguments),
            'inventory_registers' => $this->queryInventoryRegistersResource($arguments),
            'inventory_rooms' => $this->queryInventoryRoomsResource($arguments),
            'inventory_categories' => $this->queryInventoryCategoriesResource($arguments),
            default => throw new \InvalidArgumentException('Unknown assistant tool.'),
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryClasses(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, ['academic_year_search', 'class_search', 'student_search', 'include', 'page', 'per_page']);

        $academicYearSearch = $this->nullableString($arguments, 'academic_year_search');
        $academicYear = AcademicYear::query()
            ->when($academicYearSearch === null, fn ($query) => $query->where('status', 'active'))
            ->when($academicYearSearch !== null, fn ($query) => $query->where('year', $academicYearSearch))
            ->first();
        if ($academicYear === null) {
            return ['data' => [], 'meta' => ['current_page' => $this->paginationArgument($arguments, 'page'), 'per_page' => $this->paginationArgument($arguments, 'per_page', 25, 50), 'total' => 0, 'last_page' => 1]];
        }

        $classSearch = $this->nullableString($arguments, 'class_search');
        $studentSearch = $this->nullableString($arguments, 'student_search');
        $include = $arguments['include'] ?? ['class_summary', 'homeroom_teacher', 'student_count', 'student_placements'];
        if (! is_array($include) || $include === [] || array_diff($include, ['class_summary', 'homeroom_teacher', 'student_count', 'student_placements']) !== []) {
            throw new \InvalidArgumentException('Invalid class query include.');
        }
        $page = $this->paginationArgument($arguments, 'page');
        $perPage = $this->paginationArgument($arguments, 'per_page', 25, 50);
        $classes = YearClass::query()
            ->with('rombel:id,code,name,grade_level,parallel_code')
            ->where('academic_year_id', $academicYear->id)
            ->whereHas('rombel', function ($query) use ($classSearch): void {
                $query->where('status', 'active')->when($classSearch !== null, function ($searchQuery) use ($classSearch): void {
                    $like = '%'.mb_strtolower($classSearch).'%';
                    $searchQuery->where(function ($classQuery) use ($like): void {
                        $classQuery->whereRaw('LOWER(code) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(name) LIKE ?', [$like]);
                    });
                });
            })
            ->when($studentSearch !== null, function ($query) use ($academicYear, $studentSearch): void {
                $like = '%'.mb_strtolower($studentSearch).'%';
                $query->whereIn('rombel_id', StudentPlacement::query()
                    ->join('m_students', 'm_students.id', '=', 'tr_curriculum_student_placements.student_id')
                    ->where('tr_curriculum_student_placements.academic_year_id', $academicYear->id)
                    ->where('tr_curriculum_student_placements.status', 'active')
                    ->where(function ($studentQuery) use ($like): void {
                        $studentQuery->whereRaw('LOWER(m_students.full_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(m_students.nis) LIKE ?', [$like]);
                    })
                    ->select('tr_curriculum_student_placements.rombel_id'));
            })
            ->orderBy('rombel_id');
        $total = (clone $classes)->count();
        $selectedClasses = $classes->forPage($page, $perPage)->get();
        $rombelIds = $selectedClasses->pluck('rombel_id');
        $homerooms = HomeroomAssignment::query()
            ->with('teacher:id,full_name')
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('rombel_id', $rombelIds)
            ->where('status', 'active')
            ->get()
            ->groupBy('rombel_id');
        $placements = StudentPlacement::query()
            ->with('student:id,nis,full_name')
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('rombel_id', $rombelIds)
            ->where('status', 'active')
            ->get()
            ->groupBy('rombel_id');
        $data = $selectedClasses->map(function (YearClass $yearClass) use ($academicYear, $homerooms, $placements, $studentSearch, $include): array {
            $rombel = $yearClass->rombel;
            $homeroom = $homerooms->get($yearClass->rombel_id, collect())->first()?->teacher;
            $allStudents = $placements->get($yearClass->rombel_id, collect());
            $students = $allStudents->filter(function ($placement) use ($studentSearch): bool {
                return $studentSearch === null || str_contains(mb_strtolower($placement->student->full_name), mb_strtolower($studentSearch)) || str_contains(mb_strtolower((string) $placement->student->nis), mb_strtolower($studentSearch));
            })->map(fn ($placement): array => [
                'full_name' => $placement->student->full_name,
            ])->values()->all();

            return [
                'academic_year' => ['id' => $academicYear->id, 'year' => $academicYear->year],
                'class_summary' => in_array('class_summary', $include, true) ? $rombel->only(['id', 'code', 'name', 'grade_level', 'parallel_code']) : null,
                'homeroom_teacher' => in_array('homeroom_teacher', $include, true) ? $homeroom?->only(['id', 'full_name']) : null,
                'student_count' => in_array('student_count', $include, true) ? $allStudents->count() : null,
                'student_placements' => in_array('student_placements', $include, true) ? $students : null,
            ];
        })->all();

        return ['data' => $data, 'meta' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max((int) ceil($total / $perPage), 1)]];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryTeacherSubjects(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, ['subject_search', 'page', 'per_page']);
        $subjectSearch = $arguments['subject_search'] ?? null;
        if ($subjectSearch !== null && ! is_string($subjectSearch)) {
            throw new \InvalidArgumentException('Invalid teacher subject search.');
        }
        $subjectSearch = is_string($subjectSearch) ? trim($subjectSearch) : null;
        if ($subjectSearch !== null && mb_strlen($subjectSearch) > 100) {
            throw new \InvalidArgumentException('Invalid teacher subject search.');
        }
        $subjectSearch = $subjectSearch === '' ? null : $subjectSearch;

        $page = $this->paginationArgument($arguments, 'page', 1, 50);
        $perPage = $this->paginationArgument($arguments, 'per_page', 25, 50);
        $search = $subjectSearch === null ? null : mb_strtolower($subjectSearch);
        $matches = TeacherSubject::query()
            ->join('m_teacher', 'm_teacher.id', '=', 'm_teacher_subjects.teacher_id')
            ->join('m_subjects', 'm_subjects.id', '=', 'm_teacher_subjects.subject_id')
            ->where('m_teacher.staff_type', 'guru')
            ->where('m_teacher.status', 'active')
            ->where('m_subjects.status', 'active')
            ->when($search !== null, function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->whereRaw('LOWER(m_subjects.code) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(m_subjects.name) LIKE ?', ['%'.$search.'%']);
                });
            })
            ->select('m_teacher.id as teacher_id', 'm_teacher.full_name')
            ->groupBy('m_teacher.id', 'm_teacher.full_name');
        $orderedMatches = DB::query()
            ->fromSub($matches, 'teacher_subject_matches')
            ->orderByRaw('LOWER(full_name)')
            ->orderBy('teacher_id');
        $total = (clone $orderedMatches)->count();
        $lastPage = max((int) ceil($total / $perPage), 1);
        $data = $orderedMatches
            ->forPage($page, $perPage)
            ->pluck('full_name')
            ->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryKantinCatalog(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, ['search', 'category', 'status', 'page', 'per_page']);

        $search = $this->nullableString($arguments, 'search');
        $category = $this->nullableString($arguments, 'category');
        $status = $arguments['status'] ?? 'active';
        if (! is_string($status) || ! in_array($status, ['active', 'inactive'], true)) {
            throw new \InvalidArgumentException('Invalid Kantin catalog status.');
        }

        $page = $this->paginationArgument($arguments, 'page', 1, 50);
        $perPage = $this->paginationArgument($arguments, 'per_page', 25, 50);
        $query = KantinBarang::query()
            ->with('category:id,name')
            ->where('status', $status)
            ->when($search !== null, function ($query) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';
                $query->where(function ($searchQuery) use ($like): void {
                    $searchQuery->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(brand) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(satuan) LIKE ?', [$like])
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$like]));
                });
            })
            ->when($category !== null, fn ($query) => $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($category).'%'])))
            ->orderBy('name')
            ->orderBy('id');

        $result = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $result->getCollection()->map(fn (KantinBarang $item): array => [
                'name' => $item->name,
                'category' => $item->category->name,
                'brand' => $item->brand,
                'satuan' => $item->satuan,
                'harga' => $this->formatKantinHarga($item->harga),
                'description' => $item->description,
                'image_url' => $item->imageUrl(),
                'status' => $item->status,
            ])->values()->all(),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryKantinInsights(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, ['mode', 'period', 'search', 'category', 'page', 'per_page']);

        $mode = $arguments['mode'] ?? 'best_sellers';
        if (! is_string($mode) || ! in_array($mode, ['best_sellers', 'recommendations'], true)) {
            throw new \InvalidArgumentException('Invalid Kantin insights mode.');
        }
        $period = $arguments['period'] ?? 'all';
        if (! is_string($period) || ! in_array($period, ['today', 'week', 'month', 'year', 'all'], true)) {
            throw new \InvalidArgumentException('Invalid Kantin insights period.');
        }

        $search = $this->nullableString($arguments, 'search');
        $category = $this->nullableString($arguments, 'category');
        $page = $this->paginationArgument($arguments, 'page', 1, 50);
        $perPage = $this->paginationArgument($arguments, 'per_page', 25, 50);
        $now = now();
        $periodStart = match ($period) {
            'today' => $now->copy()->startOfDay(),
            'week' => $now->copy()->startOfWeek(Carbon::MONDAY),
            'month' => $now->copy()->startOfMonth(),
            'year' => $now->copy()->startOfYear(),
            default => null,
        };

        $query = DB::table('tr_kantin_penjualan_detail as details')
            ->join('tr_kantin_penjualan as sales', 'sales.id', '=', 'details.penjualan_id')
            ->join('m_kantin_barang as products', 'products.id', '=', 'details.kantin_barang_id')
            ->join('m_kantin_kategori as categories', 'categories.id', '=', 'products.kantin_kategori_id')
            ->where('products.status', 'active')
            ->when($periodStart !== null, fn ($query) => $query
                ->where('sales.created_at', '>=', $periodStart)
                ->where('sales.created_at', '<=', $now))
            ->when($search !== null, function ($query) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';
                $query->where(function ($searchQuery) use ($like): void {
                    $searchQuery->whereRaw('LOWER(products.name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(products.brand) LIKE ?', [$like]);
                });
            })
            ->when($category !== null, fn ($query) => $query->whereRaw('LOWER(categories.name) LIKE ?', ['%'.mb_strtolower($category).'%']))
            ->select('products.id', 'products.name', 'products.brand', 'products.satuan', 'products.harga', 'categories.name as category')
            ->selectRaw('SUM(details.quantity) as quantity, SUM(details.subtotal) as total')
            ->groupBy('products.id', 'products.name', 'products.brand', 'products.satuan', 'products.harga', 'categories.name')
            ->orderByDesc('quantity')
            ->orderBy('products.name')
            ->orderBy('products.id');

        $result = $query->paginate($perPage, ['*'], 'page', $page);
        $data = $result->getCollection()->map(fn (object $item): array => [
            'name' => $item->name,
            'brand' => $item->brand,
            'category' => $item->category,
            'satuan' => $item->satuan,
            'harga' => $this->formatKantinHarga((string) $item->harga),
            'quantity' => (int) $item->quantity,
            'total' => (string) $item->total,
            'recommendation' => $mode === 'recommendations'
                ? 'Pilihan populer berdasarkan agregat penjualan pada periode yang dipilih.'
                : null,
        ])->values()->all();

        return [
            'mode' => $mode,
            'period' => $period,
            'data' => $data,
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ];
    }

    private function formatKantinHarga(string $harga): string
    {
        [$integer, $fraction] = array_pad(explode('.', $harga, 2), 2, '00');
        $integer = ltrim($integer, '0') ?: '0';
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return 'Rp '.number_format((int) $integer, 0, ',', '.').','.$fraction;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventoryItemsResource(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, [
            'search', 'inventory_category_id', 'inventory_type_id', 'condition', 'year', 'asset_kind', 'page', 'per_page',
        ]);

        return $this->itemService->paginate([
            'search' => $this->nullableString($arguments, 'search'),
            'inventory_category_id' => $this->nullableInteger($arguments, 'inventory_category_id', 1),
            'inventory_type_id' => $this->nullableInteger($arguments, 'inventory_type_id', 1),
            'condition' => $this->nullableEnum($arguments, 'condition', ['B', 'KB', 'RB']),
            'year' => $this->nullableInteger($arguments, 'year', 1900, 2100),
            'asset_kind' => $this->nullableEnum($arguments, 'asset_kind', ['tangible', 'intangible']),
        ], $this->paginationArgument($arguments, 'page'), $this->paginationArgument($arguments, 'per_page', 25, 50));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventoryRegistersResource(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, [
            'search', 'inventory_item_id', 'room_id', 'condition', 'year', 'page', 'per_page',
        ]);

        return $this->registerService->paginate([
            'search' => $this->nullableString($arguments, 'search'),
            'inventory_item_id' => $this->nullableInteger($arguments, 'inventory_item_id', 1),
            'room_id' => $this->nullableInteger($arguments, 'room_id', 1),
            'condition' => $this->nullableEnum($arguments, 'condition', ['B', 'KB', 'RB']),
            'year' => $this->nullableInteger($arguments, 'year', 1900, 2100),
        ], $this->paginationArgument($arguments, 'page'), $this->paginationArgument($arguments, 'per_page', 25, 50));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventoryRoomsResource(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, ['search', 'page', 'per_page']);

        return $this->roomService->paginate(
            $this->nullableString($arguments, 'search'),
            $this->paginationArgument($arguments, 'page'),
            $this->paginationArgument($arguments, 'per_page', 25, 50),
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventoryCategoriesResource(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, ['search', 'page', 'per_page']);

        return $this->categoryService->paginate(
            $this->nullableString($arguments, 'search'),
            $this->paginationArgument($arguments, 'page'),
            $this->paginationArgument($arguments, 'per_page', 25, 50),
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, string>  $allowed
     */
    private function assertAllowedArguments(array $arguments, array $allowed): void
    {
        if (array_diff(array_keys($arguments), $allowed) !== []) {
            throw new \InvalidArgumentException('Unsupported assistant resource argument.');
        }
    }

    /** @param array<string, mixed> $arguments */
    private function nullableString(array $arguments, string $key): ?string
    {
        $value = $arguments[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || mb_strlen($value) > 100) {
            throw new \InvalidArgumentException('Invalid assistant resource search.');
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function nullableInteger(array $arguments, string $key, int $minimum, ?int $maximum = null): ?int
    {
        $value = $arguments[$key] ?? null;
        if ($value === null) {
            return null;
        }
        $integer = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
        if ($integer === null || $integer < $minimum || ($maximum !== null && $integer > $maximum)) {
            throw new \InvalidArgumentException('Invalid assistant resource filter.');
        }

        return $integer;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, string>  $allowed
     */
    private function nullableEnum(array $arguments, string $key, array $allowed): ?string
    {
        $value = $arguments[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid assistant resource filter.');
        }

        return $value;
    }

    /** @param array<string, mixed> $arguments */
    private function paginationArgument(array $arguments, string $key, int $default = 1, int $maximum = 50): int
    {
        $value = $arguments[$key] ?? $default;
        $integer = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
        if ($integer === null || $integer < 1 || $integer > $maximum) {
            throw new \InvalidArgumentException('Invalid assistant resource pagination.');
        }

        return $integer;
    }
}
