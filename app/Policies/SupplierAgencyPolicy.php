<?php

namespace App\Policies;

use App\Models\{SupplierAgency, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class SupplierAgencyPolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isOperator();
    }

    public function view(User $user, SupplierAgency $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isOperator();
    }

    public function update(User $user, SupplierAgency $supplier): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, SupplierAgency $supplier): bool
    {
        return false;
    }
}
