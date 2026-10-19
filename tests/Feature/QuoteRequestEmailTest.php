<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsuranceBranch, InsuranceQuoteCase, InsuranceQuoteEmail, OutboundMailSetting, SupplierCompany, User};
use App\Services\{ClientDocumentRegistry, QuoteRequestEmailTransport, QuoteRequestEmailWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Event, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteRequestEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
    private InsuranceQuoteCase $case;
    private SupplierCompany $nobis;
    private SupplierCompany $europe;
    private SupplierCompany $other;
    private array $smtp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,16)->setTime(12,0));
        Storage::fake('attachments');
        $this->admin = User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['name'=>'Alessandro Varesi']);
        $branch = InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $this->case = $this->client->quoteCases()->create(['insurance_branch_id'=>$branch->id,'subject'=>'Copertura veicolo','notes'=>'Nota interna riservata','status'=>'open','created_by'=>$this->admin->id]);
        $this->nobis = SupplierCompany::create(['name'=>'Nobis','email'=>'nobis@example.test','status'=>'active','quote_priority'=>true]);
        $this->europe = SupplierCompany::create(['name'=>'Europe Assistance','email'=>'europe@example.test','status'=>'active','quote_priority'=>true]);
        $this->other = SupplierCompany::create(['name'=>'Compagnia Valdoro','email'=>'valdoro@example.test','status'=>'active','quote_priority'=>false]);
        $this->smtp = ['enabled'=>true,'host'=>'smtp.example.test','port'=>587,'encryption'=>'starttls','username'=>'sender@example.test','password'=>Str::random(32),'from_address'=>'sender@example.test','from_name'=>'Soluzioni Assicurative SAS'];
        OutboundMailSetting::create(['credentials'=>$this->smtp,'updated_by'=>$this->admin->id]);
    }

    private function pdf(string $name='richiesta.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name,"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }

    private function input(?SupplierCompany $company=null, array $extra=[]): array
    {
        return array_replace(['supplier_company_id'=>($company ?? $this->nobis)->id,'subject'=>'Richiesta copertura auto','body'=>'Buongiorno, richiediamo un preventivo RC auto per Alessandro Varesi. Grazie.'],$extra);
    }

    private function review(?SupplierCompany $company=null, array $files=[]): InsuranceQuoteEmail
    {
        $response = $this->post(route('quotes.emails.review',$this->case),$this->input($company,['files'=>$files]))->assertSessionHasNoErrors()->assertRedirect();
        return InsuranceQuoteEmail::findOrFail(basename($response->headers->get('Location')));
    }

    private function send(InsuranceQuoteEmail $email): void
    {
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_preview_sends_nothing_then_exact_reviewed_email_with_multiple_pdfs_is_sent_once(): void
    {
        $email = $this->review(null,[$this->pdf('veicolo.pdf'),$this->pdf('richiesta firmata.pdf')]);
        $this->assertSame('review',$email->status);
        $this->assertSame(0,$this->case->quotes()->count());
        $this->assertSame(2,$email->documents()->count());
        $this->assertStringNotContainsString('Alessandro',DB::table('insurance_quote_emails')->value('payload'));
        $this->assertStringNotContainsString('nobis@example.test',DB::table('insurance_quote_emails')->value('payload'));
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->once()->withArgs(function($payload,$config,$files) {
            $this->assertSame('nobis@example.test',$payload['recipient']['address']);
            $this->assertSame($this->input()['body'],$payload['body']);
            $this->assertSame(['veicolo.pdf','richiesta firmata.pdf'],array_column($files,'name'));
            $this->assertStringStartsWith('%PDF',$files[0]['bytes']);
            $this->assertTrue($config['require_tls']);
            $this->assertTrue($config['verify_peer']);
            return true;
        })->andReturn('accepted@example.test');
        $this->get(route('quote-emails.show',$email))->assertOk()->assertSee('veicolo.pdf')->assertSee('Conferma e invia richiesta');
        $this->post(route('quote-emails.send',$email))->assertSessionHasErrors('reviewed');
        $this->send($email); $this->send($email);
        $this->assertSame('sent',$email->fresh()->status);
        $quote = $this->case->quotes()->sole();
        $this->assertSame('requested',$quote->status);
        $this->assertSame($this->nobis->id,$quote->supplier_company_id);
        $this->assertSame(2,$quote->documents()->whereNotNull('insurance_quote_email_id')->count());
        $this->assertDatabaseHas('audit_logs',['action'=>'quote_email_sent','user_id'=>$this->admin->id,'auditable_id'=>$this->client->id]);
    }

    public function test_other_companies_unlock_only_after_both_priority_emails_are_sent(): void
    {
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->times(3)->andReturn('accepted@example.test');
        $this->post(route('quotes.emails.review',$this->case),$this->input($this->other))->assertSessionHasErrors('email');
        $nobis = $this->review(); $europe = $this->review($this->europe);
        $this->post(route('quotes.emails.review',$this->case),$this->input($this->other))->assertSessionHasErrors('email');
        $this->send($europe);
        $this->post(route('quotes.emails.review',$this->case),$this->input($this->other))->assertSessionHasErrors('email');
        $this->send($nobis); $this->send($this->review($this->other));
        $this->assertSame(3,$this->case->quotes()->count());
        $this->assertTrue(app(QuoteRequestEmailWorkflow::class)->remainingPriorities($this->case)->isEmpty());
    }

    public function test_manual_contacts_alone_do_not_unlock_emails_but_documented_replies_do(): void
    {
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        foreach ([$this->nobis,$this->europe] as $company) {
            $this->post(route('quotes.request',$this->case),['supplier_company_id'=>$company->id,'requested_on'=>'2026-10-15','confirmed'=>1])->assertSessionHasNoErrors();
        }
        $this->post(route('quotes.emails.review',$this->case),$this->input($this->other))->assertSessionHasErrors('email');
        foreach ($this->case->quotes()->get() as $quote) {
            $this->post(route('quotes.change',[$quote,'receive']),['reference'=>'N-42','product'=>'Auto completa','starts_on'=>'2026-10-16','expires_on'=>'2027-10-16','valid_until'=>'2026-10-31','received_on'=>'2026-10-16','premium'=>'800.00','payment_frequency'=>'Semestrale','files'=>[$this->pdf()]])->assertSessionHasNoErrors();
        }
        $this->review($this->other);
        $this->post(route('quotes.emails.review',$this->case),$this->input())->assertSessionHasErrors('email');
    }

    public function test_preexisting_manual_requested_quote_is_reused_after_sending(): void
    {
        $this->post(route('quotes.request',$this->case),['supplier_company_id'=>$this->nobis->id,'requested_on'=>'2026-10-15','confirmed'=>1])->assertSessionHasNoErrors();
        $quote = $this->case->quotes()->sole();
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->once()->andReturn('accepted@example.test');
        $this->send($this->review());
        $this->assertSame($quote->id,$this->case->quotes()->sole()->id);
        $this->assertSame('2026-10-15',$quote->fresh()->requested_at->toDateString());
    }

    public function test_parallel_previews_cannot_repeat_a_request(): void
    {
        $a = $this->review(); $b = $this->review();
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->once()->andReturn('accepted@example.test');
        $this->send($a);
        $this->post(route('quote-emails.send',$b),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->post(route('quotes.emails.review',$this->case),$this->input())->assertSessionHasErrors('email');
        $this->assertSame(1,$this->case->quotes()->count());
        $this->assertSame(1,$this->case->emails()->whereNotNull('delivery_key')->count());
    }

    public function test_changed_recipient_client_case_or_sender_invalidates_review(): void
    {
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        $email = $this->review(); $this->nobis->update(['email'=>'changed@example.test']);
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasErrors('email');
        $email = $this->review(); $this->client->update(['name'=>'Giulia Montelari']);
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasErrors('email');
        $email = $this->review(); $this->case->update(['subject'=>'Garanzia diversa']);
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasErrors('email');
        $email = $this->review(); OutboundMailSetting::first()->update(['credentials'=>array_replace($this->smtp,['from_address'=>'changed@example.test'])]);
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->assertSame(0,$this->case->emails()->whereNotNull('delivery_key')->count());
    }

    public function test_expired_review_closed_case_and_inactive_company_are_blocked(): void
    {
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        $email = $this->review(); $this->travel(31)->minutes();
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasErrors('email');
        $email = $this->review(); $this->case->update(['status'=>'approved']);
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->case->update(['status'=>'open']); $email = $this->review(); $this->nobis->update(['status'=>'inactive']);
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->assertSame(0,$this->case->quotes()->count());
    }

    public function test_unconfigured_smtp_or_missing_recipient_does_not_record_fake_delivery(): void
    {
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        $email = $this->review(); OutboundMailSetting::first()->update(['credentials'=>array_replace($this->smtp,['enabled'=>false])]);
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->assertSame('review',$email->fresh()->status);
        $this->assertNull($email->fresh()->delivery_key);
        $this->nobis->update(['email'=>null]);
        $this->post(route('quotes.emails.review',$this->case),$this->input())->assertSessionHasErrors('email');
        $this->assertSame(0,$this->case->quotes()->count());
    }

    public function test_all_operators_can_prepare_requests_but_cannot_send_another_authors_review(): void
    {
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        $email = $this->review(null,[$this->pdf()]);
        $employee = User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($employee);
        $this->get(route('quotes.emails.create',$this->case))->assertOk();
        $this->get(route('quote-emails.show',$email))->assertOk();
        $this->get(route('attachments.download',$email->documents()->sole()))->assertOk();
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertForbidden();
        $own = $this->review();
        $this->assertSame($employee->id,$own->user_id);
        $other = User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($other)->get(route('quote-emails.show',$own))->assertOk();
        $this->post(route('quote-emails.send',$own),['reviewed'=>1])->assertForbidden();
    }

    public function test_invalid_or_changed_documents_and_header_injection_are_rejected(): void
    {
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        $this->post(route('quotes.emails.review',$this->case),$this->input(null,['subject'=>"Richiesta\r\nBcc: external@example.test"]))->assertSessionHasErrors('subject');
        $this->post(route('quotes.emails.review',$this->case),$this->input(null,['files'=>[UploadedFile::fake()->createWithContent('test.pdf','<script>alert(1)</script>')->mimeType('text/html')]]))->assertSessionHasErrors('files.0');
        $large = UploadedFile::fake()->createWithContent('grande.pdf',"%PDF-1.4\n".str_repeat('a',11*1024*1024));
        $this->post(route('quotes.emails.review',$this->case),$this->input(null,['files'=>[$large]]))->assertSessionHasErrors('files.0');
        $many = array_map(fn($i)=>UploadedFile::fake()->createWithContent('allegato-'.$i.'.pdf',"%PDF-1.4\n".str_repeat('a',8*1024*1024)),[1,2,3]);
        $this->post(route('quotes.emails.review',$this->case),$this->input(null,['files'=>$many]))->assertSessionHasErrors('email');
        $email = $this->review(null,[$this->pdf()]); $document = $email->documents()->sole();
        $bytes = Storage::disk($document->disk)->get($document->path);
        Storage::disk($document->disk)->put($document->path,str_replace('Catalog','Changed',$bytes));
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasErrors('email');
        Storage::disk($document->disk)->delete($document->path);
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->assertSame(0,$this->case->quotes()->count());
    }

    public function test_uncertain_result_keeps_unique_claim_and_admin_verified_delivery_records_quote(): void
    {
        $email = $this->review();
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->once()->andThrow(new \RuntimeException('Private SMTP diagnostic '.$this->smtp['password']));
        $this->send($email);
        $this->assertSame('uncertain',$email->fresh()->status);
        $this->assertSame(0,$this->case->quotes()->count());
        $this->post(route('quote-emails.send',$email),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->post(route('quotes.emails.review',$this->case),$this->input())->assertSessionHasErrors('email');
        $employee = User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($employee)->post(route('quote-emails.resolve',$email),['checked'=>1,'outcome'=>'sent','note'=>'Verificato con il servizio di posta'])->assertForbidden();
        $this->actingAs($this->admin);
        $this->post(route('quote-emails.resolve',$email),['outcome'=>'sent','note'=>'Verificato con il servizio di posta'])->assertSessionHasErrors('checked');
        $this->post(route('quote-emails.resolve',$email),['checked'=>1,'outcome'=>'sent','note'=>'Verificato con il servizio di posta'])->assertSessionHasNoErrors();
        $this->assertSame('sent',$email->fresh()->status);
        $this->assertSame(1,$this->case->quotes()->count());
        $this->assertSame([$this->europe->id],app(QuoteRequestEmailWorkflow::class)->remainingPriorities($this->case)->pluck('id')->all());
        $this->assertStringNotContainsString($this->smtp['password'],DB::table('audit_logs')->get()->toJson());
        $this->get(route('quote-emails.show',$email))->assertOk()->assertDontSee('Private SMTP');
    }

    public function test_verified_non_delivery_releases_claim_for_a_new_review_and_send(): void
    {
        $email = $this->review();
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->once()->andThrow(new \RuntimeException('Connection interrupted'));
        $this->send($email);
        $this->post(route('quote-emails.resolve',$email),['checked'=>1,'outcome'=>'not_sent','note'=>'Il servizio di posta conferma il mancato invio'])->assertSessionHasNoErrors();
        $this->assertSame('failed',$email->fresh()->status); $this->assertNull($email->fresh()->delivery_key);
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->once()->andReturn('accepted@example.test');
        $this->send($this->review());
        $this->assertSame(1,$this->case->quotes()->count());
    }

    public function test_stranded_sending_can_only_be_resolved_after_waiting(): void
    {
        $email = $this->review();
        $email->update(['status'=>'sending','sending_at'=>now(),'delivery_key'=>hash('sha256',$this->case->id.'|'.$this->nobis->id)]);
        $data = ['checked'=>1,'outcome'=>'sent','note'=>'Confermato dal servizio di posta'];
        $this->post(route('quote-emails.resolve',$email),$data)->assertSessionHasErrors('email');
        $this->travel(6)->minutes();
        $this->post(route('quote-emails.resolve',$email),$data)->assertSessionHasNoErrors();
        $this->assertSame('sent',$email->fresh()->status);
    }

    public function test_outbound_original_documents_cannot_be_edited_or_deleted(): void
    {
        $email = $this->review(null,[$this->pdf()]); $document = $email->documents()->sole();
        foreach ([fn()=> $document->delete(), fn()=> app(ClientDocumentRegistry::class)->update($document,['document_category'=>'other'])] as $operation) {
            try { $operation(); $this->fail('Original request attachment was editable.'); }
            catch (\Illuminate\Validation\ValidationException $expected) { $this->assertNotEmpty($expected->errors()); }
        }
        Storage::disk($document->disk)->assertExists($document->path);
        $this->assertDatabaseHas('attachments',['id'=>$document->id,'insurance_quote_email_id'=>$email->id]);
    }

    public function test_compose_review_history_filters_and_exports_render_and_escape_content(): void
    {
        $this->get(route('quotes.emails.create',$this->case))->assertOk()->assertSee('Controlla richiesta')->assertDontSee('Nota interna riservata');
        $email = $this->review();
        $this->case->update(['subject'=>'<script>alert(1)</script>']);
        $this->get(route('quotes.show',$this->case))->assertOk()->assertDontSee('<script>alert(1)</script>',false)->assertSee('Storico richieste email');
        $this->get(route('quotes.show',[$this->case,'email_company'=>$this->nobis->id,'email_state'=>'review','email_month'=>'2026-10','_table_all'=>1]))->assertOk()->assertViewHas('emails',fn($rows)=>$rows->total()===1);
        $this->get(route('quotes.show',[$this->case,'email_month'=>'2026-11']))->assertOk()->assertViewHas('emails',fn($rows)=>$rows->isEmpty());
        $response = $this->get(route('quote-emails.show',$email))->assertOk();
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private',$response->headers->get('Cache-Control'));
    }

    public function test_real_mail_builder_has_one_recipient_and_both_pdf_attachments_without_network(): void
    {
        $email = $this->review(null,[$this->pdf('uno.pdf'),$this->pdf('due.pdf')]);
        $sent = null;
        Event::listen(\Illuminate\Mail\Events\MessageSent::class,function($event) use (&$sent) { $sent=$event->sent->getOriginalMessage(); });
        $files = $email->documents()->get()->map(fn($d)=>['name'=>$d->original_name,'bytes'=>Storage::disk($d->disk)->get($d->path)])->all();
        $id = (new QuoteRequestEmailTransport)->send($email->payload,['transport'=>'array'],$files);
        $this->assertNotEmpty($id);
        $this->assertCount(1,$sent->getTo());
        $this->assertSame('nobis@example.test',$sent->getTo()[0]->getAddress());
        $this->assertCount(0,$sent->getCc()); $this->assertCount(0,$sent->getBcc());
        $this->assertCount(2,$sent->getAttachments());
        $this->assertSame('uno.pdf',$sent->getAttachments()[0]->getFilename());
        $this->assertSame($email->payload['body'],$sent->getTextBody());
    }
}
