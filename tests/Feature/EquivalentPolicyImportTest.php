<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AuditLog, InsurancePolicy, InsurancePolicyImport, User};
use App\Services\{ApprovedEquivalentPolicyImport, PolicyExcelImporter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\{EquivalentPolicyFixture, PolicyImportFixture};
use Tests\TestCase;

class EquivalentPolicyImportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(?string $changedField = null): array
    {
        Storage::fake('local');
        return EquivalentPolicyFixture::create(Storage::disk('local')->path('equivalent-fixture'), $changedField);
    }

    private function admin(): User { return User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']); }

    public function test_approved_groups_create_one_review_policy_each_with_intact_sources_and_audit(): void
    {
        $fixture = $this->fixture(); $actor = $this->admin(); $this->actingAs($actor);
        $before = InsurancePolicyImport::orderBy('id')->get()->keyBy('id');
        $result = app(ApprovedEquivalentPolicyImport::class)->apply($fixture['approval'], $actor);
        $this->assertSame(2, $result['created']); $this->assertSame(4, $result['source_rows']);
        $this->assertDatabaseCount('insurance_policies', 4); $this->assertDatabaseCount('insurance_policy_imports', 9);
        foreach ($fixture['approval']['groups'] as $group) {
            $entry = InsurancePolicyImport::with('policy')->findOrFail($group['entry_id']);
            $this->assertSame($before[$entry->id]->source_payload, $entry->source_payload);
            $this->assertSame($before[$entry->id]->payload_hash, $entry->payload_hash);
            $this->assertSame($before[$entry->id]->source_rows, $entry->source_rows);
            $this->assertSame('review', $entry->policy->status);
            $this->assertSame('720.50', $entry->policy->premium);
            $this->assertSame('2020-12-31', $entry->policy->expires_on->toDateString());
            $this->assertSame($fixture['client']->id, $entry->policy->client_id);
            $this->assertSame($fixture['agency']->id, $entry->policy->supplier_agency_id);
            $this->assertNull($entry->policy->product); $this->assertNull($entry->policy->vehicle_id);
            $log = $entry->auditLogs()->where('action', ApprovedEquivalentPolicyImport::ACTION)->sole();
            $this->assertSame($actor->id, $log->user_id);
            $this->assertSame($entry->source_rows, $log->new_values['source_rows']);
            $this->assertSame(ApprovedEquivalentPolicyImport::FIELDS, $log->new_values['compared_fields']);
            $this->get(route('insurance-import.policy-source', $entry))->assertOk()->assertSee('Accorpamento delle righe')->assertSee('righe originali conservate integralmente');
        }
        $entry = InsurancePolicyImport::where('number', 'REPEAT-QA')->sole();
        $this->assertSame('Archivio / OLD', $entry->source_status);
        foreach (InsurancePolicyImport::whereNotIn('id', array_column($fixture['approval']['groups'], 'entry_id'))->get() as $untouched) $this->assertSame($before[$untouched->id]->toArray(), $untouched->toArray());
        foreach (['attachments', 'client_vehicles', 'client_document_requests'] as $table) $this->assertDatabaseCount($table, 0);
        $this->get(route('clients.policies.show', [$fixture['client'], $entry->policy]))->assertOk()->assertSee('Origine dati')->assertSee('Da verificare');
        $this->actingAs(User::factory()->create(['role' => UserRole::Operator]));
        $this->get(route('insurance-import.policy-source', $entry))->assertForbidden();
    }

    public function test_subset_and_reruns_preserve_manual_edits_and_do_not_import_unapproved_groups(): void
    {
        $fixture = $this->fixture(); $approval = $fixture['approval']; $approval['groups'] = [$approval['groups'][0]];
        $resolver = app(ApprovedEquivalentPolicyImport::class); $actor = $this->admin();
        $this->assertSame(1, $resolver->apply($approval, $actor)['created']);
        $entry = InsurancePolicyImport::findOrFail($approval['groups'][0]['entry_id']);
        $entry->policy->update(['status' => 'ceased', 'notes' => 'Modifica successiva dell’operatore']);
        $before = $entry->policy->fresh()->toArray();
        $rerun = $resolver->apply($approval, $actor);
        $this->assertSame(0, $rerun['created']); $this->assertSame(1, $rerun['already_imported']);
        $importer = app(PolicyExcelImporter::class);
        $this->assertSame(0, $importer->apply($importer->plan()['batch_hash'])['created']);
        $this->assertSame($before, $entry->policy->fresh()->toArray());
        $this->assertSame('held', InsurancePolicyImport::findOrFail($fixture['approval']['groups'][1]['entry_id'])->status);
        $this->assertSame(1, AuditLog::where('action', ApprovedEquivalentPolicyImport::ACTION)->count());
        $this->assertDatabaseCount('insurance_policies', 3);
    }

    public function test_a_different_note_aborts_the_whole_approved_batch_before_creating_policies(): void
    {
        $fixture = $this->fixture('Note');
        try { app(ApprovedEquivalentPolicyImport::class)->apply($fixture['approval'], $this->admin()); $this->fail('Expected divergent note guard'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Note', $e->getMessage()); }
        $this->assertDatabaseCount('insurance_policies', 2);
        $this->assertSame(0, AuditLog::where('action', ApprovedEquivalentPolicyImport::ACTION)->count());
    }

    public function test_changed_source_or_approval_cannot_widen_the_import(): void
    {
        $fixture = $this->fixture(); $actor = $this->admin(); $service = app(ApprovedEquivalentPolicyImport::class);
        $bad = $fixture['approval']; $bad['groups'][1]['source_rows'] = [999];
        try { $service->apply($bad, $actor); $this->fail('Expected approval guard'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('elenco approvato', $e->getMessage()); }
        $bad = $fixture['approval']; $bad['groups'][] = $bad['groups'][0];
        try { $service->apply($bad, $actor); $this->fail('Expected duplicate approval guard'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('duplicato', $e->getMessage()); }
        $fixture['rows'][3]['values']['Note'] = 'Fonte successivamente modificata';
        PolicyImportFixture::write($fixture['directory'], ['source-rows.jsonl' => $fixture['rows']]);
        try { $service->apply($fixture['approval'], $actor); $this->fail('Expected changed source guard'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('lotto è cambiato', $e->getMessage()); }
        $this->assertDatabaseCount('insurance_policies', 2);
    }

    public function test_changed_mandates_and_non_admin_execution_are_rejected(): void
    {
        $fixture = $this->fixture(); $service = app(ApprovedEquivalentPolicyImport::class);
        try { $service->apply($fixture['approval'], User::factory()->create(['role' => UserRole::Operator])); $this->fail('Expected admin guard'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('amministratore', $e->getMessage()); }
        $fixture['agency']->companies()->detach();
        try { $service->apply($fixture['approval'], $this->admin()); $this->fail('Expected relationship guard'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('altri rilievi', $e->getMessage()); }
        $this->assertDatabaseCount('insurance_policies', 2);
    }
}
