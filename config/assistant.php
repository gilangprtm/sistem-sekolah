<?php

return [
    'context_ttl' => (int) env('ASSISTANT_CONTEXT_TTL', 900),
    'context_max_messages' => (int) env('ASSISTANT_CONTEXT_MAX_MESSAGES', 12),
];
