<?php
namespace App\Policies;

use App\Models\{Client, User};
use App\Policies\Concerns\HandlesRoleAuthorization;

class ClientPolicy
{
    use HandlesRoleAuthorization;

    public function viewAny(User $user): bool { return $user->isOperator(); }
    public function lookup(User $user): bool { return $this->viewAny($user); }
    public function view(User $user, Client $client): bool { return $this->viewAny($user); }
    public function viewMaterials(User $user, Client $client): bool { return $this->view($user, $client); }
    public function manageMaterials(User $user, Client $client): bool { return $this->updateRegistry($user, $client); }
    public function create(User $user): bool { return $this->viewAny($user); }
    public function quickCreate(User $user): bool { return $this->create($user); }
    public function updateRegistry(User $user, Client $client): bool { return $this->update($user, $client); }
    public function update(User $user, Client $client): bool { return $this->view($user, $client); }
    public function delete(User $user, Client $client): bool { return false; }
}
