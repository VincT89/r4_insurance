<?php

namespace App\Policies;

use App\Models\{SupplierCompany, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class SupplierCompanyPolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isOperator();
    }

    public function view(User $user, SupplierCompany $company): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isOperator();
    }

    public function update(User $user, SupplierCompany $company): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, SupplierCompany $company): bool
    {
        return false;
    }
}
