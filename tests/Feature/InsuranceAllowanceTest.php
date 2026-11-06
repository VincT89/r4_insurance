<?php

namespace Tests\Feature;

use App\Domain\Core\Queries\ClientDeadlineQuery;
use App\Enums\UserRole;
use App\Models\{AuditLog, Client, InsuranceAllowance, InsuranceAllowanceRecovery, InsurancePolicy, InsuranceTitle, SupplierAgency, SupplierCompany, User};
use App\Services\{DeadlineReminders, InsuranceLedger};
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InsuranceAllowanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
    private InsurancePolicy $policy;
    private SupplierCompany $company;
    private SupplierAgency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,11,4)->setTime(12,0));
        Storage::fake('attachments');
        $this->admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->actingAs($this->admin);
        $this->client=Client::factory()->create();
        $this->company=SupplierCompany::create(['name'=>'Compagnia dimostrativa abbuoni','status'=>'active']);
        $this->agency=SupplierAgency::create(['name'=>'Agenzia dimostrativa abbuoni','status'=>'active']);
        $this->policy=$this->client->policies()->create(['number'=>'DEMO-ABBUONI','category'=>'Abitazione','company'=>$this->company->name,'supplier_company_id'=>$this->company->id,'supplier_agency_id'=>$this->agency->id,'starts_on'=>'2026-11-04','expires_on'=>'2027-11-04','status'=>'active','premium'=>'100.00','payment_frequency'=>'Annuale','payment_method'=>'Bonifico a Intermediario']);
    }

    private function title(array $values=[]): InsuranceTitle
    {
        return InsuranceTitle::create(array_replace(['insurance_policy_id'=>$this->policy->id,'kind'=>'emission','coverage_from'=>'2026-11-04','coverage_to'=>'2027-11-04','due_on'=>'2026-11-03','amount_cents'=>10000,'payment_method'=>'Bonifico a Intermediario','payment_frequency'=>'Annuale'],$values));
    }

    private function allowance(InsuranceTitle $title, int $cents=1000, array $values=[]): array
    {
        return array_replace(['allowance_amount'=>Money::decimal($cents),'allowance_on'=>'2026-11-04','allowance_borne_by'=>'our_agency','allowance_reason'=>'Abbuono concordato con la compagnia','allowance_balance'=>$title->fresh()->balance_cents,'allowance_key'=>(string)Str::uuid()],$values);
    }

    private function pay(InsuranceTitle $title, int $cents): void
    {
        $this->post(route('titles.collect',$title),['received_on'=>'2026-11-04','amount'=>Money::decimal($cents),'payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Str::uuid()])->assertSessionHasNoErrors();
    }

    private function confirm(InsuranceTitle $title, array $extra=[])
    {
        return $this->post(route('titles.allowance-coverage',$title),array_replace(['coverage_action'=>'confirm','coverage_fingerprint'=>$title->fresh()->coverageConfirmationFingerprint(),'coverage_note'=>'Conferma ricevuta dalla compagnia, riferimento DEMO-100'],$extra));
    }

    private function agencyAllowance(int $cents=1000, array $extra=[]): InsuranceAllowance
    {
        $title=$this->title();
        $this->post(route('titles.allowances',$title),$this->allowance($title,$cents,array_replace([
            'allowance_borne_by'=>'agency','allowance_agency_id'=>$this->agency->id,
        ],$extra)))->assertSessionHasNoErrors();
        return $title->allowances()->sole();
    }

    private function recovery(InsuranceAllowance $allowance, int $cents=400, array $extra=[]): array
    {
        return array_replace(['recovery_amount'=>Money::decimal($cents),'recovery_on'=>'2026-11-04','recovery_method'=>'transfer',
            'recovery_reference'=>'Rientro dimostrativo','recovery_key'=>(string)Str::uuid(),'recovery_fingerprint'=>$allowance->fresh()->recoveryFingerprint()],$extra);
    }

    public function test_operator_records_partial_and_final_agency_recoveries_without_changing_client_cash_or_coverage(): void
    {
        $allowance=$this->agencyAllowance();
        $title=$allowance->title;
        $this->pay($title,9000);
        $this->confirm($title)->assertSessionHasNoErrors();
        $original=$title->fresh()->only(['amount_cents','allowance_cents','collected_cents','settlement_revision','coverage_confirmation_fingerprint']);
        $this->policy->forceFill(['work_closed_at'=>now(),'work_closed_by'=>$this->admin->id])->save();
        $operator=User::factory()->create(['name'=>'Operatore recuperi dimostrativo','role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($operator);
        $this->post(route('allowances.recoveries.store',$allowance),$this->recovery($allowance,400,['created_by'=>$this->admin->id]))->assertSessionHasNoErrors()->assertRedirect(route('allowances.show',$allowance));
        $this->assertSame(400,$allowance->fresh()->recovered_cents);
        $this->assertSame(600,$allowance->fresh()->recoverable_cents);
        $this->assertSame('partial',$allowance->fresh()->recovery_state);
        $this->assertSame($operator->id,$allowance->recoveries()->sole()->created_by);
        $this->post(route('allowances.recoveries.store',$allowance),$this->recovery($allowance,600,['recovery_method'=>'offset']))->assertSessionHasNoErrors();
        $this->assertSame('recovered',$allowance->fresh()->recovery_state);
        $this->assertSame(0,$allowance->fresh()->recoverable_cents);
        $this->assertSame($original,$title->fresh()->only(array_keys($original)));
        $this->assertTrue($title->fresh()->coverage_confirmation_valid);
        $this->assertSame('2027-11-04',$this->policy->fresh()->coverage_until->toDateString());
        $this->assertDatabaseCount('insurance_collections',1);
        $this->get(route('collections.index'))->assertViewHas('totals',fn($row)=>(int)$row->receipts===9000 && (int)$row->refunds===0);
        $this->get(route('allowances.show',$allowance))->assertOk()->assertSee('Recuperato')->assertSee('Compensazione')->assertDontSee('Registra rientro');
        $this->actingAs($this->admin)->get(route('audit-logs.index',['action'=>'allowances','user_id'=>$operator->id]))->assertOk()->assertViewHas('logs',fn($rows)=>$rows->total()===2)->assertSee('Operatore recuperi dimostrativo');
        $this->assertTrue($operator->hasRetainedHistory());
    }

    public function test_recovery_requests_are_idempotent_and_stale_versions_are_rejected_even_after_a_reversal(): void
    {
        $allowance=$this->agencyAllowance();
        $url=route('allowances.recoveries.store',$allowance);
        $data=$this->recovery($allowance);
        $this->post($url,$data)->assertSessionHasNoErrors();
        $this->post($url,$data)->assertSessionHasNoErrors();
        $this->post($url,array_replace($data,['recovery_amount'=>'5.00']))->assertSessionHasErrors('recovery_amount');
        $this->post($url,array_replace($data,['recovery_key'=>(string)Str::uuid()]))->assertSessionHasErrors('recovery_amount');
        $recovery=$allowance->recoveries()->sole();
        $this->post(route('allowances.recoveries.void',[$allowance,$recovery]),['recovery_void_reason'=>'Rientro duplicato nella comunicazione dimostrativa'])->assertSessionHasNoErrors();
        $this->post($url,$data)->assertSessionHasNoErrors();
        $this->assertSame(0,$allowance->fresh()->recovered_cents);
        $this->post($url,array_replace($data,['recovery_key'=>(string)Str::uuid()]))->assertSessionHasErrors('recovery_amount');
        $this->post($url,$this->recovery($allowance,1000))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('insurance_allowance_recoveries',2);
        $this->assertSame(1000,$allowance->fresh()->recovered_cents);
        $this->assertSame(2,AuditLog::where('action','allowance_recovery_recorded')->count());
    }

    public function test_recovery_reversals_reopen_only_the_agency_credit_and_protect_the_allowance_history(): void
    {
        $allowance=$this->agencyAllowance();
        $title=$allowance->title;
        $this->post(route('allowances.recoveries.store',$allowance),$this->recovery($allowance,1000,['recovery_notes'=>'<script>alert("dimostrativo")</script>']))->assertSessionHasNoErrors();
        $recovery=$allowance->recoveries()->sole();
        $this->post(route('allowances.void',$allowance),['allowance_void_reason'=>'Tentativo di storno con rientro attivo'])->assertSessionHasErrors('allowance_amount');
        $this->assertNull($allowance->fresh()->voided_at);
        $this->agency->update(['name'=>'Agenzia rinominata dimostrativa','status'=>'inactive']);
        $this->get(route('allowances.show',$allowance))->assertOk()->assertSee('Agenzia dimostrativa abbuoni')->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert("dimostrativo")</script>',false);
        $before=$title->fresh()->getRawOriginal();
        $url=route('allowances.recoveries.void',[$allowance,$recovery]);
        $this->post($url,['recovery_void_reason'=>'Contabile dimostrativa attribuita per errore'])->assertSessionHasNoErrors();
        $this->post($url,['recovery_void_reason'=>'Richiesta ripetuta dello stesso storno'])->assertSessionHasNoErrors();
        $this->assertSame('open',$allowance->fresh()->recovery_state);
        $this->assertSame(1000,$allowance->fresh()->recoverable_cents);
        $this->assertSame($before,$title->fresh()->getRawOriginal());
        $this->assertSame(1,AuditLog::where('action','allowance_recovery_voided')->count());
        $this->assertNotNull($recovery->fresh()->voided_at);
        $this->post(route('allowances.void',$allowance),['allowance_void_reason'=>'Abbuono dimostrativo concordato per errore'])->assertSessionHasNoErrors();
        $this->assertSame(0,$allowance->fresh()->recoverable_cents);
        $this->assertDatabaseCount('insurance_allowance_recoveries',1);
        $this->assertDatabaseCount('insurance_collections',0);
    }

    public function test_recovery_validation_and_parent_scopes_prevent_invalid_or_unrelated_movements(): void
    {
        $allowance=$this->agencyAllowance();
        $url=route('allowances.recoveries.store',$allowance);
        foreach ([['recovery_amount'=>'0'],['recovery_amount'=>'-1'],['recovery_amount'=>'10.01'],['recovery_amount'=>'1.001'],
            ['recovery_on'=>'2026-11-05'],['recovery_method'=>'unknown'],['recovery_key'=>'invalid'],['recovery_fingerprint'=>str_repeat('0',64)]] as $extra) {
            $this->post($url,$this->recovery($allowance,400,$extra))->assertSessionHasErrors();
        }
        foreach (['company','our_agency'] as $bearer) {
            $title=$this->title();
            $this->post(route('titles.allowances',$title),$this->allowance($title,1000,['allowance_borne_by'=>$bearer]))->assertSessionHasNoErrors();
            $other=$title->allowances()->sole();
            $this->post(route('allowances.recoveries.store',$other),$this->recovery($other))->assertSessionHasErrors('recovery_amount');
        }
        $this->assertDatabaseCount('insurance_allowance_recoveries',0);
        $data=$this->recovery($allowance);
        $this->post($url,$data)->assertSessionHasNoErrors();
        $other=$this->agencyAllowance();
        $this->post(route('allowances.recoveries.store',$other),array_replace($this->recovery($other),['recovery_key'=>$data['recovery_key']]))->assertSessionHasErrors('recovery_amount');
        $this->post(route('allowances.recoveries.void',[$other,$allowance->recoveries()->sole()]),['recovery_void_reason'=>'Tentativo su abbuono diverso'])->assertNotFound();
        $this->post(route('allowances.void',$other),['allowance_void_reason'=>'Abbuono annullato dimostrativo'])->assertSessionHasNoErrors();
        $this->post(route('allowances.recoveries.store',$other),$this->recovery($other))->assertSessionHasErrors('recovery_amount');
        $this->client->forceFill(['merged_into_id'=>Client::factory()->create()->id])->save();
        $this->post($url,$this->recovery($allowance))->assertStatus(409);
        $inactive=User::factory()->create(['role'=>UserRole::Operator,'status'=>'inactive']);
        $this->actingAs($inactive)->post($url,$this->recovery($allowance))->assertRedirect(route('login'));
        $this->assertDatabaseCount('insurance_allowance_recoveries',1);
    }

    public function test_recovery_reports_include_old_open_credits_all_pages_and_only_active_movements(): void
    {
        $first=$this->agencyAllowance(100,['allowance_on'=>'2026-11-03']);
        for ($i=1;$i<26;$i++) $this->agencyAllowance(100,['allowance_on'=>'2026-11-03']);
        $this->post(route('allowances.recoveries.store',$first),$this->recovery($first,40))->assertSessionHasNoErrors();
        $otherAgency=SupplierAgency::create(['name'=>'Altra agenzia recuperi dimostrativa','status'=>'active']);
        $settled=$this->agencyAllowance(500,['allowance_on'=>'2026-11-03','allowance_agency_id'=>$otherAgency->id]);
        $this->post(route('allowances.recoveries.store',$settled),$this->recovery($settled,500))->assertSessionHasNoErrors();
        $voided=$this->agencyAllowance(700,['allowance_on'=>'2026-11-03']);
        $this->post(route('allowances.void',$voided),['allowance_void_reason'=>'Abbuono dimostrativo stornato'])->assertSessionHasNoErrors();
        $params=['month'=>'','borne_by'=>'agency'];
        $this->get(route('allowances.index',$params))->assertOk()->assertViewHas('totalCents',3100)->assertViewHas('recoveredCents',540)->assertViewHas('outstandingCents',2560)->assertViewHas('allowances',fn($rows)=>$rows->total()===28 && $rows->count()===25);
        $this->get(route('allowances.index',$params+['bearer_agency'=>$this->agency->id]))->assertViewHas('totalCents',2600)->assertViewHas('outstandingCents',2560);
        $this->get(route('allowances.index',$params+['recovery_state'=>'open']))->assertViewHas('allowances',fn($rows)=>$rows->total()===26)->assertViewHas('outstandingCents',2560);
        $this->get(route('allowances.index',$params+['recovery_state'=>'partial']))->assertViewHas('allowances',fn($rows)=>$rows->total()===1)->assertViewHas('outstandingCents',60);
        $this->get(route('allowances.index',$params+['recovery_state'=>'recovered']))->assertViewHas('outstandingCents',0)->assertViewHas('recoveredCents',500);
        $this->get(route('allowances.index'))->assertViewHas('totalCents',0)->assertSee(route('allowances.index',['recovery_state'=>'open','month'=>'']));
        $this->get(route('allowances.index',$params+['_table_all'=>1]),['X-Table-Export'=>'1'])->assertViewHas('allowances',fn($rows)=>$rows->count()===28)->assertSee('data-allowance-outstanding-cents="60"',false);
        $this->get(route('allowances.index',['recovery_state'=>'unknown']))->assertSessionHasErrors('recovery_state');
    }

    public function test_operator_records_allowances_with_each_bearer_after_work_closure_and_history_is_visible_to_admin(): void
    {
        $operator=User::factory()->create(['name'=>'Operatore abbuoni demo','role'=>UserRole::Operator,'status'=>'active']);
        $this->policy->forceFill(['work_closed_at'=>now()->subDays(2),'work_closed_by'=>$this->admin->id])->save();
        $this->actingAs($operator);
        foreach(['company','our_agency','agency'] as $bearer) {
            $title=$this->title();
            $values=['allowance_borne_by'=>$bearer,'created_by'=>$this->admin->id];
            if($bearer==='agency')$values['allowance_agency_id']=$this->agency->id;
            $this->post(route('titles.allowances',$title),$this->allowance($title,1000,$values))->assertSessionHasNoErrors()->assertRedirect(route('titles.show',$title));
            $row=$title->allowances()->sole();
            $this->assertSame($operator->id,$row->created_by);
            $this->assertSame($bearer,$row->borne_by);
            $this->assertSame(1000,$title->fresh()->allowance_cents);
            $this->assertSame(9000,$title->fresh()->balance_cents);
        }
        $this->assertDatabaseCount('insurance_collections',0);
        $this->assertSame(3,AuditLog::where('action','allowance_recorded')->where('user_id',$operator->id)->count());
        $this->actingAs($this->admin)->get(route('audit-logs.index',['action'=>'allowances']))->assertOk()->assertSee('Operatore abbuoni demo')->assertSee('Abbuoni')->assertViewHas('logs',fn($rows)=>$rows->total()===3);
        $this->get(route('allowances.index'))->assertOk()->assertViewHas('totalCents',3000);
    }

    public function test_discount_and_cash_settle_the_title_but_require_separate_coverage_confirmation(): void
    {
        $title=$this->title();
        $data=$this->allowance($title);
        $this->post(route('policies.allowances',$this->policy),$data+['allowance_title_id'=>$title->id])->assertSessionHasNoErrors();
        $this->assertNull($this->policy->fresh()->coverage_until);
        $this->get(route('clients.policies.show',[$this->client,$this->policy,'tab'=>'payments']))->assertOk()->assertSee('Registra abbuono')->assertSee('90.00',false)->assertSee('Abbuoni: 10,00 €');
        $this->confirm($title)->assertSessionHasErrors('allowance_amount');
        $this->pay($title,9000);
        $this->assertSame(0,$title->fresh()->balance_cents);
        $this->assertSame('Saldato con abbuono',$title->fresh()->state_label);
        $this->assertSame('paid',$this->policy->fresh()->payment_status);
        $this->assertNull($this->policy->fresh()->coverage_until);
        $this->get(route('clients.policies.show',[$this->client,$this->policy,'tab'=>'payments']))->assertOk()->assertSee('Copertura da confermare.')->assertSee('Conferma copertura');
        $this->get(route('collections.index'))->assertViewHas('totals',fn($row)=>(int)$row->receipts===9000 && (int)$row->refunds===0);
        $this->confirm($title)->assertSessionHasNoErrors();
        $this->assertSame('2027-11-04',$this->policy->fresh()->coverage_until->toDateString());
        $this->assertTrue($title->fresh()->coverage_confirmation_valid);
        $this->assertSame($this->admin->id,$title->fresh()->coverage_confirmed_by);
        $this->confirm($title)->assertSessionHasNoErrors();
        $this->assertSame(1,AuditLog::where('action','allowance_coverage_confirmed')->count());
    }

    public function test_full_allowance_closes_outstanding_lists_and_reminders_without_creating_a_cash_receipt(): void
    {
        $title=$this->title();
        $reminders=app(DeadlineReminders::class);
        $reminders->forUser($this->admin);
        $pending=fn()=>tap($this->admin->notifications()->getQuery(),fn($q)=>$reminders->pending($q,$this->admin))->count();
        $this->assertSame(1,$pending());
        $this->post(route('titles.allowances',$title),$this->allowance($title,10000))->assertSessionHasNoErrors();
        $this->assertSame(0,$pending());
        $this->assertDatabaseCount('insurance_collections',0);
        $this->assertNull($this->policy->fresh()->coverage_until);
        $this->get(route('titles.index',['state'=>'open']))->assertViewHas('titles',fn($rows)=>$rows->isEmpty());
        $this->get(route('titles.index',['state'=>'paid']))->assertViewHas('titles',fn($rows)=>$rows->total()===1);
        $this->get(route('deadlines.index',['kind'=>'title','period'=>'all']))->assertOk()->assertViewHas('deadlines',fn($rows)=>$rows->isEmpty());
        $this->assertTrue(app(ClientDeadlineQuery::class)->forClient($this->client,[])->where('kind','Titolo')->isEmpty());
        $this->get(route('dashboard'))->assertOk()->assertViewHas('paymentSummary',fn($rows)=>array_sum(array_column($rows,'count'))===0);
        $this->get(route('collections.index'))->assertOk()->assertViewHas('totals',fn($row)=>(int)$row->total===0);
        $this->post(route('allowances.void',$title->allowances()->sole()),['allowance_void_reason'=>'Abbuono registrato per errore'])->assertSessionHasNoErrors();
        $this->assertSame(1,$pending());
    }

    public function test_repeated_requests_are_idempotent_and_changed_payloads_or_stale_balances_are_rejected(): void
    {
        $title=$this->title();
        $data=$this->allowance($title);
        $url=route('titles.allowances',$title);
        $this->post($url,$data)->assertSessionHasNoErrors();
        $this->post($url,$data)->assertSessionHasNoErrors();
        $this->post($url,array_replace($data,['allowance_amount'=>'20.00']))->assertSessionHasErrors('allowance_amount');
        $this->post($url,array_replace($data,['allowance_key'=>(string)Str::uuid()]))->assertSessionHasErrors('allowance_amount');
        $this->assertDatabaseCount('insurance_allowances',1);
        $this->assertSame(1000,$title->fresh()->allowance_cents);
    }

    public function test_amount_dates_bearer_and_agency_are_validated_without_mutating_the_ledger(): void
    {
        $title=$this->title();
        $inactive=SupplierAgency::create(['name'=>'Agenzia inattiva demo','status'=>'inactive']);
        $cases=[
            ['allowance_amount'=>'0'],['allowance_amount'=>'-1'],['allowance_amount'=>'100.01'],['allowance_amount'=>'1.001'],
            ['allowance_on'=>'2026-11-05'],['allowance_borne_by'=>''],['allowance_borne_by'=>'unknown'],
            ['allowance_borne_by'=>'agency'],['allowance_borne_by'=>'agency','allowance_agency_id'=>$inactive->id],
            ['allowance_borne_by'=>'company','allowance_agency_id'=>$this->agency->id],['allowance_reason'=>''],
        ];
        foreach($cases as $values)$this->post(route('titles.allowances',$title),$this->allowance($title,1000,$values))->assertSessionHasErrors();
        $this->assertDatabaseCount('insurance_allowances',0);
        $this->assertSame(10000,$title->fresh()->balance_cents);
    }

    public function test_refund_cancelled_paid_and_foreign_policy_titles_cannot_receive_allowances(): void
    {
        foreach([['direction'=>'refund'],['cancelled_at'=>now()],['collected_cents'=>10000]] as $values) {
            $title=$this->title($values);
            $this->post(route('titles.allowances',$title),$this->allowance($title,1000,['allowance_balance'=>10000]))->assertSessionHasErrors('allowance_amount');
        }
        $foreign=$this->client->policies()->create(array_replace($this->policy->only(['category','company','starts_on','expires_on','status']),['number'=>'DEMO-ALTRA-POLIZZA']));
        $title=$this->title(['insurance_policy_id'=>$foreign->id]);
        $this->post(route('policies.allowances',$this->policy),$this->allowance($title)+['allowance_title_id'=>$title->id])->assertNotFound();
        $this->assertDatabaseCount('insurance_allowances',0);
    }

    public function test_company_and_bearer_names_are_snapshots_and_report_filters_use_the_original_title(): void
    {
        $title=$this->title();
        $this->post(route('titles.allowances',$title),$this->allowance($title,1000,['allowance_borne_by'=>'company']))->assertSessionHasNoErrors();
        $other=SupplierCompany::create(['name'=>'Compagnia successiva demo','status'=>'active']);
        $this->policy->update(['company'=>$other->name,'supplier_company_id'=>$other->id]);
        $this->company->update(['name'=>'Ragione sociale aggiornata demo']);
        $row=$title->allowances()->sole();
        $this->assertSame('Compagnia dimostrativa abbuoni',$row->bearer_name);
        $this->assertSame($this->company->id,$row->supplier_company_id);
        $this->get(route('allowances.index',['company'=>$this->company->id,'agency'=>$this->agency->id]))->assertOk()->assertViewHas('totalCents',1000)->assertSee('Compagnia dimostrativa abbuoni');
        $this->get(route('allowances.index',['company'=>$other->id]))->assertViewHas('totalCents',0);
    }

    public function test_reversals_preserve_cash_and_invalidate_the_previous_coverage_confirmation(): void
    {
        $title=$this->title();
        $this->post(route('titles.allowances',$title),$this->allowance($title))->assertSessionHasNoErrors();
        $this->pay($title,9000);
        $this->confirm($title)->assertSessionHasNoErrors();
        $oldFingerprint=$title->fresh()->coverageConfirmationFingerprint();
        $row=$title->allowances()->sole();
        $url=route('allowances.void',$row);
        $this->post($url,['allowance_void_reason'=>'Annullamento abbuono comunicato dalla compagnia'])->assertSessionHasNoErrors();
        $this->post($url,['allowance_void_reason'=>'Richiesta ripetuta dello stesso storno'])->assertSessionHasNoErrors();
        $this->assertSame(9000,$title->fresh()->collected_cents);
        $this->assertSame(1000,$title->fresh()->balance_cents);
        $this->assertFalse($title->fresh()->coverage_confirmation_valid);
        $this->assertNull($this->policy->fresh()->coverage_until);
        $this->assertSame(1,AuditLog::where('action','allowance_voided')->count());
        $this->post(route('titles.allowances',$title),$this->allowance($title))->assertSessionHasNoErrors();
        $this->confirm($title,['coverage_fingerprint'=>$oldFingerprint])->assertSessionHasErrors('allowance_amount');
        $this->confirm($title)->assertSessionHasNoErrors();
        $this->post(route('collections.void',$title->collections()->sole()),['reason'=>'Storno del pagamento errato di prova'])->assertSessionHasNoErrors();
        $this->assertSame(9000,$title->fresh()->balance_cents);
        $this->assertSame(1000,$title->fresh()->allowance_cents);
        $this->assertFalse($title->fresh()->coverage_confirmation_valid);
        $this->assertNull($this->policy->fresh()->coverage_until);
    }

    public function test_coverage_confirmation_can_be_revoked_without_changing_cash_or_allowances(): void
    {
        $title=$this->title();
        $this->post(route('titles.allowances',$title),$this->allowance($title,10000))->assertSessionHasNoErrors();
        $this->confirm($title)->assertSessionHasNoErrors();
        $this->confirm($title,['coverage_action'=>'revoke','coverage_note'=>'Conferma errata, nuova verifica richiesta'])->assertSessionHasNoErrors();
        $this->assertNull($title->fresh()->coverage_confirmed_at);
        $this->assertNull($this->policy->fresh()->coverage_until);
        $this->assertSame(0,$title->fresh()->balance_cents);
        $this->assertSame(1,AuditLog::where('action','allowance_coverage_revoked')->count());
        $this->assertDatabaseCount('insurance_collections',0);
    }

    public function test_residual_cancellation_keeps_allowances_and_receipts_and_prevents_destructive_reversal(): void
    {
        $title=$this->title();
        $this->post(route('titles.allowances',$title),$this->allowance($title))->assertSessionHasNoErrors();
        $this->post(route('titles.cancel',$title),['reason'=>'Tentativo di annullamento intero titolo'])->assertSessionHasErrors('ledger');
        app(InsuranceLedger::class)->cancelResidual($title->fresh(),'Sostituzione prima della scadenza, residuo non dovuto');
        $this->assertSame(9000,$title->fresh()->cancelled_residual_cents);
        $this->assertSame(1000,$title->fresh()->allowance_cents);
        $this->assertSame(0,$title->fresh()->balance_cents);
        $this->post(route('allowances.void',$title->allowances()->sole()),['allowance_void_reason'=>'Tentativo di storno dopo annullamento residuo'])->assertSessionHasErrors('allowance_amount');
        $this->assertNull($title->allowances()->sole()->voided_at);
    }

    public function test_reporting_uses_effective_dates_all_pages_bearer_filters_and_excludes_reversals_from_total(): void
    {
        for($i=0;$i<26;$i++) {
            $title=$this->title();
            $this->post(route('titles.allowances',$title),$this->allowance($title,100,['allowance_on'=>'2026-11-03']))->assertSessionHasNoErrors();
        }
        $title=$this->title();
        $this->post(route('titles.allowances',$title),$this->allowance($title,700,['allowance_borne_by'=>'agency','allowance_agency_id'=>$this->agency->id,'allowance_on'=>'2026-11-03']))->assertSessionHasNoErrors();
        $row=$title->allowances()->sole();
        $this->post(route('allowances.void',$row),['allowance_void_reason'=>'Storno di prova del riepilogo'])->assertSessionHasNoErrors();
        $this->get(route('allowances.index'))->assertOk()->assertViewHas('totalCents',0);
        $this->get(route('allowances.index',['from'=>'2026-11-03','to'=>'2026-11-03']))->assertOk()->assertViewHas('totalCents',2600)->assertViewHas('allowances',fn($rows)=>$rows->total()===27 && $rows->count()===25);
        $this->get(route('allowances.index',['month'=>'2026-11','borne_by'=>'our_agency']))->assertViewHas('totalCents',2600);
        $this->get(route('allowances.index',['month'=>'','bearer_agency'=>$this->agency->id]))->assertViewHas('totalCents',0)->assertViewHas('allowances',fn($rows)=>$rows->total()===1);
        $this->get(route('allowances.index',['month'=>'','state'=>'voided']))->assertViewHas('totalCents',0)->assertViewHas('allowances',fn($rows)=>$rows->total()===1);
        $this->get(route('allowances.index',['from'=>'2026-11-04','to'=>'2026-11-03']))->assertSessionHasErrors('to');
        $this->get(route('allowances.index',['state'=>'paid']))->assertSessionHasErrors('state');
        $this->get(route('allowances.index',['policy'=>$this->policy->id,'month'=>'']))->assertOk()->assertSee(route('collections.index',['policy'=>$this->policy->id,'month'=>'']));
        $this->get(route('collections.index',['policy'=>$this->policy->id,'month'=>'']))->assertOk()->assertSee(route('allowances.index',['policy'=>$this->policy->id,'month'=>'']));
    }

    public function test_access_checks_merged_clients_and_escaped_text_protect_financial_history(): void
    {
        $title=$this->title();
        $data=$this->allowance($title,1000,['allowance_reason'=>'<script>alert("demo")</script>']);
        $this->post(route('titles.allowances',$title),$data)->assertSessionHasNoErrors();
        $this->get(route('titles.show',$title))->assertOk()->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert("demo")</script>',false);
        $this->client->forceFill(['merged_into_id'=>Client::factory()->create()->id])->save();
        $this->post(route('titles.allowances',$title),$this->allowance($title))->assertStatus(409);
        $this->get(route('allowances.index'))->assertOk()->assertViewHas('totalCents',0);
        $inactive=User::factory()->create(['role'=>UserRole::Operator,'status'=>'inactive']);
        $this->actingAs($inactive)->post(route('titles.allowances',$title),$this->allowance($title))->assertRedirect(route('login'));
        $this->assertDatabaseCount('insurance_allowances',1);
    }

    public function test_allowances_do_not_reintroduce_partial_cash_payments_and_coverage_keeps_gaps(): void
    {
        $first=$this->title(['coverage_to'=>'2027-05-04']);
        $later=$this->title(['coverage_from'=>'2027-05-04','kind'=>'quietance','due_on'=>'2027-05-04']);
        $this->post(route('titles.allowances',$later),$this->allowance($later,10000))->assertSessionHasNoErrors();
        $this->confirm($later)->assertSessionHasNoErrors();
        $this->assertNull($this->policy->fresh()->coverage_until);
        $this->post(route('titles.allowances',$first),$this->allowance($first))->assertSessionHasNoErrors();
        $this->post(route('titles.collect',$first),['received_on'=>'2026-11-04','amount'=>'80.00','payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Str::uuid()])->assertSessionHasErrors('ledger');
        $this->assertDatabaseCount('insurance_collections',0);
        $this->pay($first,9000);
        $this->assertNull($this->policy->fresh()->coverage_until);
        $this->confirm($first)->assertSessionHasNoErrors();
        $this->assertSame('2027-11-04',$this->policy->fresh()->coverage_until->toDateString());
    }

    public function test_policy_does_not_select_another_title_after_saving_a_payment(): void
    {
        $title=$this->title(['coverage_to'=>'2027-05-04']);
        $next=$this->title(['coverage_from'=>'2027-05-04','kind'=>'quietance','due_on'=>'2027-05-04']);
        $this->post(route('policies.collect',$this->policy),['title_id'=>$title->id,'received_on'=>'2026-11-04','amount'=>'100.00','payment_method'=>'Contanti a Intermediario','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Str::uuid()])->assertSessionHasNoErrors();
        $this->get(route('clients.policies.show',[$this->client,$this->policy,'tab'=>'payments']))->assertOk()
            ->assertViewHas('selectedPaymentTitle',$title->id)
            ->assertViewHas('payableTitles',fn($rows)=>$rows->modelKeys()===[$next->id])
            ->assertSee('x-model="amount" value=""',false);
        $this->assertSame(10000,$next->fresh()->balance_cents);
        session()->forget('recorded_payment');
        $this->get(route('clients.policies.show',[$this->client,$this->policy,'tab'=>'payments']))->assertOk()->assertViewHas('selectedPaymentTitle','')->assertSee('x-model="amount" value=""',false);
    }
}
