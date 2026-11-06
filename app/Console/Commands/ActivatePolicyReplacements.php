<?php

namespace App\Console\Commands;

class ActivatePolicyReplacements extends \Illuminate\Console\Command
{
    use \App\Support\Monitoring\TracksSystemCommandRuns;
    protected $signature = 'insurance:activate-replacements';
    protected $description = 'Attiva le sostituzioni programmate alla loro decorrenza, conservando lo storico';

    public function handle(\App\Services\ScheduledPolicyReplacements $replacements): int
    {
        return $this->runTracked($this->getName(), function () use ($replacements) {
            $this->info('Sostituzioni attivate: '.$replacements->activateDue());
            return self::SUCCESS;
        });
    }
}
