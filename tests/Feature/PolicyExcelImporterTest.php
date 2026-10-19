<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsurancePolicy, InsurancePolicyImport, RegistryImportEntry, SupplierAgency, SupplierCompany, User};
use App\Services\PolicyExcelImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\PolicyImportFixture;
use Tests\TestCase;

class PolicyExcelImporterTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(): array { Storage::fake('local'); return PolicyImportFixture::create(Storage::disk('local')->path('policy-fixture')); }
    private function apply(): array { $service = app(PolicyExcelImporter::class); return $service->apply($service->plan()['batch_hash']); }

    public function test_import_uses_approved_original_pairs_and_keeps_all_ambiguous_groups_on_hold(): void
    {
        $fixture = $this->fixture();
        $before = RegistryImportEntry::orderBy('id')->get()->toArray();
        $report = $this->apply();
        $this->assertSame(2, $report['created']); $this->assertSame(6, $report['held']);
        $this->assertDatabaseCount('insurance_policies', 2); $this->assertDatabaseCount('insurance_policy_imports', 8);
        $policy = InsurancePolicy::where('number', '000012')->sole();
        $this->assertSame('review', $policy->status); $this->assertSame('Da verificare', $policy->status_label);
        $this->assertSame('RC auto', $policy->category); $this->assertSame('AUTO', $policy->subcategory);
        $this->assertSame($fixture['branch']->id, $policy->insurance_branch_id); $this->assertSame($fixture['sub']->id, $policy->insurance_subbranch_id);
        $this->assertSame($fixture['company']->id, $policy->supplier_company_id); $this->assertSame($fixture['agency']->id, $policy->supplier_agency_id);
        $this->assertSame('720.50', $policy->premium); $this->assertSame('2020-12-31', $policy->expires_on->toDateString());
        $this->assertNull($policy->vehicle_id); $this->assertNull($policy->product);
        $this->assertNull(InsurancePolicy::where('number', '000013')->sole()->subcategory);
        $this->assertSame('OLD', InsurancePolicy::where('number', '000013')->sole()->importEntry->source_status);
        $repeated = InsurancePolicyImport::where('number', 'REPEAT-QA')->sole();
        $this->assertSame('held', $repeated->status); $this->assertCount(2, $repeated->source_payload['rows']);
        $this->assertSame('31/12/2021', $repeated->source_payload['rows'][1]['values']['Scadenza']);
        $this->assertSame($before, RegistryImportEntry::orderBy('id')->get()->toArray());
        foreach (['client_vehicles', 'attachments', 'client_document_requests'] as $table) $this->assertDatabaseCount($table, 0);
    }

    public function test_rerun_does_not_duplicate_or_overwrite_an_operator_change(): void
    {
        $this->fixture(); $this->apply();
        $policy = InsurancePolicy::firstOrFail(); $policy->update(['status' => 'ceased', 'notes' => 'Verificata manualmente']);
        $before = $policy->fresh()->toArray(); $report = $this->apply();
        $this->assertSame(0, $report['created']); $this->assertSame(2, $report['already_imported']);
        $this->assertSame($before, $policy->fresh()->toArray()); $this->assertDatabaseCount('insurance_policies', 2);
    }

    public function test_modified_imported_source_aborts_without_partial_imports(): void
    {
        $fixture = $this->fixture(); $this->apply();
        $fixture['rows'][0]['values']['Note'] = 'Fonte cambiata';
        PolicyImportFixture::write($fixture['directory'], ['source-rows.jsonl' => $fixture['rows']]);
        try { $this->apply(); $this->fail('Expected source change guard'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('già importata modificata', $e->getMessage()); }
        $this->assertDatabaseCount('insurance_policies', 2); $this->assertDatabaseCount('insurance_policy_imports', 8);
    }

    public function test_tampered_files_and_changed_batch_are_rejected(): void
    {
        $fixture = $this->fixture();
        try { app(PolicyExcelImporter::class)->apply(str_repeat('0', 64)); $this->fail('Expected batch guard'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('lotto è cambiato', $e->getMessage()); }
        file_put_contents($fixture['directory'].'/policies.json', '[]');
        try { app(PolicyExcelImporter::class)->plan(); $this->fail('Expected checksum guard'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Anteprima modificata', $e->getMessage()); }
        $this->assertDatabaseCount('insurance_policies', 0); $this->assertDatabaseCount('insurance_policy_imports', 0);
    }

    public function test_missing_mandate_and_preexisting_policy_are_held_without_guessing_links(): void
    {
        $fixture = $this->fixture();
        $existing = $fixture['client']->policies()->create(['category' => 'RC auto', 'company' => $fixture['company']->name, 'number' => '000012', 'starts_on' => '2026-01-01', 'expires_on' => '2027-01-01', 'status' => 'active']);
        $before = $existing->fresh()->toArray();
        $fixture['agency']->companies()->detach();
        $report = $this->apply();
        $this->assertSame(0, $report['created']); $this->assertSame(8, $report['held']);
        $this->assertSame($before, $existing->fresh()->toArray());
        $this->assertContains('record_esistente_da_confrontare', InsurancePolicyImport::where('number', '000012')->sole()->issues);
        $this->assertContains('fornitore_non_collegabile', InsurancePolicyImport::where('number', '000013')->sole()->issues);
    }

    public function test_current_client_identifier_changes_are_not_ignored(): void
    {
        $fixture = $this->fixture();
        $fixture['client']->update(['tax_code' => 'IDENTIFICATIVO MODIFICATO']);
        $report = $this->apply();
        $this->assertSame(0, $report['created']);
        $this->assertContains('identita_cliente_da_verificare', InsurancePolicyImport::where('number', '000012')->sole()->issues);
        $this->assertDatabaseCount('insurance_policies', 0);
    }

    public function test_policy_sources_are_admin_only_and_review_state_is_explicit_in_deadlines(): void
    {
        $fixture = $this->fixture(); $this->apply(); $policy = InsurancePolicy::where('number', '000012')->sole();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get(route('clients.policies.show', [$fixture['client'], $policy]))->assertOk()->assertSee('Origine dati')->assertSee('Da verificare')->assertSee($fixture['agency']->name)->assertDontSee('Dati originali dell’Excel');
        $this->get(route('deadlines.index', ['period' => 'all']))->assertOk()->assertSee('Stato polizza da verificare');
        $this->get(route('clients.show', [$fixture['client'], 'tab' => 'deadlines']))->assertOk()->assertSee('Stato polizza da verificare');
        $this->get(route('insurance-import.index', ['section' => 'policies', 'result' => 'held']))->assertOk()->assertSee('REPEAT-QA')->assertDontSee('>000012<', false);
        $this->get(route('insurance-import.policy-source', $policy->importEntry))->assertOk()->assertSee('60,04')->assertSee('720,50');
        $this->delete(route('clients.policies.destroy', [$fixture['client'], $policy]))->assertSessionHasErrors('policy');
        $policy->update(['status' => 'active']);
        $this->get(route('insurance-import.index', ['section' => 'policies', 'search' => '000012']))->assertOk()->assertSee('Scaduta');
        $this->actingAs(User::factory()->create(['role' => UserRole::Operator]));
        $this->get(route('insurance-import.policy-source', $policy->importEntry))->assertForbidden();
        $this->get(route('insurance-import.index', ['section' => 'policies']))->assertForbidden();
        $other = Client::factory()->create();
        $this->get(route('clients.policies.show', [$other, $policy]))->assertNotFound();
    }

    public function test_agency_company_links_are_validated_and_cannot_be_orphaned(): void
    {
        $fixture = $this->fixture(); $this->apply();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $other = SupplierCompany::create(['name' => 'ALTRA COMPAGNIA DEMO', 'status' => 'active']);
        $foreign = SupplierAgency::create(['name' => 'ALTRA AGENZIA DEMO', 'supplier_company_id' => $other->id, 'status' => 'active']);
        $policy = InsurancePolicy::where('number', '000012')->sole();
        $values = ['policy_revision' => $policy->fresh()->editFingerprint(), 'category' => 'RC auto', 'subcategory' => 'AUTO', 'company' => $fixture['company']->name, 'number' => $policy->number, 'starts_on' => '2020-01-01', 'expires_on' => '2020-12-31', 'status' => 'review'];
        $this->patch(route('clients.policies.update', [$fixture['client'], $policy]), $values + ['supplier_agency_id' => $foreign->id])->assertSessionHasErrors('supplier_agency_id');
        $this->assertSame($fixture['agency']->id, $policy->fresh()->supplier_agency_id);
        $this->delete(route('suppliers.destroy', $fixture['agency']))->assertSessionHasErrors('supplier');
        $this->patch(route('suppliers.update', $fixture['agency']), ['name' => $fixture['agency']->name, 'status' => 'active', 'supplier_company_ids' => [$other->id]])->assertSessionHasErrors('supplier_company_ids');
        $this->delete(route('supplier-companies.destroy', $fixture['company']))->assertSessionHasErrors('company');
        $this->patch(route('clients.policies.update', [$fixture['client'], $policy]), $values + ['supplier_agency_id' => $fixture['agency']->id, 'notes' => 'Controllo manuale'])->assertSessionHasNoErrors();
        $this->assertSame('Controllo manuale', $policy->fresh()->notes);
    }
}
