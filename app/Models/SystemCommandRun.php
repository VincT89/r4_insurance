<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemCommandRun extends Model
{
    protected $fillable = [
        'run_uuid',
        'command',
        'status',
        'exit_code',
        'metadata',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
