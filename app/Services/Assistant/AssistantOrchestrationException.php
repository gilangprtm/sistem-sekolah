<?php

namespace App\Services\Assistant;

use RuntimeException;

class AssistantOrchestrationException extends RuntimeException
{
    public function __construct(
        string $reason,
        public readonly int $attempt,
        public readonly int $elapsedMs,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($reason, 0, $previous);
    }
}
