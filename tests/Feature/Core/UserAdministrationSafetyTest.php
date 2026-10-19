<?php

namespace Tests\Feature\Core;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdministrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_active_administrator_can_update_personal_data_without_losing_role(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => 'active',
            'password_changed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('users.update', $admin), [
                'name' => 'Amministratore aggiornato',
                'email' => $admin->email,
                'role' => UserRole::Admin->value,
                'status' => 'active',
                'phone' => null,
                'primary_specialization' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $admin->refresh();
        $this->assertSame('Amministratore aggiornato', $admin->name);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame('active', $admin->status);
    }

    public function test_last_active_administrator_cannot_be_demoted(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => 'active',
            'password_changed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('users.edit', $admin))
            ->patch(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => UserRole::Operator->value,
                'status' => 'active',
            ])
            ->assertRedirect(route('users.edit', $admin))
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_last_active_administrator_cannot_delete_own_profile(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => 'active',
            'password' => bcrypt('password'),
            'password_changed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
