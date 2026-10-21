<?php
namespace App\Console\Commands;

class SendDeadlineReminders extends \Illuminate\Console\Command
{
    use \App\Support\Monitoring\TracksSystemCommandRuns;
    protected $signature = 'insurance:deadline-reminders';
    protected $description = 'Notifiche interne per scadenze, sinistri e incassi da comunicare, senza inviare email';
    public function handle(\App\Services\DeadlineReminders $reminders, \App\Services\ClaimReminders $claims, \App\Services\CollectionCommunicationReminders $collections): int
    {
        return $this->runTracked($this->getName(), function () use ($reminders, $claims, $collections) {
            $count = 0;
            \App\Models\User::where('status', 'active')->orderBy('id')->eachById(function ($user) use ($reminders, $claims, $collections, &$count) {
                $count += $reminders->forUser($user) + $claims->forUser($user) + $collections->forUser($user);
            });
            \App\Models\SystemHeartbeat::updateOrCreate(['name'=>'insurance-deadline-reminders'], ['last_seen_at'=>now(), 'metadata'=>['created'=>$count]]);
            $this->info('Notifiche create: '.$count);
            return self::SUCCESS;
        });
    }
}
