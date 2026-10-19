<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FirstLoginPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_is_redirected_to_forced_password_change()
    {
        $user = User::factory()->create([
            'password_changed_at' => null,
            'role' => \App\Enums\UserRole::Operator,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(302);
        $response->assertRedirect('/password/setup');
    }

    public function test_new_user_can_access_forced_password_form()
    {
        $user = User::factory()->create([
            'password_changed_at' => null,
            'role' => \App\Enums\UserRole::Operator,
        ]);

        $response = $this->actingAs($user)->get('/password/setup');

        $response->assertStatus(200);
    }

    public function test_new_user_can_update_password_and_unlock_account()
    {
        $user = User::factory()->create([
            'password_changed_at' => null,
            'password' => Hash::make('old-password'),
            'role' => \App\Enums\UserRole::Operator,
        ]);

        $response = $this->actingAs($user)->post('/password/setup', [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');

        $user->refresh();
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
    }

    public function test_user_with_changed_password_is_not_redirected()
    {
        $user = User::factory()->create([
            'password_changed_at' => now(),
            'role' => \App\Enums\UserRole::Operator,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_forced_user_cannot_access_protected_app_routes()
    {
        $user = User::factory()->create([
            'password_changed_at' => null,
            'role' => \App\Enums\UserRole::Operator,
        ]);

        $response = $this->actingAs($user)->get('/clients');

        $response->assertStatus(302);
        $response->assertRedirect('/password/setup');
    }

    public function test_forced_user_can_still_logout()
    {
        $user = User::factory()->create([
            'password_changed_at' => null,
            'role' => \App\Enums\UserRole::Operator,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        // Logout typically redirects to login
        $response->assertStatus(302);
        $this->assertGuest();
    }

    public function test_user_with_changed_password_cannot_access_setup_again()
    {
        $user = User::factory()->create([
            'password_changed_at' => now(),
            'role' => \App\Enums\UserRole::Operator,
        ]);

        $response = $this->actingAs($user)->get('/password/setup');

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');
    }

    public function test_invalid_password_does_not_unlock_account()
    {
        $user = User::factory()->create([
            'password_changed_at' => null,
            'role' => \App\Enums\UserRole::Operator,
        ]);

        $response = $this->actingAs($user)->post('/password/setup', [
            'password' => 'short', // invalid because length < 8
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
        
        $user->refresh();
        $this->assertNull($user->password_changed_at);
    }
}
