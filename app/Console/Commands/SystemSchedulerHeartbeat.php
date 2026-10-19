<?php

namespace App\Console\Commands;

use App\Models\SystemHeartbeat;
use Illuminate\Console\Command;

class SystemSchedulerHeartbeat extends Command
{
    protected $signature = 'system:scheduler-heartbeat';
    protected $description = 'Aggiorna il heartbeat dello scheduler per il monitoraggio';

    public function handle()
    {
        SystemHeartbeat::updateOrCreate(
            ['name' => 'scheduler'],
            ['last_seen_at' => now(), 'metadata' => ['status' => 'ok']]
        );

        $this->info('Scheduler heartbeat aggiornato.');
        return 0;
    }
}
