<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsuranceBranch, InsurancePolicy, InsuranceSubbranch, SupplierAgency, SupplierCompany, User};
use App\Services\InsuranceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InsuranceCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $name = 'Ramo dimostrativo'): InsuranceBranch
    {
        return app(InsuranceCatalog::class)->save(new InsuranceBranch, ['name' => $name, 'status' => 'active', 'needs_review' => false]);
    }
    private function sub(InsuranceBranch $branch, string $name): InsuranceSubbranch
    {
        return app(InsuranceCatalog::class)->save(new InsuranceSubbranch, ['insurance_branch_id' => $branch->id, 'name' => $name, 'status' => 'active', 'needs_review' => false]);
    }
    private function admin(): void { $this->actingAs(User::factory()->create(['role' => UserRole::Admin])); }

    public function test_catalog_permissions_duplicates_and_parent_relationships(): void
    {
        $this->get(route('insurance-catalog.index'))->assertRedirect('/login');
        $reader = User::factory()->create(['role' => UserRole::Operator]);
        $this->actingAs($reader)->get(route('insurance-catalog.index'))->assertOk();
        $this->get(route('insurance-catalog.create', 'branch'))->assertOk();
        $this->admin();
        $values = ['name' => 'Ramo Demo', 'status' => 'active', 'needs_review' => '0'];
        $this->post(route('insurance-catalog.store', 'branch'), $values)->assertSessionHasNoErrors();
        $branch = InsuranceBranch::sole();
        $this->post(route('insurance-catalog.store', 'branch'), array_replace($values, ['name' => '  ramo   demo  ']))->assertSessionHasErrors('name');
        $this->post(route('insurance-catalog.store', 'subbranch'), $values + ['insurance_branch_id' => 999])->assertSessionHasErrors('insurance_branch_id');
        $sub = $this->sub($branch, 'Sottoramo Demo');
        $this->delete(route('insurance-catalog.destroy', ['branch', $branch->id]))->assertSessionHasErrors('catalog');
        $this->get(route('insurance-catalog.index', ['kind' => 'subbranch', 'branch' => $branch->id]))->assertOk()->assertSee($sub->name);
        $this->patch(route('insurance-catalog.update', ['branch', $branch->id]), array_replace($values, ['status' => 'inactive']))->assertSessionHasNoErrors();
        $this->patch(route('insurance-catalog.update', ['subbranch', $sub->id]), $values + ['insurance_branch_id' => $branch->id])->assertSessionHasErrors('status');
    }

    public function test_policy_requires_a_valid_pair_and_used_catalog_entries_cannot_be_removed_or_reassigned(): void
    {
        $this->admin();
        $client = Client::factory()->create();
        $branch = $this->branch('Veicoli demo');
        $sub = $this->sub($branch, 'Autovetture demo');
        $other = $this->branch('Casa demo');
        $data = ['category' => $branch->name, 'subcategory' => $sub->name, 'company' => 'Compagnia demo', 'number' => 'DEMO-1', 'starts_on' => '2026-01-01', 'expires_on' => '2027-01-01', 'status' => 'active'];
        $this->post(route('clients.policies.store', $client), (array_replace($data, ['category' => $other->name])) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('subcategory');
        $this->assertDatabaseCount('insurance_policies', 0);
        $this->post(route('clients.policies.store', $client), ($data) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasNoErrors();
        $policy = InsurancePolicy::sole();
        $this->assertSame($branch->id, $policy->insurance_branch_id);
        $this->assertSame($sub->id, $policy->insurance_subbranch_id);
        $this->get(route('clients.show', [$client, 'tab' => 'policies', 'category' => $branch->name]))->assertOk()->assertSee('Sottorami del ramo selezionato')->assertSee($sub->name);
        $this->patch(route('insurance-catalog.update', ['subbranch', $sub->id]), ['insurance_branch_id' => $other->id, 'name' => $sub->name, 'status' => 'active', 'needs_review' => 0])->assertSessionHasErrors('name');
        $this->delete(route('insurance-catalog.destroy', ['subbranch', $sub->id]))->assertSessionHasErrors('catalog');
        $this->patch(route('insurance-catalog.update', ['branch', $branch->id]), ['name' => $branch->name, 'status' => 'inactive', 'needs_review' => 0])->assertSessionHasNoErrors();
        $this->post(route('clients.policies.store', $client), (array_replace($data, ['number' => 'DEMO-2'])) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('category');
        $this->patch(route('clients.policies.update', [$client, $policy]), $data + ['notes' => 'Aggiornamento storico', 'policy_revision' => $policy->fresh()->editFingerprint()])->assertSessionHasNoErrors();
        $this->assertSame('Aggiornamento storico', $policy->fresh()->notes);
    }

    public function test_excel_import_preserves_original_classifications_excludes_financial_rows_and_is_repeatable(): void
    {
        Storage::fake('local');
        config(['insurance-import.directory' => Storage::disk('local')->path('catalog')]);
        $pair = fn ($b, $s, $result = 'conservata') => ['source_branch' => $b, 'source_subbranch' => $s, 'branch' => 'Proposta non applicata', 'subbranch' => null, 'result' => $result, 'reason' => 'Nota originale', 'rows' => 2];
        $summary = json_encode(['version' => 1, 'mode' => 'preview_only', 'taxonomy' => [$pair('RCA', 'AUTO', 'da_confermare'), $pair('RCAUTO', ''), $pair('Casa', 'INCENDIO'), $pair('CASA', 'INCENDIO'), $pair('FATTURA', 'FATTURA', 'esclusa')]]);
        Storage::disk('local')->put('catalog/summary.json', $summary);
        Storage::disk('local')->put('catalog/manifest.json', json_encode(['files' => ['summary.json' => hash('sha256', $summary)]]));
        $report = app(InsuranceCatalog::class)->importPreview();
        $this->assertSame(['branches_created' => 3, 'subbranches_created' => 2, 'excluded_pairs' => 1], $report);
        $this->assertDatabaseHas('insurance_branches', ['name' => 'RCA', 'needs_review' => true]);
        $this->assertDatabaseHas('insurance_branches', ['name' => 'RCAUTO']);
        $this->assertDatabaseMissing('insurance_branches', ['name' => 'FATTURA']);
        $this->assertDatabaseMissing('insurance_branches', ['name' => 'Proposta non applicata']);
        $this->assertSame(0, app(InsuranceCatalog::class)->importPreview()['branches_created']);
        $this->assertDatabaseCount('insurance_policies', 0);
        $this->assertDatabaseCount('clients', 0);
        Storage::disk('local')->put('catalog/summary.json', '{}');
        $this->expectExceptionMessage('Anteprima modificata');
        app(InsuranceCatalog::class)->importPreview();
    }

    public function test_agencies_remain_single_records_with_multiple_companies_and_duplicate_entries_are_rejected(): void
    {
        $this->admin();
        $one = SupplierCompany::create(['name' => 'Compagnia Demo A', 'status' => 'active']);
        $two = SupplierCompany::create(['name' => 'Compagnia Demo B', 'status' => 'inactive']);
        $values = ['name' => 'Agenzia Unica Demo', 'supplier_company_ids' => [$one->id, $two->id], 'status' => 'active'];
        $this->post(route('suppliers.store'), $values)->assertSessionHasNoErrors();
        $this->post(route('suppliers.store'), array_replace($values, ['name' => ' agenzia  unica demo ']))->assertSessionHasErrors('name');
        $this->assertDatabaseCount('supplier_agencies', 1);
        $this->assertCount(2, SupplierAgency::sole()->companies);
        $this->post(route('suppliers.store'), $values + ['agency_code' => 'DEMO-2'])->assertSessionHasNoErrors();
        $second = SupplierAgency::where('agency_code', 'DEMO-2')->sole();
        $this->patch(route('suppliers.update', $second), $values + ['agency_code' => null])->assertSessionHasErrors('name');
        $this->assertSame('DEMO-2', $second->fresh()->agency_code);
        $this->post(route('supplier-companies.store'), ['name' => 'compagnia demo a', 'status' => 'active'])->assertSessionHasErrors('name');
        $this->get(route('supplier-companies.index', ['status' => 'inactive']))->assertOk()->assertSee($two->name)->assertDontSee($one->name);
    }
}
