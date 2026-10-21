<?php

namespace App\Console\Commands;

use App\Services\InsuranceBackup;
use Illuminate\Console\Command;

class InsuranceBackupCommand extends Command
{
    protected $signature='insurance:backup {operation : init, create, check oppure restore-test}
        {--writers-stopped : Conferma scheduler, worker e richieste arrestati}
        {--snapshot= : Identificativo completo della copia da ripristinare}
        {--target= : Nuova cartella privata esterna al progetto}
        {--database= : Nuovo database r4_restore_ID}';
    protected $description='Backup cifrati con Restic e prova di ripristino isolata senza sovrascritture';

    public function handle(InsuranceBackup $backup): int
    {
        try {
            $result=match($this->argument('operation')) {
                'init'=>$backup->initialize(),
                'create'=>$backup->create((bool)$this->option('writers-stopped')),
                'check'=>$backup->check(),
                'restore-test'=>$backup->restore((string)$this->option('snapshot'),(string)$this->option('target'),(string)$this->option('database')),
                default=>throw new \RuntimeException('Operazione non riconosciuta.'),
            };
            if (is_array($result)) $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            else $this->info('Operazione completata.');
            return self::SUCCESS;
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
