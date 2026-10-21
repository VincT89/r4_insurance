<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AgencyCommissionSchedule, Client, InsuranceBranch, InsuranceCommission, InsuranceCommissionMovement, InsurancePolicy, InsuranceTitle, SupplierAgency, SupplierCompany, User};
use App\Services\{AgencyCommissionRegistry, InsuranceCommissionLedger, InsuranceLedger};
use App\Support\{CommissionMath, Money};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InsuranceCommissionLedgerTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private SupplierAgency $agency;
    private SupplierCompany $company;
    private InsuranceBranch $branch;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,19)->setTime(12,0));
        $this->admin = User::factory()->create(['role'=>UserRole::Admin, 'status'=>'active']); $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['name'=>'Officine Valdoro']);
        $this->company = SupplierCompany::create(['name'=>'Compagnia Alba', 'status'=>'active']);
        $this->agency = SupplierAgency::create(['name'=>'Agenzia Valserena', 'status'=>'active', 'supplier_company_id'=>$this->company->id]);
        $this->branch = InsuranceBranch::create(['name'=>'RC auto', 'name_key'=>hash('sha256','RC AUTO'), 'status'=>'active']);
    }

    private function conditions(array $schedule = [], array $row = [], ?AgencyCommissionSchedule $previous = null): AgencyCommissionSchedule
    {
        return app(AgencyCommissionRegistry::class)->save($this->agency, $this->admin, array_replace([
            'supplier_company_id'=>$this->company->id, 'valid_from'=>'2026-01-01', 'valid_to'=>null,
            'mandate_reference'=>'MAND-ALBA', 'accrual_event'=>'collection', 'collection_mode'=>'proportional',
            'revision_reason'=>'Aggiornamento delle condizioni del mandato',
            'entries'=>[array_replace(['branch_id'=>$this->branch->id, 'subbranch_id'=>null, 'selected'=>1, 'basis'=>'gross_premium', 'issue_rate'=>'10', 'renewal_rate'=>'8', 'quietance_rate'=>'5'], $row)],
        ], $schedule), $previous);
    }

    private function title(array $data = [], array $policyData = []): InsuranceTitle
    {
        $policy = $this->client->policies()->create(array_replace(['number'=>'SA-'.Str::random(8), 'category'=>$this->branch->name, 'insurance_branch_id'=>$this->branch->id, 'company'=>$this->company->name, 'supplier_company_id'=>$this->company->id, 'supplier_agency_id'=>$this->agency->id,
            'starts_on'=>'2026-01-01', 'expires_on'=>'2027-01-01', 'premium'=>'1000.00', 'status'=>'active', 'payment_frequency'=>'Annuale', 'payment_method'=>'Bonifico a Intermediario'], $policyData));
        return app(InsuranceLedger::class)->manual($policy, array_replace(['kind'=>'emission', 'coverage_from'=>'2026-10-01', 'coverage_to'=>'2027-01-01', 'due_on'=>'2026-10-01', 'amount_cents'=>100000, 'payment_frequency'=>'Trimestrale', 'payment_method'=>'Bonifico a Intermediario'], $data));
    }

    private function collect(InsuranceTitle $title, int $amount, string $date = '2026-10-19', array $data = [])
    {
        return app(InsuranceLedger::class)->collect($title, array_replace(['amount_cents'=>$amount, 'received_on'=>$date, 'payment_method'=>'Bonifico a Intermediario', 'recipient'=>'our_agency', 'document_type'=>'transfer', 'idempotency_key'=>(string)Str::uuid(), 'consultant_id'=>$this->admin->id], $data));
    }

    private function movementData(InsuranceTitle $title, string $amount, array $data = []): array
    {
        return array_replace(['version'=>$title->commission()->sole()->version, 'amount'=>$amount, 'effective_on'=>'2026-10-19', 'reference'=>'REND-001', 'reason'=>'Rendiconto del mandato controllato', 'idempotency_key'=>(string)Str::uuid(), 'confirmed'=>1], $data);
    }

    public function test_predictions_use_each_installment_and_quietance_rate_without_counting_the_year_twice(): void
    {
        $schedule = $this->conditions();
        $emission = $this->title(['amount_cents'=>50000]);
        $quietance = $this->title(['kind'=>'quietance','amount_cents'=>50000]);
        $renewal = $this->title(['kind'=>'renewal','amount_cents'=>50000]);
        $this->assertSame(5000,$emission->commission->expected_cents);
        $this->assertSame(2500,$quietance->commission->expected_cents);
        $this->assertSame(4000,$renewal->commission->expected_cents);
        $this->assertSame(0,$emission->commission->accrued_cents);
        $this->assertSame($schedule->id,$emission->commission->rule_snapshot['schedule_id']);
        $this->assertDatabaseCount('insurance_collections',0);
        $this->assertDatabaseHas('audit_logs',['action'=>'commission_calculated','user_id'=>$this->admin->id]);
    }

    public function test_partial_direct_collections_accrue_proportionally_and_reverse_without_erasing_a_settlement(): void
    {
        $this->conditions(); $title = $this->title();
        $first = $this->collect($title,40000,'2026-10-10',['payment_method'=>'Bonifico a Compagnia','recipient'=>'company']);
        $this->assertSame(4000,$title->commission()->sole()->accrued_cents);
        $data = $this->movementData($title,'30.00');
        $this->post(route('commissions.record',[$title,'settlement']),$data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('commissions.record',[$title,'settlement']),$data)->assertSessionHasNoErrors();
        $this->assertSame(3000,$title->commission()->sole()->settled_cents);
        $this->assertSame(1,$title->commission->movements()->where('kind','settlement')->count());
        $this->post(route('commissions.record',[$title,'settlement']),array_replace($data,['amount'=>'29']))->assertSessionHasErrors('commission');
        app(InsuranceLedger::class)->void($first,'Pagamento registrato per errore');
        $commission = $title->commission()->sole();
        $this->assertSame(0,$commission->accrued_cents); $this->assertSame(3000,$commission->settled_cents); $this->assertSame('reconcile',$commission->state);
        $this->assertDatabaseHas('insurance_commission_movements',['insurance_commission_id'=>$commission->id,'kind'=>'accrual','amount_cents'=>-4000]);
        $this->get(route('commissions.show',$title))->assertOk()->assertSee('Da riconciliare:')->assertSee('30,00 €');
    }

    public function test_full_balance_rule_uses_last_actual_payment_date_even_with_a_backdated_last_entry(): void
    {
        $this->conditions(['collection_mode'=>'full']); $title=$this->title();
        $this->collect($title,60000,'2026-10-18');
        $this->assertSame(0,$title->commission()->sole()->accrued_cents);
        $this->collect($title,40000,'2026-10-02');
        $movement=$title->commission->movements()->where('kind','accrual')->sole();
        $this->assertSame(10000,$movement->amount_cents); $this->assertSame('2026-10-18',$movement->effective_on->toDateString());
    }

    public function test_net_basis_and_unspecified_rules_never_guess_missing_contract_terms(): void
    {
        $schedule=$this->conditions(['accrual_event'=>null],['basis'=>'net_premium']); $title=$this->title();
        $this->assertNull($title->commission->expected_cents); $this->assertSame('incomplete',$title->commission->state);
        $this->conditions(['accrual_event'=>'title'],['basis'=>'net_premium'], $schedule);
        $this->post(route('commissions.prepare',$title),['version'=>$title->commission()->sole()->version])->assertRedirect();
        $this->assertNull($title->commission()->sole()->expected_cents);
        $this->post(route('commissions.prepare',$title),['version'=>$title->commission()->sole()->version,'base_amount'=>'800','base_reference'=>'Imponibile del rendiconto'])->assertSessionHasNoErrors();
        $commission=$title->commission()->sole();
        $this->assertSame(8000,$commission->expected_cents); $this->assertSame(8000,$commission->accrued_cents);
        $this->assertSame('Imponibile del rendiconto',$commission->base_reference);
    }

    public function test_unknown_quietance_rate_and_net_amount_greater_than_gross_remain_incomplete(): void
    {
        $this->conditions([],['quietance_rate'=>null,'basis'=>'net_premium']); $quietance=$this->title(['kind'=>'quietance']);
        $this->assertNull($quietance->commission->expected_cents);
        $title=$this->title();
        $this->post(route('commissions.prepare',$title),['version'=>$title->commission->version,'base_amount'=>'1001','base_reference'=>'Documento con importo errato'])->assertSessionHasNoErrors();
        $this->assertNull($title->commission()->sole()->expected_cents);
        $this->assertStringContainsString('non può superare',$title->commission()->sole()->incomplete_reason);
    }

    public function test_confirmations_and_partial_settlements_are_admin_only_and_limited_by_balances_and_dates(): void
    {
        $this->conditions(['accrual_event'=>'confirmation']); $title=$this->title();
        $this->collect($title,100000); $this->assertSame(0,$title->commission()->sole()->accrued_cents);
        $this->post(route('commissions.record',[$title,'settlement']),$this->movementData($title,'1'))->assertSessionHasErrors('commission');
        $this->post(route('commissions.record',[$title,'accrual']),$this->movementData($title,'60',['effective_on'=>'2026-10-15']))->assertSessionHasNoErrors();
        $this->post(route('commissions.record',[$title,'settlement']),$this->movementData($title,'10',['effective_on'=>'2026-10-14']))->assertSessionHasErrors('commission');
        $this->post(route('commissions.record',[$title,'settlement']),$this->movementData($title,'61'))->assertSessionHasErrors('commission');
        $this->post(route('commissions.record',[$title,'settlement']),$this->movementData($title,'40'))->assertSessionHasNoErrors();
        $this->post(route('commissions.record',[$title,'accrual']),$this->movementData($title,'41'))->assertSessionHasErrors('commission');
        $this->post(route('commissions.record',[$title,'accrual']),$this->movementData($title,'40'))->assertSessionHasNoErrors();
        $this->post(route('commissions.record',[$title,'settlement']),$this->movementData($title,'60'))->assertSessionHasNoErrors();
        $this->assertSame('settled',$title->commission()->sole()->state);
    }

    public function test_manual_reversal_keeps_original_and_rejects_stale_or_cross_title_requests(): void
    {
        $this->conditions(['accrual_event'=>'title']); $title=$this->title();
        $this->post(route('commissions.record',[$title,'settlement']),$this->movementData($title,'30'))->assertSessionHasNoErrors();
        $movement=$title->commission->movements()->where('kind','settlement')->sole();
        $other=$this->title(); $data=['version'=>$title->commission()->sole()->version,'effective_on'=>'2026-10-19','reason'=>'Contabile attribuita alla rata errata','confirmed'=>1];
        $this->post(route('commissions.reverse',[$other,$movement]),$data)->assertNotFound();
        $this->post(route('commissions.reverse',[$title,$movement]),array_replace($data,['version'=>0]))->assertSessionHasErrors('commission');
        $this->post(route('commissions.reverse',[$title,$movement]),$data)->assertSessionHasNoErrors();
        $this->assertSame(0,$title->commission()->sole()->settled_cents); $this->assertSame(3000,$movement->fresh()->amount_cents);
        $this->assertSame(-3000,$movement->reversal->amount_cents);
        $this->post(route('commissions.reverse',[$title,$movement]),array_replace($data,['version'=>$title->commission()->sole()->version]))->assertSessionHasErrors('commission');
        $this->assertDatabaseHas('audit_logs',['action'=>'commission_reversed','user_id'=>$this->admin->id]);
    }

    public function test_new_rates_do_not_rewrite_older_commissions_and_scopes_never_fall_back_to_other_subbranches(): void
    {
        $schedule=$this->conditions(); $old=$this->title();
        $this->conditions([],['issue_rate'=>'20'],$schedule); $new=$this->title();
        app(InsuranceCommissionLedger::class)->sync($old);
        $this->assertSame(10000,$old->commission()->sole()->expected_cents); $this->assertSame(20000,$new->commission->expected_cents);
        $sub=$this->branch->subbranches()->create(['name'=>'Autovetture','name_key'=>hash('sha256','AUTO'),'status'=>'active']);
        $subTitle=$this->title([],['insurance_subbranch_id'=>$sub->id,'subcategory'=>$sub->name]);
        $this->assertNull($subTitle->commission->expected_cents);
        $this->conditions([],['subbranch_id'=>$sub->id,'issue_rate'=>'15','accrual_event'=>'title']);
        $this->assertSame(15000,app(InsuranceCommissionLedger::class)->sync($subTitle)->accrued_cents);
    }

    public function test_cancellation_posts_negative_forecast_and_accrual_without_deleting_earlier_months(): void
    {
        $this->conditions(['accrual_event'=>'title']); $title=$this->title();
        $this->travelTo(now()->setDate(2026,11,2)); app(InsuranceLedger::class)->cancel($title,'Titolo emesso per errore');
        $commission=$title->commission()->sole(); $this->assertSame(0,$commission->expected_cents); $this->assertSame(0,$commission->accrued_cents);
        $this->assertSame('cancelled',$commission->state);
        $this->get(route('commissions.index',['mode'=>'movements','month'=>'2026-10']))->assertOk()->assertViewHas('totals',fn($t)=>(int)$t->expected===10000 && (int)$t->accrued===10000);
        $this->get(route('commissions.index',['mode'=>'movements','month'=>'2026-11']))->assertOk()->assertViewHas('totals',fn($t)=>(int)$t->expected===-10000 && (int)$t->accrued===-10000)->assertSee('-100,00 €');
    }

    public function test_initial_calculation_reconstructs_collection_and_reversal_dates_and_is_repeatable(): void
    {
        $title=$this->title(); $payment=$this->collect($title,40000,'2026-09-30');
        app(InsuranceLedger::class)->void($payment,'Incasso registrato per errore');
        $this->conditions();
        $this->post(route('commissions.calculate'),['month'=>'2026-10','confirmed'=>1])->assertSessionHasNoErrors();
        $commission=$title->commission()->sole(); $this->assertSame(0,$commission->accrued_cents);
        $this->assertSame(4000,(int)$commission->movements()->where('kind','accrual')->whereDate('effective_on','2026-09-30')->sum('amount_cents'));
        $this->assertSame(-4000,(int)$commission->movements()->where('kind','accrual')->whereDate('effective_on','2026-10-19')->sum('amount_cents'));
        $before=$commission->movements()->count(); app(InsuranceCommissionLedger::class)->sync($title);
        $this->assertSame($before,$commission->movements()->count());
    }

    public function test_operator_can_collect_with_audit_but_cannot_access_reports_or_record_commissions(): void
    {
        $this->conditions(); $title=$this->title(); $data=$this->movementData($title,'1');
        $operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']); $this->actingAs($operator);
        $this->get(route('commissions.index'))->assertForbidden(); $this->get(route('commissions.show',$title))->assertForbidden();
        $this->get(route('commissions.index',['_table_all'=>1]),['X-Table-Export'=>'1'])->assertForbidden();
        $this->post(route('commissions.calculate'),['confirmed'=>1])->assertForbidden();
        $this->post(route('commissions.prepare',$title),['version'=>1])->assertForbidden();
        foreach(['accrual','settlement'] as $kind) $this->post(route('commissions.record',[$title,$kind]),$data)->assertForbidden();
        $this->collect($title,50000);
        $this->assertSame(5000,$title->commission()->sole()->accrued_cents);
        $this->assertDatabaseHas('audit_logs',['action'=>'commission_accrued','user_id'=>$operator->id]);
        $this->get(route('titles.show',$title))->assertOk()->assertDontSee('href="'.route('commissions.show',$title).'"',false);
    }

    public function test_report_filters_grouping_pagination_and_export_use_the_entire_filtered_result(): void
    {
        $this->conditions(['accrual_event'=>'title']);
        for($i=0;$i<27;$i++) $this->title([],['number'=>'ALBA-'.str_pad((string)$i,3,'0',STR_PAD_LEFT)]);
        $other=$this->title([],['number'=>'ESCLUSA','supplier_agency_id'=>null]);
        $filters=['month'=>'2026-10','search'=>'ALBA-','agency'=>$this->agency->id,'company'=>$this->company->id,'branch'=>$this->branch->id];
        $this->get(route('commissions.index',$filters))->assertOk()->assertViewHas('rows',fn($r)=>$r->count()===25 && $r->total()===27)->assertViewHas('totals',fn($t)=>(int)$t->expected===270000);
        /** Global deadline notifications may mention other policies; the report itself must contain only the filtered rows. */
        $this->get(route('commissions.index',$filters+['page'=>2]))->assertOk()->assertViewHas('rows',fn($r)=>$r->count()===2 && $r->currentPage()===2 && $r->pluck('number')->all()===['ALBA-025','ALBA-026']);
        $this->get(route('commissions.index',$filters+['_table_all'=>1]))->assertViewHas('rows',fn($r)=>$r->count()===25);
        $this->get(route('commissions.index',$filters+['_table_all'=>1]),['X-Table-Export'=>'1'])->assertOk()->assertViewHas('rows',fn($r)=>$r->count()===27);
        foreach(['agency','company','branch'] as $group) $this->get(route('commissions.index',$filters+['group'=>$group]))->assertOk()->assertViewHas('rows',fn($r)=>$r->count()===1 && (int)$r->first()->expected===270000);
        $this->get(route('commissions.index',$filters+['mode'=>'movements']))->assertOk()->assertViewHas('rows',fn($r)=>$r->total()===54);
        $this->get(route('commissions.index',['month'=>'2026-10','state'=>'incomplete']))->assertViewHas('rows',fn($r)=>$r->total()===1 && $r->first()->title_id===$other->id);
        $this->get(route('commissions.index',['period'=>'range','from'=>'2026-10-02','to'=>'2026-10-19']))->assertViewHas('rows',fn($r)=>$r->total()===0);
        $this->get(route('commissions.index',['period'=>'range','from'=>'2026-10-19','to'=>'2026-10-01']))->assertSessionHasErrors('to');
        $this->get(route('commissions.index',['agency'=>999999]))->assertSessionHasErrors('agency');
    }

    public function test_client_and_policy_summaries_use_stored_balances_once_and_do_not_write_financial_data(): void
    {
        $this->conditions();
        $first = $this->title(['amount_cents'=>50000], ['number'=>'RCA-101']);
        $this->collect($first, 20000);
        $this->post(route('commissions.record', [$first, 'settlement']), $this->movementData($first, '10.00'))->assertSessionHasNoErrors();
        $second = $this->title(['kind'=>'quietance', 'amount_cents'=>50000], ['number'=>'RCA-102']);
        $otherClient = Client::factory()->create(['name'=>'Altro contraente']);
        $other = $this->title();
        $other->policy->forceFill(['client_id'=>$otherClient->id, 'number'=>'RCA-101'])->save();
        $before = InsuranceCommission::orderBy('id')->get()->toArray();
        $movements = InsuranceCommissionMovement::count();

        foreach (['registry', 'policies'] as $tab) {
            $this->get(route('clients.show', [$this->client, 'tab'=>$tab]))->assertOk()
                ->assertSee('Riepilogo provvigioni')->assertSee('75,00 €')->assertSee('20,00 €')->assertSee('10,00 €')
                ->assertSee(route('commissions.index', ['client'=>$this->client->id, 'period'=>'all']))
                ->assertViewHas('commissionSummary', fn ($s) => (int)$s->rows_count===2 && (int)$s->expected===7500 && (int)$s->accrued===2000 && (int)$s->settled===1000);
        }
        $this->get(route('clients.policies.show', [$this->client, $first->policy]))->assertOk()
            ->assertViewHas('commissionSummary', fn ($s) => (int)$s->rows_count===1 && (int)$s->expected===5000 && (int)$s->payable===1000)
            ->assertSee(route('commissions.index', ['client'=>$this->client->id, 'policy'=>$first->policy->id, 'period'=>'all']));
        $this->get(route('clients.policies.show', [$this->client, $second->policy]))->assertOk()
            ->assertViewHas('commissionSummary', fn ($s) => (int)$s->expected===2500 && (int)$s->accrued===0);
        $this->assertSame($before, InsuranceCommission::orderBy('id')->get()->toArray());
        $this->assertSame($movements, InsuranceCommissionMovement::count());
    }

    public function test_summary_distinguishes_no_rates_missing_conditions_partial_totals_and_a_real_zero_rate(): void
    {
        $this->get(route('clients.show', $this->client))->assertOk()->assertSee('Nessuna rata registrata.');
        $unknown = $this->title([], ['supplier_agency_id'=>null]);
        $this->get(route('clients.policies.show', [$this->client, $unknown->policy]))->assertOk()
            ->assertSee('Da configurare')->assertSee('1 rata da completare')
            ->assertViewHas('commissionSummary', fn ($s) => (int)$s->incomplete===1);
        $this->conditions([], ['issue_rate'=>'0']);
        $zero = $this->title();
        $this->get(route('clients.policies.show', [$this->client, $zero->policy]))->assertOk()
            ->assertSee('0,00 €')->assertDontSee('Da configurare')
            ->assertViewHas('commissionSummary', fn ($s) => (int)$s->incomplete===0 && (int)$s->expected===0);
        $this->get(route('clients.show', [$this->client, 'category'=>$this->branch->name]))->assertOk()
            ->assertSee('Totali parziali.')->assertSee('Tutte le polizze del cliente')
            ->assertViewHas('commissionSummary', fn ($s) => (int)$s->rows_count===2 && (int)$s->incomplete===1);
    }

    public function test_summary_preserves_settlements_to_reconcile_after_payment_reversal_and_title_cancellation(): void
    {
        $this->conditions();
        $title = $this->title();
        $payment = $this->collect($title, 100000);
        $this->post(route('commissions.record', [$title, 'settlement']), $this->movementData($title, '60.00'))->assertSessionHasNoErrors();
        app(InsuranceLedger::class)->void($payment, 'Incasso registrato sulla rata errata');
        app(InsuranceLedger::class)->cancel($title, 'Rata annullata dopo lo storno del pagamento');
        $this->get(route('clients.policies.show', [$this->client, $title->policy]))->assertOk()
            ->assertSee('Da riconciliare dopo storni o annullamenti:')
            ->assertSee('60,00 €')
            ->assertViewHas('commissionSummary', fn ($s) => (int)$s->expected===0 && (int)$s->accrued===0 && (int)$s->settled===6000 && (int)$s->reconcile===6000 && (int)$s->incomplete===0);
        $this->assertGreaterThan(1, InsuranceCommissionMovement::count());
    }

    public function test_summary_and_context_reports_remain_admin_only_and_cannot_cross_client_policy_links(): void
    {
        $this->conditions();
        $title = $this->title();
        $otherClient = Client::factory()->create();
        $this->get(route('clients.policies.show', [$otherClient, $title->policy]))->assertNotFound();
        $this->getJson(route('commissions.index', ['client'=>$otherClient->id, 'policy'=>$title->policy->id, 'period'=>'all']))->assertUnprocessable()->assertJsonValidationErrors('policy');
        $this->getJson(route('commissions.index', ['client'=>'not-an-id', 'period'=>'all']))->assertUnprocessable()->assertJsonValidationErrors('client');
        $operator = User::factory()->create(['role'=>UserRole::Operator, 'status'=>'active']);
        $this->actingAs($operator);
        $this->get(route('clients.show', $this->client))->assertOk()->assertDontSee('Riepilogo provvigioni')->assertViewHas('commissionSummary', null);
        $this->get(route('clients.policies.show', [$this->client, $title->policy]))->assertOk()->assertDontSee('Riepilogo provvigioni')->assertViewHas('commissionSummary', null);
        $this->get(route('commissions.index', ['client'=>$this->client->id, 'policy'=>$title->policy->id, 'period'=>'all', '_table_all'=>1]), ['X-Table-Export'=>'1'])->assertForbidden();
        $this->post(route('commissions.calculate'), ['client'=>$this->client->id, 'period'=>'all', 'confirmed'=>1])->assertForbidden();
    }

    public function test_context_report_covers_all_years_and_keeps_the_exact_client_in_filters_and_exports(): void
    {
        $this->conditions(['valid_from'=>'2025-01-01', 'accrual_event'=>'title']);
        $old = $this->title(['coverage_from'=>'2025-10-01', 'coverage_to'=>'2026-01-01', 'due_on'=>'2025-10-01'], ['number'=>'CONTRATTO-2025', 'starts_on'=>'2025-01-01', 'expires_on'=>'2026-01-01']);
        $current = $this->title([], ['number'=>'CONTRATTO-2026']);
        $otherClient = Client::factory()->create(['name'=>'Altro contraente']);
        $other = $this->title();
        $other->policy->forceFill(['client_id'=>$otherClient->id, 'number'=>'CONTRATTO-2026'])->save();
        $scope = ['client'=>$this->client->id, 'period'=>'all'];
        $this->get(route('commissions.index', $scope))->assertOk()
            ->assertViewHas('rows', fn ($r) => $r->total()===2)
            ->assertViewHas('totals', fn ($t) => (int)$t->expected===20000)
            ->assertSee('Tutte le rate comprese nei filtri, senza limiti di data')
            ->assertSee('name="client" value="'.$this->client->id.'"', false)
            ->assertSee('data-export-value="'.$this->client->name.'"', false);
        $this->get(route('commissions.index', $scope+['_table_all'=>1]), ['X-Table-Export'=>'1'])->assertOk()
            ->assertViewHas('rows', fn ($r) => $r->pluck('title_id')->all()===[$old->id, $current->id]);
        $this->get(route('commissions.index', $scope+['policy'=>$current->policy->id]))->assertOk()
            ->assertViewHas('rows', fn ($r) => $r->total()===1 && $r->first()->title_id===$current->id)
            ->assertViewHas('totals', fn ($t) => (int)$t->expected===10000);
        $this->get(route('commissions.index', ['client'=>$this->client->id, 'period'=>'month', 'month'=>'2025-10']))->assertOk()
            ->assertViewHas('rows', fn ($r) => $r->total()===1 && $r->first()->title_id===$old->id);
        $this->get(route('commissions.index', $scope+['group'=>'agency']))->assertOk()->assertViewHas('totals', fn ($t) => (int)$t->expected===20000);
        $this->get(route('commissions.index', $scope+['mode'=>'movements']))->assertOk()->assertViewHas('totals', fn ($t) => (int)$t->expected===20000 && (int)$t->accrued===20000);
    }

    public function test_context_calculation_only_updates_the_selected_policy(): void
    {
        $selected = $this->title();
        $other = $this->title();
        $this->conditions();
        $this->post(route('commissions.calculate'), ['client'=>$this->client->id, 'policy'=>$selected->policy->id, 'period'=>'all', 'confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertSame(10000, $selected->commission()->sole()->expected_cents);
        $this->assertNull($other->commission()->sole()->expected_cents);
    }

    public function test_zero_rates_exact_rounding_maximum_amounts_and_negative_money(): void
    {
        $this->assertSame(1,CommissionMath::percentage(1,500000));
        $this->assertSame(123456,CommissionMath::percentage(1000000,123456));
        $this->assertSame(999999999999,CommissionMath::percentage(999999999999,1000000));
        $this->assertSame(333333333333,CommissionMath::proportion(999999999999,333333333333,999999999999));
        $this->assertSame(33,CommissionMath::proportion(100,1,3)); $this->assertSame(67,CommissionMath::proportion(100,2,3));
        $this->assertSame('-0,01 €',Money::format(-1)); $this->assertSame('-0.01',Money::decimal(-1));
        $this->conditions([],['issue_rate'=>'0']); $title=$this->title();
        $this->assertSame(0,$title->commission->expected_cents); $this->assertNotNull($title->commission->locked_at);
        $this->post(route('commissions.record',[$title,'settlement']),$this->movementData($title,'0.01'))->assertSessionHasErrors('commission');
    }

    public function test_forms_and_report_escape_notes_and_reject_invalid_financial_requests(): void
    {
        $schedule=$this->conditions(['accrual_event'=>'confirmation','mandate_reference'=>'<script>alert(1)</script>']); $title=$this->title();
        $this->get(route('suppliers.commissions.edit',[$this->agency,$schedule]))->assertOk()->assertSee('Quietanza (%)')->assertSee('Pagamenti parziali per questa voce');
        $this->get(route('commissions.show',$title))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
        foreach(['-1','1e2','1.001','10000000000'] as $amount) $this->post(route('commissions.record',[$title,'accrual']),$this->movementData($title,$amount))->assertSessionHasErrors('amount');
        $this->post(route('commissions.record',[$title,'accrual']),$this->movementData($title,'10',['confirmed'=>0]))->assertSessionHasErrors('confirmed');
        $this->post(route('commissions.record',[$title,'accrual']),$this->movementData($title,'10',['effective_on'=>'2026-10-20']))->assertSessionHasErrors('effective_on');
        $this->assertSame(0,$title->commission()->sole()->accrued_cents);
    }
}
