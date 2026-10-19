<?php

namespace App\Console\Commands;

use App\Models\IntegrationLog;
use App\Models\SystemCommandRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOperationalLogsCommand extends Command
{
    protected $signature = 'system:prune-operational-logs
        {--dry-run : Count records without deleting them}';

    protected $description = 'Remove expired operational and integration logs';

    public function handle(): int
    {
        $systemDays = max(
            1,
            (int) config('system-monitoring.retention.system_command_runs_days', 90)
        );
        $integrationDays = max(
            1,
            (int) config('system-monitoring.retention.integration_logs_days', 30)
        );

        $systemQuery = SystemCommandRun::query()
            ->where('status', '!=', 'running')
            ->where('created_at', '<', now()->subDays($systemDays));
        $integrationQuery = IntegrationLog::query()
            ->where('created_at', '<', now()->subDays($integrationDays));
        $idempotencyQuery = DB::table('integration_idempotency_keys')
            ->where('expires_at', '<', now());

        $systemCount = (clone $systemQuery)->count();
        $integrationCount = (clone $integrationQuery)->count();
        $idempotencyCount = (clone $idempotencyQuery)->count();

        if ($this->option('dry-run')) {
            $this->info(
                "Dry run: {$systemCount} system command runs and "
                ."{$integrationCount} integration logs and "
                ."{$idempotencyCount} idempotency records would be removed."
            );

            return self::SUCCESS;
        }

        $systemQuery->delete();
        $integrationQuery->delete();
        $idempotencyQuery->delete();

        $this->info(
            "Removed {$systemCount} system command runs and "
            ."{$integrationCount} integration logs and "
            ."{$idempotencyCount} idempotency records."
        );

        return self::SUCCESS;
    }
}
