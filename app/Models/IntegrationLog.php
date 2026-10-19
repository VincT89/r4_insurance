<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationLog extends Model
{
    protected $fillable = [
        'provider',
        'direction',
        'endpoint',
        'event',
        'payload',
        'response',
        'status_code',
        'status',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
