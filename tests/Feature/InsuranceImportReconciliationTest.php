<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{InsurancePolicy, InsurancePolicyImport, User};
use App\Services\{InsuranceImportReconciliation, PolicyExcelImporter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Fixtures\PolicyImportFixture;
use Tests\TestCase;

class InsuranceImportReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']));
        return PolicyImportFixture::create(Storage::disk('local')->path('reconciliation-fixture'));
    }

    private function import(): void
    {
        $importer=app(PolicyExcelImporter::class);$importer->apply($importer->plan()['batch_hash']);
    }

    private function row(array $report,string $number): array { return $report['rows']->firstWhere('number',$number); }

    private function title(InsurancePolicy $policy)
    {
        return $policy->titles()->create(['kind'=>'quietance','direction'=>'receipt','coverage_from'=>'2020-01-01','coverage_to'=>'2020-02-01','due_on'=>'2020-01-01',
            'amount_cents'=>6004,'collected_cents'=>0,'payment_frequency'=>'Mensile','payment_method'=>'Bonifico a Intermediario']);
    }

    public function test_reconciliation_matches_contract_and_rate_without_changing_imported_records(): void
    {
        $this->fixture();$this->import();
        $policy=InsurancePolicy::where('number','000012')->sole();$this->title($policy);
        $before=InsurancePolicy::orderBy('id')->get()->map->getAttributes()->all();
        $report=app(InsuranceImportReconciliation::class)->report();$row=$this->row($report,'000012');
        $this->assertSame('matching',$row['state']);$this->assertSame([],$row['differences']);$this->assertSame([],$row['rate_issues']);
        $this->assertSame('rates',$this->row($report,'000013')['state']);
        $this->assertSame(6,$report['counts']['held']);
        $this->assertSame(144100,$report['source_premium_cents']);$this->assertSame(144100,$report['current_premium_cents']);
        $this->assertSame($before,InsurancePolicy::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('insurance_collections',0);
    }

    public function test_operator_changes_are_reported_without_being_overwritten(): void
    {
        $this->fixture();$this->import();$policy=InsurancePolicy::where('number','000012')->sole();
        $policy->update(['premium'=>'750.00','expires_on'=>'2021-12-31']);
        $report=app(InsuranceImportReconciliation::class)->report();$row=$this->row($report,'000012');
        $this->assertSame('changed',$row['state']);
        $this->assertSame(['Scadenza','Premio del contratto'],array_column($row['differences'],'field'));
        $this->assertSame(2950,$report['current_premium_cents']-$report['source_premium_cents']);
        $this->assertSame('750.00',$policy->fresh()->premium);
        $this->get(route('insurance-import.index',['section'=>'reconciliation','comparison'=>'changed','search'=>'000012']))->assertOk()->assertSee('750,00')->assertSee('720,50')->assertViewHas('comparisonRows',fn($rows)=>$rows->total()===1);
    }

    public function test_missing_or_duplicated_links_do_not_create_a_false_matching_total(): void
    {
        $this->fixture();$this->import();$policy=InsurancePolicy::where('number','000012')->sole();
        InsurancePolicyImport::where('number','000013')->update(['insurance_policy_id'=>$policy->id]);
        $report=app(InsuranceImportReconciliation::class)->report();
        $this->assertSame(2,$report['counts']['missing']);$this->assertSame(0,$report['compared_premiums']);
        $this->assertSame(0,$report['source_premium_cents']);$this->assertSame(0,$report['current_premium_cents']);
    }

    public function test_payment_flags_are_not_imported_as_cash_and_voided_cash_is_excluded(): void
    {
        $fixture=$this->fixture();$fixture['rows'][0]['values']['Pag. Da Consulente']='OK';
        PolicyImportFixture::write($fixture['directory'],['source-rows.jsonl'=>$fixture['rows']]);$this->import();
        $policy=InsurancePolicy::where('number','000012')->sole();$title=$this->title($policy);
        $payment=$title->collections()->create(['amount_cents'=>6004,'received_on'=>'2020-01-01','payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Str::uuid(),'request_fingerprint'=>str_repeat('a',64)]);
        $row=$this->row(app(InsuranceImportReconciliation::class)->report(),'000012');$this->assertSame(6004,$row['receipt_cents']);
        $this->post(route('collections.void',$payment),['reason'=>'Registrazione storica di prova da stornare.'])->assertSessionHasNoErrors();
        $report=app(InsuranceImportReconciliation::class)->report();$this->assertSame(0,$this->row($report,'000012')['receipt_cents']);
        $this->assertStringContainsString('non identificano un incasso',$report['cash_note']);
        $this->assertDatabaseCount('insurance_collections',1);
    }

    public function test_current_source_changes_and_tampered_files_are_visible(): void
    {
        $fixture=$this->fixture();$this->import();$fixture['policies'][0]['data']['premium']='999.00';
        PolicyImportFixture::write($fixture['directory'],['policies.json'=>$fixture['policies']]);
        $report=app(InsuranceImportReconciliation::class)->report();$this->assertSame('source_changed',$this->row($report,'000012')['state']);
        file_put_contents($fixture['directory'].'/policies.json','[]');
        $this->get(route('insurance-import.index',['section'=>'reconciliation']))->assertStatus(409);
        $this->assertSame('720.50',InsurancePolicy::where('number','000012')->sole()->premium);
    }

    public function test_empty_or_absent_source_values_are_not_counted_as_zero_and_access_is_admin_only(): void
    {
        $fixture=$this->fixture();$fixture['policies'][0]['data']['premium']=null;
        PolicyImportFixture::write($fixture['directory'],['policies.json'=>$fixture['policies']]);$this->import();
        $report=app(InsuranceImportReconciliation::class)->report();
        $this->assertSame(1,$report['unavailable_premiums']);$this->assertSame(1,$report['compared_premiums']);
        $this->assertSame(72050,$report['source_premium_cents']);
        $this->get(route('insurance-import.index',['section'=>'reconciliation']))->assertOk()->assertSee('Riconciliazione')->assertSee('Premi incompleti');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']));
        $this->get(route('insurance-import.index',['section'=>'reconciliation']))->assertForbidden();
    }
}
