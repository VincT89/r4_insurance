<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AgencyCommunication, Client, InsurancePolicy, InsuranceTitle, OutboundMailSetting, SupplierAgency, User};
use App\Services\{AgencyCommunicationDocuments, AgencyEmailTransport, ClientDocumentRegistry, InsuranceLedger};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Event, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class AgencyCommunicationDocumentsTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private Client $client;
    private SupplierAgency $agency;
    private InsurancePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,19)->setTime(12,0));
        Storage::fake('attachments');
        $this->admin = User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['name'=>'Elena Varesi']);
        $this->agency = SupplierAgency::create(['name'=>'Agenzia Valserena','email'=>'valserena@example.test','status'=>'active']);
        $this->policy = $this->client->policies()->create(['number'=>'SA-2026-0028','category'=>'RC auto','company'=>'Compagnia Alba',
            'supplier_agency_id'=>$this->agency->id,'starts_on'=>'2026-10-01','expires_on'=>'2027-10-01','status'=>'active','payment_frequency'=>'Semestrale']);
        OutboundMailSetting::create(['credentials'=>['enabled'=>true,'host'=>'smtp.example.test','port'=>587,'encryption'=>'starttls',
            'username'=>'sender@example.test','password'=>Str::random(32),'from_address'=>'sender@example.test','from_name'=>'Soluzioni Assicurative SAS'],'updated_by'=>$this->admin->id]);
    }

    private function pdf(string $name = 'documento.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }

    private function document(string $name, array $links = [], ?Client $client = null)
    {
        return app(ClientDocumentRegistry::class)->upload($client ?? $this->client,$this->pdf($name),$links + ['document_category'=>'other']);
    }

    private function review(): AgencyCommunication
    {
        $this->post(route('agency-communications.review',$this->agency),['policies'=>[$this->policy->id],'period'=>'day','date'=>'2026-10-19'])->assertSessionHasNoErrors();
        return AgencyCommunication::latest('id')->firstOrFail();
    }

    private function update(AgencyCommunication $message, array $data = [])
    {
        return $this->post(route('agency-communications.documents',$message),$data + ['attachment_revision'=>$message->fresh()->attachment_revision]);
    }

    public function test_policy_review_and_multiple_attachments_work_without_recipient_but_delivery_remains_blocked(): void
    {
        $this->agency->update(['email'=>null]);
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
        $list=$this->get(route('agency-communications.index'))->assertOk();
        $this->assertMatchesRegularExpression('/aria-label="Includi polizza SA-2026-0028"\s*>/', $list->getContent());
        $source=$this->document('contratto.pdf',['insurance_policy_id'=>$this->policy->id]);
        $message=$this->review();
        $this->assertNull($message->payload['recipient']['address']);
        $this->update($message,['document_ids'=>[$source->id],'files'=>[$this->pdf('appendice.pdf')]])->assertSessionHasNoErrors();
        $page=$this->get(route('agency-communications.show',$message))->assertOk()->assertSee('Email da indicare prima dell’invio')->assertSee('Allegati inclusi: 2');
        $this->assertMatchesRegularExpression('/<button[^>]*disabled[^>]*>Conferma e invia email<\/button>/', $page->getContent());
        $this->post(route('agency-communications.send',$message),['reviewed'=>1,'attachment_revision'=>1])->assertSessionHasErrors('agency');
        $this->assertSame('review',$message->fresh()->status);
        $this->assertNull(DB::table('agency_communication_policies')->where('agency_communication_id',$message->id)->value('delivery_key'));
        $this->agency->update(['email'=>'destinatario@example.test']);
        $this->post(route('agency-communications.send',$message),['reviewed'=>1,'attachment_revision'=>1])->assertSessionHasErrors('email');
        $this->assertSame('review',$message->fresh()->status);
    }

    public function test_collection_review_without_recipient_cannot_claim_delivery_even_after_contact_is_completed(): void
    {
        $this->agency->update(['email'=>null]);
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
        $title=InsuranceTitle::create(['insurance_policy_id'=>$this->policy->id,'kind'=>'emission','due_on'=>today(),'coverage_from'=>'2026-10-01','coverage_to'=>'2027-04-01','amount_cents'=>10000,'created_by'=>$this->admin->id,'payment_frequency'=>'Semestrale','payment_method'=>'Bonifico a Intermediario']);
        $payment=app(InsuranceLedger::class)->collect($title,['received_on'=>today()->toDateString(),'amount_cents'=>5000,'payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'transfer','consultant_id'=>$this->admin->id,'idempotency_key'=>(string)Str::uuid()]);
        $list=$this->get(route('agency-communications.collections'))->assertOk();
        $this->assertMatchesRegularExpression('/aria-label="Includi incasso '.$payment->id.'"\s*>/', $list->getContent());
        $this->post(route('agency-communications.collections-review',$this->agency),['scope'=>'pending','collections'=>[$payment->id]])->assertSessionHasNoErrors();
        $message=AgencyCommunication::latest('id')->firstOrFail();
        $this->get(route('agency-communications.show',$message))->assertOk()->assertSee('Email da indicare prima dell’invio');
        $this->post(route('agency-communications.send',$message),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->assertSame('review',$message->fresh()->status);
        $this->assertNull(DB::table('agency_communication_collections')->where('agency_communication_id',$message->id)->value('delivery_key'));
        $this->agency->update(['email'=>'destinatario@example.test']);
        $this->post(route('agency-communications.send',$message),['reviewed'=>1])->assertSessionHasErrors('email');
    }

    public function test_automatic_list_is_scoped_to_selected_policies_and_their_client_and_has_no_automatic_selection(): void
    {
        $identity = $this->document('identita.pdf',['document_category'=>'identity','expires_on'=>'2026-10-01']);
        $policy = $this->document('contratto.pdf',['insurance_policy_id'=>$this->policy->id]);
        $foreign = $this->document('estraneo.pdf',[],Client::factory()->create());
        $another = $this->policy->replicate(); $another->number='SA-2026-0029'; $another->save();
        $unselected = $this->document('altra-polizza.pdf',['insurance_policy_id'=>$another->id]);
        $older = $this->document('versione-vecchia.pdf');
        $newer = $this->document('versione-attuale.pdf',['previous_version_id'=>$older->id,'version_number'=>2]);
        $message = $this->review();
        $response = $this->get(route('agency-communications.show',$message))->assertOk()->assertSee('identita.pdf')->assertSee('contratto.pdf')->assertSee('Documento non valido')
            ->assertDontSee('estraneo.pdf')->assertDontSee('altra-polizza.pdf')->assertDontSee('versione-vecchia.pdf');
        $response->assertViewHas('availableDocuments',fn($docs)=>$docs->modelKeys()===[$identity->id,$policy->id,$newer->id]);
        $this->assertSame([], $message->payload['attachments'] ?? []);
        foreach ([$foreign,$unselected,$older] as $invalid) $this->update($message,['document_ids'=>[$invalid->id]])->assertSessionHasErrors('documents');
        $this->assertSame(0,$message->documents()->count());
    }

    public function test_multiple_sources_and_uploads_are_private_immutable_copies_with_a_reviewed_manifest(): void
    {
        $source = $this->document('libretto.pdf',['insurance_policy_id'=>$this->policy->id]);
        $message = $this->review();
        $this->update($message,['document_ids'=>[$source->id], 'files'=>[$this->pdf('firma.pdf'),UploadedFile::fake()->image('fronte.png')]])->assertSessionHasNoErrors();
        $message->refresh();
        $this->assertSame(1,(int)$message->attachment_revision);
        $this->assertCount(3,$message->payload['attachments']);
        $this->assertDatabaseHas('audit_logs',['action'=>'agency_email_attachments','user_id'=>$this->admin->id]);
        $this->assertStringNotContainsString('libretto.pdf',DB::table('agency_communication_attachments')->value('details'));
        $originalBytes = Storage::disk('attachments')->get($source->path);
        Storage::disk('attachments')->put($source->path,'replaced original');
        $files = app(AgencyCommunicationDocuments::class)->checkedFiles($message);
        $this->assertSame($originalBytes,$files[0]['bytes']);
        $copy = $message->documents()->first();
        $response = $this->get(route('agency-communications.documents.download',[$message,$copy]))->assertOk();
        $this->assertStringContainsString('attachment;',$response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->update($message,['keep_ids'=>[$copy->id]])->assertSessionHasNoErrors();
        $this->assertSame(1,$message->documents()->where('included',true)->count());
        $this->assertSame(3,$message->documents()->count());
        $this->assertSame(3,count(Storage::disk('attachments')->allFiles('agency-communications/'.$message->id)));
        $this->get(route('agency-communications.show',$message))->assertOk()->assertSee('libretto.pdf')->assertSee('Allegati inclusi: 1');
    }

    public function test_old_browser_revision_and_corrupted_copy_cannot_send_or_claim_delivery(): void
    {
        $message=$this->review();
        $this->update($message,['files'=>[$this->pdf()]])->assertSessionHasNoErrors();
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
        $this->post(route('agency-communications.send',$message),['reviewed'=>1,'attachment_revision'=>0])->assertSessionHasErrors('documents');
        $this->update($message,['attachment_revision'=>0])->assertSessionHasErrors('documents');
        $copy=$message->documents()->first();
        $bytes=Storage::disk('attachments')->get($copy->path);
        Storage::disk('attachments')->put($copy->path,str_replace('Catalog','Changed',$bytes));
        $this->post(route('agency-communications.send',$message),['reviewed'=>1,'attachment_revision'=>1])->assertSessionHasErrors('documents');
        $this->assertSame('review',$message->fresh()->status);
        $this->assertSame(0,DB::table('agency_communication_policies')->whereNotNull('delivery_key')->count());
        Storage::disk('attachments')->delete($copy->path);
        $this->post(route('agency-communications.send',$message),['reviewed'=>1,'attachment_revision'=>1])->assertSessionHasErrors('documents');
    }

    public function test_authorization_and_nested_download_prevent_foreign_changes_and_operator_access(): void
    {
        $message=$this->review();
        $this->update($message,['files'=>[$this->pdf()]])->assertSessionHasNoErrors();
        $copy=$message->documents()->first();
        $other=$this->review();
        $this->get(route('agency-communications.documents.download',[$other,$copy]))->assertNotFound();
        $this->update($other,['keep_ids'=>[$copy->id]])->assertSessionHasErrors('documents');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']));
        $this->update($message)->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']));
        $this->update($message)->assertForbidden();
        $this->get(route('agency-communications.documents.download',[$message,$copy]))->assertForbidden();
        $this->actingAs($this->admin);
        $this->travel(31)->minutes();
        $this->update($message)->assertSessionHasErrors('documents');
    }

    public function test_upload_validation_limits_mime_size_count_and_repeated_source_ids(): void
    {
        $message=$this->review();
        $this->update($message,['files'=>[UploadedFile::fake()->createWithContent('fake.pdf','<script>bad</script>')]])->assertSessionHasErrors('documents');
        $this->update($message,['files'=>[UploadedFile::fake()->create('large.pdf',10241,'application/pdf')]])->assertSessionHasErrors('files.0');
        $this->update($message,['files'=>array_map(fn($i)=>$this->pdf('file-'.$i.'.pdf'),range(1,11))])->assertSessionHasErrors('files');
        $source=$this->document('source.pdf');
        $this->update($message,['document_ids'=>[$source->id,$source->id]])->assertSessionHasErrors('document_ids.0');
        $big=UploadedFile::fake()->createWithContent('big.pdf',"%PDF-1.4\n".str_repeat('x',7*1024*1024));
        $this->update($message,['files'=>[$big,$big,$big]])->assertSessionHasErrors('documents');
        $this->assertSame(0,$message->documents()->count());
        $this->assertSame([],Storage::disk('attachments')->allFiles('agency-communications/'.$message->id));
    }

    public function test_only_selected_attachments_are_sent_once_and_changes_are_blocked_after_confirmation(): void
    {
        $source=$this->document('identita.pdf',['document_category'=>'identity']);
        $this->document('riservato-non-selezionato.pdf');
        $message=$this->review();
        $this->update($message,['document_ids'=>[$source->id],'files'=>[$this->pdf('libretto.pdf')]])->assertSessionHasNoErrors();
        $this->mock(AgencyEmailTransport::class)->shouldReceive('send')->once()->withArgs(function($payload) {
            $this->assertCount(2,$payload['_attachments']);
            $this->assertSame(['identita.pdf','libretto.pdf'],array_column($payload['_attachments'],'name'));
            $this->assertStringContainsString('Allegati selezionati:',$payload['body']);
            $this->assertStringNotContainsString('riservato-non-selezionato',$payload['body']);
            return true;
        })->andReturn('accepted@example.test');
        $this->post(route('agency-communications.send',$message),['reviewed'=>1,'attachment_revision'=>1])->assertSessionHasNoErrors();
        $this->post(route('agency-communications.send',$message),['reviewed'=>1,'attachment_revision'=>1])->assertSessionHasNoErrors();
        $this->assertSame('sent',$message->fresh()->status);
        $this->update($message)->assertSessionHasErrors('documents');
        $this->get(route('agency-communications.show',$message))->assertOk()->assertSee('Scarica copia')->assertDontSee('Aggiorna allegati');
    }

    public function test_collection_communication_can_include_title_documents_and_client_documents(): void
    {
        $title=InsuranceTitle::create(['insurance_policy_id'=>$this->policy->id,'kind'=>'emission','due_on'=>today(),'coverage_from'=>'2026-10-01','coverage_to'=>'2027-04-01','amount_cents'=>10000,'created_by'=>$this->admin->id,'payment_frequency'=>'Semestrale','payment_method'=>'Bonifico a Intermediario']);
        $payment=app(InsuranceLedger::class)->collect($title,['received_on'=>today()->toDateString(),'amount_cents'=>5000,'payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'transfer','consultant_id'=>$this->admin->id,'idempotency_key'=>(string)Str::uuid()]);
        $quietance=$this->document('quietanza.pdf',['insurance_policy_id'=>$this->policy->id,'insurance_title_id'=>$title->id,'document_category'=>'quietance']);
        $receipt=$this->document('bonifico.pdf',['insurance_policy_id'=>$this->policy->id,'insurance_collection_id'=>$payment->id,'document_category'=>'collection']);
        $this->post(route('agency-communications.collections-review',$this->agency),['scope'=>'pending','collections'=>[$payment->id]])->assertSessionHasNoErrors();
        $message=AgencyCommunication::latest('id')->firstOrFail();
        $this->update($message,['document_ids'=>[$quietance->id,$receipt->id]])->assertSessionHasNoErrors();
        $this->get(route('agency-communications.show',$message))->assertOk()->assertSee('gli incassi, gli importi e i metodi di pagamento');
        $this->mock(AgencyEmailTransport::class)->shouldReceive('send')->once()->withArgs(function($payload) {
            $this->assertCount(2,$payload['_attachments']);
            $this->assertStringNotContainsString('non sono inclusi',$payload['body']);
            return true;
        })->andReturn('accepted@example.test');
        $this->post(route('agency-communications.send',$message),['reviewed'=>1,'attachment_revision'=>1])->assertSessionHasNoErrors();
        $this->assertSame('sent',$message->fresh()->status);
    }

    public function test_real_mail_builder_attaches_the_reviewed_files_to_a_single_recipient_without_network(): void
    {
        $message=$this->review();
        $this->update($message,['files'=>[$this->pdf('contratto.pdf'),UploadedFile::fake()->image('libretto.png')]])->assertSessionHasNoErrors();
        $sent=null;
        Event::listen(\Illuminate\Mail\Events\MessageSent::class,function($event) use (&$sent) { $sent=$event->sent->getOriginalMessage(); });
        $payload=$message->fresh()->deliveryPayload();
        $payload['_attachments']=app(AgencyCommunicationDocuments::class)->checkedFiles($message->fresh());
        $this->assertNotEmpty((new AgencyEmailTransport)->send($payload,['transport'=>'array']));
        $this->assertCount(1,$sent->getTo());
        $this->assertSame('valserena@example.test',$sent->getTo()[0]->getAddress());
        $this->assertCount(0,$sent->getCc()); $this->assertCount(0,$sent->getBcc());
        $this->assertCount(2,$sent->getAttachments());
        $this->assertSame('contratto.pdf',$sent->getAttachments()[0]->getFilename());
        $this->assertSame('png',$sent->getAttachments()[1]->getMediaSubtype());
    }

    public function test_demo_reset_cannot_remove_reviewed_attachment_history(): void
    {
        $message=$this->review();
        $this->update($message,['files'=>[$this->pdf()]])->assertSessionHasNoErrors();
        $copy=$message->documents()->first();
        try { app(\App\Services\InsuranceDemoReset::class)->apply($this->admin); $this->fail('Lo storico deve essere conservato.'); }
        catch (\RuntimeException $exception) { $this->assertStringContainsString('comunicazioni con allegati da conservare',$exception->getMessage()); }
        $this->assertDatabaseHas('agency_communication_attachments',['id'=>$copy->id]);
        Storage::disk('attachments')->assertExists($copy->path);
    }
}
