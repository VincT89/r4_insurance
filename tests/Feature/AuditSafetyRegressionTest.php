<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AuditLog, Client, ClientHousehold, ClientRelationship, InsuranceBranch, InsurancePolicy, SupplierCompany, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditSafetyRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private Client $client;
    private InsurancePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $this->operator = User::factory()->create(['role' => UserRole::Operator, 'status' => 'active']);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['client_type' => 'person', 'commercial_user_id' => $this->operator->id]);
        $branch = InsuranceBranch::create(['name' => 'RC auto', 'name_key' => hash('sha256', 'RC AUTO'), 'status' => 'active']);
        $company = SupplierCompany::create(['name' => 'Nobis', 'status' => 'active']);
        $this->policy = $this->client->policies()->create([
            'category' => $branch->name, 'insurance_branch_id' => $branch->id,
            'company' => $company->name, 'supplier_company_id' => $company->id,
            'number' => 'SA-2026-870', 'starts_on' => '2026-10-01', 'expires_on' => '2027-10-01',
            'status' => 'active', 'premium' => '1000.00', 'notes' => 'Nota iniziale',
        ]);
    }

    private function data(): array
    {
        return $this->policy->only(['category', 'company', 'number', 'status', 'notes'])
            + ['starts_on' => '2026-10-01', 'expires_on' => '2027-10-01', 'policy_revision' => $this->policy->fresh()->editFingerprint()];
    }

    private function policyPath(string $action): string
    {
        return route('clients.policies.'.$action, [$this->client, $this->policy]);
    }

    public function test_employees_cannot_delete_registry_records_vehicles_or_client_links(): void
    {
        $contact = $this->client->contacts()->create(['name' => 'Marco Riva']);
        $asset = $this->client->assets()->create(['name' => 'Sede', 'asset_type' => 'property', 'category' => 'Ufficio']);
        $note = $this->client->interactions()->create(['event_at' => now(), 'channel' => 'note', 'subject' => 'Colloquio', 'notes' => 'Contatto avvenuto', 'created_by' => $this->operator->id]);
        $vehicle = $this->client->vehicles()->create(['plate' => 'AB123CD']);
        $other = Client::factory()->create(['client_type' => 'person', 'commercial_user_id' => $this->operator->id]);
        $relation = ClientRelationship::create(['client_id' => $this->client->id, 'related_client_id' => $other->id, 'relationship_type' => array_key_first(ClientRelationship::TYPES)]);
        $household = ClientHousehold::create(['name' => 'Famiglia Riva']);
        $household->members()->attach([$this->client->id, $other->id]);
        $paths = [
            route('clients.records.destroy', [$this->client, 'contacts', $contact->id]),
            route('clients.records.destroy', [$this->client, 'assets', $asset->id]),
            route('clients.records.destroy', [$this->client, 'interactions', $note->id]),
            route('clients.vehicles.destroy', [$this->client, $vehicle]),
            route('clients.relationships.destroy', [$this->client, $relation]),
            route('clients.households.destroy', [$this->client, $household]),
        ];
        foreach ([$this->operator, User::factory()->create(['role' => UserRole::Operator, 'status' => 'active'])] as $employee) {
            $this->actingAs($employee);
            foreach ($paths as $path) {
                $this->delete($path)->assertForbidden();
            }
            $this->get(route('clients.show', [$this->client, 'tab' => 'network']))
                ->assertOk()->assertSee('Modifica relazione')->assertDontSee('Rimuovi relazione')->assertDontSee('Rimuovi nucleo')->assertDontSee('Elimina registrazione');
            $this->get(route('clients.vehicles.edit', [$this->client, $vehicle]))->assertOk()->assertDontSee('Elimina veicolo');
        }
        foreach ([$contact, $asset, $note, $vehicle, $relation, $household] as $record) {
            $this->assertNotNull($record->fresh());
        }
        $this->actingAs($this->admin);
        foreach ($paths as $path) {
            $this->delete($path)->assertRedirect()->assertSessionHasNoErrors();
        }
        foreach ([$contact, $asset, $note, $vehicle, $relation, $household] as $record) {
            $this->assertNull($record->fresh());
        }
    }

    public function test_admin_cannot_delete_records_through_an_unrelated_client(): void
    {
        $other = Client::factory()->create();
        $contact = $this->client->contacts()->create(['name' => 'Marco Riva']);
        $vehicle = $this->client->vehicles()->create(['plate' => 'AB123CD']);
        $this->delete(route('clients.records.destroy', [$other, 'contacts', $contact->id]))->assertNotFound();
        $this->delete(route('clients.vehicles.destroy', [$other, $vehicle]))->assertNotFound();
        $this->assertNotNull($contact->fresh());
        $this->assertNotNull($vehicle->fresh());
    }

    public function test_two_edits_in_the_same_second_cannot_overwrite_each_other_and_input_is_retained(): void
    {
        $stale = $this->data();
        $timestamp = $this->policy->fresh()->updated_at;
        $this->actingAs($this->operator)->patch($this->policyPath('update'), array_replace($stale, ['notes' => 'Aggiornamento più recente']))->assertSessionHasNoErrors();
        $this->assertEquals($timestamp, $this->policy->fresh()->updated_at);
        $this->actingAs($this->admin)->from($this->policyPath('edit'))
            ->patch($this->policyPath('update'), array_replace($stale, ['notes' => 'Testo ancora da confrontare']))
            ->assertRedirect($this->policyPath('edit'))->assertSessionHasErrors('policy_revision')
            ->assertSessionHasInput('notes', 'Testo ancora da confrontare')
            ->assertSessionHasInput('policy_revision', $stale['policy_revision']);
        $this->assertSame('Aggiornamento più recente', $this->policy->fresh()->notes);
        $this->assertTrue(session('errors')->has('policy_revision'));
        $this->patch($this->policyPath('update'), array_replace($this->data(), ['notes' => 'Confrontato e corretto']))->assertSessionHasNoErrors();
        $this->assertSame('Confrontato e corretto', $this->policy->fresh()->notes);
    }

    public function test_missing_malformed_or_foreign_revision_cannot_bypass_concurrency_check(): void
    {
        $data = $this->data();
        unset($data['policy_revision']);
        $this->patch($this->policyPath('update'), $data)->assertSessionHasErrors('policy_revision');
        $this->patch($this->policyPath('update'), $data + ['policy_revision' => 'invalid'])->assertSessionHasErrors('policy_revision');
        $this->patch($this->policyPath('update'), $data + ['policy_revision' => str_repeat('0', 64)])->assertSessionHasErrors('policy_revision');
        $this->assertSame('Nota iniziale', $this->policy->fresh()->notes);
    }

    public function test_audit_author_survives_rejected_deletion_and_admin_can_disable_the_user(): void
    {
        $this->client->update(['commercial_user_id' => null]);
        $this->actingAs($this->operator);
        app(\App\Services\InsuranceLedger::class)->log($this->policy, 'collection_recorded', 'Registrazione di collaudo');
        $event = AuditLog::where('action', 'collection_recorded')->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->delete(route('users.destroy', $this->operator))->assertRedirect()->assertSessionHas('error');
        $this->assertSame($this->operator->id, $event->fresh()->user_id);
        $this->assertSame($this->operator->name, $event->fresh()->user->name);
        $this->post(route('users.toggle-status', $this->operator))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('inactive', $this->operator->fresh()->status);
        $this->assertSame($this->operator->name, $event->fresh()->user->name);
    }

    public function test_profile_deletion_with_history_keeps_the_session_and_hides_delete_control(): void
    {
        $this->actingAs($this->operator);
        $this->get(route('profile.edit'))->assertOk()->assertDontSee('Conferma ed Elimina');
        $this->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();
        $this->assertAuthenticatedAs($this->operator);
        $this->assertNotNull($this->operator->fresh());
    }

    public function test_unlogged_authorship_alone_also_protects_the_account(): void
    {
        $author = User::factory()->create(['role' => UserRole::Operator]);
        $this->client->interactions()->create(['event_at' => now(), 'channel' => 'note', 'subject' => 'Annotazione', 'notes' => 'Attività registrata', 'created_by' => $author->id]);
        $this->assertSame(0, AuditLog::where('user_id', $author->id)->count());
        $this->delete(route('users.destroy', $author))->assertRedirect()->assertSessionHas('error');
        $this->assertSame($author->id, $this->client->interactions()->sole()->created_by);
    }

    public function test_model_delete_cannot_bypass_history_protection(): void
    {
        $this->expectException(ValidationException::class);
        $this->operator->delete();
    }

    public function test_admin_can_still_delete_an_unused_account(): void
    {
        $unused = User::factory()->create(['role' => UserRole::Operator]);
        $this->delete(route('users.destroy', $unused))->assertRedirect(route('users.index'))->assertSessionHasNoErrors();
        $this->assertNull($unused->fresh());
    }

}

