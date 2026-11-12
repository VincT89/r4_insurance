<?php

return [
    'station_public_url' => env('SIGNING_STATION_PUBLIC_URL','https://s4.sodanoconsulting.it/firma'),
    'node_binary' => env('DOCUMENT_SIGNING_NODE_BINARY', 'node'),
    'expires_after_minutes' => 60,
    'max_bytes' => 20 * 1024 * 1024,
    'max_pages' => 100,
    'station_pairing_minutes' => 10,
    'station_request_minutes' => 30,
    'station_credential_days' => 30,
];
