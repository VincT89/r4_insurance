<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, SupplierAgency, SupplierCompany, User};
use App\Services\{InsuranceImportPreview, SupplierAgencyRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InsuranceImportPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_supports_multiple_companies_without_duplicating_the_registry(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $a = SupplierCompany::create(['name' => 'Compagnia A dimostrativa']);
        $b = SupplierCompany::create(['name' => 'Compagnia B dimostrativa']);
        $this->post(route('suppliers.store'), ['name' => 'Agenzia unica dimostrativa', 'supplier_company_ids' => [$a->id, $b->id]])->assertSessionHasNoErrors();
        $agency = SupplierAgency::sole();
        $this->assertCount(2, $agency->companies);
        foreach ([$a, $b] as $company) {
            $this->get(route('supplier-companies.show', $company))->assertOk()->assertSee($agency->name);
            $this->get(route('suppliers.index', ['supplier_company_id' => $company->id]))->assertOk()->assertSee($agency->name);
            $this->delete(route('supplier-companies.destroy', $company))->assertSessionHasErrors('company');
        }
        $this->get(route('suppliers.show', $agency))->assertOk()->assertSee($a->name)->assertSee($b->name);
        $this->patch(route('suppliers.update', $agency), ['name' => $agency->name, 'status' => 'active', 'supplier_company_ids' => [$b->id]])->assertSessionHasNoErrors();
        $this->assertSame([$b->id], $agency->fresh()->companies->modelKeys());
        $log = $agency->auditLogs()->where('action', 'mandates_changed')->latest('id')->first();
        $this->assertSame([$a->id, $b->id], $log->old_values['supplier_company_ids']);
        $this->assertSame([$b->id], $log->new_values['supplier_company_ids']);
        $this->delete(route('supplier-companies.destroy', $a))->assertSessionHasNoErrors();
    }

    public function test_subagency_mandates_cannot_be_orphaned_or_assigned_to_an_unrelated_parent(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $a = SupplierCompany::create(['name' => 'A dimostrativa']);
        $b = SupplierCompany::create(['name' => 'B dimostrativa']);
        $registry = app(SupplierAgencyRegistry::class);
        $parent = $registry->save(new SupplierAgency, ['name' => 'Principale', 'supplier_company_ids' => [$a->id, $b->id]]);
        $child = $registry->save(new SupplierAgency, ['name' => 'Subagenzia', 'supplier_company_ids' => [$b->id], 'parent_agency_id' => $parent->id]);
        $this->patch(route('suppliers.update', $parent), ['name' => $parent->name, 'status' => 'active', 'supplier_company_ids' => [$a->id]])->assertSessionHasErrors('supplier_company_ids');
        $this->assertCount(2, $parent->fresh()->companies);
        $this->get(route('supplier-companies.show', $a))->assertDontSee('Subagenzia');
        $this->get(route('supplier-companies.show', $b))->assertSee('Subagenzia');
        $this->post(route('suppliers.store'), ['name' => 'Errore', 'supplier_company_ids' => [$a->id, $a->id]])->assertSessionHasErrors('supplier_company_ids.0');
        $this->post(route('suppliers.store'), ['name' => 'Errore', 'supplier_company_ids' => [999]])->assertSessionHasErrors('supplier_company_ids.0');
        $this->assertDatabaseCount('supplier_agencies', 2);
    }

    public function test_import_preview_is_admin_only_and_does_not_write_business_data(): void
    {
        Storage::fake('local');
        config(['insurance-import.directory' => Storage::disk('local')->path('preview')]);
        $this->get(route('insurance-import.index'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => UserRole::Operator]));
        $this->get(route('insurance-import.index'))->assertForbidden();
        $this->get(route('insurance-import.download'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get(route('insurance-import.index'))->assertOk()->assertSee('Nessun lotto preparato');
        $summary = ['version' => 1, 'mode' => 'preview_only', 'counts' => array_fill_keys(['registry_rows','client_candidates','clients_to_review','policy_rows','policy_groups','policy_repeated_groups','financial_rows_excluded','policies_needing_status'], 0), 'decisions' => [], 'sources' => []];
        Storage::disk('local')->put('preview/summary.json', json_encode($summary));
        Storage::disk('local')->put('preview/manifest.json', json_encode(['files' => ['summary.json' => hash('sha256', json_encode($summary))]]));
        $this->get(route('insurance-import.index'))->assertOk()->assertSee('Anteprima di preparazione');
        $this->assertDatabaseCount('clients', 0); $this->assertDatabaseCount('insurance_policies', 0);
        Storage::disk('local')->put('preview/summary.json', '{}');
        $this->get(route('insurance-import.index'))->assertStatus(409)->assertSee('incompleta');
        $this->get(route('insurance-import.download'))->assertStatus(409);
    }

    public function test_bulk_table_read_keeps_client_visibility_and_search_filters(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Client::factory()->count(24)->create(['name' => 'Cliente dimostrativo visibile']);
        Client::factory()->create(['name' => 'Altro cliente dimostrativo']);
        $this->actingAs($admin)->get(route('clients.index'))->assertViewHas('clients', fn ($c) => $c->count() === 20);
        $this->withHeader('X-Table-Export', '1')->get(route('clients.index', ['_table_all' => '1', 'search' => 'visibile']))
            ->assertOk()->assertViewHas('clients', fn ($c) => $c->count() === 24 && $c->total() === 24)->assertDontSee('Altro cliente dimostrativo');
        $commercial = User::factory()->create(['role' => UserRole::Operator]);
        $this->actingAs($commercial)->withHeader('X-Table-Export', '1')->get(route('clients.index', ['_table_all' => '1']))
            ->assertOk()->assertViewHas('clients', fn ($c) => $c->total() === 25);
    }
}
