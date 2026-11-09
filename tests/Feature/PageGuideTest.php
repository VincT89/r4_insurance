<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_is_available_to_both_roles_and_tracks_the_actual_policy_and_client_sections(): void
    {
        $client = Client::factory()->create();
        $policy = $client->policies()->create(['number'=>'GUIDA-COLLAUDO', 'category'=>'RC auto', 'company'=>'Compagnia di collaudo', 'starts_on'=>today(), 'expires_on'=>today()->addYear(), 'status'=>'active', 'premium'=>'100.00', 'payment_frequency'=>'Annuale']);
        foreach ([UserRole::Admin, UserRole::Operator] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role, 'status'=>'active']));
            $this->get(route('dashboard'))->assertOk()->assertSee('data-guide-route="dashboard"', false)->assertSee('aria-haspopup="dialog"', false);
            $this->get(route('clients.show', [$client, 'tab'=>'vehicles']))->assertOk()->assertSee('data-guide-client-section="vehicles"', false);
            $this->get(route('agency-communications.index'))->assertOk()->assertSee('data-guide-batches="empty"', false);
            $this->get(route('clients.policies.show', [$client, $policy, 'tab'=>'payments']))->assertOk()
                ->assertSee('data-guide-route="clients.policies.show"', false)
                ->assertSee('data-current-section="payments"', false)
                ->assertSee('data-guide-payments="unprepared"', false);
        }
    }

    public function test_guide_is_excluded_from_table_export_responses(): void
    {
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin, 'status'=>'active']));
        $this->get(route('policies.index'))->assertOk()->assertSee('data-page-guide', false);
        $this->withHeader('X-Table-Export', '1')->get(route('policies.index', ['_table_all'=>1]))->assertOk()->assertDontSee('data-page-guide', false);
    }

    public function test_login_help_does_not_expose_authenticated_pages_or_add_an_access_endpoint(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('data-guide-route="login"', false)->assertSee('page-guide-floating', false);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('attachments.sign', 1))->assertRedirect(route('login'));
    }
}
