<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsuranceBranch, InsuranceFormPackage, InsuranceFormTemplate, InsurancePolicy, InsurancePolicyChange, InsuranceQuote, InsuranceQuoteCase, SupplierAgency, SupplierCompany, User};
use App\Services\{InsuranceForms, InsuranceLedger};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InsurancePolicyChangesTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private Client $client;
    private InsurancePolicy $policy;
    private InsuranceBranch $branch;
    private SupplierCompany $company;
    private SupplierAgency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,20)->setTime(12,0));
        Storage::fake('attachments');
        $this->admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->actingAs($this->admin);
        $this->client=Client::factory()->create(['name'=>'Marta Bellini','client_type'=>'person']);
        $this->branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $this->company=SupplierCompany::create(['name'=>'Nobis','status'=>'active','quote_priority'=>true]);
        $this->agency=SupplierAgency::create(['name'=>'Agenzia Valserena','status'=>'active']);
        $this->agency->companies()->attach($this->company);
        $this->policy=$this->client->policies()->create(['number'=>'SA-2026-2310','category'=>'RC auto','insurance_branch_id'=>$this->branch->id,
            'company'=>$this->company->name,'supplier_company_id'=>$this->company->id,'supplier_agency_id'=>$this->agency->id,
            'starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','premium'=>'1200.00','status'=>'active',
            'payment_frequency'=>'Annuale','payment_method'=>'Bonifico a Intermediario','tacit_renewal'=>true]);
    }

    private function pdf(string $name='proposta.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name,"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }
    private function approve(string $kind, array $extra=[]): InsuranceQuote
    {
        $this->post(route('quotes.store'),['client_id'=>$this->client->id,'purpose'=>$kind,'current_policy_id'=>$this->policy->id,
            'insurance_branch_id'=>$this->branch->id,'subject'=>'Coperture concordate con il contraente','additional_coverages'=>'Aggiunta della garanzia cristalli.'])->assertSessionHasNoErrors();
        $case=InsuranceQuoteCase::latest('id')->firstOrFail();
        $this->post(route('quotes.request',$case),['supplier_company_id'=>$this->company->id,'requested_on'=>'2026-10-19','confirmed'=>1])->assertSessionHasNoErrors();
        $quote=$case->quotes()->sole();
        $this->post(route('quotes.change',[$quote,'receive']),array_replace([
            'supplier_agency_id'=>$this->agency->id,'reference'=>'PR-2026-'.$quote->id,'product'=>'Copertura cristalli',
            'starts_on'=>'2026-10-20','expires_on'=>'2027-01-01','valid_until'=>'2026-11-10','received_on'=>'2026-10-20',
            'premium'=>$kind==='addition' ? '90.00' : '700.00','payment_frequency'=>'Unico','files'=>[$this->pdf()],
        ],$extra))->assertSessionHasNoErrors();
        $this->post(route('quotes.change',[$quote,'submit']),['confirmed'=>1])->assertSessionHasNoErrors();
        $this->post(route('quotes.change',[$quote,'approve']),['approved_on'=>'2026-10-20','confirmed'=>1,'files'=>[$this->pdf('approvazione.pdf')]])->assertSessionHasNoErrors();
        return $quote->fresh();
    }
    private function input(InsuranceQuote $quote, array $extra=[]): array
    {
        $kind=$quote->quoteCase->purpose;
        $page=$this->get(route('clients.policies.changes.create',[$this->client,$this->policy,'kind'=>$kind,'quote_id'=>$quote->id]))->assertOk();
        return array_replace(['kind'=>$kind,'quote_id'=>$quote->id,'fingerprint'=>$page->viewData('fingerprint'),
            'request_key'=>(string)Str::uuid(),'description'=>'Garanzie concordate e approvate dal contraente.','confirmed'=>1,
        ]+($kind==='replacement' ? ['number'=>'SA-2026-2311'] : ['reference'=>'APP-'.$quote->id]),$extra);
    }
    private function record(InsuranceQuote $quote, array $extra=[]): InsurancePolicyChange
    {
        $this->post(route('clients.policies.changes.store',[$this->client,$this->policy]),$this->input($quote,$extra))->assertSessionHasNoErrors();
        return $quote->policyChange()->firstOrFail();
    }
    private function baseTitle()
    {
        $page=$this->get(route('titles.schedule',$this->policy))->assertOk();
        $this->post(route('titles.generate',$this->policy),['confirmed'=>1,'fingerprint'=>$page->viewData('fingerprint')])->assertSessionHasNoErrors();
        return $this->policy->titles()->sole();
    }
    private function collect($title,string $amount): void
    {
        $this->post(route('titles.collect',$title),['received_on'=>'2026-10-20','amount'=>$amount,'payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'transfer','idempotency_key'=>(string)Str::uuid()])->assertSessionHasNoErrors();
    }

    public function test_replacement_preserves_previous_contract_finances_and_links_two_policies(): void
    {
        $title=$this->baseTitle();
        $this->collect($title,'300.00');
        $before=$title->fresh()->getAttributes();
        $quote=$this->approve('replacement');
        $change=$this->record($quote,['documents'=>[$this->pdf('contratto.pdf'),$this->pdf('condizioni.pdf')]]);
        $new=$change->resultPolicy;
        $this->assertNotSame($this->policy->id,$new->id);
        $this->assertSame('SA-2026-2311',$new->number);
        $this->assertSame('replaced',$this->policy->fresh()->status);
        $this->assertSame('2027-01-01',$this->policy->fresh()->expires_on->toDateString());
        $this->assertSame($before,$title->fresh()->getAttributes());
        $this->assertSame(0,$new->titles()->count());
        $this->assertSame(4,$change->documents()->count());
        $this->assertSame('closed',$quote->quoteCase->fresh()->status);
        $this->get(route('clients.policies.show',[$this->client,$this->policy]))->assertOk()->assertSee('SA-2026-2311')->assertSee('900,00');
        $this->get(route('clients.policies.show',[$this->client,$new]))->assertOk()->assertSee('SA-2026-2310');
        $this->get(route('clients.policy-changes.show',[$this->client,$change]))->assertOk()->assertSee('Prepara moduli');
        $this->assertDatabaseHas('audit_logs',['action'=>'policy_contract_changed','user_id'=>$this->admin->id]);
        $this->delete(route('clients.policies.destroy',[$this->client,$new]))->assertSessionHasErrors();
    }

    public function test_addition_keeps_number_premium_period_and_existing_installments(): void
    {
        $title=$this->baseTitle();
        $before=$this->policy->fresh()->getAttributes();
        $quote=$this->approve('addition',['premium'=>'0.00']);
        $change=$this->record($quote);
        $this->assertSame($change->source_policy_id,$change->result_policy_id);
        $this->assertSame($before,$this->policy->fresh()->getAttributes());
        $this->assertSame(0,$change->premium_cents);
        $this->assertDatabaseCount('insurance_policies',1);
        $this->assertSame($title->id,$this->policy->titles()->sole()->id);
        $this->get(route('clients.policy-changes.show',[$this->client,$change]))->assertOk()->assertSee('0,00')->assertDontSee('Registra titolo del premio aggiuntivo');
    }

    public function test_wrong_number_cross_client_and_standard_conversion_are_rejected(): void
    {
        $quote=$this->approve('replacement');
        $input=$this->input($quote,['number'=>$this->policy->number]);
        $this->post(route('clients.policies.changes.store',[$this->client,$this->policy]),$input)->assertSessionHasErrors('change');
        $other=Client::factory()->create();
        $this->post(route('clients.policies.changes.store',[$other,$this->policy]),$input)->assertNotFound();
        $this->post(route('clients.policies.store',$this->client),[
            'entry_mode'=>'new','insurance_quote_id'=>$quote->id,'category'=>'RC auto','company'=>'Nobis','number'=>'SA-42',
            'starts_on'=>'2026-10-20','expires_on'=>'2027-01-01','status'=>'active',
        ])->assertSessionHasErrors('quote');
        $this->assertDatabaseCount('insurance_policy_changes',0);
        $this->assertDatabaseCount('insurance_policies',1);
    }

    public function test_stale_preview_changed_proof_duplicate_confirmation_and_future_replacement_are_rejected(): void
    {
        $quote=$this->approve('replacement',['starts_on'=>'2026-10-21']);
        $input=$this->input($quote);
        $this->post(route('clients.policies.changes.store',[$this->client,$this->policy]),$input)->assertSessionHasErrors('change');
        $quote->update(['starts_on'=>'2026-10-20']);
        $input=$this->input($quote->fresh());
        $this->policy->update(['insured_subject'=>'Bene aggiornato']);
        $this->post(route('clients.policies.changes.store',[$this->client,$this->policy]),$input)->assertSessionHasErrors('change');
        $input=$this->input($quote->fresh());
        Storage::disk('attachments')->put($quote->documents()->first()->path,'%PDF-1.4 changed');
        $this->post(route('clients.policies.changes.store',[$this->client,$this->policy]),$input)->assertSessionHasErrors('change');
        $input=$this->input($quote->fresh());
        $this->post(route('clients.policies.changes.store',[$this->client,$this->policy]),$input)->assertSessionHasNoErrors();
        $this->post(route('clients.policies.changes.store',[$this->client,$this->policy]),$input)->assertSessionHasErrors('quote');
        $this->assertDatabaseCount('insurance_policy_changes',1);
    }

    public function test_next_day_work_lock_blocks_operator_but_admin_can_register_with_history(): void
    {
        $quote=$this->approve('addition');
        $input=$this->input($quote);
        $operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->policy->forceFill(['work_closed_at'=>today()->subDay(),'work_closed_by'=>$operator->id])->save();
        $this->actingAs($operator);
        $this->get(route('clients.policies.changes.create',[$this->client,$this->policy,'kind'=>'addition']))->assertForbidden();
        $this->post(route('clients.policies.changes.store',[$this->client,$this->policy]),$input)->assertForbidden();
        $this->actingAs($this->admin);
        $this->record($quote);
        $this->assertTrue($this->policy->fresh()->workLocked());
    }

    public function test_forms_and_multiple_signed_files_are_linked_to_change_and_preserve_old_packages(): void
    {
        $template=InsuranceFormTemplate::create(['family'=>(string)Str::uuid(),'version'=>1,'title'=>'Modulo variazione','category'=>'consistency','scope'=>'policy',
            'body'=>'Polizza {{numero_polizza}} / {{operazione}} / {{coperture_aggiuntive}}','signature_required'=>true,'required'=>false,'status'=>'active']);
        $forms=app(InsuranceForms::class);
        $data=['policy'=>$this->policy->id,'context_hash'=>$forms->contextHash($this->client,$this->policy),'request_key'=>(string)Str::uuid(),'templates'=>[$template->id],
            'reason'=>'initial','signer_name'=>'Marta Bellini','place'=>'Napoli','document_date'=>'2026-10-20','confirmed'=>1];
        $this->post(route('clients.forms.store',$this->client),$data)->assertSessionHasNoErrors();
        $old=InsuranceFormPackage::latest('id')->firstOrFail();
        $change=$this->record($this->approve('addition'));
        $this->assertFalse($old->fresh()->currentData());
        $data=array_replace($data,['reason'=>'addition','change'=>$change->id,'context_hash'=>$forms->contextHash($this->client,$this->policy->fresh()),'request_key'=>(string)Str::uuid()]);
        $this->post(route('clients.forms.store',$this->client),$data)->assertSessionHasNoErrors();
        $package=InsuranceFormPackage::latest('id')->firstOrFail();
        $this->assertSame($change->id,$package->insurance_policy_change_id);
        $this->assertTrue($package->currentData());
        $document=$package->documents()->sole();
        $this->assertStringContainsString('Garanzie concordate',$document->body);
        $this->post(route('clients.forms.submit',[$this->client,$package,$document]),[
            'request_key'=>(string)Str::uuid(),'signer_name'=>'Marta Bellini','signed_on'=>'2026-10-20','files'=>[$this->pdf('firma1.pdf'),$this->pdf('firma2.pdf')],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2,$document->submissions()->sole()->files()->where('insurance_policy_change_id',$change->id)->count());
        $this->get(route('clients.forms.create',[$this->client,'policy'=>$this->policy->id,'change'=>$change->id]))->assertOk()->assertSee('name="change"',false);
        $this->get(route('clients.forms.show',[$this->client,$package]))->assertOk()->assertSee('Variazione contrattuale collegata');
        $this->assertDatabaseCount('insurance_form_packages',2);
    }

    public function test_addition_titles_do_not_overlap_base_titles_or_extend_base_coverage_and_cannot_exceed_quote(): void
    {
        $base=$this->baseTitle();
        $this->collect($base,'1200.00');
        $change=$this->record($this->approve('addition'));
        $data=['kind'=>'quietance','insurance_policy_change_id'=>$change->id,'reference'=>'APP-T-01','due_on'=>'2026-10-20','coverage_from'=>'2026-10-20','coverage_to'=>'2027-01-01',
            'amount'=>'91.00','payment_method'=>'Bonifico a Intermediario','payment_frequency'=>'Unico'];
        $this->post(route('titles.store',$this->policy),$data)->assertSessionHasErrors('ledger');
        $data['amount']='90.00';
        $this->post(route('titles.store',$this->policy),$data)->assertSessionHasNoErrors();
        $title=$change->titles()->sole();
        $this->assertSame('2027-01-01',$this->policy->fresh()->coverage_until->toDateString());
        $this->assertSame('unpaid',$this->policy->fresh()->payment_status);
        $this->collect($title,'90.00');
        $this->assertSame('paid',$this->policy->fresh()->payment_status);
        $this->assertSame('2027-01-01',$this->policy->fresh()->coverage_until->toDateString());
        $this->get(route('titles.show',$title))->assertOk()->assertSee('apri l’appendice');
    }

    public function test_addition_documents_cannot_be_detached_and_other_clients_cannot_access_event(): void
    {
        $change=$this->record($this->approve('addition'),['documents'=>[$this->pdf('appendice.pdf')]]);
        $this->post(route('clients.policy-changes.documents',[$this->client,$change]),['documents'=>[$this->pdf('altro1.pdf'),$this->pdf('altro2.pdf')]])->assertSessionHasNoErrors();
        $this->assertSame(5,$change->documents()->count());
        $other=Client::factory()->create();
        $this->get(route('clients.policy-changes.show',[$other,$change]))->assertNotFound();
        $this->post(route('clients.policy-changes.documents',[$other,$change]),['documents'=>[$this->pdf()]])->assertNotFound();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $change->documents()->where('original_name','appendice.pdf')->firstOrFail()->delete();
    }
}
