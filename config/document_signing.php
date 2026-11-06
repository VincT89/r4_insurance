<?php

return [
    'node_binary' => env('DOCUMENT_SIGNING_NODE_BINARY', 'node'),
    'expires_after_minutes' => 60,
    'max_bytes' => 20 * 1024 * 1024,
    'max_pages' => 100,
];
