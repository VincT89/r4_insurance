<?php

namespace App\Support\Monitoring;

use App\Models\SystemCommandRun;
use App\Support\Http\ProviderErrorSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

trait TracksSystemCommandRuns
{
    /**
     * Executes a command callback and tracks its execution.
     *
     * @param  string  $command  The signature or name of the command
     * @param  callable  $callback  The actual execution logic returning an integer exit code
     * @param  array  $metadata  Safe metadata to store
     *
     * @throws Throwable
     */
    protected function runTracked(
        string $command,
        callable $callback,
        array $metadata = []
    ): int {
        $run = SystemCommandRun::create([
            'run_uuid' => (string) Str::uuid(),
            'command' => $command,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => $metadata,
        ]);

        try {
            $exitCode = $callback();

            $run->update([
                'status' => $exitCode === Command::SUCCESS
                    ? 'succeeded'
                    : 'failed',
                'exit_code' => $exitCode,
                'finished_at' => now(),
            ]);

            return $exitCode;
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'exit_code' => Command::FAILURE,
                'error_message' => Str::limit(
                    ProviderErrorSanitizer::safeText(
                        $exception->getMessage()
                    ),
                    1000
                ),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }
}
