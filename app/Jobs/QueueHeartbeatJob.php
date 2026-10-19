<?php

namespace App\Jobs;

use App\Models\SystemHeartbeat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QueueHeartbeatJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 120;

    public function __construct(public readonly string $queueName)
    {
        $this->onQueue($queueName);
    }

    public function uniqueId(): string
    {
        return $this->queueName;
    }

    public function handle(): void
    {
        SystemHeartbeat::updateOrCreate(
            ['name' => 'queue:'.$this->queueName],
            [
                'last_seen_at' => now(),
                'metadata' => [
                    'status' => 'ok',
                    'queue' => $this->queueName,
                ],
            ]
        );
    }
}
