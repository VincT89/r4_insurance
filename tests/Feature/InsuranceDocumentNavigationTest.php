<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Attachment, Client, InsurancePolicy, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InsuranceDocumentNavigationTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private InsurancePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('attachments');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin]));
        $this->client=Client::factory()->create();
        $this->policy=$this->client->policies()->create(['number'=>'DEMO-DOC-01','category'=>'RC auto','company'=>'Compagnia dimostrativa','starts_on'=>today(),'expires_on'=>today()->addYear(),'status'=>'active']);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('contratto-dimostrativo.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }

    private function upload(array $extra=[])
    {
        return $this->post(route('clients.documents.store',$this->client), array_merge(['document_category'=>'policy','insurance_policy_id'=>$this->policy->id,'file'=>$this->pdf()],$extra));
    }

    public function test_upload_edit_and_cancel_keep_the_policy_context(): void
    {
        $back=route('clients.policies.show',[$this->client,$this->policy,'tab'=>'documents']).'#policy-documents';
        $this->get(route('clients.documents.create',[$this->client,'policy'=>$this->policy->id,'category'=>'policy']))->assertOk()->assertViewHas('document',fn($document)=>$document->document_category==='policy' && $document->insurance_policy_id===$this->policy->id)->assertViewHas('returnUrl',$back)->assertSee('name="return_policy"',false);
        $this->upload(['return_policy'=>$this->policy->id])->assertSessionHasNoErrors()->assertRedirect($back);
        $document=Attachment::sole();
        $this->get(route('clients.documents.edit',[$this->client,$document,'return_policy'=>$this->policy->id]))->assertOk()->assertViewHas('returnUrl',$back);
        $this->patch(route('clients.documents.update',[$this->client,$document]),['document_category'=>'policy','insurance_policy_id'=>$this->policy->id,'return_policy'=>$this->policy->id,'description'=>'Contratto di collaudo'])->assertSessionHasNoErrors()->assertRedirect($back);
        $this->assertSame('Contratto di collaudo',$document->fresh()->description);
        $this->get(route('clients.documents.edit',[$this->client,$document]))->assertViewHas('returnUrl',route('clients.show',[$this->client,'tab'=>'documents']));
    }

    public function test_return_context_cannot_target_another_client_or_an_external_url(): void
    {
        $other=Client::factory()->create()->policies()->create($this->policy->only(['number','category','company','starts_on','expires_on','status']));
        $this->upload(['return_policy'=>$other->id])->assertSessionHasErrors('return_policy');
        $this->get(route('clients.documents.create',[$this->client,'return_policy'=>$other->id]))->assertNotFound();
        $this->get(route('clients.documents.create',[$this->client,'return_policy'=>'https://example.test']))->assertNotFound();
        $this->assertDatabaseCount('attachments',0);
    }

    public function test_preview_uses_a_private_reader_and_keeps_the_original_download_protected(): void
    {
        $this->upload()->assertSessionHasNoErrors();
        $document=Attachment::sole();
        $response=$this->get(route('attachments.preview',$document))->assertOk()->assertViewIs('attachments.preview')->assertHeader('X-Content-Type-Options','nosniff')
            ->assertSee('data-document-preview',false)->assertSee('data-source="'.e($document->downloadUrl()).'"',false)->assertSee('Torna alla polizza')->assertSee('Scarica PDF');
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $download=$this->get($document->downloadUrl())->assertOk()->assertHeader('Content-Type','application/octet-stream')->assertHeader('Content-Security-Policy',"default-src 'none'; sandbox");
        $this->assertStringContainsString('attachment',$download->headers->get('Content-Disposition'));
        Storage::disk('attachments')->put($document->path,'<html>Documento dimostrativo</html>');
        $this->get(route('attachments.preview',$document))->assertStatus(415);
        $this->get($document->downloadUrl())->assertOk()->assertHeader('Content-Type','application/octet-stream');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'inactive']))->get(route('attachments.preview',$document))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_image_preview_checks_the_stored_type_and_never_embeds_uploaded_html(): void
    {
        $this->upload()->assertSessionHasNoErrors();
        $document=Attachment::sole();
        Storage::disk('attachments')->put($document->path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII='));
        $this->get(route('attachments.preview',$document))->assertOk()->assertHeader('Content-Type','image/png')->assertHeader('Content-Security-Policy',"default-src 'none'; sandbox");
        Storage::disk('attachments')->put($document->path,'<html><script>alert(1)</script></html>');
        $this->get(route('attachments.preview',$document))->assertStatus(415)->assertDontSee('<script>alert(1)</script>',false);
        $this->post(route('logout'));
        $this->get(route('attachments.preview',$document))->assertRedirect(route('login'));
        $this->get($document->downloadUrl())->assertRedirect(route('login'));
    }

    public function test_contract_shortcuts_open_the_current_version_from_policy_and_lists(): void
    {
        $this->upload()->assertSessionHasNoErrors(); $old=Attachment::sole();
        $this->upload(['previous_version_id'=>$old->id])->assertSessionHasNoErrors();
        $current=Attachment::where('previous_version_id',$old->id)->sole();
        foreach ([route('clients.policies.show',[$this->client,$this->policy]),route('clients.show',$this->client),route('policies.index')] as $url) {
            $this->get($url)->assertOk()->assertSee('Apri contratto')->assertSee(e($current->openUrl()),false)->assertDontSee(e($old->openUrl()),false);
        }
        $this->get(route('attachments.preview',$old))->assertOk();
    }
}
