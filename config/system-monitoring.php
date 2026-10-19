<?php

return [
    'scheduler_timeout_minutes' => 5,
    'queue_heartbeat_timeout_minutes' => 5,
    'stuck_jobs_timeout_minutes' => 30,
    'failed_jobs_threshold_per_hour' => 10,
    'stale_reserved_timeout_minutes' => 30,
    'max_available_jobs' => 100,
    'queues' => [
        'default',
    ],
    'retention' => [
        'system_command_runs_days' => (int) env(
            'SYSTEM_COMMAND_RUNS_RETENTION_DAYS',
            90
        ),
        'integration_logs_days' => (int) env(
            'INTEGRATION_LOGS_RETENTION_DAYS',
            30
        ),
    ],
];
