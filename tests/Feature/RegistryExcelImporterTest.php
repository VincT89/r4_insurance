<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, RegistryImportEntry, SupplierAgency, SupplierCompany, User};
use App\Services\RegistryExcelImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Event, Storage};
use Tests\TestCase;

class RegistryExcelImporterTest extends TestCase
{
    use RefreshDatabase;

    private function candidate(string $key, array $data = [], array $extra = []): array
    {
        return array_replace(['key'=>hash('sha256',$key),'source'=>'clients','source_rows'=>[8], 'fiscal_identifier'=>'RSSMRA80A01H501U','data'=>array_merge(['name'=>'Cliente dimostrativo '.$key,'client_type'=>'person','email'=>null,'phone'=>'0012345678'],$data),'issues'=>[],'conflicting_fields'=>[]],$extra);
    }

    private function stage(array $clients): void
    {
        Storage::fake('local');
        config(['insurance-import.directory'=>Storage::disk('local')->path('registry-test')]);
        $pairs = [];
        foreach (['A DIMOSTRATIVA','B DIMOSTRATIVA'] as $name) $pairs[]=['company'=>$name,'agency'=>'AGENZIA DEMO','source_company'=>$name,'source_agency'=>'Agenzia demo','result'=>'conservata'];
        $files = ['clients.json'=>json_encode($clients), 'summary.json'=>json_encode(['version'=>1,'mode'=>'preview_only','company_agencies'=>$pairs]), 'source-rows.jsonl'=>''];
        $hashes=[];
        foreach($files as $name=>$content) { Storage::disk('local')->put('registry-test/'.$name,$content); $hashes[$name]=hash('sha256',$content); }
        Storage::disk('local')->put('registry-test/manifest.json',json_encode(['files'=>$hashes]));
    }

    public function test_import_is_idempotent_and_keeps_policies_and_existing_clients_unchanged(): void
    {
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin]));
        $existing = Client::factory()->create(['name'=>'Anagrafica precedente','tax_code'=>'PREVIOUS','email'=>'old@example.test']);
        $before = $existing->fresh()->getAttributes();
        $this->stage([$this->candidate('A')]);
        $service = app(RegistryExcelImporter::class);
        $result = $service->apply($service->plan());
        $this->assertSame(['client'=>1,'agency'=>1,'company'=>2],$result['created']);
        $this->assertCount(2,SupplierAgency::sole()->companies);
        $this->assertSame('0012345678',Client::where('name','Cliente dimostrativo A')->sole()->phone);
        $this->assertSame($before,$existing->fresh()->getAttributes());
        $this->assertDatabaseCount('insurance_policies',0);
        $again = $service->apply($service->plan());
        $this->assertSame(['client'=>0,'agency'=>0,'company'=>0],$again['created']);
        $this->assertSame(4,$again['already_imported']);
        $this->assertDatabaseCount('clients',2);
        $this->assertDatabaseCount('supplier_agency_company',2);
    }

    public function test_uncertain_fields_are_preserved_and_conflicting_or_policy_only_clients_are_held(): void
    {
        $this->stage([
            $this->candidate('A',['email'=>'wrong email','province'=>'Provincia dimostrativa','client_type'=>null],['fiscal_identifier'=>'00123456789','issues'=>['distinguere_codice_fiscale_e_partita_iva']]),
            $this->candidate('B',[],['conflicting_fields'=>['Contraente'],'issues'=>['anagrafiche_discordanti_stesso_identificativo']]),
            $this->candidate('C',[],['source'=>'policies','issues'=>['cliente_assente_in_anagrafica']]),
        ]);
        $service=app(RegistryExcelImporter::class); $report=$service->apply($service->plan());
        $this->assertSame(1,$report['created']['client']); $this->assertSame(2,$report['held']['client']);
        $client=Client::sole(); $this->assertNull($client->client_type); $this->assertNull($client->vat_number); $this->assertNull($client->tax_code); $this->assertNull($client->email); $this->assertNull($client->province);
        $this->assertSame('00123456789',$client->importEntry->deferred_data['Cod. Fiscale / P. IVA originale']);
        $this->assertSame('wrong email',$client->importEntry->deferred_data['Email originale']);
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin]))->get(route('clients.show',[$client,'tab'=>'registry']))->assertOk()->assertSee('00123456789');
        $this->get(route('clients.index',['search'=>'00123456789']))->assertOk()->assertSee($client->name);
        $this->get(route('clients.index',['import_review'=>1]))->assertOk()->assertSee($client->name);
    }

    public function test_repeated_email_does_not_merge_clients_and_unchanged_names_with_distinct_fiscal_ids_are_kept(): void
    {
        $this->stage([$this->candidate('A',['name'=>'Omonimo dimostrativo','email'=>'shared@example.test']),$this->candidate('B',['name'=>'Omonimo dimostrativo','email'=>'shared@example.test'],['fiscal_identifier'=>'RSSMRA80A01F205X'])]);
        $service=app(RegistryExcelImporter::class); $report=$service->apply($service->plan());
        $this->assertSame(2,$report['created']['client']); $this->assertDatabaseCount('clients',2);
        foreach(Client::all() as $client) { $this->assertSame('shared@example.test',$client->email); $this->assertContains('email_condivisa',$client->importEntry->issues); }
        $client=Client::first();
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin]))
            ->patch(route('clients.update',$client),['name'=>'Omonimo aggiornato','client_type'=>'person','status'=>'active','email'=>$client->email])->assertSessionHasNoErrors();
        $this->assertSame('Omonimo aggiornato',$client->fresh()->name);
    }

    public function test_existing_fiscal_identity_is_not_duplicated_or_overwritten(): void
    {
        $existing=Client::factory()->create(['tax_code'=>'RSSMRA80A01H501U']);
        $this->stage([$this->candidate('A')]);
        $service=app(RegistryExcelImporter::class);$report=$service->apply($service->plan());
        $this->assertSame(1,$report['held']['client']);$this->assertDatabaseCount('clients',1);
        $this->assertSame($existing->name,$existing->fresh()->name);
    }

    public function test_source_tampering_is_rejected_before_database_writes(): void
    {
        $this->stage([$this->candidate('A')]);
        Storage::disk('local')->put('registry-test/clients.json','[]');
        try { app(RegistryExcelImporter::class)->plan(); $this->fail('Expected integrity failure'); } catch (\RuntimeException $e) { $this->assertStringContainsString('modificata',$e->getMessage()); }
        $this->assertDatabaseCount('clients',0); $this->assertDatabaseCount('registry_import_entries',0);
    }

    public function test_failure_rolls_back_the_entire_batch_including_audit_and_provider_links(): void
    {
        $this->stage([$this->candidate('A')]);
        Event::listen('eloquent.created: '.SupplierAgency::class, function () { throw new \RuntimeException('Injected failure'); });
        try { $service=app(RegistryExcelImporter::class); $service->apply($service->plan()); $this->fail('Expected rollback'); } catch (\RuntimeException $e) { $this->assertSame('Injected failure',$e->getMessage()); }
        foreach(['clients','supplier_companies','supplier_agencies','supplier_agency_company','registry_import_entries','audit_logs'] as $table) $this->assertDatabaseCount($table,0);
    }
}
