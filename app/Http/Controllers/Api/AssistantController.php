<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Services\Assistant\NineRouterClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class AssistantController extends Controller
{
    public function __construct(private readonly NineRouterClient $client) {}

    public function chat(Request $request): JsonResponse
    {
        if (! config('services.assistant.enabled')) {
            return response()->json(['message' => 'Assistant belum diaktifkan.'], 503);
        }

        $data = Validator::make($request->all(), [
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:4000'],
        ])->validate();

        $plainMode = (bool) config('services.assistant.plain_mode', false);
        $systemPromptOnly = (bool) config('services.assistant.system_prompt_only', false);
        $tools = ($plainMode || $systemPromptOnly) ? [] : $this->toolsFor($request->user());
        $messages = array_merge(
            $plainMode ? [] : [['role' => 'system', 'content' => $this->systemPrompt($request->user())]],
            $data['history'] ?? [],
            [['role' => 'user', 'content' => $data['message']]],
        );

        try {
            $conversation = $messages;
            $startedAt = microtime(true);
            $lastToolSignature = null;

            while (true) {
                if (microtime(true) - $startedAt > 120) {
                    throw new \RuntimeException('assistant_execution_timeout');
                }

                $result = $this->client->complete($conversation, $tools);
                $message = $result['choices'][0]['message'] ?? [];
                $conversation[] = $message;

                if (empty($message['tool_calls'])) {
                    $content = trim((string) ($message['content'] ?? ''));

                    return response()->json([
                        'message' => $content !== '' ? $content : $this->fallbackMessage($request->user()),
                    ]);
                }

                foreach ($message['tool_calls'] as $call) {
                    $signature = hash('sha256', json_encode([
                        $call['function']['name'] ?? '',
                        $call['function']['arguments'] ?? '{}',
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                    if ($signature === $lastToolSignature) {
                        throw new \RuntimeException('assistant_repeated_tool_call');
                    }
                    $lastToolSignature = $signature;

                    $name = $call['function']['name'] ?? '';
                    $arguments = json_decode($call['function']['arguments'] ?? '{}', true);
                    $toolResult = $this->executeTool($request->user(), $name, is_array($arguments) ? $arguments : []);
                    $conversation[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'] ?? $name,
                        'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                    ];
                }
            }
        } catch (Throwable $exception) {
            $requestId = (string) Str::uuid();
            $rawReason = $exception->getMessage() ?: 'assistant_execution_failed';
            $reason = str_starts_with($rawReason, 'provider_') || str_starts_with($rawReason, 'assistant_')
                ? $rawReason
                : 'assistant_execution_failed';

            Log::error('Assistant request failed', [
                'request_id' => $requestId,
                'reason' => $reason,
                'exception' => $exception::class,
                'user_id' => $request->user()?->getAuthIdentifier(),
            ]);

            return response()->json([
                'message' => 'Assistant gagal memproses permintaan.',
                'error_code' => $reason,
                'request_id' => $requestId,
            ], 503);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int|string, mixed>
     */
    private function executeTool(User $user, string $name, array $arguments): array
    {
        abort_unless($user->can('inventory.view'), 403);

        return match ($name) {
            'inventory_summary' => [
                'total_items' => InventoryItem::query()->count(),
                'total_units' => InventoryItem::query()->withCount('units')->get()->sum('units_count'),
            ],
            'inventory_query' => $this->queryInventory($arguments),
            'inventory_register_query' => $this->queryInventoryRegisters($arguments),
            'inventory_search' => InventoryItem::query()
                ->with('category')
                ->withCount('units')
                ->where(function ($query) use ($arguments) {
                    $term = (string) ($arguments['query'] ?? '');
                    $query->where('kode_barang', 'like', "%{$term}%")
                        ->orWhere('nama_jenis_barang', 'like', "%{$term}%")
                        ->orWhere('merk_type', 'like', "%{$term}%");
                })
                ->limit(20)
                ->get()
                ->map(fn (InventoryItem $item) => [
                    'kode_barang' => $item->kode_barang,
                    'nama' => $item->nama_jenis_barang,
                    'kategori' => $item->category?->name,
                    'qty' => $item->units_count,
                ])
                ->values()
                ->all(),
            default => throw new \InvalidArgumentException('Unknown assistant tool.'),
        };
    }

    private function fallbackMessage(User $user): string
    {
        if (! $user->can('inventory.view')) {
            return 'Maaf, saya belum dapat mengakses data inventaris untuk akun ini. Saya tetap dapat membantu pertanyaan umum tentang penggunaan Sistem Sekolah.';
        }

        return 'Maaf, saya belum dapat memberikan jawaban yang terverifikasi. Saya dapat membantu ringkasan inventaris, pencarian aset, query aset, dan query register/unit sesuai permission akun Anda.';
    }

    private function systemPrompt(User $user): string
    {
        $capability = $user->can('inventory.view')
            ? 'Capability data inventaris tersedia: ringkasan aset, query aset, pencarian aset, dan query register/unit dengan filter, sort, serta pagination terbatas.'
            : 'Capability data inventaris tidak tersedia untuk akun ini.';

        return 'Kamu adalah asisten Sistem Sekolah. Jawab dalam bahasa Indonesia yang sopan, natural, dan jelas. Jangan mengarang data, menginferensi kondisi register/unit individual dari agregat aset, atau mengklaim tindakan yang tidak dilakukan. Untuk data sistem gunakan capability yang tersedia dan hasil terbaru; bila data/capability tidak tersedia, katakan dengan jujur dan sopan, lalu jelaskan hanya bantuan yang tersedia. '.$capability.' Untuk pertanyaan jumlah register per kondisi B, KB, atau RB, gunakan inventory_register_query terpisah untuk setiap kondisi yang ditanyakan dan gunakan pagination.total dari hasilnya. Dalam filters, kirim hanya key yang diperlukan oleh pertanyaan; jangan mengirim placeholder kosong, angka 0, atau asset_kind default untuk filter yang tidak diminta. Jangan pernah meminta atau menggunakan SQL, kode, credential, atau HTTP arbitrer. Jika pertanyaan ambigu, ajukan klarifikasi singkat.';
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventoryRegisters(array $arguments): array
    {
        $allowedFields = [
            'id', 'inventory_item_id', 'register', 'condition', 'display_code',
            'item.kode_barang', 'item.nama_jenis_barang', 'item.merk_type',
            'item.tahun_pembelian', 'item.harga', 'item.asal_perolehan',
            'item.satuan', 'item.inventory_category_id', 'item.inventory_type_id',
            'item.asset_kind',
        ];
        $fields = $this->allowlistedFields($arguments['fields'] ?? null, $allowedFields);
        $query = InventoryUnit::query()
            ->select(['id', 'inventory_item_id', 'register', 'condition'])
            ->with(['item:id,kode_barang,nama_jenis_barang,merk_type,tahun_pembelian,harga,asal_perolehan,satuan,inventory_category_id,inventory_type_id,asset_kind']);
        $filters = $this->registerFilters($this->withoutProviderDefaultRegisterFilters($arguments['filters'] ?? null));

        if (isset($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($builder) use ($term) {
                $builder->where('register', 'like', "%{$term}%")
                    ->orWhereHas('item', function ($itemQuery) use ($term) {
                        $itemQuery
                            ->where('kode_barang', 'like', "%{$term}%")
                            ->orWhere('nama_jenis_barang', 'like', "%{$term}%")
                            ->orWhere('merk_type', 'like', "%{$term}%");
                    });
            });
        }

        foreach (['register', 'condition', 'inventory_item_id'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['kode_barang', 'tahun_pembelian', 'asal_perolehan', 'satuan', 'inventory_category_id', 'inventory_type_id', 'asset_kind'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->whereHas('item', fn ($itemQuery) => $itemQuery->where($field, $filters[$field]));
            }
        }

        $sort = $this->registerSort($arguments['sort'] ?? null);
        if ($sort['field'] === 'kode_barang') {
            $query->orderBy(
                InventoryItem::query()->select('kode_barang')
                    ->whereColumn('tr_inventory_items.id', 'tr_inventory_units.inventory_item_id'),
                $sort['direction'] === 'desc' ? 'desc' : 'asc',
            );
        } else {
            $query->orderBy(
                $sort['field'],
                $sort['direction'] === 'desc' ? 'desc' : 'asc',
            );
        }
        $perPage = $this->integerArgument($arguments, 'per_page', 25, 50);
        $page = $this->integerArgument($arguments, 'page', 1);
        $result = $query->paginate($perPage, ['*'], 'page', $page);
        $itemFields = array_values(array_filter($fields, fn (string $field): bool => str_starts_with($field, 'item.')));
        $rows = $result->getCollection()->map(function (InventoryUnit $unit) use ($fields, $itemFields): array {
            $row = [];
            foreach (['id', 'inventory_item_id', 'register', 'condition'] as $field) {
                if (in_array($field, $fields, true)) {
                    $row[$field] = $unit->{$field};
                }
            }
            if (in_array('display_code', $fields, true)) {
                $row['display_code'] = $unit->item->kode_barang.'.'.$unit->register;
            }
            if ($itemFields !== []) {
                $row['item'] = [];
                foreach ($itemFields as $field) {
                    $itemField = substr($field, 5);
                    $row['item'][$itemField] = $unit->item->{$itemField};
                }
            }

            return $row;
        })->values()->all();

        return [
            'data' => $rows,
            'pagination' => ['page' => $result->currentPage(), 'per_page' => $result->perPage(), 'total' => $result->total(), 'last_page' => $result->lastPage()],
        ];
    }

    /**
     * @param  array<int, string>  $allowed
     * @return array<int, string>
     */
    private function allowlistedFields(mixed $value, array $allowed): array
    {
        if ($value === null) {
            return $allowed;
        }
        if (! is_array($value) || ! array_is_list($value) || count($value) > count($allowed) || array_filter($value, 'is_string') !== $value) {
            throw new \InvalidArgumentException('Invalid assistant fields.');
        }

        $fields = array_values(array_unique($value));
        if (array_diff($fields, $allowed) !== []) {
            throw new \InvalidArgumentException('Unsupported assistant field.');
        }

        return $fields !== [] ? $fields : $allowed;
    }

    /**
     * Some providers materialize every optional JSON-schema property using
     * empty-string, zero, and first-enum defaults. Those values do not express
     * a usable inventory filter and would otherwise turn a condition-only
     * register query into an impossible item query.
     */
    private function withoutProviderDefaultRegisterFilters(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $defaultTemplate = [
            'search' => '',
            'register' => '',
            'inventory_item_id' => 0,
            'kode_barang' => '',
            'tahun_pembelian' => 0,
            'asal_perolehan' => '',
            'satuan' => '',
            'inventory_category_id' => 0,
            'inventory_type_id' => 0,
            'asset_kind' => 'tangible',
        ];
        $condition = $value['condition'] ?? null;

        if (is_string($condition) && array_diff_assoc($value, ['condition' => $condition] + $defaultTemplate) === [] && array_diff_assoc(['condition' => $condition] + $defaultTemplate, $value) === []) {
            return ['condition' => $condition];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function registerFilters(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (! is_array($value)) {
            throw new \InvalidArgumentException('Invalid assistant filters.');
        }

        $allowed = ['search', 'register', 'condition', 'inventory_item_id', 'kode_barang', 'tahun_pembelian', 'asal_perolehan', 'satuan', 'inventory_category_id', 'inventory_type_id', 'asset_kind'];
        if (array_diff(array_keys($value), $allowed) !== []) {
            throw new \InvalidArgumentException('Unsupported assistant filter.');
        }
        $filters = [];
        foreach ($value as $field => $filter) {
            if (in_array($field, ['search', 'register', 'kode_barang', 'asal_perolehan', 'satuan'], true)) {
                if (! is_string($filter) || mb_strlen($filter) > 100) {
                    throw new \InvalidArgumentException('Invalid assistant filter value.');
                }
                $filters[$field] = trim($filter);
            } elseif ($field === 'asset_kind') {
                if (! is_string($filter) || ! in_array($filter, ['tangible', 'intangible'], true)) {
                    throw new \InvalidArgumentException('Invalid assistant asset kind.');
                }
                $filters[$field] = $filter;
            } elseif (in_array($field, ['inventory_item_id', 'tahun_pembelian', 'inventory_category_id', 'inventory_type_id'], true)) {
                if (! is_int($filter) && ! (is_string($filter) && ctype_digit($filter))) {
                    throw new \InvalidArgumentException('Invalid assistant filter value.');
                }
                $filters[$field] = (int) $filter;
            } elseif ($field === 'condition') {
                if (! is_string($filter) || ! in_array($filter, InventoryUnit::CONDITIONS, true)) {
                    throw new \InvalidArgumentException('Invalid assistant condition.');
                }
                $filters[$field] = $filter;
            }
        }

        return $filters;
    }

    /** @return array{field: string, direction: string} */
    private function registerSort(mixed $value): array
    {
        if ($value === null) {
            return ['field' => 'id', 'direction' => 'asc'];
        }
        if (! is_array($value) || array_diff(array_keys($value), ['field', 'direction']) !== []) {
            throw new \InvalidArgumentException('Invalid assistant sort.');
        }
        $fields = ['id', 'inventory_item_id', 'register', 'condition', 'kode_barang'];
        $field = $value['field'] ?? 'id';
        $direction = $value['direction'] ?? 'asc';
        if (! is_string($field) || ! in_array($field, $fields, true) || ! is_string($direction) || ! in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Unsupported assistant sort.');
        }

        return ['field' => $field, 'direction' => $direction];
    }

    /** @param array<string, mixed> $arguments */
    private function integerArgument(array $arguments, string $key, int $default, ?int $maximum = null): int
    {
        $value = $arguments[$key] ?? $default;
        $integer = is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value
            : null;
        if ($integer === null || $integer < 1 || ($maximum !== null && $integer > $maximum)) {
            throw new \InvalidArgumentException('Invalid assistant pagination.');
        }

        return $integer;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventory(array $arguments): array
    {
        $allowedFields = [
            'kode_barang', 'nama_jenis_barang', 'merk_type', 'asal_perolehan',
            'tahun_pembelian', 'harga', 'keterangan', 'inventory_category_id',
            'inventory_type_id', 'asset_kind', 'tangible_asset_type_id',
            'intangible_asset_type_id',
        ];
        $fields = array_values(array_intersect($arguments['fields'] ?? $allowedFields, $allowedFields));
        $fields = $fields !== [] ? $fields : $allowedFields;
        $query = InventoryItem::query()->select($fields)->withCount('units');
        $filters = is_array($arguments['filters'] ?? null) ? $arguments['filters'] : [];
        if (isset($filters['search']) && is_string($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($builder) use ($term) {
                $builder->where('kode_barang', 'like', "%{$term}%")
                    ->orWhere('nama_jenis_barang', 'like', "%{$term}%")
                    ->orWhere('merk_type', 'like', "%{$term}%");
            });
        }
        foreach (['inventory_category_id', 'inventory_type_id', 'asset_kind', 'tahun_pembelian'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }
        $sort = is_array($arguments['sort'] ?? null) ? $arguments['sort'] : [];
        $sortField = $sort['field'] ?? 'id';
        if (! in_array($sortField, $allowedFields, true)) {
            $sortField = 'id';
        }
        $direction = ($sort['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($arguments['per_page'] ?? 25), 1), 100);
        $page = max((int) ($arguments['page'] ?? 1), 1);
        $result = $query->orderBy($sortField, $direction)->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $result->items(),
            'pagination' => ['page' => $result->currentPage(), 'per_page' => $result->perPage(), 'total' => $result->total(), 'last_page' => $result->lastPage()],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function toolsFor(User $user): array
    {
        if (! $user->can('inventory.view')) {
            return [];
        }

        return [
            ['type' => 'function', 'function' => [
                'name' => 'inventory_summary',
                'description' => 'Ringkasan jumlah inventaris. Read-only.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'inventory_query',
                'description' => 'Query aset inventaris read-only. Gunakan field/filter/sort yang terdaftar saja; hasil tidak boleh dipakai untuk mengarang kondisi register individual.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'fields' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['kode_barang', 'nama_jenis_barang', 'merk_type', 'asal_perolehan', 'tahun_pembelian', 'harga', 'keterangan', 'inventory_category_id', 'inventory_type_id', 'asset_kind', 'tangible_asset_type_id', 'intangible_asset_type_id']], 'maxItems' => 13],
                    'filters' => ['type' => 'object', 'properties' => ['search' => ['type' => 'string', 'maxLength' => 100], 'inventory_category_id' => ['type' => 'integer'], 'inventory_type_id' => ['type' => 'integer'], 'asset_kind' => ['type' => 'string', 'enum' => ['tangible', 'intangible']], 'tahun_pembelian' => ['type' => 'integer']], 'additionalProperties' => false],
                    'sort' => ['type' => 'object', 'properties' => ['field' => ['type' => 'string', 'enum' => ['kode_barang', 'nama_jenis_barang', 'merk_type', 'asal_perolehan', 'tahun_pembelian', 'harga', 'keterangan', 'inventory_category_id', 'inventory_type_id', 'asset_kind', 'tangible_asset_type_id', 'intangible_asset_type_id']], 'direction' => ['type' => 'string', 'enum' => ['asc', 'desc']]], 'additionalProperties' => false],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ], 'additionalProperties' => false],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'inventory_register_query',
                'description' => 'Query register/unit inventaris secara read-only. Hasil menampilkan field allowlist dan display_code terhitung backend; tidak boleh digunakan untuk mengarang data yang tidak dikembalikan.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'fields' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['id', 'inventory_item_id', 'register', 'condition', 'display_code', 'item.kode_barang', 'item.nama_jenis_barang', 'item.merk_type', 'item.tahun_pembelian', 'item.harga', 'item.asal_perolehan', 'item.satuan', 'item.inventory_category_id', 'item.inventory_type_id', 'item.asset_kind']], 'maxItems' => 16],
                    'filters' => ['type' => 'object', 'description' => 'Semua key opsional. Kirim hanya filter yang diminta pengguna; jangan isi key lain dengan string kosong, 0, atau nilai default.', 'properties' => ['search' => ['type' => 'string', 'maxLength' => 100], 'register' => ['type' => 'string', 'maxLength' => 100], 'condition' => ['type' => 'string', 'enum' => ['B', 'KB', 'RB']], 'inventory_item_id' => ['type' => 'integer'], 'kode_barang' => ['type' => 'string', 'maxLength' => 100], 'tahun_pembelian' => ['type' => 'integer'], 'asal_perolehan' => ['type' => 'string', 'maxLength' => 100], 'satuan' => ['type' => 'string', 'maxLength' => 100], 'inventory_category_id' => ['type' => 'integer'], 'inventory_type_id' => ['type' => 'integer'], 'asset_kind' => ['type' => 'string', 'enum' => ['tangible', 'intangible']]], 'additionalProperties' => false],
                    'sort' => ['type' => 'object', 'properties' => ['field' => ['type' => 'string', 'enum' => ['id', 'inventory_item_id', 'register', 'condition', 'kode_barang']], 'direction' => ['type' => 'string', 'enum' => ['asc', 'desc']]], 'additionalProperties' => false],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                ], 'additionalProperties' => false],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'inventory_search',
                'description' => 'Mencari inventaris secara read-only.',
                'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'maxLength' => 100]], 'required' => ['query'], 'additionalProperties' => false],
            ]],
        ];
    }
}
