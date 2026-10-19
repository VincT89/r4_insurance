<?php

namespace App\Console\Commands;

use App\Jobs\QueueHeartbeatJob;
use Illuminate\Console\Command;

class DispatchQueueHeartbeatsCommand extends Command
{
    protected $signature = 'system:dispatch-queue-heartbeats';

    protected $description = 'Dispatch one heartbeat probe to each monitored queue';

    public function handle(): int
    {
        $queues = array_values(array_unique(array_filter(
            (array) config('system-monitoring.queues', [])
        )));

        foreach ($queues as $queue) {
            QueueHeartbeatJob::dispatch((string) $queue);
        }

        $this->info('Queue heartbeat probes dispatched: '.count($queues).'.');

        return self::SUCCESS;
    }
}
