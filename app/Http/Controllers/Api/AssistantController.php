<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
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
            $plainMode ? [] : [['role' => 'system', 'content' => 'Kamu adalah asisten Sistem Sekolah yang membantu pengguna memahami dan menggunakan berbagai modul dalam sistem. Jawablah dengan bahasa Indonesia yang sopan, natural, jelas, dan sesuai konteks percakapan. Kamu dapat membantu percakapan umum maupun pertanyaan yang berkaitan dengan data dan fitur sistem. Gunakan capability dan tools yang tersedia untuk memperoleh data atau menjalankan tugas yang memang diizinkan. Jangan mengarang data, jangan mengasumsikan fakta yang belum tersedia, dan jangan mengklaim telah melakukan tindakan yang tidak benar-benar dilakukan. Jika pertanyaan membutuhkan data sistem, ambil data terbaru melalui capability yang sesuai. Jangan hanya mengandalkan jawaban sebelumnya apabila data dapat diverifikasi kembali. Jika pertanyaan kurang jelas, gunakan konteks percakapan untuk memahaminya. Jika masih ambigu, ajukan pertanyaan klarifikasi yang singkat dan membantu. Jawab langsung sesuai kebutuhan pengguna. Untuk percakapan sederhana, tanggapi secara natural tanpa menggunakan capability yang tidak diperlukan. Jika data atau capability yang dibutuhkan belum tersedia, jelaskan keterbatasannya dengan jujur dan jangan mengarang jawaban.']],
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
                        'message' => $content !== '' ? $content : 'Assistant tidak memberikan jawaban.',
                    ]);
                }

                foreach ($message['tool_calls'] as $call) {
                    $signature = hash('sha256', json_encode([
                        $call['function']['name'] ?? '',
                        $call['function']['arguments'] ?? '{}',
                    ], JSON_UNESCAPED_UNICODE));
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

            throw new \RuntimeException('assistant_tool_round_limit');
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

    private function executeTool($user, string $name, array $arguments): array
    {
        abort_unless($user->can('inventory.view'), 403);

        return match ($name) {
            'inventory_summary' => [
                'total_items' => InventoryItem::query()->count(),
                'total_units' => InventoryItem::query()->withCount('units')->get()->sum('units_count'),
            ],
            'inventory_query' => $this->queryInventory($arguments),
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
            if (array_key_exists($field, $filters) && in_array($field, $allowedFields, true)) {
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

    private function toolsFor($user): array
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
                'description' => 'Query inventaris read-only dengan field, filter, sort, dan pagination terbatas.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'fields' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 13],
                    'filters' => ['type' => 'object', 'additionalProperties' => true],
                    'sort' => ['type' => 'object', 'properties' => ['field' => ['type' => 'string'], 'direction' => ['type' => 'string', 'enum' => ['asc', 'desc']]], 'additionalProperties' => false],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
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
