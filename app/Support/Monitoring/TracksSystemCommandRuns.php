<?php

namespace App\Support\Monitoring;

use App\Models\SystemCommandRun;
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

            /** L'assegnazione esplicita conserva l'avvio anche nei database con aggiornamento automatico del primo timestamp. */
            SystemCommandRun::whereKey($run->id)->update([
                'started_at' => $run->getRawOriginal('started_at'),
                'status' => $exitCode === Command::SUCCESS
                    ? 'succeeded'
                    : 'failed',
                'exit_code' => $exitCode,
                'finished_at' => now(),
            ]);

            return $exitCode;
        } catch (Throwable $exception) {
            try {
                /** Il tipo e il codice descrivono l'errore senza conservare query, credenziali o dati personali del messaggio. */
                SystemCommandRun::whereKey($run->id)->update([
                    'started_at' => $run->getRawOriginal('started_at'),
                    'status' => 'failed',
                    'exit_code' => Command::FAILURE,
                    'error_message' => $exception::class.' (codice '.$exception->getCode().').',
                    'finished_at' => now(),
                ]);
            } finally {
                /** Un problema nel registro non deve sostituire l'errore che ha interrotto il controllo. */
                throw $exception;
            }
        }
    }
}
