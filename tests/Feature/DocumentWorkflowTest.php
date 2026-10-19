<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Attachment, Client, ClientDocumentRequest, InsuranceBranch, InsuranceSubbranch, User};
use App\Services\InsuranceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setupClient(): Client
    {
        Storage::fake('attachments');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());
        return Client::factory()->create(['client_type' => 'person']);
    }
    private function upload(Client $client, array $extra = [], string $name = 'prova.txt')
    {
        return $this->post(route('clients.documents.store', $client), array_merge(['document_category' => 'identity', 'file' => UploadedFile::fake()->createWithContent($name, 'Documento dimostrativo'), 'expires_on' => '2027-10-15'], $extra));
    }

    public function test_requests_versions_and_validation_keep_original_files_and_require_new_review(): void
    {
        $client = $this->setupClient();
        $this->post(route('clients.document-requests.store', $client), ['document_category' => 'identity', 'label' => 'Identità dimostrativa', 'due_on' => '2026-10-16'])->assertSessionHasNoErrors();
        $request = ClientDocumentRequest::sole();
        $this->assertSame('Da ricevere', $request->state_label);
        $this->upload($client, ['document_request_id' => $request->id])->assertSessionHasNoErrors();
        $first = Attachment::sole();
        $this->assertSame('Ricevuto, da validare', $request->fresh()->state_label);
        $this->post(route('clients.documents.validate', [$client, $first]), ['validation_note' => 'Verificato nel collaudo'])->assertSessionHasNoErrors();
        $this->assertSame('validated', $first->fresh()->review_status);
        $this->assertSame(auth()->id(), $first->fresh()->validated_by);
        $this->upload($client, ['previous_version_id' => $first->id, 'document_request_id' => $request->id], 'seconda.txt')->assertSessionHasNoErrors();
        $second = Attachment::where('previous_version_id', $first->id)->sole();
        $this->assertSame(2, $second->version_number);
        $this->assertSame('received', $second->review_status);
        $this->assertSame('Ricevuto, da validare', $request->fresh()->state_label);
        Storage::disk('attachments')->assertExists([$first->path, $second->path]);
        $this->get(route('clients.show', [$client, 'tab' => 'documents']))->assertOk()->assertSee('seconda.txt')->assertDontSee('prova.txt');
        $this->get(route('clients.show', [$client, 'tab' => 'documents', 'document_view' => 'history']))->assertOk()->assertSee('prova.txt')->assertSee('seconda.txt');
        $this->post(route('clients.documents.validate', [$client, $first]))->assertSessionHasErrors('document');
        foreach ([$first, $second] as $file) $this->delete(route('attachments.destroy', $file))->assertSessionHasErrors('document');
        $this->delete(route('clients.document-requests.destroy', [$client, $request]))->assertSessionHasErrors('document');
        $this->post(route('clients.documents.validate', [$client, $second]))->assertSessionHasNoErrors();
        $this->patch(route('clients.documents.update', [$client, $second]), ['document_category' => 'identity', 'expires_on' => '2028-10-15'])->assertSessionHasNoErrors();
        $this->assertSame('received', $second->fresh()->review_status);
        $this->assertNull($second->fresh()->validated_by);
        $this->assertNull($second->fresh()->validated_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_validated']);
    }

    public function test_foreign_clients_context_changes_duplicate_versions_and_expired_validation_are_rejected(): void
    {
        $client = $this->setupClient();
        $other = Client::factory()->create();
        $item = $other->documentRequests()->create(['label' => 'Privato', 'document_category' => 'identity']);
        $this->upload($client, ['document_request_id' => $item->id])->assertSessionHasErrors('document_request_id');
        $this->upload($other, ['expires_on' => '2026-10-14'])->assertSessionHasNoErrors();
        $file = Attachment::sole();
        $this->upload($client, ['previous_version_id' => $file->id])->assertSessionHasErrors('previous_version_id');
        $this->post(route('clients.documents.validate', [$client, $file]))->assertNotFound();
        $this->post(route('clients.documents.validate', [$other, $file]))->assertSessionHasErrors('document');
        $this->upload($other, ['previous_version_id' => $file->id, 'document_category' => 'policy'])->assertSessionHasErrors('document_category');
        $this->upload($other, ['previous_version_id' => $file->id], 'nuova.txt')->assertSessionHasNoErrors();
        $this->upload($other, ['previous_version_id' => $file->id], 'duplicata.txt')->assertSessionHasErrors('previous_version_id');
        $this->assertDatabaseCount('attachments', 2);
        $this->assertCount(2, Storage::disk('attachments')->allFiles());
        $this->actingAs(User::factory()->create(['role' => UserRole::Operator]));
        $this->get(route('clients.document-requests.create', $client))->assertOk();
        $this->post(route('clients.documents.validate', [$other, $file]))->assertSessionHasErrors('document');
    }

    public function test_checklists_are_configured_explicitly_and_do_not_duplicate_requests(): void
    {
        $client = $this->setupClient();
        $catalog = app(InsuranceCatalog::class);
        $branch = $catalog->save(new InsuranceBranch, ['name' => 'Ramo demo', 'status' => 'active', 'needs_review' => false]);
        $this->post(route('clients.document-requests.apply', $client), ['insurance_branch_id' => $branch->id])->assertSessionHasErrors('insurance_branch_id');
        $this->patch(route('insurance-catalog.update', ['branch', $branch->id]), ['name' => $branch->name, 'status' => 'active', 'needs_review' => 0, 'document_categories' => ['identity', 'tax_code']])->assertSessionHasNoErrors();
        $sub = $catalog->save(new InsuranceSubbranch, ['insurance_branch_id' => $branch->id, 'name' => 'Sottoramo demo', 'status' => 'active', 'needs_review' => false, 'document_categories' => ['identity', 'policy']]);
        $values = ['insurance_branch_id' => $branch->id, 'insurance_subbranch_id' => $sub->id, 'due_on' => '2026-10-22'];
        $this->post(route('clients.document-requests.apply', $client), $values)->assertSessionHasNoErrors();
        $this->post(route('clients.document-requests.apply', $client), $values)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('client_document_requests', 3);
        $this->assertDatabaseCount('attachments', 0);
        $this->delete(route('clients.destroy', $client))->assertSessionHasErrors('client');
        $this->patch(route('insurance-catalog.update', ['branch', $branch->id]), ['name' => $branch->name, 'status' => 'active', 'needs_review' => 0, 'document_checklist_present' => '1'])->assertSessionHasNoErrors();
        $this->assertSame([], $branch->fresh()->document_categories);
        $this->assertDatabaseCount('client_document_requests', 3);
    }

    public function test_global_deadlines_enforce_visibility_filters_and_ignore_replaced_files_and_received_requests(): void
    {
        $client = $this->setupClient();
        $commercial = User::factory()->create(['role' => UserRole::Operator]);
        $client->update(['commercial_user_id' => $commercial->id]);
        $other = Client::factory()->create(['name' => 'Cliente non visibile']);
        $client->policies()->create(['category' => 'RC auto', 'company' => 'Demo A', 'number' => 'POL-VISIBILE', 'starts_on' => '2026-01-01', 'expires_on' => '2026-10-22', 'status' => 'active']);
        $other->policies()->create(['category' => 'Casa', 'company' => 'Riservata', 'number' => 'POL-SEGRETA', 'starts_on' => '2026-01-01', 'expires_on' => '2026-10-22', 'status' => 'active']);
        $client->policies()->create(['category' => 'RC auto', 'company' => 'Demo A', 'number' => 'POL-CESSATA', 'starts_on' => '2026-01-01', 'expires_on' => '2026-10-22', 'status' => 'ceased']);
        $this->upload($client, ['expires_on' => '2026-10-16'], 'vecchia.txt')->assertSessionHasNoErrors();
        $old = Attachment::sole();
        $this->upload($client, ['previous_version_id' => $old->id, 'expires_on' => '2026-10-22'], 'corrente.txt')->assertSessionHasNoErrors();
        $client->documentRequests()->create(['label' => 'DA-RICEVERE', 'document_category' => 'identity', 'due_on' => '2026-10-15']);
        $done = $client->documentRequests()->create(['label' => 'GIA-RICEVUTO', 'document_category' => 'identity', 'due_on' => '2026-10-15']);
        $this->upload($client, ['document_request_id' => $done->id, 'expires_on' => null], 'ricevuto.txt')->assertSessionHasNoErrors();
        $this->actingAs($commercial);
        $this->get(route('deadlines.index', ['period' => '7']))->assertOk()->assertSee('POL-VISIBILE')->assertSee('corrente.txt')->assertSee('DA-RICEVERE')
            ->assertDontSee('vecchia.txt')->assertSee('POL-SEGRETA')->assertSee('Riservata')->assertDontSee('POL-CESSATA')->assertDontSee('GIA-RICEVUTO');
        $this->withHeader('X-Table-Export', '1')->get(route('deadlines.index', ['period' => '7', 'kind' => 'policy', '_table_all' => 1]))->assertOk()->assertViewHas('deadlines', fn ($rows) => $rows->total() === 2);
        $this->get(route('deadlines.index', ['period' => 'custom', 'from' => '2026-10-22', 'to' => '2026-10-22', 'company' => 'Demo A']))->assertOk()->assertSee('POL-VISIBILE')->assertDontSee('corrente.txt');
        $this->get(route('deadlines.index', ['period' => 'custom', 'from' => '2026-10-22', 'to' => '2026-10-15']))->assertSessionHasErrors('to');
    }

    public function test_request_due_dates_are_editable_without_changing_document_context(): void
    {
        $client = $this->setupClient();
        $item = $client->documentRequests()->create(['label' => 'Documento demo', 'document_category' => 'identity', 'due_on' => '2026-10-16']);
        $this->get(route('clients.document-requests.edit', [$client, $item]))->assertOk()->assertSee('2026-10-16');
        $values = ['label' => 'Documento aggiornato', 'due_on' => '2026-10-23', 'notes' => 'Termine concordato nel collaudo'];
        $this->patch(route('clients.document-requests.update', [$client, $item]), $values)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-23', $item->fresh()->due_on->toDateString());
        $this->get(route('deadlines.index', ['period' => '7']))->assertOk()->assertDontSee('Documento aggiornato');
        $this->get(route('deadlines.index', ['period' => '15']))->assertOk()->assertSee('Documento aggiornato');
        $this->patch(route('clients.document-requests.update', [$client, $item]), $values + ['document_category' => 'policy'])->assertSessionHasErrors('document_category');
        $other = Client::factory()->create();
        $this->patch(route('clients.document-requests.update', [$other, $item]), $values)->assertNotFound();
        $this->assertSame($client->id, $item->fresh()->client_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_requested', 'auditable_id' => $client->id]);
    }

    public function test_policy_checklist_stays_consistent_when_classification_changes(): void
    {
        $client = $this->setupClient();
        $catalog = app(InsuranceCatalog::class);
        $one = $catalog->save(new InsuranceBranch, ['name' => 'Casa demo', 'status' => 'active', 'needs_review' => false, 'document_categories' => ['policy']]);
        $two = $catalog->save(new InsuranceBranch, ['name' => 'Auto demo', 'status' => 'active', 'needs_review' => false]);
        $values = ['category' => $one->name, 'company' => 'Compagnia demo', 'number' => 'DEMO-CLASS', 'starts_on' => '2026-01-01', 'expires_on' => '2027-01-01', 'status' => 'active'];
        $this->post(route('clients.policies.store', $client), ($values) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasNoErrors();
        $policy = $client->policies()->sole();
        $this->post(route('clients.document-requests.apply', $client), ['insurance_branch_id' => $one->id, 'insurance_policy_id' => $policy->id])->assertSessionHasNoErrors();
        $this->patch(route('clients.policies.update', [$client, $policy]), array_replace($values, ['category' => $two->name, 'policy_revision' => $policy->fresh()->editFingerprint()]))->assertSessionHasErrors('category');
        $this->assertSame($one->id, $policy->fresh()->insurance_branch_id);
        $this->patch(route('clients.policies.update', [$client, $policy]), $values + ['notes' => 'Nota dimostrativa', 'policy_revision' => $policy->fresh()->editFingerprint()])->assertSessionHasNoErrors();
        $this->delete(route('clients.policies.destroy', [$client, $policy]))->assertSessionHasErrors('policy');
    }

    public function test_merging_clients_preserves_request_version_links_and_checklist_idempotency(): void
    {
        $target = $this->setupClient();
        $source = Client::factory()->create(['client_type' => 'person', 'commercial_user_id' => $target->commercial_user_id]);
        $branch = app(InsuranceCatalog::class)->save(new InsuranceBranch, ['name' => 'Ramo demo', 'status' => 'active', 'needs_review' => false, 'document_categories' => ['identity']]);
        $this->post(route('clients.document-requests.apply', $source), ['insurance_branch_id' => $branch->id])->assertSessionHasNoErrors();
        $item = ClientDocumentRequest::sole();
        $this->upload($source, ['document_request_id' => $item->id])->assertSessionHasNoErrors();
        $original = Attachment::sole();
        $this->upload($source, ['previous_version_id' => $original->id], 'versione.txt')->assertSessionHasNoErrors();
        $preview = app(\App\Domain\Core\Actions\MergeClientsAction::class)->preview($target->fresh(), $source->fresh());
        $this->post(route('clients.merge.store', $target), ['source' => $source->id, 'token' => $preview['token'], 'choices' => array_fill_keys(array_keys($preview['conflicts']), 'target'), 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($target->id, $item->fresh()->client_id);
        foreach (Attachment::all() as $document) {
            $this->assertSame($target->id, $document->attachable_id);
            $this->assertSame($item->id, $document->document_request_id);
            $this->get(route('attachments.download', $document))->assertOk();
        }
        $this->post(route('clients.document-requests.apply', $target), ['insurance_branch_id' => $branch->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('client_document_requests', 1);
        $this->assertDatabaseCount('attachments', 2);
        $this->get(route('clients.show', [$target, 'tab' => 'documents']))->assertOk()->assertSee('versione.txt')->assertDontSee('prova.txt');
    }
}
