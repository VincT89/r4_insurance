<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Attachment, Client, DocumentSignature, InsuranceBranch, InsuranceFormTemplate, InsurancePolicy, SupplierCompany, User};
use App\Services\{DocumentSigning, InsuranceForms, PrivateAttachmentBatch};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Crypt, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class TabletDocumentSigningTest extends TestCase
{
    use RefreshDatabase;
    private User $operator;
    private Client $client;
    private InsurancePolicy $policy;
    private Attachment $source;
    private string $pdf;

    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('attachments');
        $this->operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']); $this->actingAs($this->operator);
        $this->client=Client::factory()->create(['client_type'=>'person','name'=>'Firmatario di collaudo','tax_code'=>null,'vat_number'=>null]);
        $branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $company=SupplierCompany::create(['name'=>'Compagnia collaudo','status'=>'active']);
        $this->policy=$this->client->policies()->create(['category'=>'RC auto','insurance_branch_id'=>$branch->id,'company'=>$company->name,'supplier_company_id'=>$company->id,'number'=>'COLLAUDO-FIRMA-01','starts_on'=>today(),'expires_on'=>today()->addYear(),'status'=>'active','premium'=>'500','payment_frequency'=>'Annuale','payment_method'=>'Contanti']);
        $pdf=new \Dompdf\Dompdf(); $pdf->loadHtml('<h1>Contratto originale</h1><p>Contenuto da conservare.</p>'); $pdf->render(); $this->pdf=$pdf->output();
        $this->source=app(PrivateAttachmentBatch::class)->upload($this->client,[UploadedFile::fake()->createWithContent('contratto.pdf',$this->pdf)],['document_category'=>'policy','insurance_policy_id'=>$this->policy->id])[0];
    }

    private function input(?Attachment $source=null): array
    {
        $prepared=app(DocumentSigning::class)->prepare($source??$this->source);
        return ['token'=>$prepared['token'],'signer_name'=>'Firmatario di collaudo','signer_role'=>'','consent'=>true,'identity_confirmed'=>true,
            'strokes'=>[[[.1,.7],[.2,.2],[.25,.6],[.35,.3],[.4,.7],[.5,.4],[.6,.6]]],
            'placements'=>[['page'=>1,'x'=>.55,'y'=>.8,'width'=>.3,'height'=>.08]]];
    }

    public function test_operator_can_sign_a_contract_without_changing_the_original(): void
    {
        $this->get(route('attachments.sign',$this->source))->assertOk()->assertSee('Firma il documento')->assertSee('Conferma e salva firma');
        $input=$this->input();
        $this->postJson(route('attachments.sign.store',$this->source),$input)->assertOk()->assertJsonPath('url',route('clients.policies.show',[$this->client,$this->policy,'tab'=>'documents']));
        $record=DocumentSignature::sole();
        $this->assertSame($this->pdf,Storage::disk('attachments')->get($this->source->path));
        $this->assertSame(hash('sha256',$this->pdf),$record->source_hash);
        $this->assertSame(hash('sha256',Storage::disk('attachments')->get($record->signedFile->path)),$record->signed_hash);
        $this->assertSame($this->operator->id,$record->created_by);
        $this->assertSame('simple',$record->evidence['signature_type']);
        $this->assertDatabaseCount('insurance_collections',0);
        $this->assertSame(1,\App\Models\AuditLog::where('action','document_signed')->count());
        $this->assertDatabaseHas('audit_logs',['action'=>'document_signed']);
        $this->assertSame(1,$this->policy->contracts()->count());
        $this->get(route('clients.policies.show',[$this->client,$this->policy,'tab'=>'documents']))->assertOk()->assertSee('Apri copia con firma');
        $this->assertStringNotContainsString('Firmatario',$record->getRawOriginal('signer_name'));
    }

    public function test_empty_or_incomplete_drawing_and_unconfirmed_consent_are_rejected(): void
    {
        $input=$this->input();
        foreach ([[],[[[.1,.1]]],[[[.1,.2],[.2,.2],[.3,.2],[.4,.2],[.5,.2]]]] as $strokes) $this->postJson(route('attachments.sign.store',$this->source),array_replace($input,['strokes'=>$strokes]))->assertUnprocessable();
        $this->postJson(route('attachments.sign.store',$this->source),array_replace($input,['consent'=>false]))->assertUnprocessable()->assertJsonValidationErrors('consent');
        $this->postJson(route('attachments.sign.store',$this->source),array_replace($input,['identity_confirmed'=>false]))->assertUnprocessable()->assertJsonValidationErrors('identity_confirmed');
        $this->assertDatabaseCount('document_signatures',0); $this->assertDatabaseCount('attachments',1);
    }

    public function test_pdf_engine_also_works_when_http_server_arrays_omit_os_variables(): void
    {
        $input=$this->input(); $server=$_SERVER; $environment=$_ENV;
        try {
            foreach (array_unique(array_merge(array_keys($_SERVER),array_keys($_ENV))) as $name) {
                if (in_array(strtoupper($name),['PATH','SYSTEMROOT','WINDIR','TEMP','TMP','COMSPEC','PATHEXT'],true)) { unset($_SERVER[$name],$_ENV[$name]); }
            }
            $this->postJson(route('attachments.sign.store',$this->source),$input)->assertOk();
            $this->assertDatabaseCount('document_signatures',1);
        } finally { $_SERVER=$server; $_ENV=$environment; }
    }

    public function test_changed_source_expired_or_forged_tokens_cannot_save_a_signature(): void
    {
        $input=$this->input();
        $this->postJson(route('attachments.sign.store',$this->source),array_replace($input,['token'=>'forged']))->assertUnprocessable();
        $payload=json_decode(Crypt::decryptString($input['token']),true); $payload['expires']=now()->subMinute()->timestamp;
        $this->postJson(route('attachments.sign.store',$this->source),array_replace($input,['token'=>Crypt::encryptString(json_encode($payload))]))->assertUnprocessable();
        Storage::disk('attachments')->put($this->source->path,$this->pdf."\nchanged");
        $this->postJson(route('attachments.sign.store',$this->source),$input)->assertUnprocessable();
        $this->assertDatabaseCount('document_signatures',0);
    }

    public function test_tokens_are_bound_to_the_operator_and_source_and_guest_access_is_denied(): void
    {
        $input=$this->input();
        $other=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']); $this->actingAs($other);
        $this->postJson(route('attachments.sign.store',$this->source),$input)->assertForbidden();
        auth()->logout(); $this->get(route('attachments.sign',$this->source))->assertRedirect(route('login'));
        $this->assertDatabaseCount('document_signatures',0);
    }

    public function test_duplicate_submission_is_idempotent_and_another_copy_keeps_the_previous_one(): void
    {
        $input=$this->input(); $stale=$this->input();
        $this->postJson(route('attachments.sign.store',$this->source),$input)->assertOk(); $first=DocumentSignature::sole();
        $this->postJson(route('attachments.sign.store',$this->source),$input)->assertOk(); $this->assertDatabaseCount('document_signatures',1);
        $this->postJson(route('attachments.sign.store',$this->source),array_replace($input,['signer_name'=>'Altro nome']))->assertUnprocessable();
        $this->postJson(route('attachments.sign.store',$this->source),$stale)->assertUnprocessable();
        $this->postJson(route('attachments.sign.store',$this->source),$this->input())->assertUnprocessable();
        $next=$this->input()+['note'=>'Nuova copia richiesta dal firmatario.'];
        $this->postJson(route('attachments.sign.store',$this->source),$next)->assertOk();
        $this->assertDatabaseCount('document_signatures',2); $this->assertTrue(Storage::disk('attachments')->exists($first->signedFile->path));
        $this->assertSame($this->pdf,Storage::disk('attachments')->get($this->source->path));
    }

    public function test_out_of_page_placement_and_unavailable_engine_do_not_create_partial_records(): void
    {
        $input=$this->input(); $input['placements'][0]['x']=.9;
        $this->postJson(route('attachments.sign.store',$this->source),$input)->assertUnprocessable();
        $input=$this->input(); $input['placements'][0]['page']=2;
        $this->postJson(route('attachments.sign.store',$this->source),$input)->assertUnprocessable();
        config(['document_signing.node_binary'=>'nonexistent-signing-engine']);
        $this->postJson(route('attachments.sign.store',$this->source),$this->input())->assertUnprocessable();
        $this->assertDatabaseCount('document_signatures',0); $this->assertDatabaseCount('attachments',1);
    }

    public function test_generated_form_uses_the_existing_submission_and_review_workflow(): void
    {
        $template=InsuranceFormTemplate::create(['family'=>(string)Str::uuid(),'version'=>1,'title'=>'Modulo firma tablet','category'=>'consistency','scope'=>'policy','body'=>'Contraente {{contraente}}. Firma: __________________','signature_required'=>true,'required'=>false,'status'=>'active','approved_by'=>$this->operator->id,'approved_at'=>now()]);
        $package=app(InsuranceForms::class)->createPackage($this->client,$this->policy,['context_hash'=>app(InsuranceForms::class)->contextHash($this->client,$this->policy),'request_key'=>(string)Str::uuid(),'templates'=>[$template->id],'reason'=>'initial','signer_name'=>$this->client->name,'place'=>'Napoli','document_date'=>today()->toDateString()]);
        $document=$package->documents()->sole(); $source=$document->generated;
        $this->postJson(route('attachments.sign.store',$source),$this->input($source))->assertOk();
        $record=DocumentSignature::sole(); $submission=$document->latestSubmission;
        $this->assertSame($submission->id,$record->insurance_form_submission_id); $this->assertSame('received',$submission->status);
        $this->assertSame($record->signed_attachment_id,$submission->files->sole()->id);
        $this->post(route('clients.forms.review',[$this->client,$package,$submission]),['outcome'=>'validated','confirmed'=>true])->assertSessionHasNoErrors();
        $this->assertSame('Firma controllata',$document->fresh()->state_label);
    }

    public function test_signature_history_originals_and_results_cannot_be_deleted_or_rebound(): void
    {
        $this->postJson(route('attachments.sign.store',$this->source),$this->input())->assertOk();
        $record=DocumentSignature::sole();
        foreach ([$record,$this->source,$record->signedFile] as $model) {
            try { $model->delete(); $this->fail('History must be retained.'); }
            catch (\Illuminate\Validation\ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
        }
        try { $this->source->update(['path'=>'different.pdf']); $this->fail('The original cannot be rebound.'); }
        catch (\Illuminate\Validation\ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
        $this->assertTrue($this->operator->hasRetainedHistory());
        $this->expectException(\RuntimeException::class); $this->expectExceptionMessage('firme e documenti originali');
        app(\App\Services\InsuranceDemoReset::class)->apply($this->operator);
    }
}
