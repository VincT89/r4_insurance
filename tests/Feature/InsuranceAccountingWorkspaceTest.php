<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsuranceCollection, InsurancePolicy, InsuranceTitle, SupplierAgency, SupplierCompany, User};
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InsuranceAccountingWorkspaceTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Support\HistoricalCollections;

    private User $admin;
    private Client $client;
    private InsurancePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,31)->setTime(12,0));
        Storage::fake('attachments');
        $this->admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->actingAs($this->admin);
        $this->client=Client::factory()->create();
        $company=SupplierCompany::create(['name'=>'Compagnia prova contabilità','status'=>'active']);
        $agency=SupplierAgency::create(['name'=>'Agenzia prova contabilità','status'=>'active']);
        $this->policy=$this->client->policies()->create(['number'=>'TEST-CONT-01','category'=>'Abitazione','company'=>$company->name,'supplier_company_id'=>$company->id,'supplier_agency_id'=>$agency->id,'starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','status'=>'active','premium'=>'1000.00','payment_frequency'=>'Annuale','payment_method'=>'Bonifico a Intermediario']);
    }

    private function title(array $values=[]): InsuranceTitle
    {
        return InsuranceTitle::create(array_replace(['insurance_policy_id'=>$this->policy->id,'kind'=>'quietance','due_on'=>'2026-10-31','coverage_from'=>'2026-01-01','coverage_to'=>'2027-01-01','amount_cents'=>100000,'collected_cents'=>0,'payment_method'=>'Bonifico a Intermediario','payment_frequency'=>'Annuale'],$values));
    }

    private function payment(InsuranceTitle $title,int $amount,array $values=[]): array
    {
        return array_replace(['title_id'=>$title->id,'received_on'=>'2026-10-31','amount'=>Money::decimal($amount),'payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Str::uuid()],$values);
    }

    private function page(): string { return route('clients.policies.show',[$this->client,$this->policy]); }

    public function test_policy_payment_updates_residual_state_and_accounting_once(): void
    {
        $title=$this->title();
        $this->get($this->page())->assertOk()->assertSee('Registra pagamento')->assertSee('Data effettiva del pagamento')->assertSee('name="title_id"',false)->assertViewHas('payableTitles',fn($rows)=>$rows->modelKeys()===[$title->id]);
        $data=$this->payment($title,100000);
        $this->post(route('policies.collect',$this->policy),$data)->assertSessionHasNoErrors()->assertRedirect(route('clients.policies.show',[$this->client,$this->policy,'tab'=>'payments']));
        $this->post(route('policies.collect',$this->policy),$data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('insurance_collections',1);
        $this->assertSame(0,$title->fresh()->balance_cents);
        $this->assertSame('paid',$title->fresh()->state);
        $this->assertSame('paid',$this->policy->fresh()->payment_status);
        $this->get($this->page())->assertOk()->assertViewHas('payableTitles',fn($rows)=>$rows->isEmpty())->assertDontSee('action="'.route('policies.collect',$this->policy).'"',false);
        $this->get(route('titles.index',['state'=>'open']))->assertViewHas('titles',fn($rows)=>$rows->isEmpty());
        $this->get(route('collections.index'))->assertViewHas('totals',fn($row)=>(int)$row->total===100000);
    }

    public function test_policy_payment_rejects_another_contract_overpayment_cancellation_and_reused_request(): void
    {
        $title=$this->title();
        $other=$this->client->policies()->create(array_replace($this->policy->only(['category','company','starts_on','expires_on','status']),['number'=>'TEST-CONT-02']));
        $foreign=$this->title(['insurance_policy_id'=>$other->id]);
        $url=route('policies.collect',$this->policy);
        $this->post($url,$this->payment($foreign,100))->assertNotFound();
        $this->post($url,$this->payment($title,100001))->assertSessionHasErrors('ledger');
        $this->post($url,$this->payment($title,0))->assertSessionHasErrors('ledger');
        $this->post($url,$this->payment($title,100,['received_on'=>'2026-11-01']))->assertSessionHasErrors('received_on');
        $this->post($url,$this->payment($title,100,['payment_method'=>'']))->assertSessionHasErrors('payment_method');
        $this->post($url,$this->payment($title,100,['payment_method'=>'Bonifico a Compagnia','recipient'=>'our_agency']))->assertSessionHasErrors('ledger');
        $cancelled=$this->title(['cancelled_at'=>now()]);
        $this->post($url,$this->payment($cancelled,100))->assertSessionHasErrors('ledger');
        $this->assertDatabaseCount('insurance_collections',0);
        $data=$this->payment($title,100000);
        $this->post($url,$data)->assertSessionHasNoErrors();
        $this->post($url,array_replace($data,['amount'=>'2.00']))->assertSessionHasErrors('ledger');
        $this->assertSame(0,$title->fresh()->balance_cents);
        $this->assertDatabaseCount('insurance_collections',1);
    }

    public function test_operator_records_payment_after_work_closure_and_cannot_spoof_consultant(): void
    {
        $operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->policy->forceFill(['work_closed_at'=>now()->subDays(2),'work_closed_by'=>$this->admin->id])->save();
        $title=$this->title();
        $this->actingAs($operator)->get($this->page())->assertOk()->assertSee('Registra pagamento');
        $this->post(route('policies.collect',$this->policy),$this->payment($title,100000,['consultant_id'=>$this->admin->id]))->assertSessionHasNoErrors();
        $payment=$title->collections()->sole();
        $this->assertSame($operator->id,$payment->consultant_id);
        $this->assertSame($operator->id,$payment->created_by);
        $this->assertNotNull($this->policy->fresh()->work_closed_at);
    }

    public function test_effective_date_drives_today_totals_including_late_entry_and_excluding_voided_payments(): void
    {
        $title=$this->title();
        $url=route('policies.collect',$this->policy);
        $this->post($url,$this->payment($this->title(['amount_cents'=>1000]),1000,['received_on'=>'2026-10-30']))->assertSessionHasNoErrors();
        $dayLedger = route('collections.index', ['policy'=>$this->policy->id, 'from'=>'2026-10-30', 'to'=>'2026-10-30']);
        $this->get($this->page().'?tab=payments')->assertOk()->assertSee(e($dayLedger).'#movimenti', false);
        $this->get($dayLedger)->assertOk()->assertViewHas('collections', fn($rows) => $rows->total() === 1 && $rows->first()->payment_method === 'Bonifico a Intermediario');
        $this->post($url,$this->payment($this->title(['amount_cents'=>2000]),2000))->assertSessionHasNoErrors();
        $this->post($url,$this->payment($this->title(['amount_cents'=>3000]),3000,['payment_method'=>'Bonifico a Compagnia','recipient'=>'company']))->assertSessionHasNoErrors();
        $this->post($url,$this->payment($this->title(['amount_cents'=>4000]),4000))->assertSessionHasNoErrors();
        $voided=InsuranceCollection::latest('id')->first();
        $this->post(route('collections.void',$voided),['reason'=>'Pagamento duplicato di prova'])->assertSessionHasNoErrors();
        $this->get(route('collections.index'))->assertOk()->assertViewHas('filters',['from'=>'2026-10-31','to'=>'2026-10-31'])
            ->assertViewHas('collections',fn($rows)=>$rows->total()===3 && $rows->every(fn($row)=>$row->received_on->toDateString()==='2026-10-31'))
            ->assertViewHas('totals',fn($row)=>(int)$row->total===5000 && (int)$row->ours===2000);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('todayCollections',fn($row)=>(int)$row->total===5000 && (int)$row->ours===2000 && (int)$row->count===2);
        $this->get(route('collections.index',['from'=>'2026-10-30','to'=>'2026-10-30']))->assertViewHas('totals',fn($row)=>(int)$row->total===1000);
        $this->get(route('collections.index',['month'=>'']))->assertViewHas('totals',fn($row)=>(int)$row->total===6000);
        $this->get(route('collections.index',['month'=>'2026-10']))->assertViewHas('totals',fn($row)=>(int)$row->total===6000);
        $this->get(route('collections.index',['from'=>'2026-10-31','to'=>'2026-10-30']))->assertSessionHasErrors('to');
        $this->get(route('collections.index',['state'=>'voided']))->assertViewHas('collections',fn($rows)=>$rows->total()===1)->assertViewHas('totals',fn($row)=>(int)$row->total===0);
    }

    public function test_filtered_totals_cover_every_page_and_keep_company_agency_and_method_filters(): void
    {
        $title=$this->title();
        for($i=0;$i<27;$i++)$title->collections()->create(['received_on'=>'2026-10-31','amount_cents'=>100,'payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Str::uuid(),'request_fingerprint'=>hash('sha256','accounting-'.$i),'created_by'=>$this->admin->id]);
        $hiddenClient=Client::factory()->create(['merged_into_id'=>$this->client->id]);
        $hidden=$hiddenClient->policies()->create(array_replace($this->policy->only(['category','company','starts_on','expires_on','status']),['number'=>'TEST-HIDDEN']));
        $hiddenTitle=$this->title(['insurance_policy_id'=>$hidden->id]);
        $hiddenTitle->collections()->create(['received_on'=>'2026-10-31','amount_cents'=>90000,'payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Str::uuid(),'request_fingerprint'=>hash('sha256','hidden'),'created_by'=>$this->admin->id]);
        $filters=['company'=>$this->policy->supplier_company_id,'agency'=>$this->policy->supplier_agency_id,'payment_method'=>'Bonifico a Intermediario'];
        $this->get(route('collections.index',$filters))->assertOk()->assertViewHas('collections',fn($rows)=>$rows->total()===27 && $rows->count()===25)->assertViewHas('totals',fn($row)=>(int)$row->total===2700)->assertSee('Saldo dei filtri, esclusi storni');
        $this->get(route('collections.index',$filters+['page'=>2]))->assertViewHas('collections',fn($rows)=>$rows->count()===2)->assertViewHas('totals',fn($row)=>(int)$row->total===2700);
        $this->get(route('collections.index',$filters+['_table_all'=>1]),['X-Table-Export'=>'1'])->assertViewHas('collections',fn($rows)=>$rows->count()===27)->assertSee('Bonifico a Intermediario');
        $this->get(route('collections.index',array_replace($filters,['company'=>999999])))->assertViewHas('totals',fn($row)=>(int)$row->total===0);
        $this->get(route('collections.index',array_replace($filters,['agency'=>999999])))->assertViewHas('totals',fn($row)=>(int)$row->total===0);
        $this->get(route('collections.index',array_replace($filters,['payment_method'=>'Bonifico a Compagnia'])))->assertViewHas('totals',fn($row)=>(int)$row->total===0);
        $this->get(route('dashboard'))->assertViewHas('todayCollections',fn($row)=>(int)$row->total===2700);
    }

    public function test_dashboard_title_date_range_is_inclusive_independent_and_visible_when_empty(): void
    {
        $overdue=$this->title(['due_on'=>'2026-10-30']);
        $boundary=$this->title(['due_on'=>'2026-11-15']);
        $later=$this->title(['due_on'=>'2026-11-20']);
        $this->title(['due_on'=>'2026-11-20','collected_cents'=>100000]);
        $this->title(['due_on'=>'2026-11-20','cancelled_at'=>now()]);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('dueTitles',fn($rows)=>$rows->pluck('id')->all()===[$overdue->id,$boundary->id]);
        $this->get(route('dashboard',['period'=>'7','titles_from'=>'2026-11-20','titles_to'=>'2026-11-20']))->assertOk()->assertViewHas('period','7')->assertViewHas('dueTitles',fn($rows)=>$rows->pluck('id')->all()===[$later->id]);
        $this->get(route('dashboard',['titles_from'=>'2026-12-01','titles_to'=>'2026-12-02']))->assertOk()->assertViewHas('dueTitles',fn($rows)=>$rows->isEmpty())->assertSee('name="titles_from"',false)->assertSee('Nessun titolo da incassare nel periodo.');
        $this->get(route('dashboard',['titles_from'=>'2026-12-02','titles_to'=>'2026-12-01']))->assertSessionHasErrors('titles_to');
    }

    public function test_form_lists_only_payable_titles_and_keeps_input_after_validation_error(): void
    {
        $one=$this->title(['due_on'=>'2026-10-20']);
        $two=$this->title();
        $this->title(['cancelled_at'=>now()]);
        $this->title(['collected_cents'=>100000]);
        $this->get($this->page())->assertOk()->assertViewHas('payableTitles',fn($rows)=>$rows->modelKeys()===[$one->id,$two->id])->assertSee('Scegli il titolo');
        $this->from($this->page())->post(route('policies.collect',$this->policy),$this->payment($two,1234,['received_on'=>'2026-11-10']))->assertSessionHasErrors('received_on');
        $this->get($this->page())->assertOk()->assertSee('value="1000.00"',false)->assertViewHas('policyTab','payments')->assertSee('value="2026-11-10"',false);
        $this->get(route('titles.show',$one))->assertOk()->assertSee('Registra pagamento');
        $this->client->forceFill(['merged_into_id'=>Client::factory()->create()->id])->save();
        $this->post(route('policies.collect',$this->policy),$this->payment($one,100))->assertStatus(409);
        $this->assertDatabaseCount('insurance_collections',0);
    }
    public function test_new_partial_payments_are_rejected_and_historical_balance_can_be_settled(): void
    {
        $title=$this->title();
        foreach ([route('titles.collect',$title),route('policies.collect',$this->policy)] as $url) {
            $this->post($url,$this->payment($title,25000))->assertSessionHasErrors('ledger');
        }
        $this->assertDatabaseCount('insurance_collections',0);
        $this->assertSame(100000,$title->fresh()->balance_cents);
        $old=$this->historicalCollection($title,25000,['received_on'=>'2026-10-20']);
        $url=route('clients.policies.show',[$this->client,$this->policy,'tab'=>'payments','title'=>$title->id]);
        $this->get($url)->assertOk()->assertSee('value="750.00"',false)->assertSee('readonly',false)->assertDontSee('o solo una parte');
        $data=$this->payment($title,75000);
        $this->post(route('policies.collect',$this->policy),$data)->assertSessionHasNoErrors();
        $this->post(route('policies.collect',$this->policy),$data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('insurance_collections',2);
        $this->assertSame(0,$title->fresh()->balance_cents);
        $this->assertSame(25000,$old->fresh()->amount_cents);
        $this->assertNull($old->fresh()->voided_at);
    }

    public function test_policy_navigation_keeps_client_title_and_permissions_in_context(): void
    {
        $title=$this->title();
        $this->get(route('clients.show',$this->client))->assertOk()->assertSee('Polizze del cliente')->assertDontSee('Riepilogo provvigioni')->assertSee(e(route('clients.policies.show',[$this->client,$this->policy,'tab'=>'payments']).'#payment-entry'),false);
        foreach (['overview','documents','payments','forms','history','commissions'] as $tab) {
            $this->get(route('clients.policies.show',[$this->client,$this->policy,'tab'=>$tab]))->assertOk()->assertViewHas('policyTab',$tab)->assertSee('data-policy-section="'.$tab.'"',false);
        }
        $other=$this->client->policies()->create(array_replace($this->policy->only(['category','company','starts_on','expires_on','status']),['number'=>'TEST-OTHER']));
        $this->get(route('clients.policies.show',[$this->client,$other,'title'=>$title->id]))->assertNotFound();
        $operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($operator)->get(route('clients.policies.show',[$this->client,$this->policy,'tab'=>'commissions']))->assertForbidden();
        $this->get(route('clients.show',[$this->client,'tab'=>'commissions']))->assertForbidden();
        $this->get($this->page())->assertOk()->assertDontSee('data-policy-section="commissions"',false);
    }

    public function test_summary_separates_company_agency_refunds_and_direct_relationships(): void
    {
        $record = function (InsurancePolicy $policy, int $amount, string $direction='receipt', array $extra=[]) {
            $title=$this->title(['insurance_policy_id'=>$policy->id,'direction'=>$direction,'amount_cents'=>$amount,'collected_cents'=>$amount]);
            return $title->collections()->create(array_replace([
                'received_on'=>'2026-10-31','amount_cents'=>$amount,'direction'=>$direction,
                'payment_method'=>$direction==='refund' ? 'Bonifico' : 'Bonifico a Intermediario','recipient'=>'our_agency',
                'document_type'=>'record','idempotency_key'=>(string)Str::uuid(),'request_fingerprint'=>hash('sha256',(string)Str::uuid()),
            ],$extra));
        };
        $otherCompany=SupplierCompany::create(['name'=>'Seconda compagnia contabilità','status'=>'active']);
        $other=$this->client->policies()->create(array_replace($this->policy->getAttributes(),['id'=>null,'number'=>'TEST-CONT-02','company'=>$otherCompany->name,'supplier_company_id'=>$otherCompany->id]));
        $direct=$this->client->policies()->create(array_replace($this->policy->getAttributes(),['id'=>null,'number'=>'TEST-DIRECT','supplier_agency_id'=>null]));
        $record($this->policy,10000);$record($this->policy,2000);$record($this->policy,2500,'refund');
        $record($other,4000);$record($direct,1200);
        $record($this->policy,99000,'receipt',['voided_at'=>now()]);
        $record($this->policy,50000,'receipt',['received_on'=>'2026-10-30']);
        $response=$this->get(route('collections.index'))->assertOk()->assertSee('Riepilogo per compagnia e agenzia');
        $response->assertViewHas('groups',function($groups) {
            $first=$groups->first(fn($group)=>(int)$group->company_id===$this->policy->supplier_company_id && $group->agency_id!==null);
            return $groups->count()===3 && (int)$groups->sum('total')===14700 && (int)$first->receipts===12000 && (int)$first->refunds===2500 && (int)$first->receipt_count===2 && (int)$first->refund_count===1;
        });
        $this->get(route('collections.index',['agency'=>'direct']))->assertOk()->assertViewHas('collections',fn($rows)=>$rows->total()===1)->assertViewHas('totals',fn($row)=>(int)$row->total===1200);
        $this->get(route('collections.index',['direction'=>'refund']))->assertOk()->assertViewHas('groups',fn($groups)=>$groups->count()===1 && (int)$groups->first()->total===-2500);
        $this->get(route('collections.index',['state'=>'voided']))->assertOk()->assertViewHas('groups',fn($groups)=>$groups->isEmpty());
        $this->get(route('collections.index',['agency'=>'invalid']))->assertSessionHasErrors('agency');
    }

    public function test_summary_keeps_historical_mandate_and_the_export_contains_more_than_one_page(): void
    {
        $oldTitle=$this->title();
        for($i=0;$i<28;$i++) $oldTitle->collections()->create(['received_on'=>'2026-10-31','amount_cents'=>101,'direction'=>'receipt','payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Str::uuid(),'request_fingerprint'=>hash('sha256','historical-'.$i)]);
        $oldCompany=$this->policy->supplier_company_id;$oldAgency=$this->policy->supplier_agency_id;
        $next=SupplierCompany::create(['name'=>'Nuova compagnia contabilità','status'=>'active']);
        $agency=SupplierAgency::create(['name'=>'Nuova agenzia contabilità','status'=>'active']);
        $this->policy->update(['company'=>$next->name,'supplier_company_id'=>$next->id,'supplier_agency_id'=>$agency->id]);
        $filters=['company'=>$oldCompany,'agency'=>$oldAgency,'payment_method'=>'Bonifico a Intermediario'];
        $this->get(route('collections.index',$filters+['page'=>2]))->assertOk()
            ->assertViewHas('collections',fn($rows)=>$rows->count()===3)
            ->assertViewHas('groups',fn($groups)=>$groups->count()===1 && (int)$groups->first()->total===2828 && (int)$groups->first()->company_id===$oldCompany)
            ->assertViewHas('agencyCompanies',fn($links)=>in_array((string)$oldCompany,$links[$oldAgency],true));
        $this->get(route('collections.index',$filters+['_table_all'=>1]),['X-Table-Export'=>'1'])->assertOk()
            ->assertViewHas('collections',fn($rows)=>$rows->count()===28)->assertSee('Tipo pagamento')->assertSee('data-collection-group=',false);
        $this->get(route('collections.index',['company'=>$next->id]))->assertOk()->assertViewHas('groups',fn($groups)=>$groups->isEmpty());
    }
}
