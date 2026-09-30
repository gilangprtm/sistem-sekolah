<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Assistant\AssistantContextBuilder;
use App\Services\Assistant\AssistantConversationStore;
use App\Services\Assistant\AssistantOrchestrationException;
use App\Services\Assistant\AssistantOrchestrator;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Assistant\AssistantToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class AssistantController extends Controller
{
    public function __construct(
        private readonly AssistantOrchestrator $orchestrator,
        private readonly AssistantToolRegistry $toolRegistry,
        private readonly AssistantToolExecutor $toolExecutor,
        private readonly AssistantContextBuilder $contextBuilder,
        private readonly AssistantConversationStore $conversationStore,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        if (! config('services.assistant.enabled')) {
            return response()->json(['message' => 'Assistant belum diaktifkan.'], 503);
        }

        $data = Validator::make($request->all(), [
            'message' => ['required', 'string', 'max:2000'],
            'refresh' => ['sometimes', 'boolean'],
            'conversation_id' => ['sometimes', 'nullable', 'uuid'],
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:4000'],
        ])->validate();

        $user = $request->user();
        $sessionId = $request->hasSession() ? $request->session()->getId() : 'stateless';
        $requestedConversationId = $data['conversation_id'] ?? null;
        $storedHistory = $requestedConversationId !== null
            ? $this->conversationStore->get($requestedConversationId, $user->getAuthIdentifier(), $sessionId)
            : null;
        $conversationId = $requestedConversationId;
        $history = $requestedConversationId === null
            ? ($data['history'] ?? [])
            : ($storedHistory ?? []);
        if ($requestedConversationId !== null && $storedHistory === null) {
            $conversationId = null;
            $history = [];
        }
        $plainMode = (bool) config('services.assistant.plain_mode', false);
        $systemPromptOnly = (bool) config('services.assistant.system_prompt_only', false);
        $tools = ($plainMode || $systemPromptOnly) ? [] : $this->toolRegistry->forUser($user);
        $messages = $this->contextBuilder->build(
            $user,
            $history,
            $data['message'],
            $plainMode,
            (bool) ($data['refresh'] ?? false),
            $requestedConversationId !== null && $storedHistory !== null,
        );
        $conversationId ??= $this->conversationStore->put($user->getAuthIdentifier(), $sessionId, $history);

        $requestId = (string) Str::uuid();

        $startedAt = microtime(true);
        $attempt = 0;
        $elapsedMs = null;

        try {
            $content = $this->orchestrator->run(
                $messages,
                $tools,
                $requestId,
                fn (string $name, array $arguments): array => $this->toolExecutor->execute($request->user(), $name, $arguments),
            );

            $assistantContent = $content !== '' ? $content : $this->fallbackMessage($user);
            $this->conversationStore->save(
                $conversationId,
                $user->getAuthIdentifier(),
                $sessionId,
                array_merge($history, [
                    ['role' => 'user', 'content' => $data['message']],
                    ['role' => 'assistant', 'content' => $assistantContent],
                ]),
            );

            return response()->json([
                'message' => $assistantContent,
                'conversation_id' => $conversationId,
            ]);
        } catch (AssistantOrchestrationException $exception) {
            $attempt = $exception->attempt;
            $elapsedMs = $exception->elapsedMs;
            $exceptionForResponse = $exception->getPrevious() ?? $exception;
        } catch (Throwable $exception) {
            $exceptionForResponse = $exception;
        }

        $rawReason = $exceptionForResponse->getMessage() ?: 'assistant_execution_failed';
        $reason = str_starts_with($rawReason, 'provider_') || str_starts_with($rawReason, 'assistant_')
            ? $rawReason
            : 'assistant_execution_failed';

        $context = [
            'request_id' => $requestId,
            'reason' => $reason,
            'exception' => $exceptionForResponse::class,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'attempt' => $attempt,
            'elapsed_ms' => $elapsedMs ?? (int) ((microtime(true) - $startedAt) * 1000),
        ];

        if ($reason === 'assistant_repeated_tool_call') {
            Log::warning('Assistant request stopped after repeated tool call', $context);

            return response()->json([
                'message' => 'Saya sudah memperoleh data yang diperlukan, tetapi belum dapat menyusun jawaban akhir. Silakan coba lagi.',
                'error_code' => $reason,
                'request_id' => $requestId,
            ], 503);
        }

        Log::error('Assistant request failed', $context);

        return response()->json([
            'message' => 'Assistant gagal memproses permintaan.',
            'error_code' => $reason,
            'request_id' => $requestId,
        ], 503);
    }

    private function fallbackMessage(User $user): string
    {
        if (! $user->can('inventory.view')) {
            return 'Maaf, saya belum dapat mengakses data inventaris untuk akun ini. Saya tetap dapat membantu pertanyaan umum tentang penggunaan Sistem Sekolah.';
        }

        return 'Maaf, saya belum dapat memberikan jawaban yang terverifikasi. Saya dapat membantu membaca resource inventaris yang diizinkan untuk akun Anda.';
    }
}
