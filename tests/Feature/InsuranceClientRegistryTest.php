<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Attachment, AuditLog, Client, InsurancePolicy, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InsuranceClientRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function policyData(array $overrides = []): array
    {
        return array_replace([
            'category' => 'RC auto', 'company' => 'Compagnia dimostrativa', 'number' => 'TEST-001',
            'starts_on' => '2026-01-01', 'expires_on' => '2026-12-31', 'status' => 'active',
        ], $overrides);
    }

    private function policy(Client $client, array $overrides = []): InsurancePolicy
    {
        return $client->policies()->create($this->policyData($overrides));
    }

    public function test_excel_fields_are_saved_for_people_and_companies(): void
    {
        $this->post(route('clients.store'), [
            'client_type' => 'person', 'name' => 'Persona dimostrativa', 'title' => 'Sig.',
            'tax_code' => 'demo-persona', 'birth_date' => '1980-01-02', 'birth_place' => 'Luogo dimostrativo',
            'phone' => '0100000000', 'mobile' => '3200000000', 'fax' => '0100000001', 'email' => 'test@example.test',
            'target' => 'Target dimostrativo', 'province' => 'rm', 'city' => 'Roma', 'address' => 'Indirizzo dimostrativo', 'postal_code' => '00100',
            'reference_person' => 'Referente dimostrativo', 'reference_email' => 'referente@example.test',
            'reference_role' => 'Ruolo dimostrativo', 'reference_phone' => '0100000002',
            'office' => 'Ufficio dimostrativo', 'consultant' => 'Consulente dimostrativo', 'collaborator' => 'Collaboratore dimostrativo',
            'operator' => 'Operatore dimostrativo', 'salient_notes' => 'Notizie dimostrative',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $person = Client::where('name', 'Persona dimostrativa')->sole();
        $this->assertSame('DEMO-PERSONA', $person->tax_code);
        $this->assertSame('1980-01-02', $person->birth_date->toDateString());
        $this->assertSame('3200000000', $person->mobile);
        $this->assertSame('Operatore dimostrativo', $person->operator);
        $this->get(route('clients.show', [$person, 'tab' => 'registry']))->assertOk()->assertSee('Notizie dimostrative')->assertSee('Referente dimostrativo');
        $this->post(route('clients.store'), ['client_type' => 'company', 'name' => 'Azienda dimostrativa', 'vat_number' => 'DEMO-IVA'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $company = Client::where('name', 'Azienda dimostrativa')->sole();
        $this->assertSame('company', $company->client_type);
        $this->get(route('clients.show', [$company, 'tab' => 'registry']))->assertSee('Azienda')->assertDontSee('Data di nascita');
    }

    public function test_only_admin_can_delete_policies_and_registered_documents_even_for_assigned_clients(): void
    {
        Storage::fake('attachments');
        $admin=auth()->user();
        $employee=User::factory()->create(['role'=>UserRole::Operator]);
        $client=Client::factory()->create(['commercial_user_id'=>$employee->id]);
        $policy=$this->policy($client);
        $this->actingAs($employee)->post(route('clients.documents.store',$client),[
            'file'=>UploadedFile::fake()->createWithContent('contratto.pdf',"%PDF-1.4\nContratto"),
            'document_category'=>'policy','insurance_policy_id'=>$policy->id,
        ])->assertSessionHasNoErrors();
        $document=$client->attachments()->sole();
        $this->get(route('attachments.download',$document))->assertOk();
        foreach ([$employee,User::factory()->create(['role'=>UserRole::Operator])] as $user) {
            $this->actingAs($user)->get(route('clients.policies.show',[$client,$policy]))->assertOk()->assertDontSee('Elimina polizza');
            $this->delete(route('clients.policies.destroy',[$client,$policy]))->assertForbidden();
            $this->delete(route('attachments.destroy',$document))->assertForbidden();
        }
        Storage::disk('attachments')->assertExists($document->path);
        $this->actingAs($admin)->delete(route('attachments.destroy',$document))->assertSessionHasNoErrors();
        $this->delete(route('clients.policies.destroy',[$client,$policy]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('insurance_policies',['id'=>$policy->id]);
    }

    public function test_registry_rejects_duplicate_identity_and_invalid_person_data(): void
    {
        Client::factory()->create(['tax_code' => 'DEMO-CF']);
        $this->post(route('clients.store'), ['name' => 'Duplicato dimostrativo', 'tax_code' => 'demo-cf'])->assertSessionHasErrors('tax_code');
        $this->post(route('clients.store'), ['name' => 'Azienda dimostrativa', 'client_type' => 'company', 'birth_date' => '1990-01-01'])->assertSessionHasErrors('birth_date');
        $this->post(route('clients.store'), ['name' => 'Persona dimostrativa', 'client_type' => 'person', 'birth_date' => today()->addDay()->toDateString()])->assertSessionHasErrors('birth_date');
    }

    public function test_customer_can_have_multiple_policies_grouped_by_category(): void
    {
        foreach (['person', 'company'] as $type) {
            $client = Client::factory()->create(['client_type' => $type]);
            $vehicle = $client->vehicles()->create(['plate' => 'TEST01']);
            foreach (['RC auto', 'Infortuni', 'RC persone', 'Categoria dimostrativa'] as $index => $category) {
                $this->post(route('clients.policies.store', $client), ($this->policyData(['number' => 'TEST-'.$index, 'category' => $category, 'vehicle_id' => $index === 0 ? $vehicle->id : null])) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])
                    ->assertSessionHasNoErrors()->assertRedirect();
            }
            $this->assertSame(4, $client->policies()->count());
            $this->get(route('clients.show', $client))->assertOk()->assertSee('RC auto')->assertSee('Infortuni')->assertSee('RC persone')->assertSee('Categoria dimostrativa')->assertSee('TEST01');
        }
    }

    public function test_all_client_sections_and_edit_forms_render(): void
    {
        $client = Client::factory()->create(['client_type' => 'person']);
        $vehicle = $client->vehicles()->create(['plate' => 'TEST02']);
        $policy = $this->policy($client, ['vehicle_id' => $vehicle->id]);
        foreach (['policies', 'registry', 'vehicles', 'documents', 'deadlines'] as $tab) {
            $this->get(route('clients.show', [$client, 'tab' => $tab]))->assertOk();
        }
        foreach (['clients.create' => [], 'clients.edit' => [$client], 'clients.policies.create' => [$client], 'clients.policies.show' => [$client, $policy], 'clients.policies.edit' => [$client, $policy], 'clients.vehicles.create' => [$client], 'clients.vehicles.edit' => [$client, $vehicle], 'clients.documents.create' => [$client]] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertOk();
        }
    }

    public function test_policy_tabs_filter_each_category_and_keep_all_policies_available(): void
    {
        $client = Client::factory()->create();
        $this->policy($client, ['number' => 'AUTO-TAB', 'category' => 'RC auto']);
        $this->policy($client, ['number' => 'INFORTUNI-TAB', 'category' => 'Infortuni']);
        $this->policy($client, ['number' => 'ALTRO-TAB', 'category' => 'Ramo dimostrativo']);
        $this->get(route('clients.show', [$client, 'tab' => 'policies', 'category' => 'RC auto']))
            ->assertOk()->assertSee('Tipologie di polizza')->assertSee('Tutte le polizze')->assertSee('AUTO-TAB')->assertDontSee('INFORTUNI-TAB')->assertDontSee('ALTRO-TAB');
        $this->get(route('clients.show', [$client, 'tab' => 'policies', 'category' => 'Ramo dimostrativo']))
            ->assertOk()->assertSee('ALTRO-TAB')->assertDontSee('AUTO-TAB');
        $this->get(route('clients.show', [$client, 'tab' => 'policies']))
            ->assertOk()->assertSee('AUTO-TAB')->assertSee('INFORTUNI-TAB')->assertSee('ALTRO-TAB');
    }

    public function test_policy_validation_and_cross_customer_links_are_enforced(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $vehicle = $other->vehicles()->create(['plate' => 'OTHER01']);
        $policy = $this->policy($other);
        $this->post(route('clients.policies.store', $client), ($this->policyData(['vehicle_id' => $vehicle->id])) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('vehicle_id');
        $this->post(route('clients.policies.store', $client), ($this->policyData(['expires_on' => '2025-01-01'])) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('expires_on');
        $this->get(route('clients.policies.show', [$client, $policy]))->assertNotFound();
        $this->patch(route('clients.policies.update', [$client, $policy]), $this->policyData())->assertNotFound();
        $this->delete(route('clients.vehicles.destroy', [$client, $vehicle]))->assertNotFound();
        $this->post(route('clients.policies.store', $other), ($this->policyData()) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('number');
        $this->assertSame(0, $client->policies()->count());
    }

    public function test_vehicle_and_policy_changes_are_audited_and_linked_history_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        $this->post(route('clients.vehicles.store', $client), ['plate' => ' test 03 ', 'make' => 'Marca dimostrativa'])->assertSessionHasNoErrors();
        $vehicle = $client->vehicles()->sole();
        $this->assertSame('TEST03', $vehicle->plate);
        $this->post(route('clients.vehicles.store', $client), ['plate' => 'test03'])->assertSessionHasErrors('plate');
        $policy = $this->policy($client, ['vehicle_id' => $vehicle->id]);
        $this->patch(route('clients.policies.update', [$client, $policy]), $this->policyData(['expires_on' => '2027-01-01', 'policy_revision' => $policy->fresh()->editFingerprint()]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $client->id, 'description' => 'Polizza TEST-001: record aggiornato.']);
        $this->delete(route('clients.vehicles.destroy', [$client, $vehicle]))->assertSessionHasErrors('vehicle');
        $this->delete(route('clients.destroy', $client))->assertSessionHasErrors('client');
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_operators_manage_all_clients_but_cannot_bypass_quote_approval(): void
    {
        $commercial = User::factory()->create(['role' => UserRole::Operator]);
        $own = Client::factory()->create(['commercial_user_id' => $commercial->id]);
        $other = Client::factory()->create();
        $this->actingAs($commercial);
        $this->post(route('clients.policies.store', $own), ($this->policyData()) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('entry_mode');
        $ownedPolicy=$this->policy($own); $this->patch(route('clients.policies.update',[$own,$ownedPolicy]),$this->policyData(['notes'=>'Aggiornamento cliente assegnato','policy_revision'=>$ownedPolicy->fresh()->editFingerprint()]))->assertSessionHasNoErrors();
        $this->post(route('clients.policies.store', $other), ($this->policyData()) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('entry_mode');
        $this->get(route('clients.show', [$other, 'tab' => 'deadlines']))->assertOk();
        $assigned = User::factory()->create(['role' => UserRole::Operator]);
        $own->authorizedUsers()->attach($assigned);
        $this->actingAs($assigned)->get(route('clients.show', $own))->assertOk();
        $this->post(route('clients.vehicles.store', $own), ['plate' => 'AA123BB'])->assertSessionHasNoErrors();
    }

    public function test_documents_are_private_scoped_and_expiry_can_be_corrected(): void
    {
        Storage::fake('attachments');
        $client = Client::factory()->create();
        $policy = $this->policy($client);
        $this->post(route('clients.documents.store', $client), [
            'file' => UploadedFile::fake()->createWithContent('documento-test.pdf', "%PDF-1.4\nDati dimostrativi"),
            'document_category' => 'policy', 'insurance_policy_id' => $policy->id, 'expires_on' => '2026-12-31',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $document = $client->attachments()->sole();
        Storage::disk('attachments')->assertExists($document->path);
        $this->get(route('attachments.download', $document))->assertOk()->assertDownload('documento-test.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('clients.documents.edit', [$client, $document]))->assertOk();
        $this->get(route('clients.policies.show', [$client, $policy]))->assertOk()->assertSee('documento-test.pdf');
        $this->patch(route('clients.documents.update', [$client, $document]), ['document_category' => 'policy', 'insurance_policy_id' => $policy->id, 'expires_on' => '2027-01-31'])->assertSessionHasNoErrors();
        $this->assertSame('2027-01-31', $document->fresh()->expires_on->toDateString());
        $this->delete(route('clients.policies.destroy', [$client, $policy]))->assertSessionHasErrors('policy');
        $other = Client::factory()->create();
        $this->patch(route('clients.documents.update', [$other, $document]), ['document_category' => 'other'])->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => UserRole::Operator]));
        $this->get(route('attachments.download', $document))->assertOk();
        $this->delete(route('attachments.destroy', $document))->assertForbidden();
        Storage::disk('attachments')->assertExists($document->path);
    }

    public function test_document_upload_rejects_wrong_links_and_executable_files(): void
    {
        Storage::fake('attachments');
        $client = Client::factory()->create();
        $policy = $this->policy(Client::factory()->create());
        $this->post(route('clients.documents.store', $client), [
            'file' => UploadedFile::fake()->createWithContent('test.txt', 'Dimostrativo'), 'document_category' => 'other', 'insurance_policy_id' => $policy->id,
        ])->assertSessionHasErrors('insurance_policy_id');
        $this->post(route('clients.documents.store', $client), [
            'file' => UploadedFile::fake()->createWithContent('test.php', '<?php echo 1;'), 'document_category' => 'other',
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('attachments', 0);
        $this->assertCount(0, Storage::disk('attachments')->allFiles());
    }

    public function test_partial_document_updates_cannot_conflict_with_the_linked_policy_vehicle(): void
    {
        Storage::fake('attachments');
        $client = Client::factory()->create();
        $first = $client->vehicles()->create(['plate' => 'TEST05']);
        $second = $client->vehicles()->create(['plate' => 'TEST06']);
        $policy = $this->policy($client, ['vehicle_id' => $first->id]);
        $this->post(route('clients.documents.store', $client), [
            'file' => UploadedFile::fake()->createWithContent('coerenza.txt', 'Dimostrativo'),
            'document_category' => 'vehicle', 'insurance_policy_id' => $policy->id, 'vehicle_id' => $first->id,
        ])->assertSessionHasNoErrors();
        $document = $client->attachments()->sole();
        $this->patch(route('clients.documents.update', [$client, $document]), ['document_category' => 'vehicle', 'vehicle_id' => $second->id])->assertSessionHasErrors('vehicle_id');
        $this->assertSame($first->id, $document->fresh()->vehicle_id);
        $this->patch(route('clients.policies.update', [$client, $policy]), $this->policyData(['vehicle_id' => $second->id, 'policy_revision' => $policy->fresh()->editFingerprint()]))->assertSessionHasErrors('vehicle_id');
        $this->assertSame($first->id, $policy->fresh()->vehicle_id);
    }

    public function test_deadlines_are_derived_filtered_and_do_not_include_ceased_policies(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14)->startOfDay());
        $client = Client::factory()->create();
        $this->policy($client, ['number' => 'SCADUTA', 'expires_on' => '2026-10-13']);
        $this->policy($client, ['number' => 'OGGI', 'expires_on' => '2026-10-14']);
        $this->policy($client, ['number' => 'ENTRO7', 'expires_on' => '2026-10-21']);
        $this->policy($client, ['number' => 'DOPO7', 'expires_on' => '2026-10-22']);
        $this->policy($client, ['number' => 'CESSATA', 'expires_on' => '2026-10-20', 'status' => 'ceased']);
        /** The notification bell has its own 15-day window; assertions here cover only the filtered client deadline list. */
        $numbers = fn ($rows) => $rows->map(fn ($row) => \Illuminate\Support\Str::afterLast($row['title'], ' / '))->all();
        $this->get(route('clients.show', [$client, 'tab' => 'deadlines', 'period' => '7']))->assertOk()->assertViewHas('deadlines', fn ($rows) => $numbers($rows) === ['OGGI','ENTRO7']);
        $this->get(route('clients.show', [$client, 'tab' => 'deadlines', 'period' => 'overdue']))->assertOk()->assertViewHas('deadlines', fn ($rows) => $numbers($rows) === ['SCADUTA']);
        $this->get(route('clients.show', [$client, 'tab' => 'deadlines', 'period' => 'custom', 'from' => '2026-10-22', 'to' => '2026-10-22']))->assertOk()->assertViewHas('deadlines', fn ($rows) => $numbers($rows) === ['DOPO7']);
        $this->get(route('clients.show', [$client, 'tab' => 'deadlines', 'period' => 'custom', 'from' => '2026-10-22', 'to' => '2026-10-21']))->assertSessionHasErrors('to');
    }

    public function test_search_by_plate_or_policy_respects_visibility_and_client_type(): void
    {
        $commercial = User::factory()->create(['role' => UserRole::Operator]);
        $client = Client::factory()->create(['client_type' => 'company', 'commercial_user_id' => $commercial->id, 'name' => 'Azienda ricercabile dimostrativa']);
        $client->vehicles()->create(['plate' => 'TEST04']);
        $this->policy($client, ['number' => 'RICERCA04']);
        $other = Client::factory()->create(['name' => 'Azienda privata dimostrativa']);
        $other->vehicles()->create(['plate' => 'TEST04']);
        $this->actingAs($commercial);
        $this->get(route('clients.index', ['search' => 'test 04']))->assertOk()->assertSee($client->name)->assertSee($other->name);
        $this->getJson(route('api.clients.search', ['q' => 'RICERCA04']))->assertOk()->assertJsonCount(1);
        $this->get(route('clients.index', ['client_type' => 'person']))->assertOk()->assertDontSee($client->name);
    }

    public function test_every_deadline_window_includes_its_boundaries_for_policies_and_documents(): void
    {
        Storage::fake('attachments');
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(12, 30));
        $client = Client::factory()->create();
        $offsets = [-1, 0, 7, 8, 15, 16, 30, 31, 45, 46, 60, 61];
        foreach ($offsets as $days) {
            $expiry = today()->addDays($days)->toDateString();
            $this->policy($client, ['number' => 'FINESTRA-'.$days, 'expires_on' => $expiry]);
            $this->post(route('clients.documents.store', $client), [
                'file' => UploadedFile::fake()->createWithContent('scadenza-'.$days.'.txt', 'Documento dimostrativo'),
                'document_category' => 'identity', 'expires_on' => $expiry,
            ])->assertSessionHasNoErrors();
        }
        foreach ([7, 15, 30, 45, 60] as $window) {
            $expected = count(array_filter($offsets, fn ($days) => $days >= 0 && $days <= $window)) * 2;
            $this->get(route('clients.show', [$client, 'tab' => 'deadlines', 'period' => (string) $window]))
                ->assertOk()->assertViewHas('deadlines', fn ($rows) => $rows->count() === $expected
                    && $rows->first()['date']->isToday() && $rows->last()['date']->equalTo(today()->addDays($window)));
        }
        $this->get(route('clients.show', [$client, 'tab' => 'deadlines', 'period' => 'overdue']))
            ->assertOk()->assertViewHas('deadlines', fn ($rows) => $rows->count() === 2 && $rows->every(fn ($r) => $r['date']->isYesterday()));
        $this->get(route('clients.show', [$client, 'tab' => 'deadlines', 'period' => 'custom', 'from' => '2026-10-29', 'to' => '2026-10-29']))
            ->assertOk()->assertViewHas('deadlines', fn ($rows) => $rows->count() === 2 && $rows->every(fn ($r) => $r['date']->toDateString() === '2026-10-29'));
    }

    public function test_document_reassignment_and_deletion_update_every_related_view_and_file(): void
    {
        Storage::fake('attachments');
        $client = Client::factory()->create();
        $firstVehicle = $client->vehicles()->create(['plate' => 'QAOLD']);
        $secondVehicle = $client->vehicles()->create(['plate' => 'QANEW']);
        $first = $this->policy($client, ['number' => 'ORIGINALE', 'vehicle_id' => $firstVehicle->id]);
        $second = $this->policy($client, ['number' => 'DESTINAZIONE', 'vehicle_id' => $secondVehicle->id]);
        $this->post(route('clients.documents.store', $client), [
            'file' => UploadedFile::fake()->createWithContent('riassegnabile.txt', 'Documento dimostrativo'),
            'document_category' => 'vehicle', 'insurance_policy_id' => $first->id, 'vehicle_id' => $firstVehicle->id, 'expires_on' => '2026-11-01',
        ])->assertSessionHasNoErrors();
        $document = $client->attachments()->sole();
        $this->patch(route('clients.documents.update', [$client, $document]), [
            'document_category' => 'vehicle', 'insurance_policy_id' => $second->id, 'vehicle_id' => $secondVehicle->id, 'expires_on' => '2026-11-02',
        ])->assertSessionHasNoErrors();
        $this->get(route('clients.policies.show', [$client, $first]))->assertOk()->assertDontSee('riassegnabile.txt');
        $this->get(route('clients.policies.show', [$client, $second]))->assertOk()->assertSee('riassegnabile.txt');
        $this->get(route('clients.show', [$client, 'tab' => 'deadlines', 'period' => 'custom', 'from' => '2026-11-02', 'to' => '2026-11-02']))
            ->assertOk()->assertSee('riassegnabile.txt')->assertSee('DESTINAZIONE');
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $client->id, 'user_id' => auth()->id(), 'description' => 'Dati del documento riassegnabile.txt aggiornati.']);
        $this->delete(route('clients.policies.destroy', [$client, $first]))->assertSessionHasNoErrors();
        $this->delete(route('clients.vehicles.destroy', [$client, $firstVehicle]))->assertSessionHasNoErrors();
        $this->delete(route('attachments.destroy', $document))->assertSessionHasNoErrors();
        Storage::disk('attachments')->assertMissing($document->path);
        $this->assertDatabaseMissing('attachments', ['id' => $document->id]);
        $this->get(route('attachments.download', $document))->assertNotFound();
        $this->get(route('clients.show', [$client, 'tab' => 'deadlines']))->assertOk()->assertDontSee('riassegnabile.txt');
        $this->delete(route('clients.policies.destroy', [$client, $second]))->assertSessionHasNoErrors();
        $this->delete(route('clients.vehicles.destroy', [$client, $secondVehicle]))->assertSessionHasNoErrors();
        $this->delete(route('clients.destroy', $client))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_invalid_document_updates_and_large_uploads_leave_records_and_files_unchanged(): void
    {
        Storage::fake('attachments');
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $foreignVehicle = $other->vehicles()->create(['plate' => 'PRIVATA']);
        $foreignPolicy = $this->policy($other);
        $this->post(route('clients.documents.store', $client), [
            'file' => UploadedFile::fake()->createWithContent('originale.txt', 'Documento dimostrativo'), 'document_category' => 'identity',
        ])->assertSessionHasNoErrors();
        $document = $client->attachments()->sole();
        foreach (['vehicle_id' => $foreignVehicle->id, 'insurance_policy_id' => $foreignPolicy->id] as $field => $id) {
            $this->patch(route('clients.documents.update', [$client, $document]), ['document_category' => 'identity', $field => $id])->assertSessionHasErrors($field);
            $this->assertNull($document->fresh()->$field);
        }
        $this->get(route('clients.documents.edit', [$other, $document]))->assertNotFound();
        $this->get(route('clients.documents.create', [$client, 'policy' => $foreignPolicy->id]))->assertNotFound();
        $this->post(route('clients.documents.store', $client), [
            'file' => UploadedFile::fake()->createWithContent('grande.txt', 'Documento dimostrativo')->size(10241),
            'document_category' => 'other',
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('attachments', 1);
        $this->assertCount(1, Storage::disk('attachments')->allFiles());
        Storage::disk('attachments')->assertExists($document->path);
    }
}
