<?php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client,User,InsurancePolicy,InsuranceTitle,InsuranceCollection,InsuranceQuote,InsuranceQuoteCase,SupplierCompany,SupplierAgency,InsuranceBranch};
use App\Services\{InsuranceLedger,InsuranceQuoteWorkflow,ClientDocumentRegistry,DeadlineReminders};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InsuranceOperationsTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private Client $client; private InsurancePolicy $policy; private InsuranceBranch $branch; private SupplierCompany $company;
    protected function setUp(): void
    {
        parent::setUp();$this->travelTo(now()->setDate(2026,10,16)->setTime(12,0));Storage::fake('attachments');
        $this->admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);$this->actingAs($this->admin);$this->client=Client::factory()->create();
        $this->branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $this->company=SupplierCompany::create(['name'=>'Nobis','status'=>'active','quote_priority'=>true]);
        $this->policy=$this->client->policies()->create(['category'=>'RC auto','insurance_branch_id'=>$this->branch->id,'company'=>$this->company->name,'supplier_company_id'=>$this->company->id,'number'=>'SA-2026-501','starts_on'=>'2026-10-16','expires_on'=>'2027-10-16','status'=>'active','premium'=>'1000.01','payment_frequency'=>'Semestrale','payment_method'=>'Bonifico a Intermediario']);
    }
    private function pdf(string $name='proposta.pdf'): UploadedFile {return UploadedFile::fake()->createWithContent($name,"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");}
    private function policyInput(): array {return $this->policy->only(['category','company','status'])+['starts_on'=>$this->policy->starts_on->toDateString(),'expires_on'=>$this->policy->expires_on->toDateString()];}
    private function generate(): array
    {
        $response=$this->get(route('titles.schedule',$this->policy))->assertOk();
        $this->post(route('titles.generate',$this->policy),['fingerprint'=>$response->viewData('fingerprint'),'confirmed'=>1])->assertSessionHasNoErrors();
        return $this->policy->titles()->orderBy('coverage_from')->get()->all();
    }
    private function payment(int $amount,array $extra=[]): array {return array_replace(['received_on'=>'2026-10-16','amount'=>\App\Support\Money::decimal($amount),'payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'transfer','idempotency_key'=>(string)Str::uuid()],$extra);}
    private function case(): InsuranceQuoteCase {return $this->client->quoteCases()->create(['insurance_branch_id'=>$this->branch->id,'subject'=>'Copertura veicolo','status'=>'open']);}
    private function approve(?Client $client=null): InsuranceQuote
    {
        $case=$this->case();if($client)$case->update(['client_id'=>$client->id]);
        $this->post(route('quotes.request',$case),['supplier_company_id'=>$this->company->id,'requested_on'=>'2026-10-15','confirmed'=>1])->assertSessionHasNoErrors();$quote=$case->quotes()->sole();
        $this->post(route('quotes.change',[$quote,'receive']),['reference'=>'N-42','product'=>'Auto completa','starts_on'=>'2026-10-16','expires_on'=>'2027-10-16','valid_until'=>'2026-10-31','received_on'=>'2026-10-16','premium'=>'800.00','payment_frequency'=>'Semestrale','files'=>[$this->pdf()]])->assertSessionHasNoErrors();
        $this->post(route('quotes.change',[$quote,'submit']),['confirmed'=>1])->assertSessionHasNoErrors()->assertRedirect(route('quotes.show',$case).'#proposal-'.$quote->id);
        $this->post(route('quotes.change',[$quote,'approve']),['approved_on'=>'2026-10-16','confirmed'=>1,'files'=>[$this->pdf('approvazione.pdf')]])->assertSessionHasNoErrors()->assertRedirect(route('quotes.show',$case).'#proposal-'.$quote->id);return $quote->fresh();
    }
    public function test_dashboard_payment_groups_follow_collection_settlement_and_reversal(): void
    {
        [$first,$second] = $this->generate();
        $summary = $this->get(route('dashboard'))->assertOk()->viewData('paymentSummary');
        $this->assertSame(['future'=>1,'partial'=>0,'due_today'=>1,'overdue'=>0],array_map(fn($group)=>$group['count'],$summary));
        $this->post(route('titles.collect',$first),$this->payment(20000))->assertSessionHasNoErrors();
        $summary = $this->get(route('dashboard'))->assertOk()->viewData('paymentSummary');
        $this->assertSame(['count'=>1,'balance_cents'=>$first->amount_cents-20000],$summary['partial']);
        $this->assertSame(0,$summary['due_today']['count']);
        $this->post(route('titles.collect',$first),$this->payment($first->amount_cents-20000))->assertSessionHasNoErrors();
        $last = $first->collections()->latest('id')->first();
        $this->get(route('dashboard'))->assertViewHas('paymentSummary',fn($rows)=>$rows['partial']['count']===0 && $rows['future']['count']===1);
        $this->post(route('collections.void',$last),['reason'=>'Incasso registrato per errore'])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertViewHas('paymentSummary',fn($rows)=>$rows['partial']['count']===1);
        $this->post(route('titles.cancel',$second),['reason'=>'Rata inserita per errore'])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertViewHas('paymentSummary',fn($rows)=>$rows['future']['count']===0);
    }

    public function test_payment_reminders_follow_partial_balance_settlement_and_reversal_for_every_recipient(): void
    {
        [$title] = $this->generate();
        $other = User::factory()->create(['role'=>UserRole::Admin, 'status'=>'active']);
        $reminders = app(DeadlineReminders::class);
        foreach ([$this->admin, $other] as $user) {
            $reminders->forUser($user);
            $reminders->refresh($user);
            $this->assertSame(1, $user->visibleNotifications()->where('data->kind', 'title')->count());
            $user->notifications()->update(['read_at'=>now()]);
        }
        $this->post(route('titles.collect', $title), $this->payment(20000))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->admin->visibleNotifications()->where('data->kind', 'title')->count());
        $this->post(route('titles.collect', $title), $this->payment(30000))->assertSessionHasNoErrors();
        $lastPayment = $title->collections()->latest('id')->first();
        foreach ([$this->admin, $other] as $user) {
            $reminders->refresh($user);
            $this->assertSame(0, $user->visibleNotifications()->where('data->kind', 'title')->count());
            $this->assertSame(1, $user->notifications()->where('data->kind', 'title')->count());
        }
        $this->travelTo(now()->addDays(20));
        $this->post(route('collections.void', $lastPayment), ['reason'=>'Incasso registrato per errore'])->assertSessionHasNoErrors();
        foreach ([$this->admin, $other] as $user) {
            $reminders->refresh($user);
            $this->assertSame(1, $user->visibleNotifications()->where('data->kind', 'title')->whereNull('read_at')->count());
            $this->assertSame(1, $user->notifications()->where('data->kind', 'title')->count());
        }
        $this->assertSame(30000, $title->fresh()->balance_cents);
    }

    public function test_cancelled_and_rescheduled_deadlines_disappear_without_deleting_notification_history(): void
    {
        [$title] = $this->generate();
        $reminders = app(DeadlineReminders::class); $reminders->forUser($this->admin);
        $this->assertSame(1, $this->admin->visibleNotifications()->count());
        $title->update(['due_on'=>'2026-10-25']);
        $this->assertSame(0, $this->admin->visibleNotifications()->count());
        $this->assertSame(1, $reminders->forUser($this->admin));
        $this->assertSame(1, $this->admin->visibleNotifications()->count());
        $this->post(route('titles.cancel', $title), ['reason'=>'Rata inserita per errore'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->admin->visibleNotifications()->count());
        $this->assertSame(2, $this->admin->notifications()->count());
    }

    public function test_replaced_documents_fulfilled_requests_and_changed_policies_hide_only_the_old_alert(): void
    {
        $this->policy->update(['expires_on'=>'2026-10-25']);
        $request = $this->client->documentRequests()->create(['label'=>'Documento identità', 'document_category'=>'identity', 'due_on'=>'2026-10-25']);
        $this->post(route('clients.documents.store',$this->client), ['document_category'=>'identity','file_details'=>[['expires_on'=>'2026-10-25']],'files'=>[$this->pdf('identita.pdf')]])->assertSessionHasNoErrors();
        $original = $this->client->attachments()->sole();
        $reminders = app(DeadlineReminders::class); $reminders->forUser($this->admin);
        $this->assertSame(3, $this->admin->visibleNotifications()->count());
        $this->post(route('clients.documents.store',$this->client), ['document_category'=>'identity','file_details'=>[['expires_on'=>'2029-10-25']],'files'=>[$this->pdf('identita-nuova.pdf')],'previous_version_id'=>$original->id])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->admin->visibleNotifications()->count());
        $this->post(route('clients.documents.store',$this->client), ['document_category'=>'identity','file_details'=>[['expires_on'=>'2029-10-25']],'files'=>[$this->pdf('identita-richiesta.pdf')],'document_request_id'=>$request->id])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->admin->visibleNotifications()->count());
        $this->policy->update(['expires_on'=>'2027-10-25']);
        $this->assertSame(0, $this->admin->visibleNotifications()->count());
        $this->assertSame(3, $this->admin->notifications()->count());
    }

    public function test_new_policy_entry_point_requires_a_proposal_before_showing_the_form(): void
    {
        $this->get(route('policies.create'))->assertOk()->assertViewIs('policies.start')->assertSee('Richiedi preventivo')->assertDontSee('name="number"',false);
        $this->get(route('policies.create',['entry_mode'=>'existing']))->assertOk()->assertViewIs('clients.policies.form')->assertSee('Cerca cliente')->assertSee('name="existing_reason"',false);
        $operator=User::factory()->create(['role'=>UserRole::Operator]);
        $this->actingAs($operator)->get(route('policies.create'))->assertOk()->assertDontSee('Registra polizza già stipulata');
        $this->get(route('policies.create',['entry_mode'=>'existing']))->assertForbidden();
    }

    public function test_policy_quote_choice_filters_invalid_proposals_and_survives_validation_errors(): void
    {
        $quote=$this->approve();
        $this->get(route('policies.create'))->assertOk()->assertViewIs('policies.start')->assertSee($quote->reference)->assertSee('800,00 €');
        $url=route('policies.create',['quote_id'=>$quote->id]);
        $this->get($url)->assertOk()->assertViewIs('clients.policies.form')->assertSee('name="insurance_quote_id"',false)->assertDontSee('name="existing_reason"',false)->assertSee('value="active"',false)->assertSee('value="renewed"',false)->assertDontSee('value="cancelled"',false)->assertDontSee('value="expired"',false);
        $this->from($url)->post(route('policies.store'),$this->policyInput()+['client_id'=>$this->client->id,'entry_mode'=>'new','insurance_quote_id'=>$quote->id,'number'=>''])->assertSessionHasErrors('number');
        $this->get($url)->assertOk()->assertViewIs('clients.policies.form')->assertSee($quote->reference);
        $quote->update(['valid_until'=>'2026-10-15']);
        $this->get(route('policies.create'))->assertOk()->assertDontSee($quote->reference);
        $this->get($url)->assertNotFound();
    }

    public function test_rate_filters_and_collections_keep_the_selected_contract_including_empty_results(): void
    {
        [$title]=$this->generate();
        $this->post(route('titles.collect',$title),$this->payment(100))->assertSessionHasNoErrors();
        $other=Client::factory()->create();
        $rateUrl=route('titles.index',['policy'=>$this->policy->id]);
        $collectionUrl=route('collections.index',['policy'=>$this->policy->id,'month'=>'']);
        $this->get($rateUrl)->assertOk()->assertSee('Rate e quietanze / '.$this->policy->number)
            ->assertSee(e($collectionUrl),false)->assertSee('href="'.$rateUrl.'">Azzera filtri',false)
            ->assertSee(e(route('titles.index',['policy'=>$this->policy->id,'state'=>'overdue'])),false)
            ->assertViewHas('titles',fn($titles)=>$titles->every(fn($row)=>$row->insurance_policy_id===$this->policy->id));
        $this->get($collectionUrl)->assertOk()->assertSee('Incassi / '.$this->policy->number)->assertSee($rateUrl,false)
            ->assertViewHas('collections',fn($rows)=>$rows->count()===1 && $rows->first()->insurance_title_id===$title->id);
        $this->get(route('collections.index',['policy'=>$this->policy->id,'month'=>'2027-12']))->assertOk()
            ->assertSee('Incassi / '.$this->policy->number)->assertViewHas('collections',fn($rows)=>$rows->isEmpty());
        $this->get(route('titles.index',['policy'=>$this->policy->id,'client'=>$other->id]))->assertNotFound();
        $this->get(route('collections.index',['policy'=>999999]))->assertNotFound();
    }
    public function test_schedule_preserves_every_cent_and_duplicate_confirmation_is_rejected(): void
    {
        [$a,$b]=$this->generate();$this->assertSame(50000,$a->amount_cents);$this->assertSame(50001,$b->amount_cents);$this->assertSame('2027-04-16',$b->due_on->toDateString());
        $preview=$this->get(route('titles.schedule',$this->policy))->assertOk();$this->post(route('titles.generate',$this->policy),['fingerprint'=>$preview->viewData('fingerprint'),'confirmed'=>1])->assertSessionHasErrors('ledger');$this->assertDatabaseCount('insurance_titles',2);
        $this->assertNull($this->policy->fresh()->coverage_until);
    }
    public function test_schedule_rejects_mismatched_instalments_irregular_dates_and_stale_preview(): void
    {
        $preview=$this->get(route('titles.schedule',$this->policy));$this->policy->update(['premium'=>'1100.00']);
        $this->post(route('titles.generate',$this->policy),['fingerprint'=>$preview->viewData('fingerprint'),'confirmed'=>1])->assertSessionHasErrors('ledger');
        $this->policy->update(['installment_premium'=>'500.00']);$this->get(route('titles.schedule',$this->policy))->assertSessionHasErrors('ledger');
        $this->policy->update(['installment_premium'=>null,'expires_on'=>'2027-10-15']);$this->get(route('titles.schedule',$this->policy))->assertSessionHasErrors('ledger');$this->assertDatabaseCount('insurance_titles',0);
    }
    public function test_month_end_and_leap_year_periods_are_anchored_to_original_start(): void
    {
        $this->policy->update(['starts_on'=>'2028-01-31','expires_on'=>'2028-04-30','payment_frequency'=>'Mensile','premium'=>'100.00']);
        $rows=app(InsuranceLedger::class)->plan($this->policy);$this->assertSame(['2028-02-29','2028-03-31','2028-04-30'],array_column($rows,'coverage_to'));$this->assertSame(10000,array_sum(array_column($rows,'amount_cents')));
    }
    public function test_partial_full_and_direct_payments_are_idempotent_and_never_overpay(): void
    {
        [$title]=$this->generate();$data=$this->payment(20000,['files'=>[$this->pdf('contabile.pdf')]]);
        $this->post(route('titles.collect',$title),$data)->assertSessionHasNoErrors();$this->post(route('titles.collect',$title),$data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('insurance_collections',1);$this->assertSame(30000,$title->fresh()->balance_cents);$this->assertSame(1,$title->documents()->count());
        $this->post(route('titles.collect',$title),$this->payment(30001))->assertSessionHasErrors('ledger');
        $this->post(route('titles.collect',$title),$this->payment(30000,['payment_method'=>'Bonifico a Compagnia','recipient'=>'company']))->assertSessionHasNoErrors();
        $this->assertSame('paid',$title->fresh()->state);$this->assertSame('2027-04-16',$this->policy->fresh()->coverage_until->toDateString());
        $this->get(route('collections.index',['month'=>'2026-10']))->assertOk()->assertViewHas('totals',fn($v)=>(int)$v->total===50000&&(int)$v->ours===20000);
        $this->post(route('titles.collect',$title),$this->payment(1))->assertSessionHasErrors('ledger');
    }
    public function test_later_paid_title_does_not_bridge_unpaid_or_cancelled_coverage_and_storno_rewinds(): void
    {
        [$a,$b]=$this->generate();$this->post(route('titles.collect',$b),$this->payment(50001))->assertSessionHasNoErrors();$this->assertNull($this->policy->fresh()->coverage_until);
        $this->post(route('titles.cancel',$a),['reason'=>'Titolo da correggere'])->assertSessionHasNoErrors();$this->assertNull($this->policy->fresh()->coverage_until);
        $manual=['kind'=>'emission','coverage_from'=>'2026-10-16','coverage_to'=>'2027-04-16','due_on'=>'2026-10-16','amount'=>'500.00','payment_method'=>'Bonifico a Intermediario','payment_frequency'=>'Semestrale'];
        $this->post(route('titles.store',$this->policy),$manual)->assertSessionHasNoErrors();$replacement=$this->policy->titles()->latest('id')->first();
        $this->post(route('titles.collect',$replacement),$this->payment(50000))->assertSessionHasNoErrors();$this->assertSame('2027-10-16',$this->policy->fresh()->coverage_until->toDateString());
        $payment=$replacement->collections()->sole();$this->post(route('collections.void',$payment),['reason'=>'Registrazione duplicata per errore'])->assertSessionHasNoErrors();
        $this->assertNull($this->policy->fresh()->coverage_until);$this->assertNotNull($payment->fresh()->voided_at);$this->assertSame(50000,$replacement->fresh()->balance_cents);
        $this->post(route('collections.void',$payment),['reason'=>'Secondo clic sullo stesso storno'])->assertSessionHasNoErrors();$this->assertSame(50000,$replacement->fresh()->balance_cents);
    }
    public function test_manual_title_rejects_gaps_outside_contract_and_overlaps(): void
    {
        $data=['kind'=>'quietance','coverage_from'=>'2027-04-16','coverage_to'=>'2027-10-16','due_on'=>'2027-04-16','amount'=>'500','payment_method'=>'Bonifico a Intermediario','payment_frequency'=>'Semestrale'];
        $this->post(route('titles.store',$this->policy),$data)->assertSessionHasNoErrors();$title=$this->policy->titles()->sole();
        $this->post(route('titles.collect',$title),$this->payment(50000))->assertSessionHasNoErrors();$this->assertNull($this->policy->fresh()->coverage_until);
        $this->post(route('titles.store',$this->policy),$data)->assertSessionHasErrors('ledger');
        $this->post(route('titles.store',$this->policy),array_replace($data,['coverage_to'=>'2028-01-01']))->assertSessionHasErrors('ledger');
    }
    public function test_renewal_creates_new_titles_but_retains_original_payments_and_periods(): void
    {
        [$a,$b]=$this->generate();$this->post(route('titles.collect',$a),$this->payment(50000));$this->post(route('titles.collect',$b),$this->payment(50001));
        $data=['starts_on'=>'2027-10-16','expires_on'=>'2028-10-16','premium'=>'1200.00','payment_frequency'=>'Semestrale','payment_method'=>'Bonifico a Intermediario'];
        $preview=$this->post(route('titles.renewal-preview',$this->policy),$data)->assertOk();$data+=['confirmed'=>1,'fingerprint'=>$preview->viewData('fingerprint')];
        $this->post(route('titles.renew',$this->policy),$data)->assertSessionHasNoErrors();$this->assertDatabaseCount('insurance_titles',4);$this->assertDatabaseCount('insurance_collections',2);
        $this->assertSame('2027-10-16',$this->policy->fresh()->coverage_until->toDateString());$this->assertSame('2026-10-16',$this->policy->fresh()->coverage_origin_on->toDateString());
        $this->assertSame('Rinnovata',$this->policy->fresh()->status_label);
        $this->assertTrue(app(\App\Domain\Core\Queries\PolicyPortfolioQuery::class)->forUser($this->admin,['status'=>'active'])->whereKey($this->policy->id)->exists());
        $this->post(route('titles.renew',$this->policy),$data)->assertSessionHasErrors('ledger');$this->assertDatabaseCount('insurance_titles',4);
    }
    public function test_title_payments_documents_and_catalogue_links_cannot_be_deleted_or_reassigned(): void
    {
        [$title]=$this->generate();$this->post(route('titles.documents',$title),['files'=>[$this->pdf('quietanza.pdf'),$this->pdf('quietanza2.pdf')]])->assertSessionHasNoErrors();
        $doc=$title->documents()->first();$this->delete(route('attachments.destroy',$doc))->assertSessionHasErrors();
        $this->post(route('clients.documents.store',$this->client),['files'=>[$this->pdf()],'document_category'=>'quietance','insurance_policy_id'=>$this->policy->id,'previous_version_id'=>$doc->id])->assertSessionHasErrors('previous_version_id');
        $this->delete(route('clients.policies.destroy',[$this->client,$this->policy]))->assertSessionHasErrors('policy');
        $this->patch(route('clients.policies.update',[$this->client,$this->policy]),$this->policyInput()+['number'=>$this->policy->number,'policy_revision'=>$this->policy->fresh()->editFingerprint()]+['premium'=>'1.00'])->assertSessionHasErrors('premium');
        $this->get(route('attachments.download',$doc))->assertOk();
    }
    public function test_operator_manages_titles_for_all_clients_and_payment_author_cannot_be_forged(): void
    {
        [$title]=$this->generate();$employee=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);$this->actingAs($employee);
        $this->get(route('titles.show',$title))->assertOk();
        $this->client->update(['commercial_user_id'=>$employee->id]);$this->get(route('titles.show',$title))->assertOk();
        $this->post(route('titles.collect',$title),$this->payment(100,['consultant_id'=>$this->admin->id]))->assertSessionHasNoErrors();$payment=$title->collections()->sole();$this->assertSame($employee->id,$payment->consultant_id);
        $this->post(route('collections.void',$payment),['reason'=>'Correzione operatore tracciata'])->assertSessionHasNoErrors();$this->post(route('titles.cancel',$title),['reason'=>'Correzione operatore tracciata'])->assertSessionHasNoErrors();$this->get(route('titles.create',$this->policy))->assertOk();
    }
    public function test_payment_recipient_date_file_and_idempotency_cannot_be_forged(): void
    {
        [$title]=$this->generate();$data=$this->payment(100);$this->post(route('titles.collect',$title),$data)->assertSessionHasNoErrors();
        $this->post(route('titles.collect',$title),array_replace($data,['amount'=>'2.00']))->assertSessionHasErrors('ledger');
        $this->post(route('titles.collect',$title),$this->payment(100,['recipient'=>'company']))->assertSessionHasErrors('ledger');
        $this->post(route('titles.collect',$title),$this->payment(100,['recipient'=>'agency']))->assertSessionHasErrors('ledger');
        $this->post(route('titles.collect',$title),$this->payment(100,['received_on'=>'2026-10-17']))->assertSessionHasErrors('received_on');
        $this->post(route('titles.collect',$title),$this->payment(100,['files'=>[UploadedFile::fake()->createWithContent('bad.php','<?php echo 1;')]]))->assertSessionHasErrors('files.0');$this->assertDatabaseCount('insurance_collections',1);
    }
    public function test_overdue_titles_and_fifteen_day_reminders_ignore_paid_and_cancelled_titles(): void
    {
        $this->policy->update(['starts_on'=>'2026-10-01','expires_on'=>'2027-10-01','payment_frequency'=>'Mensile']);$rows=$this->generate();
        $this->get(route('titles.index',['state'=>'overdue']))->assertOk()->assertViewHas('titles',fn($r)=>$r->total()===1);
        $rows[1]->update(['due_on'=>'2026-10-31']);$rows[2]->update(['due_on'=>'2026-10-30']);$rows[3]->update(['due_on'=>'2026-10-29']);
        $this->post(route('titles.collect',$rows[2]),$this->payment($rows[2]->amount_cents));$this->post(route('titles.cancel',$rows[3]),['reason'=>'Titolo non dovuto']);
        app(DeadlineReminders::class)->forUser($this->admin);app(DeadlineReminders::class)->forUser($this->admin);
        $this->assertEqualsCanonicalizing([$rows[0]->id,$rows[1]->id],$this->admin->notifications()->where('data->kind','title')->get()->map(fn($notification)=>(int)$notification->data['source_id'])->all());
        $this->get(route('deadlines.index',['kind'=>'title','period'=>'15']))->assertOk()->assertViewHas('deadlines',fn($r)=>$r->total()===1);
        $this->get(route('dashboard'))->assertOk()->assertSee('Rate scadute e in scadenza');$this->get(route('clients.show',[$this->client,'tab'=>'deadlines']))->assertOk()->assertSee('Residuo');
    }
    public function test_priority_companies_must_be_contacted_before_other_companies(): void
    {
        $europe=SupplierCompany::create(['name'=>'Europ Assistance','quote_priority'=>true,'status'=>'active']);$other=SupplierCompany::create(['name'=>'Altra compagnia','status'=>'active']);$case=$this->case();
        $data=['requested_on'=>'2026-10-16','confirmed'=>1];$this->post(route('quotes.request',$case),$data+['supplier_company_id'=>$other->id])->assertSessionHasErrors('quote');
        foreach([$this->company,$europe,$other] as $company)$this->post(route('quotes.request',$case),$data+['supplier_company_id'=>$company->id])->assertSessionHasNoErrors();
        $this->assertSame(3,$case->quotes()->count());$this->post(route('quotes.request',$case),$data+['supplier_company_id'=>$other->id])->assertSessionHasErrors('quote');
    }
    public function test_quote_requires_document_then_customer_approval_with_evidence_and_cannot_skip_states(): void
    {
        $case=$this->case();$quote=app(InsuranceQuoteWorkflow::class)->request($case,$this->company->id,'2026-10-15');
        $this->post(route('quotes.change',[$quote,'submit']),['confirmed'=>1])->assertSessionHasErrors('quote');
        $this->post(route('quotes.change',[$quote,'approve']),['confirmed'=>1,'approved_on'=>'2026-10-16'])->assertSessionHasErrors('files');
        $this->assertSame('requested',$quote->fresh()->status);$this->assertSame(0,$quote->documents()->count());
    }
    public function test_conversion_uses_approved_premium_links_all_documents_and_cannot_be_duplicated(): void
    {
        $quote=$this->approve();$data=$this->policyInput();$data+=['client_id'=>$this->client->id,'entry_mode'=>'new','insurance_quote_id'=>$quote->id,'premium'=>'1.00','number'=>'SA-2026-502'];
        $this->get(route('policies.create',['quote_id'=>$quote->id]))->assertOk()->assertSee('800,00 €');
        $this->post(route('policies.store'),$data)->assertSessionHasNoErrors();$policy=$this->client->policies()->where('number',$data['number'])->sole();
        $this->assertSame('800.00',$policy->premium);$this->assertSame('Auto completa',$policy->product);$this->assertSame(2,$policy->documents()->count());$this->assertSame('converted',$quote->fresh()->status);
        $this->post(route('policies.store'),array_replace($data,['number'=>'SA-2026-503']))->assertSessionHasErrors('quote');$this->assertSame(1,InsurancePolicy::where('insurance_quote_id',$quote->id)->count());
        $this->get(route('quotes.show',$quote->insurance_quote_case_id))->assertOk()->assertSee('SA-2026-502');
    }
    public function test_quote_cannot_be_used_for_another_client_or_after_expiry_and_history_is_private(): void
    {
        $quote=$this->approve();$other=Client::factory()->create();$data=$this->policyInput()+['client_id'=>$other->id,'entry_mode'=>'new','insurance_quote_id'=>$quote->id,'number'=>'SA-2026-503'];
        $this->post(route('policies.store'),$data)->assertSessionHasErrors('quote');$data['client_id']=$this->client->id;
        $quote->update(['valid_until'=>'2026-10-15']);$this->post(route('policies.store'),$data)->assertSessionHasErrors('quote');
        $employee=User::factory()->create(['role'=>UserRole::Operator]);$this->actingAs($employee);$this->get(route('quotes.show',$quote->insurance_quote_case_id))->assertOk();$this->get(route('attachments.download',$quote->documents()->first()))->assertOk();
    }
    public function test_employee_can_convert_own_approved_proposal_but_cannot_bypass_approval_as_historical_entry(): void
    {
        $employee=User::factory()->create(['role'=>UserRole::Operator]);$this->client->update(['commercial_user_id'=>$employee->id]);$this->actingAs($employee);$quote=$this->approve();
        $data=$this->policyInput()+['client_id'=>$this->client->id,'entry_mode'=>'existing','existing_reason'=>'Archivio pregresso','number'=>'SA-2026-504'];
        $this->post(route('policies.store'),$data)->assertSessionHasErrors('entry_mode');
        $this->post(route('policies.store'),array_replace($data,['entry_mode'=>'new','insurance_quote_id'=>$quote->id]))->assertSessionHasNoErrors();
    }
    public function test_closed_policy_is_locked_from_next_day_except_for_admin(): void
    {
        $employee=User::factory()->create(['role'=>UserRole::Operator]);$this->client->update(['commercial_user_id'=>$employee->id]);$this->policy->forceFill(['work_closed_at'=>today()->subDay(),'work_closed_by'=>$employee->id,'status'=>'active'])->save();
        $this->actingAs($employee)->get(route('clients.policies.edit',[$this->client,$this->policy]))->assertForbidden();
        $this->patch(route('clients.policies.update',[$this->client,$this->policy]),['notes'=>'Cambio tardivo'])->assertForbidden();
        $this->actingAs($this->admin)->get(route('clients.policies.edit',[$this->client,$this->policy]))->assertOk();
    }
    public function test_new_pages_filters_and_exports_render_with_real_relations(): void
    {
        [$title]=$this->generate();$this->post(route('titles.collect',$title),$this->payment(100));$quote=$this->approve();
        foreach(['quotes.index'=>[],'quotes.create'=>[],'quotes.show'=>[$quote->insurance_quote_case_id],'titles.index'=>[],'titles.show'=>[$title],'titles.create'=>[$this->policy],'titles.renewal'=>[$this->policy],'collections.index'=>['month'=>'2026-10','company'=>$this->company->id,'category'=>$this->branch->name,'state'=>'recorded']] as $route=>$params)$this->get(route($route,$params))->assertOk();
        $this->get(route('collections.index',['month'=>'2026-11']))->assertOk()->assertViewHas('collections',fn($r)=>$r->isEmpty());
        $this->get(route('titles.index',['_table_all'=>1]))->assertOk()->assertViewHas('titles',fn($r)=>$r->total()===2);
    }
    private function mailFixture(): array
    {
        $agency=SupplierAgency::create(['name'=>'Agenzia Valserena','email'=>'agenzia@example.test','status'=>'active','supplier_company_id'=>$this->company->id]);$this->policy->update(['supplier_agency_id'=>$agency->id]);
        \App\Models\OutboundMailSetting::create(['credentials'=>['enabled'=>true,'host'=>'smtp.example.test','port'=>587,'encryption'=>'starttls','username'=>'sender@example.test','password'=>Str::random(32),'from_address'=>'sender@example.test','from_name'=>'Soluzioni Assicurative SAS'],'updated_by'=>$this->admin->id]);
        [$title]=$this->generate();$this->post(route('titles.collect',$title),$this->payment(10000))->assertSessionHasNoErrors();$this->post(route('titles.collect',$title),$this->payment(5000,['payment_method'=>'Bonifico a Compagnia','recipient'=>'company']))->assertSessionHasNoErrors();
        return [$agency,$title,$title->collections()->get()];
    }
    private function reviewCollections(SupplierAgency $agency,array $ids): \App\Models\AgencyCommunication
    {
        $this->post(route('agency-communications.collections-review',$agency),['from'=>'2026-10-16','to'=>'2026-10-16','collections'=>$ids])->assertSessionHasNoErrors();return \App\Models\AgencyCommunication::latest('id')->firstOrFail();
    }
    public function test_collection_email_includes_actual_methods_and_amounts_and_claims_each_movement_once(): void
    {
        [$agency,$title,$payments]=$this->mailFixture();$first=$this->reviewCollections($agency,$payments->modelKeys());$second=$this->reviewCollections($agency,$payments->modelKeys());
        $this->assertSame('1.000,01 €',$first->payload['policies'][0]['premium']);
        $this->assertStringContainsString('Premio della polizza: 1.000,01 €.', $first->payload['body']);
        $this->mock(\App\Services\AgencyEmailTransport::class)->shouldReceive('send')->once()->withArgs(function($payload){$this->assertSame('collections',$payload['kind']);$this->assertCount(2,$payload['collections']);$this->assertStringContainsString('100,00 €',$payload['body']);$this->assertStringContainsString('Bonifico a Compagnia',$payload['body']);$this->assertStringContainsString('residuo del titolo: 350,00 €',$payload['body']);return true;})->andReturn('accepted@example.test');
        $this->get(route('agency-communications.collections'))->assertOk()->assertSee('Rate e incassi');$this->get(route('agency-communications.show',$first))->assertOk()->assertSee('100,00 €');
        $this->post(route('agency-communications.send',$first),['reviewed'=>1])->assertSessionHasNoErrors();$this->post(route('agency-communications.send',$first),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->post(route('agency-communications.send',$second),['reviewed'=>1])->assertSessionHasErrors('email');$this->assertSame('sent',$first->fresh()->status);
        $this->assertSame(2,\Illuminate\Support\Facades\DB::table('agency_communication_collections')->whereNotNull('delivery_key')->count());
    }
    public function test_storno_invalidates_review_and_cross_agency_collection_selection_is_rejected(): void
    {
        [$agency,$title,$payments]=$this->mailFixture();$message=$this->reviewCollections($agency,$payments->modelKeys());
        $this->mock(\App\Services\AgencyEmailTransport::class)->shouldNotReceive('send');
        $other=SupplierAgency::create(['name'=>'Agenzia Fonteluce','email'=>'altra@example.test','status'=>'active']);
        $this->post(route('agency-communications.collections-review',$other),['collections'=>$payments->modelKeys()])->assertSessionHasErrors('collections');
        $this->post(route('collections.void',$payments->first()),['reason'=>'Incasso registrato per errore'])->assertSessionHasNoErrors();
        $this->post(route('agency-communications.send',$message),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->assertSame(0,\Illuminate\Support\Facades\DB::table('agency_communication_collections')->whereNotNull('delivery_key')->count());
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator]))->get(route('agency-communications.collections'))->assertForbidden();
    }
}
