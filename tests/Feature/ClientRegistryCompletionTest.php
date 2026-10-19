<?php

namespace Tests\Feature;

use App\Domain\Core\Actions\MergeClientsAction;
use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\ClientConsent;
use App\Models\ClientHousehold;
use App\Models\ClientMerge;
use App\Models\ClientRelationship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientRegistryCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Storage::fake('attachments');
    }

    private function person(array $values = []): Client
    {
        return Client::factory()->create(array_merge(['client_type' => 'person', 'name' => 'Cliente dimostrativo', 'email' => null, 'phone' => null, 'status' => 'active'], $values));
    }

    private function policyData(array $values = []): array
    {
        return array_merge(['category' => 'Ramo dimostrativo', 'subcategory' => 'Sottoramo dimostrativo', 'company' => 'Compagnia dimostrativa', 'number' => 'TEST-01', 'starts_on' => '2026-01-01', 'expires_on' => '2026-12-31', 'status' => 'active'], $values);
    }

    private function consentData(array $values = []): array
    {
        return array_merge(['kind' => 'electronic_delivery', 'scope' => 'all', 'status' => 'given', 'event_at' => now()->subHour()->format('Y-m-d\TH:i'), 'signer_name' => 'Firmatario dimostrativo', 'notice_reference' => 'Modulo dimostrativo, versione test', 'evidence_reference' => 'Archivio dimostrativo / prova test'], $values);
    }

    private function document(Client $client): Attachment
    {
        $this->post(route('clients.documents.store', $client), ['document_category' => 'consent', 'file' => UploadedFile::fake()->createWithContent('prova-demo.txt', 'Documento dimostrativo.')])->assertSessionHasNoErrors();

        return $client->attachments()->latest('id')->firstOrFail();
    }

    private function mergePayload(Client $target, Client $source): array
    {
        $preview = app(MergeClientsAction::class)->preview($target->fresh(), $source->fresh());

        return ['source' => $source->id, 'token' => $preview['token'], 'choices' => array_fill_keys(array_keys($preview['conflicts']), 'target'), 'confirm' => '1'];
    }

    public function test_registry_preferences_referents_and_history_are_saved_and_audited(): void
    {
        $client = $this->person();
        $this->patch(route('clients.update', $client), ['name' => $client->name, 'status' => 'active', 'preferred_contact_channel' => 'whatsapp', 'preferred_contact_time' => 'Pomeriggio', 'contact_preferences' => 'Indicazione dimostrativa'])->assertSessionHasNoErrors();
        $this->assertSame('whatsapp', $client->fresh()->preferred_contact_channel);
        $this->assertSame(0, $client->consents()->count());
        $this->post(route('clients.records.store', [$client, 'contacts']), ['name' => 'Referente dimostrativo', 'role' => 'Amministrazione', 'email' => 'DEMO@example.test'])->assertSessionHasNoErrors();
        $contact = $client->contacts()->sole();
        $this->assertSame('demo@example.test', $contact->email);
        $this->post(route('clients.records.store', [$client, 'interactions']), ['event_at' => now()->subHour()->format('Y-m-d\TH:i'), 'channel' => 'phone', 'subject' => 'Contatto dimostrativo', 'notes' => 'Dettaglio dimostrativo', 'created_by' => 999])->assertSessionHasNoErrors();
        $interaction = $client->interactions()->sole();
        $this->assertSame(auth()->id(), $interaction->created_by);
        $this->get(route('clients.show', [$client, 'tab' => 'history']))->assertOk()->assertSee('Contatto dimostrativo')->assertSee('Dettaglio dimostrativo');
        $this->assertTrue($client->auditLogs()->where('description', 'like', '%Referente dimostrativo%')->exists());
        $this->delete(route('clients.records.destroy', [$client, 'contacts', $contact->id]))->assertSessionHasNoErrors();
        $this->assertSame(0, $client->contacts()->count());
    }

    public function test_new_records_enforce_client_ownership_and_existing_permissions(): void
    {
        $one = $this->person();
        $two = $this->person(['name' => 'Altro cliente dimostrativo']);
        $record = $two->contacts()->create(['name' => 'Referente riservato']);
        $this->patch(route('clients.records.update', [$one, 'contacts', $record->id]), ['name' => 'Alterato'])->assertNotFound();
        $this->delete(route('clients.records.destroy', [$one, 'contacts', $record->id]))->assertNotFound();
        $viewer = User::factory()->create(['role' => UserRole::Operator]);
        $one->authorizedUsers()->attach($viewer);
        $this->actingAs($viewer)->get(route('clients.show', [$one, 'tab' => 'history']))->assertOk();
        $this->post(route('clients.records.store', [$one, 'contacts']), ['name' => 'Prova'])->assertSessionHasNoErrors();
        $this->get(route('clients.show', [$two, 'tab' => 'history']))->assertOk();
        $this->get(route('clients.merge.preview', [$one, 'source' => $two->id]))->assertForbidden();
    }

    public function test_relationship_roles_are_reciprocal_and_all_clients_are_visible_to_operators(): void
    {
        $one = $this->person(['name' => 'Persona principale dimostrativa']);
        $two = $this->person(['name' => 'Azienda riservata dimostrativa', 'client_type' => 'company']);
        $this->post(route('clients.relationships.store', $two), ['related_client_id' => $one->id, 'relationship_type' => 'business', 'client_role' => 'Azienda', 'related_role' => 'Referente'])->assertSessionHasNoErrors();
        $relation = ClientRelationship::sole();
        $this->assertSame('Referente', $relation->roleFor($one));
        $this->assertSame('Azienda', $relation->roleFor($two));
        $this->get(route('clients.show', [$one, 'tab' => 'network']))->assertOk()->assertSee($two->name)->assertSee('Referente');
        $this->post(route('clients.relationships.store', $one), ['related_client_id' => $two->id, 'relationship_type' => 'reference'])->assertSessionHasErrors('related_client_id');
        $this->post(route('clients.relationships.store', $one), ['related_client_id' => $one->id, 'relationship_type' => 'family'])->assertSessionHasErrors('related_client_id');
        $viewer = User::factory()->create(['role' => UserRole::Operator]);
        $one->authorizedUsers()->attach($viewer);
        $this->actingAs($viewer)->get(route('clients.show', [$one, 'tab' => 'network']))->assertOk()->assertSee($two->name);
    }

    public function test_households_accept_only_visible_people_and_protect_company_conversion(): void
    {
        $one = $this->person();
        $two = $this->person(['name' => 'Familiare dimostrativo']);
        $company = $this->person(['client_type' => 'company']);
        $this->post(route('clients.households.store', $one), ['name' => 'Nucleo dimostrativo', 'members' => [$one->id, $two->id], 'roles' => [$one->id => 'Genitore', $two->id => 'Figlio']])->assertSessionHasNoErrors();
        $group = ClientHousehold::sole();
        $this->get(route('clients.households.edit', [$one, $group]))->assertOk()->assertSee('Genitore');
        $this->get(route('clients.show', [$two, 'tab' => 'network']))->assertOk()->assertSee('Nucleo dimostrativo')->assertSee('Figlio');
        $this->post(route('clients.households.store', $one), ['name' => 'Nucleo non valido', 'members' => [$one->id, $company->id]])->assertSessionHasErrors('members');
        $this->patch(route('clients.update', $one), ['name' => $one->name, 'status' => 'active', 'client_type' => 'company'])->assertSessionHasErrors('client_type');
        $viewer = User::factory()->create(['role' => UserRole::Operator]);
        $one->authorizedUsers()->attach($viewer);
        $this->actingAs($viewer)->get(route('clients.show', [$one, 'tab' => 'network']))->assertOk()->assertSee('Nucleo dimostrativo')->assertSee($two->name);
    }

    public function test_assets_are_linked_only_to_own_policies_and_cannot_be_deleted_when_used(): void
    {
        $client = $this->person();
        $other = $this->person();
        $this->post(route('clients.records.store', [$client, 'assets']), ['asset_type' => 'property', 'name' => 'Immobile dimostrativo', 'category' => 'Abitazione'])->assertSessionHasErrors('address');
        $this->post(route('clients.records.store', [$client, 'assets']), ['asset_type' => 'property', 'name' => 'Immobile dimostrativo', 'category' => 'Abitazione', 'address' => 'Indirizzo dimostrativo'])->assertSessionHasNoErrors();
        $asset = $client->assets()->sole();
        $vehicle = $client->vehicles()->create(['plate' => 'TEST01']);
        $this->post(route('clients.policies.store', $client), ($this->policyData(['client_asset_id' => $asset->id, 'vehicle_id' => $vehicle->id])) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('client_asset_id');
        $this->post(route('clients.policies.store', $other), ($this->policyData(['client_asset_id' => $asset->id])) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('client_asset_id');
        $this->post(route('clients.policies.store', $client), ($this->policyData(['client_asset_id' => $asset->id])) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasNoErrors();
        $policy = $client->policies()->sole();
        $this->patch(route('clients.policies.update', [$client, $policy]), $this->policyData(['vehicle_id' => $vehicle->id, 'policy_revision' => $policy->fresh()->editFingerprint()]))->assertSessionHasErrors('client_asset_id');
        $this->get(route('clients.show', [$client, 'tab' => 'assets']))->assertOk()->assertSee('TEST-01');
        $this->get(route('clients.policies.show', [$client, $policy]))->assertOk()->assertSee('Immobile dimostrativo');
        $this->get(route('clients.show', [$client, 'tab' => 'deadlines']))->assertOk()->assertSee('Immobile dimostrativo')->assertSee('Sottoramo dimostrativo');
        $this->delete(route('clients.records.destroy', [$client, 'assets', $asset->id]))->assertSessionHasErrors('asset');
    }

    public function test_policy_tabs_filter_branch_then_subbranch_without_reclassifying_existing_values(): void
    {
        $client = $this->person();
        foreach ([['Uno', 'A', 'SUB-A'], ['Uno', 'B', 'SUB-B'], ['Due', 'A', 'OTHER-A']] as [$branch, $subbranch, $number]) {
            $client->policies()->create($this->policyData(['category' => $branch, 'subcategory' => $subbranch, 'number' => $number]));
        }
        $this->get(route('clients.show', [$client, 'category' => 'Uno', 'subcategory' => 'A']))->assertOk()->assertSee('SUB-A')->assertDontSee('SUB-B')->assertDontSee('OTHER-A');
        $this->get(route('clients.show', [$client, 'category' => 'Uno']))->assertSee('SUB-A')->assertSee('SUB-B')->assertDontSee('OTHER-A');
        $legacy = $client->policies()->create($this->policyData(['number' => 'NO-SUB', 'subcategory' => null]));
        $this->get(route('clients.policies.show', [$client, $legacy]))->assertOk()->assertSee('Da specificare');
        $this->assertNull($legacy->fresh()->subcategory);
        $this->get(route('clients.policies.create', [$client, 'entry_mode' => 'existing', 'category' => 'Uno', 'subcategory' => 'A']))->assertOk()
            ->assertViewHas('policy', fn ($policy) => $policy->category === 'Uno' && $policy->subcategory === 'A');
    }

    public function test_electronic_consent_requires_scope_evidence_and_company_signer_capacity(): void
    {
        $client = $this->person(['client_type' => 'company']);
        $this->post(route('clients.records.store', [$client, 'consents']), $this->consentData(['evidence_reference' => null]))->assertSessionHasErrors(['signer_role', 'evidence_reference']);
        $this->post(route('clients.records.store', [$client, 'consents']), $this->consentData(['scope' => 'policy', 'signer_role' => 'Delegato']))->assertSessionHasErrors('insurance_policy_id');
        $other = $this->person();
        $otherPolicy = $other->policies()->create($this->policyData());
        $this->post(route('clients.records.store', [$client, 'consents']), $this->consentData(['scope' => 'policy', 'insurance_policy_id' => $otherPolicy->id, 'signer_role' => 'Delegato']))->assertSessionHasErrors('insurance_policy_id');
        $this->post(route('clients.records.store', [$client, 'consents']), $this->consentData(['status' => '', 'signer_role' => 'Delegato']))->assertSessionHasErrors('status');
        $this->post(route('clients.records.store', [$client, 'consents']), $this->consentData(['signer_role' => 'Delegato', 'purpose' => 'Marketing non autorizzato']))->assertSessionHasNoErrors();
        $consent = $client->consents()->sole();
        $this->assertSame(ClientConsent::KINDS['electronic_delivery'], $consent->purpose);
        $this->assertNull($consent->insurance_policy_id);
        $this->get(route('clients.show', [$client, 'tab' => 'consents']))->assertOk()->assertSee('Delegato')->assertSee('non autorizza comunicazioni promozionali');
    }

    public function test_consent_history_is_append_only_and_evidence_file_and_policy_are_protected(): void
    {
        $client = $this->person();
        $policy = $client->policies()->create($this->policyData());
        $doc = $this->document($client);
        $given = $this->consentData(['scope' => 'policy', 'insurance_policy_id' => $policy->id, 'attachment_id' => $doc->id, 'evidence_reference' => null]);
        $this->post(route('clients.records.store', [$client, 'consents']), $given)->assertSessionHasNoErrors();
        $consent = $client->consents()->sole();
        $this->patch(route('clients.records.update', [$client, 'consents', $consent->id]), $this->consentData(['status' => 'revoked']))->assertStatus(405);
        $this->delete(route('clients.records.destroy', [$client, 'consents', $consent->id]))->assertStatus(405);
        $this->post(route('clients.records.store', [$client, 'consents']), array_replace($given, ['status' => 'revoked', 'event_at' => now()->subMinute()->format('Y-m-d\TH:i')]))->assertSessionHasNoErrors();
        $this->assertSame(['given', 'revoked'], $client->consents()->orderBy('id')->pluck('status')->all());
        $this->delete(route('attachments.destroy', $doc))->assertSessionHasErrors('document');
        Storage::disk('attachments')->assertExists($doc->path);
        $this->delete(route('clients.policies.destroy', [$client, $policy]))->assertSessionHasErrors('policy');
        $this->delete(route('clients.destroy', $client))->assertSessionHasErrors('client');
    }

    public function test_consent_rejects_other_clients_documents_and_future_dates(): void
    {
        $one = $this->person();
        $two = $this->person();
        $doc = $this->document($two);
        $this->post(route('clients.records.store', [$one, 'consents']), $this->consentData(['attachment_id' => $doc->id, 'evidence_reference' => null]))->assertSessionHasErrors('attachment_id');
        $this->post(route('clients.records.store', [$one, 'consents']), $this->consentData(['event_at' => now()->addDay()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('event_at');
        $legacy = $this->document($one);
        $legacy->update(['document_category' => null]);
        $this->post(route('clients.records.store', [$one, 'consents']), $this->consentData(['attachment_id' => $legacy->id, 'evidence_reference' => null]))->assertSessionHasErrors('attachment_id');
        $this->assertSame(0, $one->consents()->count());
    }

    public function test_duplicate_detection_checks_both_phone_fields_and_respects_visibility(): void
    {
        $commercial = User::factory()->create(['role' => UserRole::Operator]);
        $one = $this->person(['name' => 'Uno dimostrativo', 'phone' => '+39 320 1234567', 'mobile' => '3339999999', 'commercial_user_id' => $commercial->id]);
        $two = $this->person(['name' => 'Due dimostrativo', 'mobile' => '3201234567', 'commercial_user_id' => $commercial->id]);
        $hidden = $this->person(['name' => 'Cliente non assegnato', 'phone' => '3201234567']);
        $this->assertSame('393201234567', $one->normalized_phone);
        $this->assertSame('393339999999', $one->normalized_mobile);
        $this->actingAs($commercial)->get(route('clients.duplicates', $one))->assertOk()->assertSee($two->name)->assertSee('recapito telefonico in comune')->assertSee($hidden->name)->assertDontSee('Confronta e unifica');
        $this->assertNull($two->fresh()->merged_into_id);
    }

    public function test_merge_transfers_records_preserves_files_and_consent_history_and_blocks_stale_source_writes(): void
    {
        $target = $this->person(['name' => 'Cliente comune dimostrativo', 'phone' => '3200000000']);
        $source = $this->person(['name' => $target->name, 'phone' => '3200000000', 'salient_notes' => 'Nota da conservare']);
        $vehicle = $source->vehicles()->create(['plate' => 'TEST99']);
        $asset = $source->assets()->create(['name' => 'Bene dimostrativo', 'asset_type' => 'other', 'category' => 'Tipo dimostrativo']);
        $policy = $source->policies()->create($this->policyData(['vehicle_id' => $vehicle->id]));
        $doc = $this->document($source);
        $doc->update(['insurance_policy_id' => $policy->id, 'vehicle_id' => $vehicle->id]);
        $source->contacts()->create(['name' => 'Referente dimostrativo']);
        $this->post(route('clients.records.store', [$source, 'consents']), $this->consentData(['scope' => 'policy', 'insurance_policy_id' => $policy->id, 'attachment_id' => $doc->id]))->assertSessionHasNoErrors();
        $payload = $this->mergePayload($target, $source);
        $this->get(route('clients.merge.preview', [$target, 'source' => $source->id]))->assertOk()->assertSee('Nota da conservare');
        $this->post(route('clients.merge.store', $target), $payload)->assertSessionHasNoErrors()->assertRedirect(route('clients.show', [$target, 'tab' => 'registry']));
        $this->assertSame($target->id, $source->fresh()->merged_into_id);
        $this->assertSame('Nota da conservare', $target->fresh()->salient_notes);
        foreach ([$vehicle, $asset, $policy] as $row) {
            $this->assertSame($target->id, $row->fresh()->client_id);
        }
        $this->assertSame($target->id, $doc->fresh()->attachable_id);
        $this->assertSame($policy->id, $target->consents()->sole()->insurance_policy_id);
        $this->assertSame('Nota da conservare', ClientMerge::sole()->snapshot['source']['client']['salient_notes']);
        Storage::disk('attachments')->assertExists($doc->path);
        $this->get(route('attachments.download', $doc))->assertOk();
        $this->get(route('clients.show', $source))->assertRedirect(route('clients.show', [$target, 'tab' => 'registry']));
        $this->post(route('clients.records.store', [$source, 'contacts']), ['name' => 'Modifica obsoleta'])->assertStatus(409);
        $this->post(route('attachments.store'), ['attachable_type' => 'client', 'attachable_id' => $source->id, 'type' => 'document', 'file' => UploadedFile::fake()->createWithContent('demo.txt', 'Demo')])->assertStatus(409);
        $this->get(route('clients.show', [$target, 'tab' => 'registry']))->assertOk()->assertSee('Storico unificazioni');
        $this->assertSame(1, Client::current()->count());
    }

    public function test_merge_requires_review_of_conflicts_and_rejects_changed_previews(): void
    {
        $target = $this->person(['notes' => 'Nota principale']);
        $source = $this->person(['notes' => 'Nota alternativa']);
        $payload = $this->mergePayload($target, $source);
        $payload['choices'] = [];
        $this->post(route('clients.merge.store', $target), $payload)->assertSessionHasErrors('merge');
        $this->assertNull($source->fresh()->merged_into_id);
        $payload = $this->mergePayload($target, $source);
        $source->contacts()->create(['name' => 'Aggiunta dopo anteprima']);
        $this->post(route('clients.merge.store', $target), $payload)->assertSessionHasErrors('merge');
        $payload = $this->mergePayload($target, $source);
        $payload['choices']['notes'] = 'source';
        $this->post(route('clients.merge.store', $target), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Nota alternativa', $target->fresh()->notes);
    }

    public function test_merge_rejects_access_differences_and_colliding_policies_atomically(): void
    {
        $target = $this->person();
        $source = $this->person();
        $source->commercial_user_id = User::factory()->create(['role' => UserRole::Operator])->id;
        $source->save();
        $this->post(route('clients.merge.store', $target), $this->mergePayload($target, $source))->assertSessionHasErrors('merge');
        $source->update(['commercial_user_id' => null]);
        $a = $target->policies()->create($this->policyData());
        $b = $source->policies()->create($this->policyData());
        $this->post(route('clients.merge.store', $target), $this->mergePayload($target, $source))->assertSessionHasErrors('merge');
        $this->assertSame($source->id, $b->fresh()->client_id);
        $this->assertNull($source->fresh()->merged_into_id);
        $this->assertSame(0, ClientMerge::count());
    }

    public function test_merge_preserves_reciprocal_relations_households_and_alias_chains(): void
    {
        $target = $this->person();
        $source = $this->person();
        $other = $this->person(['name' => 'Familiare dimostrativo']);
        $this->post(route('clients.relationships.store', $source), ['related_client_id' => $other->id, 'relationship_type' => 'family', 'client_role' => 'Genitore', 'related_role' => 'Figlio'])->assertSessionHasNoErrors();
        $this->post(route('clients.households.store', $source), ['name' => 'Nucleo dimostrativo', 'members' => [$source->id, $other->id], 'roles' => [$source->id => 'Genitore']])->assertSessionHasNoErrors();
        $this->post(route('clients.merge.store', $target), $this->mergePayload($target, $source))->assertSessionHasNoErrors();
        $this->assertSame('Genitore', $target->relationships()->sole()->roleFor($target));
        $this->assertTrue(ClientHousehold::sole()->members->contains('id', $target->id));
        $next = $this->person();
        $this->post(route('clients.merge.store', $next), $this->mergePayload($next, $target))->assertSessionHasNoErrors();
        $this->assertSame($next->id, $source->fresh()->merged_into_id);
        $this->get(route('clients.show', $source))->assertRedirect(route('clients.show', [$next, 'tab' => 'registry']));
        $this->get(route('clients.show', [$next, 'tab' => 'network']))->assertOk()->assertSee($other->name);
    }

    public function test_all_new_forms_render_and_consent_is_not_preselected(): void
    {
        $client = $this->person();
        foreach (['contacts', 'assets', 'interactions', 'consents'] as $section) {
            $this->get(route('clients.records.create', [$client, $section]))->assertOk();
        }
        $this->get(route('clients.relationships.create', $client))->assertOk();
        $this->get(route('clients.households.create', $client))->assertOk();
        $this->get(route('clients.records.create', [$client, 'consents']))->assertDontSee('value="given" selected', false)->assertSee('informativa');
        $this->get(route('clients.records.create', [$client, 'policies']))->assertNotFound();
    }
}
