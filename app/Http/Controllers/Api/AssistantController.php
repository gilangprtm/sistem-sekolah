<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Services\Assistant\NineRouterClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

        $tools = $this->toolsFor($request->user());
        $messages = array_merge([
            ['role' => 'system', 'content' => 'Jawab dalam Bahasa Indonesia. Gunakan hanya tool yang tersedia. Jangan mengarang data.'],
        ], $data['history'] ?? [], [['role' => 'user', 'content' => $data['message']]]);

        try {
            $result = $this->client->complete($messages, $tools);
            $message = $result['choices'][0]['message'] ?? [];

            if (! empty($message['tool_calls'])) {
                $toolMessages = [$message];
                foreach ($message['tool_calls'] as $call) {
                    $name = $call['function']['name'] ?? '';
                    $arguments = json_decode($call['function']['arguments'] ?? '{}', true);
                    $result = $this->executeTool($request->user(), $name, is_array($arguments) ? $arguments : []);
                    $toolMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'] ?? $name,
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }
                $followUp = $this->client->complete(array_merge($messages, $toolMessages), $tools);
                return response()->json(['message' => $followUp['choices'][0]['message']['content'] ?? 'Assistant tidak memberikan jawaban.']);
            }

            return response()->json(['message' => $message['content'] ?? 'Assistant tidak memberikan jawaban.']);
        } catch (Throwable) {
            return response()->json(['message' => 'Assistant sedang tidak tersedia.'], 503);
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

    private function toolsFor($user): array
    {
        if (! $user->can('inventory.view')) {
            return [];
        }

        return [
            ['type' => 'function', 'function' => [
                'name' => 'inventory_summary',
                'description' => 'Ringkasan jumlah inventaris. Read-only.',
                'parameters' => ['type' => 'object', 'properties' => [], 'additionalProperties' => false],
            ]],
            ['type' => 'function', 'function' => [
                'name' => 'inventory_search',
                'description' => 'Mencari inventaris secara read-only.',
                'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'maxLength' => 100]], 'required' => ['query'], 'additionalProperties' => false],
            ]],
        ];
    }
}
