<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, RegistryImportEntry, User};
use App\Services\{HeldRegistryResolver, InsuranceImportPreview, RegistryExcelImporter};
use App\Support\ImportFiscalIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class HeldRegistryResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function candidate(string $name, string $identifier, string $source, array $rows, array $extra = []): array
    {
        return array_replace(['key' => hash('sha256', $name.$identifier), 'source' => $source, 'source_rows' => $rows,
            'fiscal_identifier' => $identifier, 'data' => ['name' => $name, 'client_type' => strlen($identifier) === 16 ? 'person' : null],
            'issues' => $source === 'policies' ? ['cliente_assente_in_anagrafica'] : [], 'conflicting_fields' => []], $extra);
    }

    private function prepare(bool $invalid = false): array
    {
        Storage::fake('local');
        config(['insurance-import.directory' => Storage::disk('local')->path('held-test')]);
        $candidates = [
            $this->candidate('Azienda dimostrativa', '01234567897', 'clients', [8]),
            $this->candidate('AZIENDA DIMOSTRATIVA', '1234567897', 'policies', [9]),
            $this->candidate('Persona dimostrativa', $invalid ? 'RSSMRA80A01H501A' : 'RSSMRA80A01H501U', 'policies', [10, 11]),
            $this->candidate('Ente dimostrativo', '00000000000', 'policies', [12]),
            $this->candidate('Coppia dimostrativa', 'RSSMRA80A01F205X', 'clients', [13, 14], ['conflicting_fields' => ['Cellulare'], 'issues' => ['anagrafiche_discordanti_stesso_identificativo']]),
        ];
        $rows = [];
        foreach ($candidates as $candidate) foreach ($candidate['source_rows'] as $number) {
            $values = ['Titolo' => $number === 11 ? 'Dott.' : 'Sig.', 'Contraente' => $candidate['data']['name'], 'Cod. Fiscale / P. IVA' => $candidate['fiscal_identifier']];
            if (in_array($number, [13, 14])) $values += ['Cellulare' => $number === 13 ? '001111' : '002222', 'CAP' => $number === 13 ? '70124' : '70125', 'Operatore' => '<script>unsafe()</script>'];
            $rows[] = json_encode(['source' => $candidate['source'], 'row' => $number, 'values' => $values]);
        }
        $files = ['clients.json' => json_encode($candidates), 'source-rows.jsonl' => implode("\n", $rows),
            'summary.json' => json_encode(['version' => 1, 'mode' => 'preview_only', 'company_agencies' => [], 'sources' => []])];
        $hashes = [];
        foreach ($files as $name => $contents) {
            Storage::disk('local')->put('held-test/'.$name, $contents);
            $hashes[$name] = hash('sha256', $contents);
        }
        Storage::disk('local')->put('held-test/manifest.json', json_encode(['files' => $hashes]));
        $importer = app(RegistryExcelImporter::class);
        $importer->apply($importer->plan());
        $client = Client::sole();
        $operations = [];
        foreach (RegistryImportEntry::where('source', 'policies')->get() as $entry) {
            $link = $entry->source_key === $candidates[1]['key'];
            $operations[] = ['entry_id' => $entry->id, 'source_key' => $entry->source_key, 'payload_hash' => $entry->payload_hash,
                'action' => $link ? 'link' : 'create', 'target_id' => $link ? $client->id : null, 'expected_entry' => $entry->getRawOriginal()];
        }
        return ['version' => 1, 'source_hashes' => $hashes, 'operations' => $operations,
            'expected_clients' => DB::table('clients')->orderBy('id')->get(['id', 'name', 'tax_code', 'vat_number', 'updated_at'])->map(fn ($r) => (array) $r)->all()];
    }

    public function test_resolutions_preserve_originals_users_policies_and_conflicting_rows_and_are_idempotent(): void
    {
        $plan = $this->prepare();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);
        $client = Client::sole();
        $clientBefore = $client->getAttributes();
        $client->policies()->create(['category' => 'RC auto', 'company' => 'Demo', 'number' => 'TEST', 'status' => 'active', 'starts_on' => today(), 'expires_on' => today()->addYear()]);
        $policies = DB::table('insurance_policies')->get()->toJson();
        $userBefore = $admin->fresh()->getAttributes();
        $heldBefore = RegistryImportEntry::where('source_name', 'Coppia dimostrativa')->sole()->getAttributes();
        $resolver = app(HeldRegistryResolver::class);
        $result = $resolver->apply($plan);
        $this->assertSame(1, $result['linked']);
        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['remaining_held']);
        $this->assertSame($clientBefore, $client->fresh()->getAttributes());
        $this->assertSame($userBefore, $admin->fresh()->getAttributes());
        $this->assertSame($policies, DB::table('insurance_policies')->get()->toJson());
        $this->assertSame($heldBefore, RegistryImportEntry::where('source_name', 'Coppia dimostrativa')->sole()->getAttributes());
        $this->assertSame('clients', $client->fresh()->importEntry->source);
        $this->assertCount(1, $client->fresh()->linkedImportEntries);
        $person = Client::where('name', 'Persona dimostrativa')->sole();
        $this->assertSame('RSSMRA80A01H501U', $person->tax_code);
        foreach (['title', 'email', 'phone', 'address', 'birth_date'] as $field) $this->assertNull($person->$field);
        $this->assertContains('titolo_da_verificare', $person->importEntry->issues);
        $entity = Client::where('name', 'Ente dimostrativo')->sole();
        $this->assertNull($entity->client_type);
        $this->assertNull($entity->tax_code);
        $this->assertNull($entity->vat_number);
        $this->assertSame('00000000000', $entity->importEntry->deferred_data['Cod. Fiscale / P. IVA originale']);
        $this->assertSame(3, $resolver->apply($plan)['already_resolved']);
        $importer = app(RegistryExcelImporter::class);
        $again = $importer->apply($importer->plan());
        $this->assertSame(4, $again['already_imported']);
        $this->assertDatabaseCount('clients', 3);
        $this->assertDatabaseHas('registry_import_entries', ['id' => $plan['operations'][0]['entry_id'], 'status' => 'linked', 'entity_id' => $client->id]);
        $this->get(route('clients.show', [$person, 'tab' => 'registry']))->assertOk()->assertSee(route('insurance-import.compare', $person->importEntry), false);
        $this->get(route('clients.show', [$client, 'tab' => 'registry']))->assertOk()->assertSee(route('insurance-import.compare', $client->fresh()->linkedImportEntries->sole()), false);
        $this->get(route('insurance-import.index', ['section' => 'registry', 'result' => 'linked']))->assertOk()->assertSee('Collegato a scheda esistente');
    }

    public function test_invalid_identifier_rolls_back_links_clients_and_audit(): void
    {
        $plan = $this->prepare(true);
        $before = DB::table('registry_import_entries')->orderBy('id')->get()->toJson();
        $audit = DB::table('audit_logs')->count();
        try { app(HeldRegistryResolver::class)->apply($plan); $this->fail('Expected failure'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Identificativo non valido', $e->getMessage()); }
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('audit_logs', $audit);
        $this->assertSame($before, DB::table('registry_import_entries')->orderBy('id')->get()->toJson());
    }

    public function test_changed_clients_abort_resolution_instead_of_using_stale_matches(): void
    {
        $plan = $this->prepare();
        Client::sole()->update(['name' => 'Nome aggiornato dopo il confronto']);
        $this->expectExceptionMessage('Le anagrafiche sono cambiate');
        app(HeldRegistryResolver::class)->apply($plan);
    }

    public function test_changed_source_and_wrong_target_are_rejected(): void
    {
        $plan = $this->prepare();
        $plan['operations'][0]['target_id'] = Client::factory()->create(['name' => 'Altra società'])->id;
        $plan['expected_clients'] = DB::table('clients')->orderBy('id')->get(['id', 'name', 'tax_code', 'vat_number', 'updated_at'])->map(fn ($r) => (array) $r)->all();
        try { app(HeldRegistryResolver::class)->apply($plan); $this->fail('Expected wrong target failure'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('collegamento non soddisfa', $e->getMessage()); }
        $this->assertDatabaseCount('clients', 2);
        Storage::disk('local')->put('held-test/source-rows.jsonl', '{}');
        $this->expectExceptionMessage('Anteprima modificata');
        app(HeldRegistryResolver::class)->apply($plan);
    }

    public function test_comparison_shows_original_rows_escapes_values_and_is_admin_only(): void
    {
        $this->prepare();
        $entry = RegistryImportEntry::where('source_name', 'Coppia dimostrativa')->sole();
        $url = route('insurance-import.compare', $entry);
        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => UserRole::Operator]))->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $response = $this->get($url)->assertOk()->assertSee('Riga 13')->assertSee('Riga 14')->assertSee('001111')->assertSee('002222')
            ->assertSee('70124')->assertSee('70125')->assertDontSee('<script>unsafe()</script>', false)->assertSee('&lt;script&gt;unsafe()', false)
            ->assertHeader('Cache-Control');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('registry_import_entries', ['id' => $entry->id, 'status' => 'held', 'entity_id' => null]);
        Storage::disk('local')->put('held-test/clients.json', '[]');
        $this->get($url)->assertStatus(409);
    }

    public function test_fiscal_validation_checks_numeric_and_personal_identifiers_without_repairing_them(): void
    {
        foreach (['01234567897', 'RSSMRA80A01H501U'] as $value) $this->assertTrue(ImportFiscalIdentifier::valid($value));
        foreach (['1234567897', 'RSSMRA80A01H501A', '', '00000000001', 'RSSMRA80Z01H501U'] as $value) $this->assertFalse(ImportFiscalIdentifier::valid($value));
    }
}
