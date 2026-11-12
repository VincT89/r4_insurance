<?php

namespace Tests\Feature;

use App\Domain\Core\Actions\MergeClientsAction;
use App\Enums\UserRole;
use App\Models\{Client, InboundEmail, InboundEmailFile, InsuranceBranch, User};
use App\Services\InsuranceDemoReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Event, Schema, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class InboundEmailTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->token = Str::random(64);
        config(['inbound-email.enabled'=>true, 'inbound-email.token_sha256'=>hash('sha256',$this->token), 'inbound-email.source'=>'test-mailbox']);
        $this->operator = User::factory()->create(['role'=>UserRole::Operator, 'status'=>'active']);
    }

    private function message(array $changes=[]): array
    {
        return array_replace(['external_id'=>'provider-message-1', 'message_id'=>'<demo-1@example.test>', 'from'=>['name'=>'Agenzia dimostrativa','address'=>'agenzia@example.test'],
            'to'=>['ricezione@example.test'], 'subject'=>'Offerta dimostrativa', 'text'=>" Testo originale\ncon seconda riga. ", 'sent_at'=>'2026-11-11T12:00:00+01:00'], $changes);
    }

    private function pdf(string $name='offerta.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name,"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }

    private function deliver(array $changes=[], array $files=[])
    {
        return $this->withToken($this->token)->withHeaders(['Accept'=>'application/json'])->post(route('api.inbound-emails.store'), ['message'=>json_encode($this->message($changes)), 'attachments'=>$files]);
    }

    private function email(array $changes=[], array $files=[]): InboundEmail
    {
        $response = $this->deliver($changes,$files)->assertCreated();
        return InboundEmail::where('public_id',$response->json('id'))->sole();
    }

    private function association(Client $client, array $changes=[]): array
    {
        return array_replace(['target'=>'client','client_id'=>$client->id,'document_kind'=>'quote','version'=>0,'confirmed'=>1],$changes);
    }

    public function test_disabled_endpoint_and_missing_credentials_never_write_messages(): void
    {
        config(['inbound-email.enabled'=>false]);
        $this->deliver()->assertStatus(503);
        config(['inbound-email.enabled'=>true,'inbound-email.token_sha256'=>'']);
        $this->deliver()->assertStatus(503);
        config(['inbound-email.token_sha256'=>hash('sha256',$this->token)]);
        $this->flushHeaders()->actingAs($this->operator)->postJson(route('api.inbound-emails.store'),['message'=>$this->message()])->assertUnauthorized();
        $this->withToken(Str::random(64))->postJson(route('api.inbound-emails.store'),['message'=>$this->message()])->assertUnauthorized();
        $this->assertDatabaseCount('inbound_emails',0);
        $this->assertSame([],Storage::disk('local')->allFiles());
    }

    public function test_external_endpoint_requires_https_outside_tests(): void
    {
        $this->app->detectEnvironment(fn ()=>'production');
        $this->deliver()->assertStatus(400)->assertJsonPath('message','Il collegamento richiede HTTPS.');
        $this->assertDatabaseCount('inbound_emails',0);
        $this->withToken($this->token)->postJson('https://localhost'.route('api.inbound-emails.store',[],false),['message'=>$this->message()])->assertCreated();
        $this->assertDatabaseCount('inbound_emails',1);
    }

    public function test_receives_encrypted_message_and_private_files_without_changing_business_records(): void
    {
        $email = $this->email([],[$this->pdf()]);
        $this->assertSame('unassigned',$email->status);
        $this->assertNull($email->client_id);
        $this->assertSame($this->message()['text'],$email->payload['text']);
        $file = $email->files()->sole();
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame(hash('sha256',Storage::disk('local')->get($file->path)),$file->sha256);
        $this->assertStringNotContainsString('agenzia@example.test',DB::table('inbound_emails')->value('payload'));
        $this->assertStringNotContainsString('offerta.pdf',DB::table('inbound_email_files')->value('original_name'));
        foreach (['clients','insurance_quotes','insurance_policies','insurance_collections'] as $table) $this->assertDatabaseCount($table,0);
        $this->assertDatabaseHas('audit_logs',['action'=>'inbound_email_received','auditable_id'=>$email->id]);
    }

    public function test_json_and_multipart_preserve_the_same_body_and_deduplicate(): void
    {
        $email = $this->email();
        $this->withToken($this->token)->postJson(route('api.inbound-emails.store'),['message'=>$this->message()])->assertOk()->assertJsonPath('duplicate',true)->assertJsonPath('id',$email->public_id);
        $this->assertDatabaseCount('inbound_emails',1);
    }

    public function test_retry_including_reordered_files_returns_same_id_without_duplicates(): void
    {
        $first = $this->deliver([],[$this->pdf('a.pdf'),$this->pdf('b.pdf')])->assertCreated();
        $this->deliver([],[$this->pdf('b.pdf'),$this->pdf('a.pdf')])->assertOk()->assertJsonPath('duplicate',true)->assertJsonPath('id',$first->json('id'))->assertJsonPath('attachments_count',2);
        $this->assertDatabaseCount('inbound_emails',1);
        $this->assertDatabaseCount('inbound_email_files',2);
        $this->assertCount(2,Storage::disk('local')->allFiles());
        $this->assertSame(1,DB::table('audit_logs')->where('action','inbound_email_received')->count());
    }

    public function test_reused_identifier_with_changed_body_or_attachment_is_rejected_without_overwrite(): void
    {
        $email = $this->email([],[$this->pdf()]);
        $this->deliver(['text'=>'Testo diverso'],[$this->pdf()])->assertStatus(409);
        $this->deliver([],[$this->pdf('altro.pdf')])->assertStatus(409);
        $this->assertSame($this->message()['text'],$email->fresh()->payload['text']);
        $this->assertDatabaseCount('inbound_email_files',1);
        $this->assertCount(1,Storage::disk('local')->allFiles());
    }

    public function test_source_is_server_controlled_and_isolates_identifiers(): void
    {
        $this->deliver(['source'=>'spoofed'])->assertUnprocessable();
        $this->email();
        config(['inbound-email.source'=>'second-mailbox']);
        $this->email();
        $this->assertDatabaseCount('inbound_emails',2);
    }

    public function test_rejects_urls_unknown_fields_bad_addresses_and_malformed_json(): void
    {
        foreach ([['attachments'=>['https://example.test/file.pdf']],['client_id'=>1],['from'=>['address'=>"x@example.test\r\nBcc: y@example.test"]],['external_id'=>"bad\nidentifier"]] as $changes) $this->deliver($changes)->assertUnprocessable();
        $this->withToken($this->token)->postJson(route('api.inbound-emails.store'),['message'=>'{broken'])->assertUnprocessable();
        $this->assertDatabaseCount('inbound_emails',0);
    }

    public function test_rejects_unsafe_extensions_forged_mime_and_attachment_limits(): void
    {
        $this->deliver([],[UploadedFile::fake()->createWithContent('file.php','<?php echo 1;')])->assertUnprocessable();
        $forged = UploadedFile::fake()->createWithContent('file.pdf','<html><script>alert(1)</script></html>');
        $this->deliver([],[new UploadedFile($forged->getRealPath(),'file.pdf',null,null,true)])->assertUnprocessable();
        $this->deliver([],array_map(fn($i)=>$this->pdf('file'.$i.'.pdf'),range(1,11)))->assertUnprocessable();
        $large = $this->pdf(); $large->sizeToReport = 10241 * 1024;
        $this->deliver([],[$large])->assertUnprocessable();
        $files = array_map(function($i){$file=$this->pdf('file'.$i.'.pdf');$file->sizeToReport=9*1024*1024;return $file;},range(1,3));
        $this->deliver([],$files)->assertUnprocessable();
        $this->assertDatabaseCount('inbound_emails',0);
        $this->assertSame([],Storage::disk('local')->allFiles());
    }

    public function test_failure_mid_batch_rolls_back_rows_and_removes_written_files(): void
    {
        $count = 0;
        Event::listen('eloquent.creating: '.InboundEmailFile::class,function() use (&$count){if(++$count===2)throw new \RuntimeException('Errore simulato di archiviazione.');});
        $this->deliver([],[$this->pdf('a.pdf'),$this->pdf('b.pdf')])->assertStatus(503)->assertDontSee('Errore simulato');
        $this->assertDatabaseCount('inbound_emails',0);
        $this->assertDatabaseCount('inbound_email_files',0);
        $this->assertSame([],Storage::disk('local')->allFiles());
    }

    public function test_operator_can_read_and_download_only_a_file_of_the_requested_message(): void
    {
        $email = $this->email([],[$this->pdf()]);
        $other = $this->email(['external_id'=>'message-2'],[$this->pdf('other.pdf')]);
        $this->flushHeaders();
        $this->get(route('inbound-emails.index'))->assertRedirect(route('login'));
        $this->get(route('inbound-emails.files.download',[$email,$email->files()->sole()->id]))->assertRedirect(route('login'));
        $this->actingAs($this->operator);
        $this->get(route('inbound-emails.index'))->assertOk()->assertSee('Offerta dimostrativa');
        $this->get(route('inbound-emails.show',$email))->assertOk()->assertSee('Allegati ricevuti');
        $this->get(route('inbound-emails.files.download',[$email,$email->files()->sole()->id]))->assertOk()->assertDownload('offerta.pdf')->assertHeader('X-Content-Type-Options','nosniff');
        $this->get(route('inbound-emails.files.download',[$email,$other->files()->sole()->id]))->assertNotFound();
        $this->operator->update(['status'=>'inactive']);
        $this->getJson(route('inbound-emails.show',$email))->assertForbidden();
    }

    public function test_html_subject_and_body_are_escaped_and_external_content_never_rendered(): void
    {
        $email = $this->email(['subject'=>'<script>alert(1)</script>', 'text'=>null, 'html'=>'<p>Testo leggibile</p><script>alert(2)</script><img src="https://example.test/tracker"><a href="javascript:alert(3)">Link ricevuto</a>']);
        $this->flushHeaders()->actingAs($this->operator)->get(route('inbound-emails.show',$email))->assertOk()->assertSee('Testo leggibile')->assertSee('Link ricevuto')->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert',false)->assertDontSee('https://example.test/tracker',false)->assertDontSee('javascript:alert',false);
    }

    public function test_can_associate_correct_and_unassign_without_modifying_originals(): void
    {
        $email = $this->email([],[$this->pdf()]);
        $client = Client::factory()->create(); $other = Client::factory()->create();
        $this->flushHeaders()->actingAs($this->operator);
        $route = route('inbound-emails.associate',$email);
        $this->patch($route,$this->association($client))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($client->id,$email->fresh()->client_id);
        $this->patch($route,$this->association($other,['version'=>1,'association_note'=>'Correzione dimostrativa']))->assertSessionHasNoErrors();
        $this->assertSame($other->id,$email->fresh()->client_id);
        $this->patch($route,$this->association($other,['target'=>'unassigned','version'=>2]))->assertSessionHasNoErrors();
        $this->assertNull($email->fresh()->client_id);
        $this->assertSame('unassigned',$email->fresh()->status);
        $this->assertSame($this->message()['text'],$email->fresh()->payload['text']);
        $this->assertCount(1,Storage::disk('local')->allFiles());
        $this->assertSame(3,DB::table('audit_logs')->where('action','inbound_email_associated')->count());
        $this->assertSame(3,$email->fresh()->version);
    }

    public function test_rejects_stale_form_and_cross_client_policy_or_quote_case(): void
    {
        $email = $this->email();
        $client = Client::factory()->create(); $other = Client::factory()->create();
        $policy = $other->policies()->create(['number'=>'TEST','company'=>'Demo','category'=>'RC auto','starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','status'=>'active']);
        $branch = InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $case = $other->quoteCases()->create(['subject'=>'Pratica test','insurance_branch_id'=>$branch->id,'status'=>'open','created_by'=>$this->operator->id]);
        $this->flushHeaders()->actingAs($this->operator);
        $route = route('inbound-emails.associate',$email);
        $this->patch($route,$this->association($client,['target'=>'policy','insurance_policy_id'=>$policy->id]))->assertSessionHasErrors('insurance_policy_id');
        $this->patch($route,$this->association($client,['target'=>'quote','insurance_quote_case_id'=>$case->id]))->assertSessionHasErrors('insurance_quote_case_id');
        $this->patch($route,$this->association($client,['confirmed'=>0]))->assertSessionHasErrors('confirmed');
        $this->patch($route,$this->association($client))->assertSessionHasNoErrors();
        $this->patch($route,$this->association($other))->assertSessionHasErrors('association');
        $this->assertSame($client->id,$email->fresh()->client_id);
    }

    public function test_linked_message_is_visible_in_client_and_quote_without_changing_quote_status(): void
    {
        $email = $this->email([],[$this->pdf()]); $client = Client::factory()->create();
        $branch = InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $case = $client->quoteCases()->create(['subject'=>'Pratica test','insurance_branch_id'=>$branch->id,'status'=>'open','created_by'=>$this->operator->id]);
        $this->flushHeaders()->actingAs($this->operator);
        $this->patch(route('inbound-emails.associate',$email),$this->association($client,['target'=>'quote','insurance_quote_case_id'=>$case->id]))->assertSessionHasNoErrors();
        $this->get(route('quotes.show',$case))->assertOk()->assertSee('Email e allegati ricevuti')->assertSee(route('inbound-emails.show',$email),false);
        $this->get(route('clients.show',[$client,'tab'=>'documents']))->assertOk()->assertSee('Offerta dimostrativa');
        $this->assertSame('open',$case->fresh()->status);
        $this->assertSame(0,$case->quotes()->count());
    }

    public function test_filters_and_pagination_keep_messages_available(): void
    {
        for($i=0;$i<27;$i++) $this->email(['external_id'=>'message-'.$i]);
        $this->flushHeaders()->actingAs($this->operator);
        $this->get(route('inbound-emails.index'))->assertViewHas('emails',fn($rows)=>$rows->total()===27&&$rows->count()===25);
        $this->get(route('inbound-emails.index',['page'=>2]))->assertViewHas('emails',fn($rows)=>$rows->count()===2);
        $this->get(route('inbound-emails.index',['sender'=>'missing@example.test']))->assertViewHas('emails',fn($rows)=>$rows->total()===0);
        $this->get(route('inbound-emails.index',['sender'=>'AGENZIA@example.test']))->assertViewHas('emails',fn($rows)=>$rows->total()===27);
        $this->get(route('inbound-emails.index',['to'=>today()->format('Y-m-d')]))->assertSessionHasNoErrors()->assertOk();
    }

    public function test_existing_archive_without_migration_has_a_safe_inactive_page(): void
    {
        Schema::drop('inbound_email_files'); Schema::drop('inbound_emails');
        $this->deliver()->assertStatus(503);
        $this->flushHeaders()->actingAs($this->operator)->get(route('inbound-emails.index'))->assertOk()->assertSee('deve ancora essere attivata');
        $this->get(route('inbound-emails.show',(string)Str::uuid()))->assertStatus(503);
    }

    public function test_demo_reset_refuses_to_delete_received_email_and_files(): void
    {
        $email = $this->email([],[$this->pdf()]);
        $admin = User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        try { app(InsuranceDemoReset::class)->apply($admin); $this->fail('Il reset deve conservare la posta.'); }
        catch (\RuntimeException $exception) { $this->assertStringContainsString('email ricevute e allegati da conservare',$exception->getMessage()); }
        $this->assertDatabaseHas('inbound_emails',['id'=>$email->id]);
        Storage::disk('local')->assertExists($email->files()->sole()->path);
    }

    public function test_client_merge_moves_message_and_invalidates_stale_association_form(): void
    {
        $email = $this->email();
        $admin = User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->flushHeaders()->actingAs($admin);
        $target = Client::factory()->create(['client_type'=>'person','commercial_user_id'=>null]);
        $source = Client::factory()->create(['client_type'=>'person','commercial_user_id'=>null]);
        $this->patch(route('inbound-emails.associate',$email),$this->association($source))->assertSessionHasNoErrors();
        $action = app(MergeClientsAction::class); $preview = $action->preview($target->fresh(),$source->fresh());
        $action->execute($target,$source,$preview['token'],array_fill_keys(array_keys($preview['conflicts']),'target'));
        $this->assertSame($target->id,$email->fresh()->client_id);
        $this->assertSame(2,$email->fresh()->version);
        $this->patch(route('inbound-emails.associate',$email),$this->association($target,['version'=>1]))->assertSessionHasErrors('association');
    }

    public function test_ingestion_token_cannot_read_inbox_or_private_files(): void
    {
        $email = $this->email([],[$this->pdf()]);
        $this->withToken($this->token)->getJson(route('inbound-emails.index'))->assertUnauthorized();
        $this->getJson(route('inbound-emails.files.download',[$email,$email->files()->sole()->id]))->assertUnauthorized();
    }

    public function test_retry_after_association_keeps_operator_choice(): void
    {
        $email = $this->email(); $client = Client::factory()->create();
        $this->flushHeaders()->actingAs($this->operator)->patch(route('inbound-emails.associate',$email),$this->association($client))->assertSessionHasNoErrors();
        $this->deliver()->assertOk()->assertJsonPath('duplicate',true)->assertJsonPath('status','assigned');
        $this->assertSame($client->id,$email->fresh()->client_id);
        $this->assertSame(1,$email->fresh()->version);
    }

    public function test_policy_link_exposes_source_without_creating_validated_contracts_or_payments(): void
    {
        $email = $this->email([],[$this->pdf()]); $client = Client::factory()->create();
        $policy = $client->policies()->create(['number'=>'TEST-POLICY','company'=>'Demo','category'=>'RC auto','starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','status'=>'active']);
        $this->flushHeaders()->actingAs($this->operator)->patch(route('inbound-emails.associate',$email),$this->association($client,['target'=>'policy','insurance_policy_id'=>$policy->id,'document_kind'=>'contract']))->assertSessionHasNoErrors();
        $this->get(route('clients.policies.show',[$client,$policy,'tab'=>'documents']))->assertOk()->assertSee('Email e allegati ricevuti')->assertSee('Offerta dimostrativa');
        $this->assertSame(0,$policy->documents()->count());
        $this->assertDatabaseCount('insurance_collections',0);
        $this->assertSame('active',$policy->fresh()->status);
    }

    public function test_rate_limit_applies_also_to_invalid_credentials(): void
    {
        $this->withToken(Str::random(64));
        for($i=0;$i<30;$i++) $this->postJson(route('api.inbound-emails.store'),[])->assertUnauthorized();
        $this->postJson(route('api.inbound-emails.store'),[])->assertStatus(429);
        $this->assertDatabaseCount('inbound_emails',0);
    }
}
