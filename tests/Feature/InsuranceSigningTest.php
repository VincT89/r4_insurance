<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Attachment, Client, InsuranceBranch, InsuranceCoverageCheck, InsuranceFormPackage, InsuranceFormTemplate, InsurancePolicy, SupplierCompany, User};
use App\Services\{InsuranceForms, PolicyFormReminder};
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
    public function test_formatted_model_preview_revision_and_generated_pdf_keep_the_same_fields_and_layout(): void
    {
        $template = $this->template();
        $old = $this->package($template);
        $originalPdf = $old->documents()->sole()->pdf_hash;
        $delta = json_encode(['ops'=>[
            ['insert'=>'Dati del contratto','attributes'=>['bold'=>true]], ['insert'=>"\n",'attributes'=>['header'=>2,'align'=>'center']],
            ['insert'=>['field'=>'numero_polizza']], ['insert'=>"\n",'attributes'=>['list'=>'bullet']],
            ['insert'=>'Cliente'], ['insert'=>"\n",'attributes'=>['table'=>'row-a']],
            ['insert'=>['field'=>'contraente']], ['insert'=>"\n",'attributes'=>['table'=>'row-a']],
        ]]);
        $payload = ['title'=>'Scheda revisionata','category'=>'consistency','scope'=>'policy','insurance_branch_id'=>$this->branch->id,'client_type'=>'person',
            'editor_content'=>$delta,'signature_required'=>0,'required'=>1,'revise'=>1,'revision_reason'=>'Aggiornate tabella e condizioni.','edit_key'=>$template->fresh()->editKey()];
        $this->post(route('form-templates.draft-preview'),$payload)->assertOk()->assertSee('data-document-preview',false);
        $response = $this->post(route('form-templates.draft-preview'),$payload,['Accept'=>'application/pdf'])->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertDatabaseCount('insurance_form_templates',1);
        $this->patch(route('form-templates.update',$template),$payload)->assertSessionHasNoErrors();
        $draft = $template->next()->sole();
        $this->assertTrue($draft->required);
        $this->assertFalse($draft->signature_required);
        $this->get(route('form-templates.edit',[$draft]))->assertOk()->assertSee('Inserisci campo automatico');
        $this->post(route('form-templates.approve',$draft),['confirmed'=>1,'edit_key'=>str_repeat('0',64)])->assertSessionHasErrors('forms');
        $this->post(route('form-templates.approve',$draft),['confirmed'=>1,'edit_key'=>$draft->editKey()])->assertSessionHasNoErrors();
        $docs = $this->package($draft)->documents()->sole();
        $this->assertStringContainsString('SA-2026-501',$docs->body);
        $this->assertStringContainsString('Paolo Ferri',$docs->body);
        $this->assertSame($originalPdf,$old->documents()->sole()->pdf_hash);
        $html = \App\Support\FormTemplateContent::html($draft->body,['{{contraente}}'=>'<img src=x>','{{numero_polizza}}'=>'P-1']);
        $this->assertStringContainsString('<h2 style="text-align:center"><strong>Dati del contratto</strong></h2>',$html);
        $this->assertStringContainsString('<ul><li>P-1</li></ul>',$html);
        $this->assertStringContainsString('<td>Cliente</td><td>&lt;img src=x&gt;</td>',$html);
    }
    public function test_editor_rejects_unsafe_content_and_stale_draft_edits(): void
    {
        $data = ['title'=>'Scheda','category'=>'other','scope'=>'policy','signature_required'=>0,'required'=>0];
        foreach ([[['insert'=>['image'=>'https://example.test/a']]], [['insert'=>'Testo','attributes'=>['color'=>'url(https://example.test)']]],
            [['insert'=>['field'=>'sconosciuto']]], [['insert'=>'{{numero_'],['insert'=>'polizza}}','attributes'=>['bold'=>true]]]] as $ops) {
            $this->post(route('form-templates.store'), $data+['editor_content'=>json_encode(['ops'=>$ops])])->assertSessionHasErrors('body');
        }
        $this->assertDatabaseCount('insurance_form_templates',0);
        $draft = $this->template(['status'=>'draft']);
        $stale = $draft->editKey();
        $draft->update(['title'=>'Aggiornamento da un’altra scheda']);
        $this->patch(route('form-templates.update',$draft),$data+['edit_key'=>$stale,'body'=>'Testo valido'])->assertSessionHasErrors('forms');
        $this->assertSame('Aggiornamento da un’altra scheda',$draft->fresh()->title);
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']));
        $this->post(route('form-templates.draft-preview'),$data+['body'=>'Testo valido'])->assertOk();
    }
    public function test_generated_pdf_is_private_frozen_encrypted_and_linked_to_client_and_policy(): void
    {
        $template=$this->template();$package=$this->package($template);$document=$package->documents()->sole();$attachment=$document->generated;
        $bytes=Storage::disk('attachments')->get($attachment->path);$this->assertStringStartsWith('%PDF-',$bytes);$this->assertSame(hash('sha256',$bytes),$document->pdf_hash);
        $this->assertStringContainsString('Paolo Ferri',$document->body);$this->assertStringContainsString('SI / NO',$document->body);$this->assertStringNotContainsString('{{',$document->body);
        $this->assertStringNotContainsString('Paolo Ferri',DB::table('insurance_form_documents')->value('body'));$this->assertStringNotContainsString('Paolo Ferri',DB::table('insurance_form_packages')->value('snapshot'));
        $this->assertSame($this->policy->id,$attachment->insurance_policy_id);$this->assertSame($this->client->id,$attachment->attachable_id);$this->assertSame('generated',$attachment->review_status);
        $this->get($attachment->downloadUrl())->assertOk();$this->get(route('clients.forms.show',[$this->client,$package]))->assertOk()->assertSee('Firma mancante');
        $this->get(route('clients.show',[$this->client,'tab'=>'documents']))->assertOk()->assertSee('Apri modulo nello storico')->assertSee('#form-document-'.$document->id);
        $this->assertDatabaseCount('client_consents',0);$this->assertDatabaseCount('insurance_collections',0);
        $template->update(['body'=>'Testo successivo']);$this->assertStringContainsString('Paolo Ferri',$document->fresh()->body);
    }
    public function test_stale_duplicate_and_unavailable_templates_do_not_create_pdfs(): void
    {
        $t=$this->template();
        $data=$this->data($t);$this->client->update(['name'=>'Paolo Ferri aggiornato']);
        $this->post(route('clients.forms.store',$this->client),$data)->assertSessionHasErrors('forms');
        $t->update(['status'=>'retired']);$this->post(route('clients.forms.store',$this->client),$this->data($t))->assertSessionHasErrors('forms');$t->update(['status'=>'active']);
        $this->assertDatabaseCount('insurance_form_packages',0);$this->assertCount(0,Storage::disk('attachments')->allFiles());
        $data=$this->data($t);$this->post(route('clients.forms.store',$this->client),$data)->assertSessionHasNoErrors();$this->post(route('clients.forms.store',$this->client),$data)->assertSessionHasErrors('forms');
        $this->assertDatabaseCount('insurance_form_packages',1);$this->assertCount(1,Storage::disk('attachments')->allFiles());
    }
    public function test_reminder_generates_only_the_chosen_templates_and_can_include_more_than_twelve(): void
    {
        $default = $this->template(['title'=>'Preselezionato','required'=>true]);
        $selected = collect(range(1,13))->map(fn ($i) => $this->template(['title'=>'Scheda '.$i,'signature_required'=>false,'body'=>'Polizza {{numero_polizza}}.']));
        PolicyFormReminder::record($this->policy);
        $key = PolicyFormReminder::key($this->policy, null);
        $reminder = PolicyFormReminder::find($this->policy);
        $this->get(PolicyFormReminder::url($this->policy))->assertOk()->assertSee('Preselezionato')->assertSee('Scheda 13');
        $this->get(route('clients.policies.show', [$this->client,$this->policy]))->assertOk()->assertSee('Scegli i moduli');
        $reminder->markAsRead();
        $this->assertNotNull(PolicyFormReminder::find($this->policy));
        $data = $this->data($default, extra:['reminder'=>$key,'templates'=>$selected->pluck('id')->all(),'signer_name'=>null,'place'=>null]);
        $this->post(route('clients.forms.store',$this->client), $data)->assertSessionHasNoErrors();
        $package = InsuranceFormPackage::sole();
        $this->assertEqualsCanonicalizing($selected->pluck('id')->all(), $package->documents()->pluck('insurance_form_template_id')->all());
        $this->assertNull(PolicyFormReminder::find($this->policy));
        $this->get(PolicyFormReminder::url($this->policy))->assertRedirect(route('clients.forms.show', [$this->client,$package]));
        $this->post(route('clients.forms.store',$this->client), $data)->assertSessionHasErrors('forms');
        $this->assertCount(13, Storage::disk('attachments')->allFiles());
    }
    public function test_operator_can_skip_all_modules_without_generating_documents_or_consuming_a_stale_reminder(): void
    {
        $operator = User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($operator);
        $default = $this->template(['required'=>true]);
        PolicyFormReminder::record($this->policy);
        $key = PolicyFormReminder::key($this->policy, null);
        $input = ['policy'=>$this->policy->id,'reminder'=>$key,'context_hash'=>app(InsuranceForms::class)->contextHash($this->client,$this->policy)];
        $url = route('clients.forms.reminder-decision',[$this->client,'none']);
        $this->post(route('clients.forms.reminder-decision',[$this->client,'later']),$input)->assertSessionHasNoErrors();
        $this->assertSame([], PolicyFormReminder::find($this->policy)->data['selected_templates']);
        $this->post($url,$input+['templates'=>[$default->id]])->assertSessionHasErrors('templates');
        $this->post($url,array_replace($input,['reminder'=>(string)Str::uuid()]))->assertSessionHasErrors('forms');
        $this->post($url,array_replace($input,['context_hash'=>str_repeat('0',64)]))->assertSessionHasErrors('forms');
        $this->assertNotNull(PolicyFormReminder::find($this->policy));
        $this->post($url,$input)->assertSessionHasNoErrors()->assertRedirect(route('clients.policies.show',[$this->client,$this->policy,'tab'=>'forms']));
        $this->assertNull(PolicyFormReminder::find($this->policy));
        $this->assertDatabaseCount('insurance_form_packages',0);
        $this->assertDatabaseHas('audit_logs',['action'=>'forms_skipped','user_id'=>$operator->id]);
        $this->assertCount(0, Storage::disk('attachments')->allFiles());
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
    public function test_policy_shows_each_pending_document_and_receives_and_reviews_the_correct_copy_in_place(): void
    {
        $package = $this->package($this->template(['title'=>'Modulo da sottoscrivere']));
        $document = $package->documents()->sole();
        $this->package($this->template(['title'=>'Informazioni senza firma','signature_required'=>false]));
        $url = route('clients.policies.show',[$this->client,$this->policy]);
        $return = route('clients.policies.show',[$this->client,$this->policy,'tab'=>'forms']).'#policy-signatures';
        $this->get($url)->assertOk()->assertSee('Carica copia firmata')->assertSee('Modulo da sottoscrivere')
            ->assertViewHas('signatures',fn ($groups) => $groups['toSign']->modelKeys() === [$document->id] && $groups['toReview']->isEmpty());
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']));
        $this->policy->forceFill(['work_closed_at'=>now()->subDays(2)])->save();
        $payload = ['signature_origin'=>'policy','previous_submission'=>0,'signature_document'=>$document->id,'signature_action'=>'upload',
            'request_key'=>(string)Str::uuid(),'signer_name'=>'Firmatario dichiarato','signed_on'=>today()->subDay()->toDateString(),
            'files'=>[UploadedFile::fake()->createWithContent('copia.pdf',Storage::disk('attachments')->get($document->generated->path))]];
        $upload = route('clients.forms.submit',[$this->client,$package,$document]);
        $this->post($upload,$payload)->assertSessionHasNoErrors()->assertRedirect($return);
        $first = $document->submissions()->sole();
        $this->assertSame('received',$first->status);
        $this->get($url)->assertOk()->assertSee('Firmatario dichiarato')->assertSee('Apri copia ricevuta')
            ->assertViewHas('signatures',fn ($groups) => $groups['toSign']->isEmpty() && $groups['toReview']->modelKeys() === [$document->id]);
        $this->post($upload,array_replace($payload,['request_key'=>(string)Str::uuid(),'note'=>'Nuova copia da una scheda ormai superata.']))->assertSessionHasErrors('forms');
        $this->assertCount(1,$document->submissions()->get());
        $this->review($package,$first,['signature_origin'=>'policy','outcome'=>'rejected','review_note'=>'Occorre la seconda pagina.'])->assertRedirect($return);
        $this->get($url)->assertOk()->assertSee('Carica copia corretta')->assertSee('Occorre la seconda pagina.');
        $this->post($upload,array_replace($payload,['request_key'=>(string)Str::uuid(),'previous_submission'=>$first->id,'note'=>'Aggiunta la pagina richiesta.']))->assertSessionHasNoErrors()->assertRedirect($return);
        $second = $document->submissions()->first();
        $this->assertSame(2,$second->version);
        $this->review($package,$second,['signature_origin'=>'policy'])->assertRedirect($return);
        $this->get($url)->assertOk()->assertViewHas('signatures',fn ($groups) => $groups['toSign']->isEmpty() && $groups['toReview']->isEmpty() && $groups['completed']->modelKeys() === [$document->id]);
        $this->assertSame('rejected',$first->fresh()->status);
        $otherClient = Client::factory()->create();
        $this->post(route('clients.forms.submit',[$otherClient,$package,$document]),$payload)->assertNotFound();
    }
    public function test_renewal_keeps_only_unfinished_older_signatures_in_the_policy_queue(): void
    {
        $old = $this->package($this->template(['title'=>'Modulo periodo precedente']));
        $closed = $this->package($this->template(['title'=>'Modulo storico controllato']));
        $this->review($closed,$this->signed($closed))->assertSessionHasNoErrors();
        $before = app(InsuranceForms::class)->context($this->client,$this->policy);
        $after = $before;
        $after['policy']['starts_on'] = '2027-10-18';
        $after['policy']['expires_on'] = '2028-10-18';
        $renewal = $this->policy->incomingChanges()->create([
            'source_policy_id'=>$this->policy->id,'kind'=>'renewal','effective_on'=>'2027-10-18','expires_on'=>'2028-10-18',
            'reference'=>$this->policy->number,'description'=>'Rinnovo di collaudo','premium_cents'=>100001,'snapshot'=>['before'=>$before,'after'=>$after],
            'request_key'=>(string)Str::uuid(),'created_by'=>$this->admin->id,
        ]);
        $this->policy->update(['starts_on'=>$renewal->effective_on,'expires_on'=>$renewal->expires_on]);
        $current = $this->package($this->template(['title'=>'Modulo periodo corrente']),['change'=>$renewal->id,'reason'=>'renewal','note'=>$renewal->description]);
        $otherPolicy = $this->client->policies()->create(['number'=>'ALTRA-POLIZZA','category'=>'RC auto','company'=>'Altra compagnia','starts_on'=>today(),'expires_on'=>today()->addYear(),'status'=>'active']);
        $url = route('clients.policies.show',[$this->client,$this->policy]);
        $groups = $this->get($url)->assertOk()->assertSee('Firme da completare dei periodi precedenti')->viewData('signatures');
        $this->assertSame([$old->documents()->sole()->id], $groups['previous']->values()->modelKeys());
        $this->assertSame([$current->documents()->sole()->id], $groups['toSign']->values()->modelKeys());
        $this->assertTrue($groups['completed']->isEmpty());
        $this->get(route('clients.policies.show',[$this->client,$otherPolicy]))->assertOk()->assertViewHas('signatures',fn ($groups) => collect($groups)->every(fn ($group) => $group->isEmpty()));
        $this->review($old,$this->signed($old),['signature_origin'=>'policy'])->assertSessionHasNoErrors();
        $this->get($url)->assertOk()->assertViewHas('signatures',fn ($groups) => $groups['previous']->isEmpty() && $groups['toSign']->count()===1);
        $this->get(route('clients.forms.show',[$this->client,$old]))->assertOk()->assertSee('Firma controllata');
    }
    public function test_client_and_upload_screens_point_to_the_exact_open_module_and_respect_its_scope(): void
    {
        $policyPackage = $this->package($this->template(['title'=>'Modulo legato al contratto']));
        $policyDocument = $policyPackage->documents()->sole();
        $registryPackage = $this->package($this->template(['scope'=>'client','title'=>'Modulo anagrafica']),[
            'policy'=>null,'reason'=>'client','context_hash'=>app(InsuranceForms::class)->contextHash($this->client->fresh(),null),
        ]);
        $registryDocument = $registryPackage->documents()->sole();
        $policyAction = route('clients.policies.show',[$this->client,$this->policy,'tab'=>'forms']).'#policy-signature-'.$policyDocument->id;
        $registryAction = route('clients.forms.show',[$this->client,$registryPackage]).'#form-document-'.$registryDocument->id;
        $this->get(route('clients.show',$this->client))->assertOk()->assertSee($policyAction)->assertDontSee($registryAction);
        foreach ([route('clients.show',[$this->client,'tab'=>'documents']),route('clients.documents.create',$this->client),route('clients.forms.index',$this->client)] as $url) {
            $this->get($url)->assertOk()->assertSee($policyAction)->assertSee($registryAction)->assertSee('Carica copia firmata');
        }
        $this->get(route('clients.documents.create',[$this->client,'policy'=>$this->policy->id]))->assertOk()->assertSee($policyAction)->assertDontSee($registryAction);
        $this->get(route('clients.forms.index',[$this->client,'reason'=>'client']))->assertOk()->assertSee($registryAction)->assertDontSee($policyAction);
        $registryCopy = $this->signed($registryPackage);
        $this->get(route('clients.forms.show',[$this->client,$registryPackage]))->assertOk()
            ->assertSeeInOrder(['Controlla copia ricevuta',$registryCopy->files->first()->openUrl(),'Conferma controllo','Copie firmate']);
        $another = Client::factory()->create();
        $this->get(route('clients.show',[$another,'tab'=>'documents']))->assertOk()->assertDontSee($policyAction)->assertDontSee($registryAction);
    }
    public function test_current_documents_show_all_files_of_the_latest_signed_copy_and_keep_older_ones_in_history(): void
    {
        $package = $this->package();
        $first = $this->signed($package);
        $this->review($package,$first,['outcome'=>'rejected','review_note'=>'Manca una pagina.'])->assertSessionHasNoErrors();
        $latest = $this->signed($package,['note'=>'Nuova copia con la seconda pagina.']);
        $current = $this->get(route('clients.show',[$this->client,'tab'=>'documents']))->assertOk()->assertSee('Controlla copia ricevuta');
        $currentIds = $current->viewData('documents')->modelKeys();
        $this->assertSame([], array_values(array_intersect($currentIds,$first->files->modelKeys())));
        $this->assertSame([], array_values(array_diff($latest->files->modelKeys(),$currentIds)));
        $this->assertContains($package->documents()->sole()->generated->id,$currentIds);
        $policyIds = $this->get(route('clients.policies.show',[$this->client,$this->policy,'tab'=>'documents']))->assertOk()->viewData('policy')->documents->modelKeys();
        $this->assertEqualsCanonicalizing($currentIds,$policyIds);
        $history = $this->get(route('clients.show',[$this->client,'tab'=>'documents','document_view'=>'history']))->assertOk()->assertSee('Copia 1 / storica')->viewData('documents');
        $this->assertSame([], array_values(array_diff($first->files->modelKeys(),$history->modelKeys())));
        $this->review($package,$latest)->assertSessionHasNoErrors();
        $this->get(route('clients.show',[$this->client,'tab'=>'documents']))->assertOk()->assertSee('Firma controllata')->assertViewHas('openSignatures',fn ($docs) => $docs->isEmpty());
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
        $this->get(route('clients.forms.show',[$this->client,$package]))->assertOk();$this->get(route('form-templates.index'))->assertOk();
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
    public function test_operators_must_supply_proof_and_can_configure_form_templates(): void
    {
        $package=$this->package();$reader=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);$this->actingAs($reader);
        $this->get(route('clients.forms.show',[$this->client,$package]))->assertOk()->assertSee('Salva copia firmata');
        $this->post(route('clients.forms.submit',[$this->client,$package,$package->documents()->sole()]),[])->assertSessionHasErrors();
        $this->post(route('clients.policies.coverage.store',[$this->client,$this->policy]),[])->assertSessionHasErrors();
        $this->post(route('form-templates.store'),[])->assertSessionHasErrors('title');$this->assertDatabaseCount('insurance_coverage_checks',0);
    }
    public function test_operator_edits_complete_documents_with_images_layout_and_preserved_versions(): void
    {
        $operator = User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($operator);
        $image = ['data'=>'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('logo-dimostrativo.png',80,40)->get()),
            'alt'=>'Logo dimostrativo','width'=>25,'align'=>'left'];
        $delta = ['ops'=>[['insert'=>'Documento dimostrativo per '],['insert'=>['field'=>'contraente']],['insert'=>"\n"],
                ['insert'=>['page_break'=>true]],['insert'=>"Seconda pagina\n"]],
            'header'=>[['insert'=>['image'=>$image]],['insert'=>"\n"],['insert'=>['field'=>'titolo_modello']],['insert'=>"\n"]],
            'footer'=>[['insert'=>'Riferimento '],['insert'=>['field'=>'riferimento_documento']],['insert'=>"\n"]],
            'layout'=>array_replace(\App\Support\FormTemplateContent::defaultLayout(),['orientation'=>'landscape','margin_left'=>20])];
        $data = ['title'=>'Modello completo dimostrativo','category'=>'other','scope'=>'policy','client_type'=>'all',
            'signature_required'=>0,'required'=>0,'editor_content'=>json_encode($delta)];
        $this->get(route('form-templates.create'))->assertOk()->assertSee('Intestazione')->assertSee('Impostazioni della pagina');
        $this->post(route('form-templates.store'),$data)->assertSessionHasNoErrors();
        $template = InsuranceFormTemplate::sole();
        $this->assertSame($operator->id,$template->created_by);
        $this->assertTrue(\App\Support\FormTemplateContent::isDocument($template->body));
        $this->assertSame($delta,\App\Support\FormTemplateContent::editorDocument($template->body));
        $this->get(route('form-templates.preview',$template))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->post(route('form-templates.approve',$template),['confirmed'=>1,'edit_key'=>$template->editKey()])->assertSessionHasNoErrors();
        $package = $this->package($template);
        $document = $package->documents()->sole();
        $bytes = Storage::disk('attachments')->get($document->generated->path);
        $this->assertStringContainsString('/Subtype /Image',$bytes);
        $this->assertSame(1,preg_match('~/MediaBox\s*\[\s*([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s*\]~',$bytes,$box));
        $this->assertGreaterThan((float)$box[4],(float)$box[3]);
        $this->assertStringContainsString('Paolo Ferri',$document->body);
        $this->assertStringContainsString('Modello completo dimostrativo',$document->body);
        $oldHash = $document->pdf_hash;
        $delta['ops'] = [['insert'=>"Versione aggiornata\n"]];
        $this->patch(route('form-templates.update',$template),array_replace($data,['editor_content'=>json_encode($delta),'edit_key'=>$template->fresh()->editKey(),'revise'=>1,'revision_reason'=>'Aggiornamento dimostrativo completo']))->assertSessionHasNoErrors();
        $revision = $template->next()->sole();
        $this->post(route('form-templates.approve',$revision),['confirmed'=>1,'edit_key'=>$revision->editKey()])->assertSessionHasNoErrors();
        $this->assertSame('retired',$template->fresh()->status);
        $this->assertSame($oldHash,hash('sha256',Storage::disk('attachments')->get($document->generated->path)));
        $this->assertTrue($operator->hasRetainedHistory());
        $this->get(route('users.index'))->assertForbidden();
        $operator->update(['status'=>'inactive']);
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($operator->fresh())->denies('forms.manage'));
    }

    public function test_complete_document_rejects_remote_images_unsafe_headers_and_invalid_layout(): void
    {
        $valid = ['ops'=>[['insert'=>"Testo dimostrativo\n"]],'header'=>[],'footer'=>[],'layout'=>\App\Support\FormTemplateContent::defaultLayout()];
        $invalid = [];
        $invalid[] = array_replace($valid,['header'=>[['insert'=>'<script>alert(1)</script>']]]);
        $invalid[] = array_replace($valid,['header'=>[['insert'=>['page_break'=>true]]]]);
        $invalid[] = array_replace($valid,['footer'=>[['insert'=>['field'=>'campo_non_consentito']]]]);
        $invalid[] = array_replace($valid,['layout'=>array_replace($valid['layout'],['margin_left'=>-10])]);
        $invalid[] = array_replace($valid,['layout'=>array_replace($valid['layout'],['orientation'=>'url(https://example.test)'])]);
        foreach (['https://example.test/logo.png','data:image/svg+xml;base64,'.base64_encode('<svg onload="alert(1)"/>'),'data:image/png;base64,'.base64_encode('contenuto dimostrativo non immagine')] as $source) {
            $invalid[] = array_replace($valid,['header'=>[['insert'=>['image'=>['data'=>$source,'alt'=>'Logo dimostrativo','width'=>25,'align'=>'left']]]]]);
        }
        $invalid[] = array_replace($valid,['ops'=>array_merge($valid['ops'],array_fill(0,41,['insert'=>['page_break'=>true]]))]);
        $image = ['data'=>'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('logo-dimostrativo.png',80,40)->get()),'alt'=>'Logo dimostrativo','width'=>25,'align'=>'left'];
        $invalid[] = array_replace($valid,['header'=>array_fill(0,21,['insert'=>['image'=>$image]])]);
        foreach ($invalid as $delta) {
            $this->post(route('form-templates.store'),['title'=>'Modello dimostrativo','category'=>'other','scope'=>'policy','signature_required'=>0,'required'=>0,
                'editor_content'=>json_encode($delta)])->assertSessionHasErrors('body');
        }
        $this->assertDatabaseCount('insurance_form_templates',0);
    }

    public function test_repeated_header_cannot_leave_the_printed_document_without_room_for_text(): void
    {
        $document = ['ops'=>[['insert'=>"Testo dimostrativo\n"]],'header'=>[['insert'=>str_repeat("Intestazione dimostrativa\n",45)]],
            'footer'=>[],'layout'=>array_replace(\App\Support\FormTemplateContent::defaultLayout(),['repeat_header'=>true,'orientation'=>'landscape'])];
        $template = $this->template(['status'=>'draft','body'=>\App\Support\FormTemplateContent::encode(json_encode($document))]);
        $this->get(route('form-templates.preview',$template))->assertSessionHasErrors('body');
        $this->assertDatabaseCount('attachments',0);
    }

    public function test_policy_reminder_lists_all_active_models_and_keeps_the_operator_selection(): void
    {
        $operator = User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($operator);
        $policyTemplate = $this->template(['title'=>'Modulo polizza dimostrativo']);
        $clientTemplate = $this->template(['title'=>'Modulo generico cliente','scope'=>'client','client_type'=>'company','required'=>true]);
        $branch = InsuranceBranch::create(['name'=>'Abitazione dimostrativa','name_key'=>hash('sha256','ABITAZIONE DIMOSTRATIVA'),'status'=>'active']);
        $otherBranch = $this->template(['title'=>'Modulo altro ramo','insurance_branch_id'=>$branch->id]);
        $this->template(['title'=>'Ritirato dimostrativo','status'=>'retired']);
        $this->template(['title'=>'Bozza dimostrativa','status'=>'draft']);
        PolicyFormReminder::record($this->policy);
        $key = PolicyFormReminder::key($this->policy,null);
        $url = route('clients.forms.create',[$this->client,'policy'=>$this->policy->id,'reminder'=>$key]);
        $response = $this->get($url)->assertOk()->assertSee($policyTemplate->title)->assertSee($clientTemplate->title)->assertSee($otherBranch->title)
            ->assertDontSee('Ritirato dimostrativo')->assertDontSee('Bozza dimostrativa');
        $this->assertEqualsCanonicalizing([$policyTemplate->id,$clientTemplate->id,$otherBranch->id],$response->viewData('templates')->modelKeys());
        $selection = [$policyTemplate->id,$clientTemplate->id];
        $this->post(route('clients.forms.reminder-decision',[$this->client,'later']),['policy'=>$this->policy->id,'reminder'=>$key,
            'context_hash'=>app(InsuranceForms::class)->contextHash($this->client,$this->policy),'templates'=>$selection])->assertSessionHasNoErrors();
        $this->assertSame($selection,PolicyFormReminder::find($this->policy)->data['selected_templates']);
        $this->post(route('clients.forms.store',$this->client),$this->data($policyTemplate,extra:['templates'=>$selection,'reminder'=>$key]))->assertSessionHasNoErrors();
        $this->assertSame($selection,InsuranceFormPackage::sole()->documents()->orderBy('insurance_form_template_id')->pluck('insurance_form_template_id')->all());
        $this->assertNull(PolicyFormReminder::find($this->policy));
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
