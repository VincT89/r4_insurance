<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsuranceCollection, InsurancePolicy, InsurancePolicyChange, InsuranceTitle, SupplierAgency, SupplierCompany, User};
use App\Services\{InsuranceLedger, PolicyContractTransitions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PolicyContractTransitionsTest extends TestCase
{
    use RefreshDatabase;

    private InsurancePolicy $source;
    private SupplierCompany $company;
    private SupplierAgency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,11,2)->startOfDay());
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']));
        Storage::fake('attachments');
        $this->company=SupplierCompany::create(['name'=>'Compagnia dimostrativa A','status'=>'active']);
        $this->agency=SupplierAgency::create(['name'=>'Agenzia dimostrativa A','status'=>'active']);
        $this->company->agencies()->attach($this->agency);
        $this->source=Client::factory()->create()->policies()->create([
            'company'=>$this->company->name,'supplier_company_id'=>$this->company->id,'supplier_agency_id'=>$this->agency->id,
            'category'=>'RC auto','number'=>'DEMO-PREV-1','starts_on'=>'2026-01-01','expires_on'=>'2027-01-01',
            'premium'=>'1200.00','payment_frequency'=>'Semestrale','payment_method'=>'Contanti a Intermediario','status'=>'active',
        ])->fresh();
    }

    private function input(string $kind, array $extra=[]): array
    {
        return array_replace([
            'number'=>$kind === 'renewal' ? $this->source->number : 'DEMO-NUOVA-2', 'company'=>$this->company->name,'supplier_agency_id'=>$this->agency->id,
            'category'=>'RC auto','starts_on'=>$kind === 'renewal' ? '2027-01-01' : '2026-11-02','expires_on'=>$kind === 'renewal' ? '2028-01-01' : '2027-11-02',
            'premium'=>'1000.00','payment_frequency'=>'Semestrale','payment_method'=>'Contanti a Intermediario','adjustment'=>'none','adjustment_policy'=>'new',
            'source_revision'=>app(PolicyContractTransitions::class)->revision($this->source->fresh()),
            'request_key'=>(string)Str::uuid(),'contract_reviewed'=>1,
            'contract'=>UploadedFile::fake()->createWithContent('contratto-demo.pdf',"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"),
        ],$extra);
    }

    private function preview(string $kind,array $input): array
    {
        return $this->postJson(route('policy-transitions.preview',[$this->source,$kind]),$input)->assertOk()->json();
    }

    private function save(string $kind,array $input)
    {
        $input['schedule_fingerprint']=$this->preview($kind,$input)['fingerprint'];
        return $this->postJson(route('policy-transitions.store',[$this->source,$kind]),$input);
    }

    private function title(int $paid=0): InsuranceTitle
    {
        return $this->source->titles()->create(['kind'=>'emission','coverage_from'=>'2026-01-01','coverage_to'=>'2027-01-01','due_on'=>'2026-01-01','amount_cents'=>120000,'collected_cents'=>$paid,'payment_frequency'=>'Annuale','payment_method'=>'Contanti a Intermediario']);
    }

    public function test_operator_can_start_new_cycle_after_closed_work_without_unlocking_previous_edits(): void
    {
        $this->source->forceFill(['work_closed_at'=>now()->subDays(2),'work_closed_by'=>auth()->id()])->save();
        $this->get(route('clients.policies.edit',[$this->source->client,$this->source]))->assertForbidden();
        foreach (['renewal','replacement'] as $kind) $this->get(route('policy-transitions.create',[$this->source,$kind]))->assertOk()->assertSee('Carica il contratto');
        $result=$this->save('renewal',$this->input('renewal'))->assertOk();
        $this->assertNull($this->source->fresh()->work_closed_at);
        $this->assertSame('renewed',$this->source->fresh()->status);
        $this->assertDatabaseCount('insurance_titles',2);
        $change=InsurancePolicyChange::sole();
        $this->assertNotNull($change->snapshot['previous_work']['work_closed_at']);
        $this->get($result->json('url'))->assertOk()->assertSee('Apri contratto')->assertSee('Titoli e pagamenti');
        Storage::disk('attachments')->assertExists($change->documents()->sole()->path);
    }

    public function test_operator_explicitly_chooses_replacement_of_first_title_or_additional_charge(): void
    {
        foreach (['replace_first'=>[9000,50000],'additional'=>[50000,50000,9000]] as $mode=>$amounts) {
            $data=$this->input('replacement',['adjustment'=>'charge','adjustment_amount'=>'90','charge_mode'=>$mode,'adjustment_due_on'=>'2026-11-02','adjustment_reference'=>'Comunicazione compagnia del 02/11']);
            $preview=$this->preview('replacement',$data);
            $this->assertSame($amounts,array_column($preview['rows'],'amount_cents'));
        }
        $data['charge_mode']=null;
        $this->postJson(route('policy-transitions.preview',[$this->source,'replacement']),$data)->assertUnprocessable()->assertJsonValidationErrors('charge_mode');
        $this->assertDatabaseCount('insurance_titles',0);
    }

    public function test_replacement_preserves_received_money_and_cancels_only_selected_residual(): void
    {
        $title=$this->title(30000);
        $data=$this->input('replacement',['cancel_titles'=>[$title->id],'cancellation_reason'=>'Residuo annullato dalla compagnia','adjustment'=>'charge','adjustment_amount'=>'75','charge_mode'=>'additional','adjustment_due_on'=>'2026-11-02','adjustment_reference'=>'Appendice di sostituzione']);
        $result=$this->save('replacement',$data)->assertOk();
        $title->refresh();
        $this->assertSame(30000,$title->collected_cents);
        $this->assertSame(120000,$title->amount_cents);
        $this->assertSame(90000,$title->cancelled_residual_cents);
        $this->assertSame(0,$title->balance_cents);
        $this->assertSame('replaced',$this->source->fresh()->status);
        $change=InsurancePolicyChange::sole();
        $this->assertSame(3,$change->titles()->count());
        $this->assertSame('active',$change->resultPolicy->status);
        $this->assertDatabaseCount('insurance_collections',0);
        $this->get($result->json('url'))->assertOk()->assertSee('Residui annullati');
        $this->get(route('titles.show',$title))->assertOk()->assertSee('Incassi conservati');
    }

    public function test_new_payment_invalidates_transition_review_without_mutating_any_contract(): void
    {
        $title=$this->title();$data=$this->input('replacement');
        $data['schedule_fingerprint']=$this->preview('replacement',$data)['fingerprint'];
        $title->update(['collected_cents'=>120000]);
        $this->postJson(route('policy-transitions.store',[$this->source,'replacement']),$data)->assertUnprocessable()->assertJsonValidationErrors('source_revision');
        $this->assertDatabaseCount('insurance_policies',1);$this->assertDatabaseCount('insurance_policy_changes',0);
        $this->assertSame('active',$this->source->fresh()->status);$this->assertCount(0,Storage::disk('attachments')->allFiles());
    }

    public function test_renewal_keeps_historical_company_agency_and_number_on_paid_title(): void
    {
        $title=$this->title(120000);
        $payment=$title->collections()->create(['amount_cents'=>120000,'received_on'=>'2026-11-01','payment_method'=>'Contanti a Intermediario','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Str::uuid(),'request_fingerprint'=>str_repeat('a',64)]);
        $newCompany=SupplierCompany::create(['name'=>'Compagnia dimostrativa B','status'=>'active']);
        $newAgency=SupplierAgency::create(['name'=>'Agenzia dimostrativa B','status'=>'active']);$newCompany->agencies()->attach($newAgency);
        $this->save('renewal',$this->input('renewal',['company'=>$newCompany->name,'supplier_agency_id'=>$newAgency->id,'number'=>'DEMO-RINNOVO-B']))->assertOk();
        $title->refresh();$this->assertSame($this->agency->id,$title->supplier_agency_id);
        $this->assertSame('DEMO-PREV-1',$title->contract->number);$this->assertSame($this->company->name,$title->contract->company);
        $this->get(route('collections.index',['agency'=>$this->agency->id,'month'=>'']))->assertOk()->assertSee('DEMO-PREV-1')->assertSee('1.200,00');
        $this->get(route('collections.index',['agency'=>$newAgency->id,'month'=>'']))->assertOk()->assertDontSee('DEMO-PREV-1');
        $this->assertEquals(120000,$payment->fresh()->amount_cents);
    }

    public function test_refund_waits_for_actual_payment_and_is_separate_from_receipts(): void
    {
        $this->save('replacement',$this->input('replacement',['adjustment'=>'refund','adjustment_amount'=>'90','adjustment_due_on'=>'2026-11-02','adjustment_reference'=>'Rimborso approvato dalla compagnia']))->assertOk();
        $title=InsuranceTitle::where('direction','refund')->sole();
        $this->assertSame('Da rimborsare',$title->state_label);$this->assertFalse($title->affects_coverage);$this->assertDatabaseCount('insurance_collections',0);
        $this->post(route('titles.collect',$title),['amount'=>'90','received_on'=>'2026-11-01','payment_method'=>'Bonifico','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Str::uuid()])->assertSessionHasNoErrors()->assertRedirect();
        $payment=InsuranceCollection::sole();$this->assertSame('refund',$payment->direction);$this->assertSame('Rimborsato',$title->fresh()->state_label);
        $this->get(route('collections.index',['from'=>'2026-11-01','to'=>'2026-11-01']))->assertOk()->assertSee('Rimborso registrato')->assertSee('-90,00');
        $this->assertDatabaseHas('audit_logs',['action'=>'refund_recorded','user_id'=>auth()->id()]);
    }

    public function test_confirmation_requires_pdf_and_repeated_request_never_duplicates_titles(): void
    {
        $data=$this->input('renewal');$data['schedule_fingerprint']=$this->preview('renewal',$data)['fingerprint'];
        $this->postJson(route('policy-transitions.store',[$this->source,'renewal']),array_replace($data,['contract'=>null,'contract_reviewed'=>0]))->assertUnprocessable()->assertJsonValidationErrors(['contract','contract_reviewed']);
        $first=$this->postJson(route('policy-transitions.store',[$this->source,'renewal']),$data)->assertOk();
        $again=$this->postJson(route('policy-transitions.store',[$this->source,'renewal']),$data)->assertOk();
        $this->assertSame($first->json('url'),$again->json('url'));$this->assertDatabaseCount('insurance_policy_changes',1);$this->assertDatabaseCount('insurance_titles',2);$this->assertCount(1,Storage::disk('attachments')->allFiles());
    }

    public function test_refund_can_belong_to_previous_mandate_when_new_contract_changes_company(): void
    {
        $next=SupplierCompany::create(['name'=>'Compagnia dimostrativa successiva','status'=>'active']);
        $data=$this->input('replacement',['company'=>$next->name,'supplier_agency_id'=>null,'adjustment'=>'refund','adjustment_policy'=>'previous','adjustment_amount'=>'40','adjustment_due_on'=>'2026-11-10','adjustment_reference'=>'Rimborso della vecchia compagnia']);
        $this->save('replacement',$data)->assertOk();
        $refund=InsuranceTitle::where('direction','refund')->sole();
        $this->assertSame($this->source->id,$refund->insurance_policy_id);
        $this->assertSame($this->company->id,$refund->supplier_company_id);
        $this->assertSame($this->agency->id,$refund->supplier_agency_id);
        $this->assertSame($next->id,InsurancePolicyChange::sole()->resultPolicy->supplier_company_id);
        $this->assertNull($refund->summary_state);
    }

    public function test_manual_titles_are_explicit_for_irregular_contracts_and_wrong_agency_is_rejected(): void
    {
        $data=$this->input('replacement',['expires_on'=>'2027-02-02']);
        $this->postJson(route('policy-transitions.preview',[$this->source,'replacement']),$data)->assertUnprocessable();
        $data['titles_mode']='manual';
        $other=SupplierAgency::create(['name'=>'Agenzia senza mandato demo','status'=>'active']);
        $this->postJson(route('policy-transitions.preview',[$this->source,'replacement']),array_replace($data,['supplier_agency_id'=>$other->id]))->assertUnprocessable()->assertJsonValidationErrors('supplier_agency_id');
        $result=$this->save('replacement',$data)->assertOk();
        $this->assertDatabaseCount('insurance_titles',0);
        $this->get($result->json('url'))->assertOk()->assertSee('inserimento manuale');
    }

    public function test_charge_on_previous_contract_remains_collectible_after_replacement(): void
    {
        $data=$this->input('replacement',['adjustment'=>'charge','charge_mode'=>'additional','adjustment_policy'=>'previous','adjustment_amount'=>'40','adjustment_due_on'=>'2026-11-10','adjustment_reference'=>'Conguaglio della vecchia compagnia']);
        $this->save('replacement',$data)->assertOk();
        $charge=InsuranceTitle::where('kind','adjustment')->sole();
        $this->assertSame($this->source->id,$charge->insurance_policy_id);
        $this->assertSame($this->agency->id,$charge->supplier_agency_id);
        $this->assertNotNull($charge->summary_state);
        $this->assertTrue(InsuranceTitle::withinContract()->whereKey($charge->id)->exists());
        $this->post(route('titles.collect',$charge),['amount'=>'40','received_on'=>'2026-11-02','payment_method'=>'Contanti a Intermediario','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Str::uuid()])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(0,$charge->fresh()->balance_cents);
        $this->assertDatabaseHas('insurance_collections',['insurance_title_id'=>$charge->id,'direction'=>'receipt','amount_cents'=>4000]);
    }

    public function test_selected_forms_are_generated_from_renewed_contract_and_linked_to_history(): void
    {
        $template=\App\Models\InsuranceFormTemplate::create(['family'=>(string)Str::uuid(),'version'=>1,'title'=>'Modulo rinnovo dimostrativo','category'=>'consistency','scope'=>'policy','client_type'=>'all','body'=>'Polizza {{numero_polizza}} / {{scadenza}}','signature_required'=>false,'required'=>false,'status'=>'active']);
        $result=$this->save('renewal',$this->input('renewal'))->assertOk();
        $change=InsurancePolicyChange::sole();
        $this->get($result->json('url'))->assertOk()->assertSee('Modulo rinnovo dimostrativo')->assertSee('name="templates[]"',false);
        $policy=$this->source->fresh();$client=$policy->client;
        $this->post(route('clients.forms.store',$client),[
            'policy'=>$policy->id,'change'=>$change->id,'templates'=>[$template->id],'reason'=>'renewal','note'=>$change->description,
            'context_hash'=>app(\App\Services\InsuranceForms::class)->contextHash($client,$policy),'request_key'=>(string)Str::uuid(),
            'signer_name'=>$client->name,'signer_role'=>'Contraente dimostrativo','place'=>'Napoli','document_date'=>'2026-11-02','confirmed'=>1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('insurance_form_packages',['insurance_policy_change_id'=>$change->id,'reason'=>'renewal']);
    }
}
