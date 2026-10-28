<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Attachment, Client, InsuranceBranch, InsurancePolicy, InsuranceQuoteCase, InsuranceQuoteEmail, SupplierCompany, User};
use App\Services\{ClientDocumentRegistry, InsuranceQuoteWorkflow, QuoteRequestEmailTransport};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class QuoteRequestContextTest extends TestCase
{
    use RefreshDatabase;
    private Client $client;
    private InsuranceBranch $branch;
    private SupplierCompany $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,19)->setTime(12,0));
        Storage::fake('attachments');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']));
        $this->client=Client::factory()->create(['name'=>'Officine Valdoro S.r.l.','client_type'=>'company']);
        $this->branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $this->company=SupplierCompany::create(['name'=>'Nobis','email'=>'nobis@example.test','status'=>'active','quote_priority'=>true]);
    }

    private function pdf(string $name): UploadedFile { return UploadedFile::fake()->createWithContent($name,"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"); }
    private function input(array $extra=[]): array { return array_replace(['client_id'=>$this->client->id,'insurance_branch_id'=>$this->branch->id,'subject'=>'Rinnovo copertura veicolo'],$extra); }
    private function createCase(array $extra=[]): InsuranceQuoteCase
    {
        $response=$this->post(route('quotes.store'),$this->input($extra))->assertSessionHasNoErrors()->assertRedirect();
        return InsuranceQuoteCase::findOrFail(basename($response->headers->get('Location')));
    }
    private function policy(?Client $client=null): InsurancePolicy
    {
        return ($client??$this->client)->policies()->create(['number'=>'SA-2026-815','category'=>'RC auto','insurance_branch_id'=>$this->branch->id,'company'=>$this->company->name,'supplier_company_id'=>$this->company->id,'starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','status'=>'active','premium'=>'500.00']);
    }
    private function emailInput(array $extra=[]): array { return array_replace(['supplier_company_id'=>$this->company->id,'subject'=>'Richiesta coperture','body'=>'Buongiorno, si richiede la proposta per le coperture concordate con il cliente.'],$extra); }

    public function test_optional_documents_and_current_contract_never_change_existing_policy(): void
    {
        $policy=$this->policy(); $before=$policy->refresh()->getAttributes();
        $case=$this->createCase(['current_policy_id'=>$policy->id,'requester_name'=>'Luca Valdoro','current_coverages'=>'Responsabilità civile','additional_coverages'=>'Cristalli e furto','notes'=>'Nota riservata agli operatori','current_policy_snapshot'=>['number'=>'FALSO']]);
        $this->assertSame($before,$policy->fresh()->getAttributes());
        $this->assertSame($policy->number,$case->current_policy_snapshot['number']);
        $this->assertSame(0,$case->documents()->count());
        $this->assertStringNotContainsString($policy->number,DB::table('insurance_quote_cases')->where('id',$case->id)->value('current_policy_snapshot'));
        $policy->update(['number'=>'SA-2026-816']);
        $this->assertSame('SA-2026-815',$case->fresh()->current_policy_snapshot['number']);
        $this->get(route('quotes.emails.create',$case))->assertOk()->assertSee('Cristalli e furto')->assertSee('Responsabilità civile')->assertSee('Luca Valdoro')->assertSee('SA-2026-815')->assertDontSee('Nota riservata agli operatori');
        $this->get(route('quotes.create',['client_id'=>$this->client->id]))->assertOk()->assertSee('Coperture aggiuntive desiderate')->assertSee('Documento d’identità del richiedente');
        $this->createCase(['current_policy_reference'=>'Compagnia esterna / contratto 000045','additional_coverages'=>'Danni da acqua']);
        $this->createCase();
    }

    public function test_all_upload_groups_are_multiple_optional_and_keep_individual_identity_dates(): void
    {
        $case=$this->createCase([
            'identity_files'=>[$this->pdf('carta.pdf'),$this->pdf('patente.pdf')],
            'identity_file_details'=>[['identity_type'=>'electronic','expires_on'=>'2027-03-01'],['identity_type'=>'driving_licence','expires_on'=>'2026-10-01']],
            'vehicle_files'=>[$this->pdf('libretto-fronte.pdf'),$this->pdf('libretto-retro.pdf')],
            'files'=>[$this->pdf('visura.pdf'),$this->pdf('coperture.pdf')],
        ]);
        $this->assertSame(6,$case->documents()->count());
        $this->assertSame(2,$case->documents()->where('document_category','identity')->count());
        $documents=$case->documents()->get()->keyBy('original_name');
        $this->assertSame('Documento valido',$documents['carta.pdf']->validity_label);
        $this->assertStringContainsString('01/10/2026',$documents['patente.pdf']->validity_label);
        foreach ($documents as $document) Storage::disk('attachments')->assertExists($document->path);
        $this->get(route('quotes.show',$case))->assertOk()->assertSee('visura.pdf')->assertSee('Documento valido')->assertSee('01/10/2026');
        $this->get(route('clients.show',[$this->client,'tab'=>'documents']))->assertOk()->assertSee('Pratica preventivi')->assertSee('libretto-fronte.pdf');
        $this->post(route('quotes.documents.store',$case),['identity_files'=>[$this->pdf('copia.pdf')]])->assertSessionHasNoErrors()->assertSessionHas('warning');
        $this->assertSame('Da verificare, data di scadenza non leggibile',$case->documents()->where('original_name','copia.pdf')->sole()->validity_label);
    }

    public function test_cross_client_links_are_rejected_while_operators_can_access_all_clients(): void
    {
        $other=Client::factory()->create(); $policy=$this->policy($other);
        $this->post(route('quotes.store'),$this->input(['current_policy_id'=>$policy->id]))->assertSessionHasErrors('current_policy_id');
        $case=$this->createCase(['files'=>[$this->pdf('riservato.pdf')]]);
        $second=$this->createCase();
        $this->post(route('quotes.emails.review',$second),$this->emailInput(['document_ids'=>[$case->documents()->sole()->id]]))->assertSessionHasErrors('document_ids');
        $operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($operator);
        $document=$case->documents()->sole();
        $this->post(route('quotes.documents.store',$case),['files'=>[$this->pdf('altro.pdf')]])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('attachments.download',$document))->assertOk();
        $this->get(route('clients.policy-context',$this->client))->assertOk();
    }

    public function test_client_context_is_retained_between_registry_quotes_filters_and_new_policy(): void
    {
        $own=$this->createCase(['subject'=>'Coperture Officine Valdoro']);
        $other=Client::factory()->create();
        $other->quoteCases()->create(['subject'=>'Pratica altro contraente','insurance_branch_id'=>$this->branch->id]);
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']));
        $list=route('quotes.index',['client'=>$this->client->id]);
        $create=route('quotes.create',['client_id'=>$this->client->id]);
        $this->get(route('clients.show',$this->client))->assertOk()->assertSee($list,false)->assertSee($create,false);
        $this->get($list)->assertOk()->assertSee('Preventivi / '.$this->client->name)->assertSee($create,false)
            ->assertSee('href="'.$list.'">Azzera filtri',false)
            ->assertViewHas('cases',fn($cases)=>$cases->modelKeys()===[$own->id]);
        $this->get($create)->assertOk()->assertViewHas('client',fn($client)=>$client->is($this->client))->assertSee($list,false);
        $this->get(route('clients.policies.create',$this->client))->assertOk()->assertSee($list,false);
        $this->get(route('quotes.index',['client'=>999999]))->assertNotFound();
    }

    public function test_selected_contract_context_contains_its_classification_and_excludes_other_clients(): void
    {
        $policy=$this->policy();
        $other=$this->policy(Client::factory()->create());
        $subbranch=$this->branch->subbranches()->create(['name'=>'Autovetture','name_key'=>hash('sha256','AUTOVETTURE'),'status'=>'active']);
        $policy->update(['insurance_subbranch_id'=>$subbranch->id,'subcategory'=>$subbranch->name]);
        $this->get(route('clients.policy-context',$this->client))->assertOk()
            ->assertJsonCount(1,'policies')->assertJsonPath('policies.0.id',$policy->id)
            ->assertJsonPath('policies.0.insurance_branch_id',$this->branch->id)
            ->assertJsonPath('policies.0.insurance_subbranch_id',$subbranch->id);
        $this->get(route('quotes.create',['client_id'=>$this->client->id,'purpose'=>'addition','current_policy_id'=>$other->id]))->assertNotFound();
    }

    public function test_email_includes_only_explicitly_selected_files_and_preserves_immutable_copies(): void
    {
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        $case=$this->createCase(['files'=>[$this->pdf('visura.pdf'),$this->pdf('polizza.pdf')]]);
        $this->post(route('quotes.emails.review',$case),$this->emailInput())->assertSessionHasNoErrors();
        $this->assertSame([],$case->emails()->sole()->payload['attachments']);
        $source=$case->documents()->where('original_name','polizza.pdf')->sole();
        $response=$this->post(route('quotes.emails.review',$case),$this->emailInput(['document_ids'=>[$source->id],'identity_files'=>[$this->pdf('identita.pdf')],'identity_file_details'=>[['expires_on'=>'2027-10-01','identity_type'=>'paper']]]))->assertSessionHasNoErrors();
        $message=InsuranceQuoteEmail::findOrFail(basename($response->headers->get('Location')));
        $this->assertSame(['identita.pdf','polizza.pdf'],array_column($message->payload['attachments'],'name'));
        $copy=$message->documents()->where('original_name','polizza.pdf')->sole();
        $this->assertNotSame($source->id,$copy->id); $this->assertNotSame($source->path,$copy->path);
        $this->assertNull($copy->insurance_quote_case_id);
        $this->assertSame(2,$case->documents()->count());
        $this->get(route('quote-emails.show',$message))->assertOk()->assertSee('Documento valido')->assertSee('polizza.pdf')->assertDontSee('visura.pdf');
        $this->delete(route('attachments.destroy',$source))->assertSessionHasErrors('document');
        $this->patch(route('clients.documents.update',[$this->client,$source]),['document_category'=>'quote_request','description'=>'Sostituita'])->assertSessionHasErrors('document');
        $this->assertSame('review',$message->fresh()->status);
    }

    public function test_bad_files_total_limits_and_duplicate_selections_are_rejected_atomically(): void
    {
        $fixture=UploadedFile::fake()->createWithContent('falso.pdf','<script>alert(1)</script>');
        $bad=new UploadedFile($fixture->getPathname(),'falso.pdf',null,null,true);
        $this->post(route('quotes.store'),$this->input(['identity_files'=>[$bad]]))->assertSessionHasErrors('identity_files.0');
        $this->assertDatabaseCount('insurance_quote_cases',0);
        $this->post(route('quotes.store'),$this->input(['identity_files'=>array_map(fn($i)=>$this->pdf('id-'.$i.'.pdf'),range(1,6)),'vehicle_files'=>array_map(fn($i)=>$this->pdf('veicolo-'.$i.'.pdf'),range(1,5))]))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('attachments',0);
        $case=$this->createCase(['files'=>[$this->pdf('dati.pdf')]]); $id=$case->documents()->sole()->id;
        $this->post(route('quotes.emails.review',$case),$this->emailInput(['document_ids'=>[$id,$id]]))->assertSessionHasErrors('document_ids.0');
        $this->post(route('quotes.store'),$this->input(['current_policy_id'=>$this->policy()->id,'current_policy_reference'=>'Un altro contratto']))->assertSessionHasErrors('current_policy_reference');
        $case->update(['status'=>'approved']);
        $this->post(route('quotes.documents.store',$case),['files'=>[$this->pdf('tardivo.pdf')]])->assertStatus(409);
        $this->assertSame(1,$case->documents()->count());
    }

    public function test_approved_conversion_links_request_documents_without_altering_the_current_contract(): void
    {
        $current=$this->policy(); $before=$current->refresh()->getAttributes();
        $case=$this->createCase(['current_policy_id'=>$current->id,'additional_coverages'=>'Cristalli','vehicle_files'=>[$this->pdf('libretto.pdf')]]);
        $quote=$case->quotes()->create(['supplier_company_id'=>$this->company->id,'status'=>'approved','priority'=>true,'requested_at'=>today(),'received_at'=>today(),'approved_on'=>today(),'starts_on'=>'2026-11-01','expires_on'=>'2027-11-01','valid_until'=>'2026-11-01','premium_cents'=>75000,'payment_frequency'=>'Annuale','product'=>'Auto completa']);
        foreach (['quote','quote_approval'] as $category) app(ClientDocumentRegistry::class)->upload($this->client,$this->pdf($category.'.pdf'),['insurance_quote_id'=>$quote->id,'document_category'=>$category]);
        $case->update(['status'=>'approved']);
        $policy=DB::transaction(fn()=>app(InsuranceQuoteWorkflow::class)->createPolicy($this->client,['number'=>'SA-2026-910','status'=>'active'],$quote->id));
        $this->assertSame(3,$policy->documents()->count());
        $this->assertSame($policy->id,$case->documents()->sole()->insurance_policy_id);
        $this->assertSame($before,$current->fresh()->getAttributes());
        $this->assertSame('closed',$case->fresh()->status);
        $this->delete(route('clients.policies.destroy',[$this->client,$current]))->assertSessionHasErrors('policy');
    }
}
