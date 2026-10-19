<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditLogService;

class UserObserver
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function created(User $user): void
    {
        $this->auditLog->log('created', $user, null, null);
    }

    public function updated(User $user): void
    {
        $changed = array_intersect_key($user->getChanges(), array_flip(['name', 'email', 'role', 'status']));
        if ($changed) {
            $this->auditLog->log('updated', $user, array_intersect_key($user->getOriginal(), $changed), $changed);
        }
        if ($user->wasChanged('password')) {
            // Se l'utente che sta compiendo l'azione non è se stesso, o comunque è un admin panel reset
            $this->auditLog->log('password_reset', $user, null, null);
        }
    }

    public function deleted(User $user): void
    {
        $this->auditLog->log('deleted', $user, null, null);
    }
}
