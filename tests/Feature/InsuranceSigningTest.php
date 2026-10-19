<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Attachment, Client, InsuranceBranch, InsuranceCoverageCheck, InsuranceFormPackage, InsuranceFormTemplate, InsurancePolicy, SupplierCompany, User};
use App\Services\InsuranceForms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class InsuranceSigningTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private Client $client;
    private InsurancePolicy $policy;
    private InsuranceBranch $branch;

    protected function setUp(): void
    {
        parent::setUp(); $this->travelTo(now()->setDate(2026,10,18)->setTime(12,0)); Storage::fake('attachments');
        $this->admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']); $this->actingAs($this->admin);
        $this->client=Client::factory()->create(['client_type'=>'person','name'=>'Paolo Ferri','tax_code'=>null,'vat_number'=>null]);
        $this->branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $company=SupplierCompany::create(['name'=>'Nobis','status'=>'active']);
        $vehicle=$this->client->vehicles()->create(['plate'=>'AB123CD']);
        $this->policy=$this->client->policies()->create(['vehicle_id'=>$vehicle->id,'category'=>'RC auto','insurance_branch_id'=>$this->branch->id,'company'=>$company->name,'supplier_company_id'=>$company->id,'number'=>'SA-2026-501','starts_on'=>'2026-10-18','expires_on'=>'2027-10-18','status'=>'active','premium'=>'1000.01','payment_frequency'=>'Semestrale','payment_method'=>'Bonifico a Intermediario']);
    }
    private function template(array $extra=[]): InsuranceFormTemplate
    {
        return InsuranceFormTemplate::create(array_replace(['family'=>(string)Str::uuid(),'version'=>1,'title'=>'Modulo di collaudo','category'=>'consistency','scope'=>'policy','body'=>"Contraente: {{contraente}}\n\nPolizza: {{numero_polizza}}\n\nFirmatario: {{firmatario}}\n\nScelta: SI / NO\n\nFirma: __________________",'signature_required'=>true,'required'=>false,'status'=>'active','approved_by'=>$this->admin->id,'approved_at'=>now()],$extra));
    }
    private function data(InsuranceFormTemplate $template,?InsurancePolicy $policy=null,array $extra=[]): array
    {
        $policy??=$this->policy;
        return array_replace(['policy'=>$policy->id,'context_hash'=>app(InsuranceForms::class)->contextHash($this->client->fresh(),$policy->fresh()),'request_key'=>(string)Str::uuid(),'templates'=>[$template->id],'reason'=>'initial','signer_name'=>'Paolo Ferri','place'=>'Napoli','document_date'=>'2026-10-18','confirmed'=>1],$extra);
    }
    private function package(?InsuranceFormTemplate $template=null,array $extra=[]): InsuranceFormPackage
    {
        $this->post(route('clients.forms.store',$this->client),$this->data($template??$this->template(),extra:$extra))->assertSessionHasNoErrors()->assertRedirect();
        return InsuranceFormPackage::latest('id')->firstOrFail();
    }
    private function signed(InsuranceFormPackage $package,array $extra=[])
    {
        $document=$package->documents()->firstOrFail();
        $pdf=Storage::disk('attachments')->get($document->generated->path);
        $data=array_replace(['request_key'=>(string)Str::uuid(),'signer_name'=>'Paolo Ferri','signed_on'=>'2026-10-18','files'=>[UploadedFile::fake()->createWithContent('firma.pdf',$pdf),UploadedFile::fake()->createWithContent('seconda-firma.pdf',$pdf)]],$extra);
        $this->post(route('clients.forms.submit',[$this->client,$package,$document]),$data)->assertSessionHasNoErrors()->assertRedirect();
        return $document->submissions()->firstOrFail();
    }
    private function review(InsuranceFormPackage $package,$submission,array $extra=[])
    {
        return $this->post(route('clients.forms.review',[$this->client,$package,$submission]),array_replace(['outcome'=>'validated','confirmed'=>1],$extra));
    }
    public function test_original_templates_seed_once_without_approving_or_copying_the_original_contractor(): void
    {
        $this->seed(\Database\Seeders\InsuranceFormTemplateSeeder::class);$this->seed(\Database\Seeders\InsuranceFormTemplateSeeder::class);
        $this->assertDatabaseCount('insurance_form_templates',7);$this->assertSame(5,InsuranceFormTemplate::where('status','legacy')->count());$this->assertSame(2,InsuranceFormTemplate::where('status','draft')->count());
        $this->assertSame(0,InsuranceFormTemplate::where('status','active')->count());
        $body=InsuranceFormTemplate::pluck('body')->implode(' ');$this->assertStringNotContainsString('SPADONE IVAN',$body);$this->assertStringNotContainsString('215797176',$body);
        $this->get(route('clients.forms.create',[$this->client,'policy'=>$this->policy->id]))->assertOk()->assertSee('Non ci sono modelli approvati');
        $this->get(route('form-templates.index'))->assertOk()->assertSee('Modelli documentali');
    }
    public function test_template_creation_approval_and_revision_preserve_prior_versions(): void
    {
        $this->get(route('form-templates.create'))->assertOk();
        $data=['title'=>'Modulo controllato','category'=>'consistency','scope'=>'policy','body'=>'Contraente {{contraente}}','signature_required'=>1,'required'=>1];
        $this->post(route('form-templates.store'),$data)->assertSessionHasNoErrors()->assertRedirect();$first=InsuranceFormTemplate::sole();$this->assertSame('draft',$first->status);
        $this->post(route('form-templates.approve',$first),[])->assertSessionHasErrors('confirmed');
        $this->post(route('form-templates.approve',$first),['confirmed'=>1])->assertSessionHasNoErrors();
        $this->patch(route('form-templates.update',$first),$data)->assertSessionHasErrors('forms');
        $this->patch(route('form-templates.update',$first),$data+['revise'=>1,'revision_reason'=>'Revisione del testo approvata'])->assertSessionHasNoErrors();
        $second=InsuranceFormTemplate::latest('id')->first();$this->assertSame(2,$second->version);$this->assertSame($first->family,$second->family);
        $this->post(route('form-templates.approve',$second),['confirmed'=>1])->assertSessionHasNoErrors();$this->assertSame('retired',$first->fresh()->status);
        $this->get(route('form-templates.edit',$first))->assertOk()->assertSee('Testo conservato');
        $this->post(route('form-templates.retire',$second),['confirmed'=>1,'reason'=>'Modello sostituito dal mandante'])->assertSessionHasNoErrors();$this->assertSame('retired',$second->fresh()->status);
    }
    public function test_unsafe_invalid_or_obsolete_templates_cannot_be_enabled(): void
    {
        $base=['title'=>'Modello','category'=>'consistency','scope'=>'policy','body'=>'{{sconosciuto}}','signature_required'=>1,'required'=>0];
        $this->post(route('form-templates.store'),$base)->assertSessionHasErrors('body');
        $this->post(route('form-templates.store'),array_replace($base,['body'=>'<img src="https://example.test/a">']))->assertSessionHasErrors('body');
        $other=InsuranceBranch::create(['name'=>'Infortuni','name_key'=>'injury','status'=>'active']);$sub=$other->subbranches()->create(['name'=>'Conducente','name_key'=>'driver','status'=>'active']);
        $this->post(route('form-templates.store'),array_replace($base,['body'=>'Testo','insurance_branch_id'=>$this->branch->id,'insurance_subbranch_id'=>$sub->id]))->assertSessionHasErrors('insurance_subbranch_id');
        $legacy=$this->template(['status'=>'draft','category'=>'annex_3']);$this->post(route('form-templates.approve',$legacy),['confirmed'=>1])->assertSessionHasErrors('forms');
        $this->assertSame('draft',$legacy->fresh()->status);
    }
    public function test_generated_pdf_is_private_frozen_encrypted_and_linked_to_client_and_policy(): void
    {
        $template=$this->template();$package=$this->package($template);$document=$package->documents()->sole();$attachment=$document->generated;
        $bytes=Storage::disk('attachments')->get($attachment->path);$this->assertStringStartsWith('%PDF-',$bytes);$this->assertSame(hash('sha256',$bytes),$document->pdf_hash);
        $this->assertStringContainsString('Paolo Ferri',$document->body);$this->assertStringContainsString('SI / NO',$document->body);$this->assertStringNotContainsString('{{',$document->body);
        $this->assertStringNotContainsString('Paolo Ferri',DB::table('insurance_form_documents')->value('body'));$this->assertStringNotContainsString('Paolo Ferri',DB::table('insurance_form_packages')->value('snapshot'));
        $this->assertSame($this->policy->id,$attachment->insurance_policy_id);$this->assertSame($this->client->id,$attachment->attachable_id);$this->assertSame('generated',$attachment->review_status);
        $this->get($attachment->downloadUrl())->assertOk();$this->get(route('clients.forms.show',[$this->client,$package]))->assertOk()->assertSee('Firma mancante');
        $this->get(route('clients.show',[$this->client,'tab'=>'documents']))->assertOk()->assertSee('Fascicolo moduli e firme');
        $this->assertDatabaseCount('client_consents',0);$this->assertDatabaseCount('insurance_collections',0);
        $template->update(['body'=>'Testo successivo']);$this->assertStringContainsString('Paolo Ferri',$document->fresh()->body);
    }
    public function test_stale_duplicate_required_and_incompatible_requests_do_not_create_pdfs(): void
    {
        $t=$this->template();$required=$this->template(['required'=>true]);
        $this->post(route('clients.forms.store',$this->client),$this->data($t))->assertSessionHasErrors('forms');$required->update(['required'=>false]);
        $data=$this->data($t);$this->client->update(['name'=>'Paolo Ferri aggiornato']);
        $this->post(route('clients.forms.store',$this->client),$data)->assertSessionHasErrors('forms');
        $t->update(['scope'=>'client']);$this->post(route('clients.forms.store',$this->client),$this->data($t))->assertSessionHasErrors('forms');$t->update(['scope'=>'policy']);
        $this->assertDatabaseCount('insurance_form_packages',0);$this->assertCount(0,Storage::disk('attachments')->allFiles());
        $data=$this->data($t);$this->post(route('clients.forms.store',$this->client),$data)->assertSessionHasNoErrors();$this->post(route('clients.forms.store',$this->client),$data)->assertSessionHasErrors('forms');
        $this->assertDatabaseCount('insurance_form_packages',1);$this->assertCount(1,Storage::disk('attachments')->allFiles());
    }
    public function test_client_scope_company_signer_and_stale_contract_warning_are_distinct_from_receipts(): void
    {
        $this->client->update(['client_type'=>'company']);$t=$this->template(['scope'=>'client']);
        $extra=['policy'=>null,'reason'=>'client','context_hash'=>app(InsuranceForms::class)->contextHash($this->client->fresh(),null)];
        $this->post(route('clients.forms.store',$this->client),$this->data($t,extra:$extra))->assertSessionHasErrors('signer_role');
        $package=$this->package($t,$extra+['signer_role'=>'Legale rappresentante']);$this->assertNull($package->insurance_policy_id);
        $this->client->update(['client_type'=>'person']);$policyPackage=$this->package();$this->assertTrue($policyPackage->currentData());
        $this->policy->update(['payment_status'=>'paid','notes'=>'Annotazione interna']);$this->assertTrue($policyPackage->fresh()->currentData());
        $this->policy->update(['premium'=>'1200.00']);$this->assertFalse($policyPackage->fresh()->currentData());$this->assertSame('Dati aggiornati: predisporre nuovi moduli',$policyPackage->fresh()->state_label);
    }
    public function test_multiple_signed_files_require_manual_review_and_keep_all_revisions(): void
    {
        $package=$this->package();$first=$this->signed($package);$this->assertSame('received',$first->status);$this->assertCount(2,$first->files);$this->assertSame('Firme da completare',$package->fresh()->state_label);
        $this->review($package,$first,['confirmed'=>0])->assertSessionHasErrors('confirmed');
        $this->review($package,$first)->assertSessionHasNoErrors();$this->assertSame('Documentazione completata',$package->fresh()->state_label);
        $second=$this->signed($package,['note'=>'Nuova copia leggibile dei moduli firmati']);$this->assertSame(2,$second->version);$this->assertSame('Firme da completare',$package->fresh()->state_label);
        $this->review($package,$first)->assertSessionHasErrors('forms');$this->review($package,$second,['outcome'=>'rejected'])->assertSessionHasErrors('review_note');
        $this->review($package,$second,['outcome'=>'rejected','review_note'=>'Manca la firma in seconda pagina'])->assertSessionHasNoErrors();
        $this->assertSame('validated',$first->fresh()->status);$this->assertSame('rejected',$second->fresh()->status);$this->assertDatabaseCount('attachments',5);
        $this->get(route('clients.forms.show',[$this->client,$package]))->assertOk()->assertSee('storica')->assertSee('Manca la firma');
        $this->assertDatabaseCount('client_consents',0);
    }
    public function test_tampered_evidence_and_ordinary_document_mutations_are_blocked(): void
    {
        $package=$this->package();$submission=$this->signed($package);$file=$submission->files->first();
        Storage::disk('attachments')->put($file->path,'replaced');$this->review($package,$submission)->assertSessionHasErrors('forms');$this->assertSame('received',$submission->fresh()->status);
        foreach([$file,$package->documents()->sole()->generated] as $attachment){
            $this->delete(route('attachments.destroy',$attachment))->assertSessionHasErrors();$this->assertDatabaseHas('attachments',['id'=>$attachment->id]);Storage::disk('attachments')->assertExists($attachment->path);
            $this->post(route('clients.documents.validate',[$this->client,$attachment]),['confirmed'=>1])->assertSessionHasErrors();
        }
        $this->delete(route('clients.policies.destroy',[$this->client,$this->policy]))->assertSessionHasErrors();$this->delete(route('clients.destroy',$this->client))->assertSessionHasErrors();
    }
    public function test_invalid_uploads_cannot_be_used_as_signed_documents(): void
    {
        $package=$this->package();$doc=$package->documents()->sole();$base=['request_key'=>(string)Str::uuid(),'signer_name'=>'Paolo Ferri','signed_on'=>'2026-10-18'];
        $this->post(route('clients.forms.submit',[$this->client,$package,$doc]),$base+['files'=>[UploadedFile::fake()->createWithContent('firma.php','<?php echo 1;')]])->assertSessionHasErrors('files.0');
        $this->post(route('clients.forms.submit',[$this->client,$package,$doc]),$base+['files'=>[UploadedFile::fake()->create('firma.pdf',10241,'application/pdf')]])->assertSessionHasErrors('files.0');
        $this->assertDatabaseCount('insurance_form_submissions',0);$this->assertDatabaseCount('attachments',1);
    }
    public function test_authorization_and_next_day_lock_protect_generation_but_allow_receiving_signatures(): void
    {
        $package=$this->package();$operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);$this->actingAs($operator);
        $this->get(route('clients.forms.show',[$this->client,$package]))->assertOk();$this->get(route('form-templates.index'))->assertForbidden();
        $this->get($package->documents()->sole()->generated->downloadUrl())->assertOk();
        $this->client->update(['commercial_user_id'=>$operator->id]);$this->get(route('clients.forms.show',[$this->client,$package]))->assertOk();
        $this->policy->forceFill(['work_closed_at'=>now()->subDay(),'work_closed_by'=>$operator->id])->save();
        $this->get(route('clients.forms.create',[$this->client,'policy'=>$this->policy->id]))->assertForbidden();$this->post(route('clients.forms.store',$this->client),$this->data($this->template()))->assertForbidden();
        $this->signed($package);$this->actingAs($this->admin);$this->get(route('clients.forms.create',[$this->client,'policy'=>$this->policy->id]))->assertOk();
        $other=Client::factory()->create();$this->get(route('clients.forms.show',[$other,$package]))->assertNotFound();$this->get(route('clients.policies.coverage',[$other,$this->policy]))->assertNotFound();
    }
    public function test_manual_rca_evidence_is_historical_and_never_certifies_receipts_or_policy_status(): void
    {
        $response=$this->get(route('clients.policies.coverage',[$this->client,$this->policy]))->assertOk()->assertSee(InsuranceCoverageCheck::URL);
        $original=$this->policy->fresh()->getAttributes();$package=$this->package();$pdf=Storage::disk('attachments')->get($package->documents()->sole()->generated->path);
        $data=['outcome'=>'covered','request_key'=>(string)Str::uuid(),'context_hash'=>$response->viewData('contextHash'),'confirmed'=>1,'files'=>[UploadedFile::fake()->createWithContent('esito.pdf',$pdf),UploadedFile::fake()->createWithContent('dettaglio.pdf',$pdf)]];
        $this->post(route('clients.policies.coverage.store',[$this->client,$this->policy]),$data)->assertSessionHasNoErrors();
        $check=InsuranceCoverageCheck::sole();$this->assertSame('AB123CD',$check->plate);$this->assertCount(2,$check->files);$this->assertSame($original,$this->policy->fresh()->getAttributes());$this->assertDatabaseCount('insurance_collections',0);
        unset($data['files']);$this->post(route('clients.policies.coverage.store',[$this->client,$this->policy]),$data)->assertSessionHasErrors('check');
        $this->policy->vehicle->update(['plate'=>'CD456EF']);$data['request_key']=(string)Str::uuid();$this->post(route('clients.policies.coverage.store',[$this->client,$this->policy]),$data)->assertSessionHasErrors('check');
        $this->get(route('clients.policies.coverage',[$this->client,$this->policy]))->assertOk()->assertSee('AB123CD')->assertSee('Dati della polizza cambiati');
        $this->delete(route('attachments.destroy',$check->files->first()))->assertSessionHasErrors();$this->assertDatabaseCount('insurance_coverage_checks',1);
    }
    public function test_operators_must_supply_proof_and_cannot_configure_form_templates(): void
    {
        $package=$this->package();$reader=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);$this->actingAs($reader);
        $this->get(route('clients.forms.show',[$this->client,$package]))->assertOk()->assertSee('Registra file firmati');
        $this->post(route('clients.forms.submit',[$this->client,$package,$package->documents()->sole()]),[])->assertSessionHasErrors();
        $this->post(route('clients.policies.coverage.store',[$this->client,$this->policy]),[])->assertSessionHasErrors();
        $this->post(route('form-templates.store'),[])->assertForbidden();$this->assertDatabaseCount('insurance_coverage_checks',0);
    }
    public function test_admin_pdf_preview_has_draft_marking_and_does_not_create_client_files(): void
    {
        $template=$this->template(['status'=>'draft']);$response=$this->get(route('form-templates.preview',$template))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringStartsWith('%PDF-',$response->getContent());$this->assertDatabaseCount('attachments',0);
        $this->get(route('form-templates.edit',$template))->assertOk()->assertSee('Approva versione');
        $this->get(route('clients.forms.index',[$this->client,'from'=>'2026-10-01','to'=>'2026-10-31']))->assertOk();
        $this->get(route('clients.forms.index',[$this->client,'to'=>'2026-10-31']))->assertOk();
    }
}
