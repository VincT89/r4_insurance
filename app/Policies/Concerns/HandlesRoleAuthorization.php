<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait HandlesRoleAuthorization
{
    // Ignora le policy specifiche se l'utente ha privilegi globali
    public function before(User $user, string $ability): ?bool
    {
        if ($user->canManageSystem()) {
            return true;
        }
        return null; // Delega il controllo alla policy specifica
    }
}
