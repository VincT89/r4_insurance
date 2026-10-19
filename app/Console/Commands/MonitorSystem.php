<?php

namespace App\Console\Commands;

use App\Models\SystemHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorSystem extends Command
{
    protected $signature = 'monitor:system';

    protected $description = 'Monitora lo stato del sistema e ritorna un exit code semantico';

    public function handle()
    {
        $critical = false;
        $warning = false;
        $messages = [];

        // 1. Retention per System Command Runs
        // Retention moved to a separate cleanup job as this monitor must be strictly read-only.

        // 2. Comandi appesi (Stale running)
        $staleCommandsCount = DB::table('system_command_runs')
            ->where('status', 'running')
            ->where('started_at', '<', now()->subHours(2))
            ->count();

        if ($staleCommandsCount > 0) {
            $warning = true;
            $messages[] = "[WARNING] Ci sono {$staleCommandsCount} comandi in stato 'running' da oltre 2 ore (possibile crash/OOM/SIGKILL non intercettato).";
        }

        // 3. Controllo Scheduler
        $schedulerTimeout = config('system-monitoring.scheduler_timeout_minutes', 5);
        $schedulerHeartbeat = SystemHeartbeat::where('name', 'scheduler')->first();
        if (! $schedulerHeartbeat) {
            $critical = true;
            $messages[] = '[CRITICAL] Scheduler heartbeat mancante.';
        } elseif ($schedulerHeartbeat->last_seen_at->lt(now()->subMinutes($schedulerTimeout))) {
            $critical = true;
            $messages[] = "[CRITICAL] Scheduler fermo da oltre {$schedulerTimeout} minuti. Ultimo: ".$schedulerHeartbeat->last_seen_at;
        }

        // 4. Metriche Queue
        $queues = config('system-monitoring.queues', ['default']);

        foreach ($queues as $queue) {
            $now = now()->timestamp;
            $staleTimeout = now()->subMinutes(config('system-monitoring.stale_reserved_timeout_minutes', 30))->timestamp;

            $available = DB::table('jobs')->where('queue', $queue)->whereNull('reserved_at')->where('available_at', '<=', $now)->count();
            $delayed = DB::table('jobs')->where('queue', $queue)->whereNull('reserved_at')->where('available_at', '>', $now)->count();
            $reserved = DB::table('jobs')->where('queue', $queue)->whereNotNull('reserved_at')->count();
            $staleReserved = DB::table('jobs')->where('queue', $queue)->whereNotNull('reserved_at')->where('reserved_at', '<', $staleTimeout)->count();

            if ($available > config('system-monitoring.max_available_jobs', 100)) {
                $warning = true;
                $messages[] = "[WARNING] Coda '{$queue}' ha troppi job available: {$available}.";
            }

            if ($staleReserved > 0) {
                $critical = true;
                $messages[] = "[CRITICAL] Coda '{$queue}' ha {$staleReserved} job in stale_reserved da troppo tempo.";
            }

            $heartbeatTimeout = max(
                1,
                (int) config(
                    'system-monitoring.queue_heartbeat_timeout_minutes',
                    5
                )
            );
            $heartbeat = SystemHeartbeat::query()
                ->where('name', 'queue:'.$queue)
                ->first();

            if (! $heartbeat
                || $heartbeat->last_seen_at->lt(
                    now()->subMinutes($heartbeatTimeout)
                )) {
                $critical = true;
                $lastSeen = $heartbeat?->last_seen_at?->toIso8601String()
                    ?? 'mai';
                $messages[] = "[CRITICAL] Worker coda '{$queue}' senza heartbeat recente. Ultimo: {$lastSeen}.";
            }
        }

        $stuckJobsCount = DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<', now()->subMinutes(config('system-monitoring.stuck_jobs_timeout_minutes', 30))->timestamp)
            ->count();

        if ($stuckJobsCount > 0) {
            $warning = true;
            $messages[] = "[WARNING] Ci sono {$stuckJobsCount} job fermi in coda da oltre 30 minuti.";
        }

        // 5. Controllo Failed Jobs recenti
        $failedThreshold = config('system-monitoring.failed_jobs_threshold_per_hour', 10);
        $failedJobsCount = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
        if ($failedJobsCount > $failedThreshold) {
            $warning = true;
            $messages[] = "[WARNING] Registrati {$failedJobsCount} job falliti nell'ultima ora. Soglia: {$failedThreshold}.";
        }

        // Output
        foreach ($messages as $msg) {
            if (str_contains($msg, '[CRITICAL]')) {
                $this->error($msg);
            } else {
                $this->warn($msg);
            }
        }

        if ($critical) {
            $this->error('Stato del sistema: CRITICAL');

            return 2;
        }

        if ($warning) {
            $this->warn('Stato del sistema: WARNING');

            return 1;
        }

        $this->info('Stato del sistema: OK');

        return 0;
    }
}
