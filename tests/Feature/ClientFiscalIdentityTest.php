<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFiscalIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => UserRole::Operator]));
    }

    public function test_full_and_quick_creation_check_both_identifiers_and_normalize_them(): void
    {
        foreach (['clients.store', 'api.clients.quick-store'] as $route) {
            $this->postJson(route($route), ['name' => 'Cliente dimostrativo', 'vat_number' => '01234567897', 'tax_code' => 'RSSMRA80A01H501A'])->assertUnprocessable()->assertJsonValidationErrors('tax_code');
            $this->postJson(route($route), ['name' => 'Cliente dimostrativo', 'vat_number' => '01234567890', 'tax_code' => 'RSSMRA80A01H501U'])->assertUnprocessable()->assertJsonValidationErrors('vat_number');
            $this->postJson(route($route), ['name' => 'Cliente dimostrativo', 'vat_number' => '00000000000', 'tax_code' => '00000000000'])->assertUnprocessable()->assertJsonValidationErrors(['vat_number', 'tax_code']);
        }
        $this->assertDatabaseCount('clients', 0);
        $this->post(route('clients.store'), ['name' => 'Cliente dimostrativo', 'tax_code' => ' rss mra80a01h501u ', 'vat_number' => 'it 01234567897'])->assertSessionHasNoErrors();
        $client = Client::sole();
        $this->assertSame('RSSMRA80A01H501U', $client->tax_code);
        $this->assertSame('01234567897', $client->vat_number);
        $this->postJson(route('api.clients.quick-store'), ['name' => 'Duplicato', 'vat_number' => 'IT01234567897'])->assertUnprocessable()->assertJsonValidationErrors('vat_number');
        $this->assertDatabaseCount('clients', 1);
        $this->patch(route('clients.update', $client), ['name' => $client->name, 'status' => 'active', 'tax_code' => $client->tax_code, 'vat_number' => $client->vat_number])->assertSessionHasNoErrors();
        $this->patch(route('clients.update', $client), ['name' => $client->name, 'status' => 'active', 'tax_code' => 'errato', 'vat_number' => $client->vat_number])->assertSessionHasErrors('tax_code');
        $this->assertSame('RSSMRA80A01H501U', $client->fresh()->tax_code);
    }

    public function test_numeric_tax_code_and_omocodia_are_accepted(): void
    {
        $this->postJson(route('api.clients.quick-store'), ['name' => 'Azienda dimostrativa', 'client_type' => 'company', 'tax_code' => '01234567897', 'vat_number' => '01234567897'])->assertCreated();
        $this->postJson(route('api.clients.quick-store'), ['name' => 'Persona dimostrativa', 'tax_code' => 'RSSMRA80A01H50MM'])->assertCreated();
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_conflicting_identities_are_explained_and_vat_match_is_listed_first(): void
    {
        $taxClient = Client::factory()->create(['name' => 'Anagrafica CF dimostrativa', 'tax_code' => 'RSSMRA80A01H501U']);
        $vatClient = Client::factory()->create(['name' => 'Anagrafica IVA dimostrativa', 'vat_number' => '01234567897']);
        $values = ['tax_code' => $taxClient->tax_code, 'vat_number' => $vatClient->vat_number];
        $this->postJson(route('api.clients.check-fiscal-identifiers'), $values)->assertOk()
            ->assertJsonPath('status', 'conflict')->assertJsonPath('matches.0.id', $vatClient->id)->assertJsonPath('matches.1.id', $taxClient->id)
            ->assertJsonPath('matches.0.fields.0', 'vat_number')->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('clients.store'), $values + ['name' => 'Tentativo dimostrativo'])->assertSessionHasErrors(['vat_number', 'tax_code'])->assertSessionHasErrors(['vat_number' => 'Partita IVA e codice fiscale rimandano a clienti diversi. Controlla entrambi i dati prima di salvare.']);
        $this->postJson(route('api.clients.quick-store'), $values + ['name' => 'Tentativo dimostrativo'])->assertUnprocessable()->assertJsonValidationErrors(['vat_number', 'tax_code']);
        $this->patch(route('clients.update', $vatClient), $values + ['name' => $vatClient->name, 'status' => 'active'])->assertSessionHasErrors(['vat_number', 'tax_code']);
        $this->assertDatabaseCount('clients', 2);
        $this->assertNull($vatClient->fresh()->tax_code);
        $this->assertNull($taxClient->fresh()->merged_into_id);
    }

    public function test_check_does_not_write_data_and_excludes_self_and_merged_records(): void
    {
        $client = Client::factory()->create(['vat_number' => '01234567897', 'tax_code' => 'RSSMRA80A01H501U']);
        Client::factory()->create(['vat_number' => '12345678903', 'merged_into_id' => $client->id]);
        $this->postJson(route('api.clients.check-fiscal-identifiers'), ['vat_number' => 'IT01234567897'])->assertOk()->assertJsonPath('status', 'duplicate')->assertJsonPath('matches.0.id', $client->id);
        $this->postJson(route('api.clients.check-fiscal-identifiers'), ['vat_number' => '01234567897', 'client_id' => $client->id])->assertOk()->assertJsonPath('status', 'clear')->assertJsonCount(0, 'matches');
        $this->postJson(route('api.clients.check-fiscal-identifiers'), ['vat_number' => '12345678903'])->assertOk()->assertJsonPath('status', 'clear');
        $this->postJson(route('api.clients.check-fiscal-identifiers'), ['tax_code' => 'sbagliato', 'vat_number' => '01234567897'])->assertOk()->assertJsonPath('matches.0.id', $client->id)->assertJsonStructure(['errors' => ['tax_code']]);
        $this->postJson(route('api.clients.check-fiscal-identifiers'), ['tax_code' => ['non valido']])->assertUnprocessable();
        $this->assertDatabaseCount('clients', 2);
        $this->get(route('clients.create'))->assertOk()->assertSee('data-fiscal-check', false);
        $this->get(route('clients.edit', $client))->assertOk()->assertSee('data-current-client="'.$client->id.'"', false);
        auth()->logout();
        $this->postJson(route('api.clients.check-fiscal-identifiers'), ['vat_number' => '01234567897'])->assertUnauthorized();
    }
}
