<?php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AuditLog, Client, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Gate, Schema};
use Tests\TestCase;

class RetiredModulesCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_use_remaining_pages_without_removed_navigation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $this->actingAs($admin);
        $client = Client::factory()->create();
        foreach (['dashboard', 'clients.index', 'clients.create', 'policies.index', 'policies.create', 'reports.index', 'users.index', 'users.create', 'profile.edit', 'audit-logs.index', 'daily-notes.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('Nuovo Task')->assertDontSee('Sodano Drive')
                ->assertDontSee('Social Media')->assertDontSee('Offerte commerciali')->assertDontSee('Disponibilità team');
        }
        $this->get(route('clients.show', $client))->assertOk();
        $this->get(route('clients.edit', $client))->assertOk();
        $this->get(route('clients.materials.index', $client))->assertOk();
    }

    public function test_removed_routes_and_tables_are_absent(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        foreach (['tasks', 'tickets', 'projects', 'quote-services', 'availability', 'admin/availability', 'invoices', 'payments', 'expenses', 'economic-summary', 'billing-profile', 'hosting-services', 'calendar-events', 'social/campaigns', 'social/calendar', 'admin/social/connections', 'nextcloud/preview', 'nextcloud/download'] as $path) {
            $this->get('/'.$path)->assertNotFound();
            $this->post('/'.$path)->assertNotFound();
        }
        $tables = json_decode(file_get_contents(base_path('tests/Fixtures/retired-table-names.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($tables as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->assertTrue(Schema::hasTable('client_user'));
        $this->assertFalse(Schema::hasTable('teams'));
        $this->assertFalse(Schema::hasTable('team_user'));
        $this->get('/teams')->assertNotFound();
        $this->post('/teams')->assertNotFound();
        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertTrue(Schema::hasColumn('clients', 'country_code'));
        $this->assertFalse(Schema::hasColumn('clients', 'nextcloud_folder_name'));
        $this->assertFalse(Schema::hasColumn('users', 'last_tickets_viewed_at'));
    }

    public function test_operator_registry_is_shared_and_company_identifiers_survive(): void
    {
        $commercial = User::factory()->create(['role' => UserRole::Operator]);
        $own = Client::factory()->create(['commercial_user_id' => $commercial->id, 'name' => 'Cliente dimostrativo assegnato', 'company_name' => 'Azienda dimostrativa', 'vat_number' => 'DEMO-IVA', 'tax_code' => 'DEMO-CF', 'notes' => 'Nota interna riservata']);
        $other = Client::factory()->create(['name' => 'Cliente dimostrativo non assegnato']);
        $this->actingAs($commercial)->get(route('clients.index'))->assertOk()->assertSee($own->name)->assertSee($other->name);
        $this->get(route('clients.show', $own))->assertOk()->assertSee('DEMO-CF')->assertDontSee('Nota interna riservata');
        $this->get(route('clients.show', $other))->assertOk();
        $this->getJson(route('api.clients.search', ['q' => 'dimostrativo']))->assertOk()->assertJsonCount(2);
        $this->post(route('clients.store'), ['name' => 'Nuovo cliente dimostrativo', 'company_name' => 'Società dimostrativa', 'tax_code' => 'demo-nuovo'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('clients', ['name' => 'Nuovo cliente dimostrativo', 'commercial_user_id' => $commercial->id, 'tax_code' => 'DEMO-NUOVO']);
    }

    public function test_operator_access_is_independent_of_legacy_direct_assignments(): void
    {
        $assigned = User::factory()->create(['role' => UserRole::Operator]);
        $unassigned = User::factory()->create(['role' => UserRole::Operator]);
        $client = Client::factory()->create();
        $client->authorizedUsers()->attach($assigned);
        $this->assertTrue(Gate::forUser($assigned)->allows('view', $client));
        $this->assertTrue(Gate::forUser($unassigned)->allows('view', $client));
        $this->actingAs($assigned)->get(route('clients.show', $client))->assertOk();
        $this->actingAs($unassigned)->get(route('clients.show', $client))->assertOk();
    }

    public function test_historical_audit_entries_render_without_instantiating_retired_classes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'created', 'auditable_type' => 'App\\Models\\Invoice', 'auditable_id' => 12, 'description' => 'Registrazione storica dimostrativa', 'created_at' => now()]);
        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk()->assertSee('record storico #12');
        $this->assertDatabaseHas('audit_logs', ['description' => 'Registrazione storica dimostrativa']);
    }
}
