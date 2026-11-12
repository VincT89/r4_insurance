<?php

return [
    'enabled' => env('INBOUND_EMAIL_ENABLED', false),
    /** La credenziale in chiaro resta nel connettore; R4 conserva soltanto la sua impronta. */
    'token_sha256' => env('INBOUND_EMAIL_TOKEN_SHA256', ''),
    'source' => env('INBOUND_EMAIL_SOURCE', 'dedicated-mailbox'),
];
