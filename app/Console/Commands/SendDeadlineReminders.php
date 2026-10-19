<?php
namespace App\Console\Commands;

class SendDeadlineReminders extends \Illuminate\Console\Command
{
    protected $signature = 'insurance:deadline-reminders';
    protected $description = 'Notifiche interne per scadenze e attività sinistri, senza duplicati';
    public function handle(\App\Services\DeadlineReminders $reminders, \App\Services\ClaimReminders $claims): int
    {
        $count = 0;
        \App\Models\User::where('status', 'active')->orderBy('id')->eachById(function ($user) use ($reminders,$claims, &$count) { $count += $reminders->forUser($user)+$claims->forUser($user); });
        $this->info('Notifiche create: '.$count);
        return self::SUCCESS;
    }
}
