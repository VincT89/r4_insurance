<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AgencyCommunication, Client, InsuranceBranch, InsuranceFormPackage, InsuranceFormTemplate, InsurancePolicy, InsuranceTitle, OutboundMailSetting, SupplierAgency, SupplierCompany, User};
use App\Services\{AgencyCommissionRegistry, AgencyEmailTransport, CollectionCommunicationReminders, DeadlineReminders, InsuranceForms};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InsuranceLifecycleFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private Client $client;
    private InsurancePolicy $policy;
    private SupplierAgency $agency;
    private SupplierCompany $company;
    private InsuranceBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        Storage::fake('attachments');
        $this->admin = User::factory()->create(['role'=>UserRole::Admin, 'status'=>'active']);
        $this->operator = User::factory()->create(['role'=>UserRole::Operator, 'status'=>'active']);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['name'=>'Elena Varesi', 'client_type'=>'person']);
        $this->company = SupplierCompany::create(['name'=>'Nobis', 'status'=>'active']);
        $this->agency = SupplierAgency::create(['name'=>'Agenzia Valserena', 'email'=>'valserena@example.test', 'status'=>'active']);
        $this->agency->companies()->attach($this->company);
        $this->branch = InsuranceBranch::create(['name'=>'RC auto', 'name_key'=>hash('sha256', 'RC AUTO'), 'status'=>'active']);
        $this->policy = $this->client->policies()->create([
            'number'=>'SA-2025-0481', 'category'=>$this->branch->name, 'insurance_branch_id'=>$this->branch->id,
            'company'=>$this->company->name, 'supplier_company_id'=>$this->company->id, 'supplier_agency_id'=>$this->agency->id,
            'starts_on'=>'2025-11-04', 'expires_on'=>'2026-11-04', 'premium'=>'1000.00', 'status'=>'active',
            'payment_frequency'=>'Semestrale', 'payment_method'=>'Bonifico a Intermediario', 'tacit_renewal'=>true,
        ]);
        $this->policy->forceFill(['created_at'=>'2025-11-04 10:00:00'])->save();
        OutboundMailSetting::create(['credentials'=>[
            'enabled'=>true, 'host'=>'smtp.example.test', 'port'=>587, 'encryption'=>'starttls',
            'username'=>'sender@example.test', 'password'=>Str::random(32),
            'from_address'=>'sender@example.test', 'from_name'=>'Soluzioni Assicurative SAS',
        ], 'updated_by'=>$this->admin->id]);
    }

    private function conditions(string $from, ?string $to, string $renewalRate = '8'): void
    {
        app(AgencyCommissionRegistry::class)->save($this->agency, $this->admin, [
            'supplier_company_id'=>$this->company->id, 'valid_from'=>$from, 'valid_to'=>$to,
            'mandate_reference'=>'CONDIZIONI DI COLLAUDO', 'accrual_event'=>'collection', 'collection_mode'=>'proportional',
            'entries'=>[['branch_id'=>$this->branch->id, 'subbranch_id'=>null, 'selected'=>1, 'basis'=>'gross_premium',
                'issue_rate'=>'10', 'renewal_rate'=>$renewalRate, 'quietance_rate'=>'5']],
        ]);
    }

    private function generate(): \Illuminate\Database\Eloquent\Collection
    {
        $preview = $this->get(route('titles.schedule', $this->policy))->assertOk();
        $this->post(route('titles.generate', $this->policy), ['confirmed'=>1, 'fingerprint'=>$preview->viewData('fingerprint')])->assertSessionHasNoErrors();
        return $this->policy->titles()->orderBy('id')->get();
    }

    private function collect(InsuranceTitle $title, int $cents): \App\Models\InsuranceCollection
    {
        $this->post(route('titles.collect', $title), [
            'received_on'=>today()->toDateString(), 'amount'=>\App\Support\Money::decimal($cents),
            'payment_method'=>'Bonifico a Compagnia', 'recipient'=>'company', 'document_type'=>'transfer',
            'idempotency_key'=>(string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        return $title->collections()->latest('id')->firstOrFail();
    }

    private function renewal(): array
    {
        return ['starts_on'=>'2026-11-04', 'expires_on'=>'2027-11-04', 'premium'=>'1200.00',
            'payment_frequency'=>'Semestrale', 'payment_method'=>'Bonifico a Intermediario'];
    }

    private function renew(): void
    {
        $data = $this->renewal();
        $preview = $this->post(route('titles.renewal-preview', $this->policy), $data)->assertOk();
        $this->post(route('titles.renew', $this->policy), $data + ['confirmed'=>1, 'fingerprint'=>$preview->viewData('fingerprint')])->assertSessionHasNoErrors();
    }

    private function values(string $status): array
    {
        $policy = $this->policy->fresh();
        return ['policy_revision'=>$policy->editFingerprint(), 'category'=>$policy->category, 'company'=>$policy->company,
            'supplier_agency_id'=>$policy->supplier_agency_id, 'number'=>$policy->number, 'status'=>$status,
            'starts_on'=>$policy->starts_on->toDateString(), 'expires_on'=>$policy->expires_on->toDateString(),
            'premium'=>$policy->premium, 'payment_frequency'=>$policy->payment_frequency, 'payment_method'=>$policy->payment_method];
    }

    private function package(): InsuranceFormPackage
    {
        $template = InsuranceFormTemplate::create([
            'family'=>(string) Str::uuid(), 'version'=>1, 'title'=>'Riepilogo tecnico di collaudo',
            'category'=>'consistency', 'scope'=>'policy', 'client_type'=>'all', 'body'=>"Documento di collaudo, privo di valore contrattuale.\nPolizza: {{numero_polizza}}\nScadenza: {{scadenza}}",
            'signature_required'=>false, 'required'=>false, 'status'=>'active', 'approved_by'=>$this->admin->id, 'approved_at'=>now(),
        ]);
        return app(InsuranceForms::class)->createPackage($this->client, $this->policy->fresh(), [
            'templates'=>[$template->id], 'reason'=>'initial', 'request_key'=>(string) Str::uuid(),
            'context_hash'=>app(InsuranceForms::class)->contextHash($this->client, $this->policy->fresh()),
            'signer_name'=>$this->client->name, 'place'=>'Napoli', 'document_date'=>today()->toDateString(), 'confirmed'=>1,
        ]);
    }

    public function test_renewal_preserves_previous_period_and_updates_documents_commissions_reminders_and_daily_communications(): void
    {
        $this->conditions('2025-01-01', '2026-11-03');
        $this->conditions('2026-11-04', null, '12');
        [$first, $second] = $this->generate();
        $this->collect($first, 50000);
        $this->collect($second, 50000);
        $oldCommission = $first->commission()->sole()->getAttributes();
        $package = $this->package();
        app(DeadlineReminders::class)->forUser($this->admin);
        $this->assertSame(1, $this->admin->visibleNotifications()->where('data->kind', 'policy')->count());
        $this->renew();
        $policy = $this->policy->fresh();
        $this->assertSame('2026-10-20', $policy->last_renewed_at?->toDateString());
        $this->assertSame('2025-11-04', $policy->created_at->toDateString());
        $this->assertSame('2026-11-04', $policy->coverage_until->toDateString());
        $this->assertSame($oldCommission, $first->commission()->sole()->getAttributes());
        $titles = $policy->titles()->orderBy('id')->get();
        $this->assertCount(4, $titles);
        $this->assertSame('renewal', $titles[2]->kind);
        $this->assertSame('quietance', $titles[3]->kind);
        $this->assertSame(7200, $titles[2]->commission->expected_cents);
        $this->assertSame(3000, $titles[3]->commission->expected_cents);
        $this->assertSame(0, $titles[2]->commission->accrued_cents);
        $this->assertSame('Dati aggiornati: predisporre nuovi moduli', $package->fresh()->state_label);
        $this->assertDatabaseCount('insurance_form_packages', 1);
        $this->assertDatabaseCount('insurance_collections', 2);
        $this->assertSame(0, $this->admin->visibleNotifications()->where('data->kind', 'policy')->count());
        app(DeadlineReminders::class)->forUser($this->admin);
        $this->assertSame($titles[2]->id, $this->admin->visibleNotifications()->where('data->kind', 'title')->sole()->data['source_id']);
        $this->get(route('agency-communications.index', ['period'=>'day', 'date'=>'2026-10-20']))
            ->assertOk()->assertViewHas('policies', fn ($rows) => $rows->pluck('id')->all() === [$policy->id])->assertSee('Ultimo rinnovo');
        $this->get(route('agency-communications.index', ['period'=>'day', 'date'=>'2026-10-20', 'operation'=>'created']))
            ->assertViewHas('policies', fn ($rows) => $rows->total() === 0);
        $this->get(route('agency-communications.index', ['period'=>'day', 'date'=>'2026-10-20', 'operation'=>'renewed']))
            ->assertViewHas('policies', fn ($rows) => $rows->total() === 1);
        $this->get(route('agency-communications.index', ['period'=>'month', 'month'=>'2026-10', 'operation'=>'renewed', 'company'=>'Altra compagnia']))
            ->assertViewHas('policies', fn ($rows) => $rows->total() === 0);
        $this->post(route('agency-communications.review', $this->agency), ['period'=>'day', 'date'=>'2026-10-20', 'operation'=>'created', 'policies'=>[$policy->id]])->assertSessionHasErrors('policies');
        $this->post(route('agency-communications.review', $this->agency), ['period'=>'day', 'date'=>'2026-10-20', 'policies'=>[$policy->id]])->assertSessionHasNoErrors();
        $message = AgencyCommunication::where('status', 'review')->sole();
        $this->mock(AgencyEmailTransport::class)->shouldReceive('send')->once()
            ->withArgs(fn ($payload) => str_contains($payload['body'], 'Rinnovo registrato il 20/10/2026'))->andReturn('accepted@example.test');
        $this->post(route('agency-communications.send', $message), ['reviewed'=>1])->assertSessionHasNoErrors();
        $this->assertSame('sent', $message->fresh()->status);
        $this->get(route('agency-communications.index', ['period'=>'day', 'date'=>'2026-10-20', '_table_all'=>1]), ['X-Table-Export'=>'1'])
            ->assertViewHas('policies', fn ($rows) => $rows->total() === 1);
    }

    public function test_a_changed_policy_or_client_requires_a_new_renewal_preview_and_work_lock_remains(): void
    {
        $this->generate();
        $preview = $this->post(route('titles.renewal-preview', $this->policy), $this->renewal())->assertOk();
        $this->policy->update(['number'=>'SA-2025-0482']);
        $this->post(route('titles.renew', $this->policy), $this->renewal() + ['confirmed'=>1, 'fingerprint'=>$preview->viewData('fingerprint')])->assertSessionHasErrors('ledger');
        $this->assertDatabaseCount('insurance_titles', 2);
        $preview = $this->post(route('titles.renewal-preview', $this->policy), $this->renewal())->assertOk();
        $this->client->update(['name'=>'Elena Varesi Corretta']);
        $this->post(route('titles.renew', $this->policy), $this->renewal() + ['confirmed'=>1, 'fingerprint'=>$preview->viewData('fingerprint')])->assertSessionHasErrors('ledger');
        $this->policy->forceFill(['work_closed_at'=>today()->subDay(), 'work_closed_by'=>$this->operator->id])->save();
        $this->actingAs($this->operator);
        $this->get(route('titles.renewal', $this->policy))->assertForbidden();
        $this->post(route('titles.renewal-preview', $this->policy), $this->renewal())->assertForbidden();
        $this->post(route('titles.renew', $this->policy), $this->renewal() + ['confirmed'=>1, 'fingerprint'=>$preview->viewData('fingerprint')])->assertForbidden();
        $this->actingAs($this->admin);
        $this->renew();
        $this->assertSame('2026-10-19', $this->policy->fresh()->work_closed_at->toDateString());
        $this->assertFalse($this->policy->fresh()->editableBy($this->operator));
        $this->assertDatabaseHas('audit_logs', ['action'=>'policy_renewed', 'user_id'=>$this->admin->id]);
    }

    public function test_renewal_keeps_the_contract_number_and_rejects_a_different_number(): void
    {
        $this->generate();
        $originalNumber = $this->policy->number;
        $changed = $this->renewal() + ['number'=>'SA-2026-0931'];
        $this->post(route('titles.renewal-preview', $this->policy), $changed)->assertSessionHasErrors('number');
        $preview = $this->post(route('titles.renewal-preview', $this->policy), $this->renewal())->assertOk();
        $this->post(route('titles.renew', $this->policy), $changed + ['confirmed'=>1, 'fingerprint'=>$preview->viewData('fingerprint')])
            ->assertSessionHasErrors('number');
        $this->assertSame($originalNumber, $this->policy->fresh()->number);
        $this->assertSame('2026-11-04', $this->policy->fresh()->expires_on->toDateString());
        $this->assertDatabaseCount('insurance_titles', 2);
        $this->renew();
        $this->assertSame($originalNumber, $this->policy->fresh()->number);
        $this->assertSame('2027-11-04', $this->policy->fresh()->expires_on->toDateString());
        $this->assertDatabaseCount('insurance_policies', 1);
        $this->assertDatabaseCount('insurance_titles', 4);
    }

    public function test_title_generation_preview_is_bound_to_the_policy_and_customer(): void
    {
        $preview = $this->get(route('titles.schedule', $this->policy))->assertOk();
        $other = $this->policy->replicate();
        $other->number = 'SA-2025-0483';
        $other->save();
        $this->post(route('titles.generate', $other), ['confirmed'=>1, 'fingerprint'=>$preview->viewData('fingerprint')])->assertSessionHasErrors('ledger');
        $this->assertDatabaseCount('insurance_titles', 0);
        $this->client->update(['name'=>'Elena Varesi Corretta']);
        $this->post(route('titles.generate', $this->policy), ['confirmed'=>1, 'fingerprint'=>$preview->viewData('fingerprint')])->assertSessionHasErrors('ledger');
    }

    public function test_cancellation_preserves_receipts_and_reversal_reopens_debt_and_reconciles_paid_commission(): void
    {
        $this->conditions('2025-01-01', null);
        [$title] = $this->generate();
        $this->actingAs($this->operator);
        $payment = $this->collect($title, 50000);
        $this->actingAs($this->admin);
        $commission = $title->commission()->sole();
        $this->post(route('commissions.record', [$title, 'settlement']), [
            'version'=>$commission->version, 'amount'=>'50.00', 'effective_on'=>today()->toDateString(),
            'reference'=>'REND-COLLAUDO', 'reason'=>'Liquidazione simulata per verifica del flusso',
            'idempotency_key'=>(string) Str::uuid(), 'confirmed'=>1,
        ])->assertSessionHasNoErrors();
        $this->post(route('agency-communications.collections-review', $this->agency), ['scope'=>'pending', 'collections'=>[$payment->id]])->assertSessionHasNoErrors();
        $message = AgencyCommunication::where('status', 'review')->sole();
        $this->mock(AgencyEmailTransport::class)->shouldReceive('send')->once()->andReturn('accepted@example.test');
        $this->post(route('agency-communications.send', $message), ['reviewed'=>1])->assertSessionHasNoErrors();
        $this->actingAs($this->operator);
        $this->patch(route('clients.policies.update', [$this->client, $this->policy]), $this->values('cancelled'))->assertSessionHasNoErrors();
        $this->assertSame(50000, $title->fresh()->collected_cents);
        $this->assertSame(5000, $commission->fresh()->settled_cents);
        $this->assertSame(5000, $commission->fresh()->accrued_cents);
        $this->assertNull($payment->fresh()->voided_at);
        $this->get(route('clients.policies.show', [$this->client, $this->policy]))->assertOk()->assertSee('Verifica contabile')->assertDontSee('Prepara rate e quietanze')
            ->assertViewHas('accountingReview', fn ($review) => (int) $review->open_titles === 1 && (int) $review->balance_cents === 50000);
        $this->get(route('titles.show', $title))->assertOk()->assertSee('La polizza è annullata');
        $this->post(route('titles.cancel', $title), ['reason'=>'Titolo ancora incassato'])->assertSessionHasErrors('ledger');
        $this->post(route('collections.void', $payment), ['reason'=>'Incasso attribuito alla polizza errata'])->assertSessionHasNoErrors();
        $this->assertSame(0, $commission->fresh()->accrued_cents);
        $this->assertSame(5000, $commission->fresh()->settled_cents);
        $this->assertSame('reconcile', $commission->fresh()->state);
        $this->assertNull($this->policy->fresh()->coverage_until);
        $this->assertSame(1, $this->admin->visibleNotifications()->where('type', CollectionCommunicationReminders::REVERSAL_TYPE)->count());
        $this->post(route('titles.cancel', $title), ['reason'=>'Titolo non dovuto confermato dalla compagnia'])->assertSessionHasNoErrors();
        $this->assertSame(0, $commission->fresh()->expected_cents);
        $this->assertSame('reconcile', $commission->fresh()->state);
        $this->assertDatabaseHas('audit_logs', ['action'=>'collection_voided', 'user_id'=>$this->operator->id]);
        $this->assertDatabaseHas('audit_logs', ['action'=>'title_cancelled', 'user_id'=>$this->operator->id]);
        $this->assertSame('sent', $message->fresh()->status);
        $this->actingAs($this->admin);
        $settlement = $commission->movements()->where('kind', 'settlement')->where('amount_cents', '>', 0)->sole();
        $this->post(route('commissions.reverse', [$title, $settlement]), [
            'version'=>$commission->fresh()->version, 'effective_on'=>today()->toDateString(),
            'reason'=>'Rettifica concordata del rendiconto simulato', 'confirmed'=>1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $commission->fresh()->settled_cents);
        $this->assertSame('cancelled', $commission->fresh()->state);
        $this->assertDatabaseCount('insurance_collections', 1);
        $this->assertDatabaseCount('insurance_titles', 2);
    }

    public function test_replaced_policy_keeps_documents_and_outstanding_titles_without_renewal_or_policy_email(): void
    {
        [$title] = $this->generate();
        $package = $this->package();
        $this->collect($title, 10000);
        app(DeadlineReminders::class)->forUser($this->admin);
        $this->patch(route('clients.policies.update', [$this->client, $this->policy]), $this->values('replaced'))->assertSessionHasNoErrors();
        $this->assertSame(40000, $title->fresh()->balance_cents);
        $this->assertSame(0, $this->admin->visibleNotifications()->where('data->kind', 'policy')->count());
        $this->assertSame(2, $this->admin->visibleNotifications()->where('data->kind', 'title')->count());
        $this->assertSame('Dati aggiornati: predisporre nuovi moduli', $package->fresh()->state_label);
        $this->post(route('titles.renewal-preview', $this->policy), $this->renewal())->assertSessionHasErrors('ledger');
        $this->get(route('clients.policies.show', [$this->client, $this->policy]))->assertOk()->assertSee('Verifica contabile')->assertDontSee('Prepara rate e quietanze')
            ->assertSee('900,00 €')->assertViewHas('accountingReview', fn ($review) => (int) $review->open_titles === 2 && (int) $review->balance_cents === 90000);
        $this->delete(route('clients.policies.destroy', [$this->client, $this->policy]))->assertSessionHasErrors('policy');
        $this->post(route('agency-communications.review', $this->agency), [
            'period'=>'custom', 'from'=>'2025-11-04', 'to'=>'2026-10-20', 'policies'=>[$this->policy->id],
        ])->assertSessionHasErrors('policies');
        $this->assertDatabaseCount('insurance_policies', 1);
        $this->assertDatabaseCount('insurance_collections', 1);
        $this->assertDatabaseCount('insurance_form_packages', 1);
    }

    public function test_stale_review_and_duplicate_renewal_cannot_send_or_create_extra_titles(): void
    {
        $this->generate();
        $this->post(route('agency-communications.review', $this->agency), [
            'period'=>'custom', 'from'=>'2025-11-04', 'to'=>'2026-10-20', 'policies'=>[$this->policy->id],
        ])->assertSessionHasNoErrors();
        $message = AgencyCommunication::where('status','review')->sole();
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
        $data = $this->renewal();
        $preview = $this->post(route('titles.renewal-preview', $this->policy), $data)->assertOk();
        $confirmation = $data + ['confirmed'=>1, 'fingerprint'=>$preview->viewData('fingerprint')];
        $this->post(route('titles.renew', $this->policy), $confirmation)->assertSessionHasNoErrors();
        $this->post(route('titles.renew', $this->policy), $confirmation)->assertSessionHasErrors('ledger');
        $this->assertDatabaseCount('insurance_titles', 4);
        $this->post(route('agency-communications.send', $message), ['reviewed'=>1])->assertSessionHasErrors('email');
        $this->assertSame('review', $message->fresh()->status);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('agency_communication_policies')->whereNotNull('delivery_key')->count());
        $this->patch(route('clients.policies.update', [$this->client, $this->policy]),
            $this->values('renewed') + ['last_renewed_at'=>'2020-01-01'])->assertSessionHasErrors('last_renewed_at');
    }

    public function test_paid_renewal_does_not_bridge_a_reversed_prior_period_and_reports_follow_actual_movements(): void
    {
        $this->conditions('2025-01-01', null);
        [$first, $second] = $this->generate();
        $this->collect($first, 50000);
        $priorPayment = $this->collect($second, 50000);
        $this->renew();
        $renewalTitle = $this->policy->titles()->where('kind','renewal')->sole();
        $this->collect($renewalTitle, 60000);
        $this->assertSame('2027-05-04', $this->policy->fresh()->coverage_until->toDateString());
        $this->assertSame(4800, $renewalTitle->commission()->sole()->accrued_cents);
        $this->post(route('collections.void', $priorPayment), ['reason'=>'Pagamento del periodo precedente attribuito per errore'])->assertSessionHasNoErrors();
        $this->assertSame('2026-05-04', $this->policy->fresh()->coverage_until->toDateString());
        $this->assertSame(4800, $renewalTitle->commission()->sole()->accrued_cents);
        $this->assertSame(0, $second->commission()->sole()->accrued_cents);
        $this->get(route('collections.index', ['month'=>'2026-10', 'policy'=>$this->policy->id]))
            ->assertOk()->assertViewHas('totals', fn ($totals) => (int)$totals->total === 110000 && (int)$totals->ours === 0);
        $this->get(route('commissions.index', ['mode'=>'movements', 'month'=>'2026-10']))
            ->assertOk()->assertViewHas('totals', fn ($totals) => (int)$totals->accrued === 9800 && (int)$totals->settled === 0);
        $this->collect($second, 50000);
        $this->assertSame('2027-05-04', $this->policy->fresh()->coverage_until->toDateString());
        $this->assertDatabaseCount('insurance_collections', 4);
    }
}
