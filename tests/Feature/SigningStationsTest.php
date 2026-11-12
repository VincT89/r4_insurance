<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Attachment, Client, DocumentSignature, InsuranceBranch, InsuranceFormTemplate, InsurancePolicy, SigningStation, StationSigningRequest, SupplierCompany, User};
use App\Services\{DocumentSigning, InsuranceForms, PrivateAttachmentBatch, SigningStations};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class SigningStationsTest extends TestCase
{
    use RefreshDatabase;
    private User $operator;
    private Client $client;
    private InsurancePolicy $policy;
    private Attachment $source;
    private string $pdf;

    public function test_station_creation_uses_explicit_datetimes_without_legacy_mysql_timestamp_defaults(): void
    {
        $mysql=new \Illuminate\Database\MySqlConnection(fn()=>throw new \LogicException('No database connection expected.'));
        $mysql->useDefaultSchemaGrammar();
        $queries=[];
        \Illuminate\Support\Facades\Schema::shouldReceive('hasTable')->andReturn(false);
        \Illuminate\Support\Facades\Schema::shouldReceive('hasColumns')->andReturn(true);
        \Illuminate\Support\Facades\Schema::shouldReceive('hasIndex')->andReturn(true);
        \Illuminate\Support\Facades\Schema::shouldReceive('create')->times(3)->andReturnUsing(function($table,$definition)use($mysql,&$queries) {
            $blueprint=new \Illuminate\Database\Schema\Blueprint($mysql,$table);
            $blueprint->create(); $definition($blueprint);
            $queries[$table]=implode("\n",$blueprint->toSql());
        });
        (require database_path('migrations/2026_11_09_120000_create_signing_stations.php'))->up();
        foreach(['pairing_expires_at','credential_expires_at'] as $column) $this->assertStringContainsString('`'.$column.'` datetime not null',$queries['signing_stations']);
        foreach(['identity_confirmed_at','expires_at'] as $column) $this->assertStringContainsString('`'.$column.'` datetime not null',$queries['station_signing_requests']);
        $this->assertDoesNotMatchRegularExpression('/timestamp(?:\\(\\d+\\))? not null/i',implode("\n",$queries));
        preg_match_all('/(?:index|key|constraint|unique)\\s+`([^`]+)`/i',implode("\n",$queries),$names);
        foreach($names[1] as $name) $this->assertLessThanOrEqual(64,strlen($name),$name);
    }

    public function test_the_migration_resumes_partial_tables_and_preserves_existing_station_credentials(): void
    {
        [$station]=$this->device();
        $before=$station->fresh()->getRawOriginal();
        \Illuminate\Support\Facades\Schema::drop('station_signing_items');
        \Illuminate\Support\Facades\Schema::table('station_signing_requests',fn($table)=>$table->dropIndex('station_requests_pending_idx'));
        (require database_path('migrations/2026_11_09_120000_create_signing_stations.php'))->up();
        $this->assertSame($before,$station->fresh()->getRawOriginal());
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasIndex('station_signing_requests',['signing_station_id','status','expires_at']));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasIndex('station_signing_items',['station_signing_request_id','source_attachment_id'],'unique'));
    }

    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('attachments');
        $this->operator = User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']); $this->actingAs($this->operator);
        $this->client = Client::factory()->create(['client_type'=>'person','name'=>'Firmatario postazione collaudo']);
        $branch = InsuranceBranch::create(['name'=>'Ramo di collaudo','name_key'=>hash('sha256','RAMO COLLAUDO'),'status'=>'active']);
        $company = SupplierCompany::create(['name'=>'Compagnia di collaudo','status'=>'active']);
        $this->policy = $this->client->policies()->create(['category'=>$branch->name,'insurance_branch_id'=>$branch->id,'company'=>$company->name,'supplier_company_id'=>$company->id,'number'=>'TEST-STAZIONE-01','starts_on'=>today(),'expires_on'=>today()->addYear(),'status'=>'active','premium'=>'500.00']);
        $pdf = new \Dompdf\Dompdf(); $pdf->loadHtml('<h1>Documento di collaudo</h1><p>Senza validità contrattuale</p>'); $pdf->render(); $this->pdf = $pdf->output();
        $this->source = $this->file();
    }

    private function file(?Client $client = null, ?InsurancePolicy $policy = null): Attachment
    {
        return app(PrivateAttachmentBatch::class)->upload($client ?? $this->client,[UploadedFile::fake()->createWithContent('documento-collaudo.pdf',$this->pdf)],['document_category'=>'policy','insurance_policy_id'=>($policy ?? $this->policy)->id])[0];
    }
    private function device(string $name='Reception collaudo'): array
    {
        $credential = bin2hex(random_bytes(32));
        $station = SigningStation::create(['name'=>$name,'status'=>'active','credential_hash'=>hash('sha256',$credential),'pairing_expires_at'=>now()->subMinute(),'credential_expires_at'=>now()->addDays(30),'paired_by'=>$this->operator->id,'paired_at'=>now(),'last_seen_at'=>now()]);
        return [$station,$credential];
    }
    private function data(SigningStation $station, array $extra=[]): array
    {
        return array_replace(['signing_station_id'=>$station->id,'attachment_ids'=>[$this->source->id],'signer_name'=>$this->client->name,'identity_confirmed'=>1],$extra);
    }
    private function send(SigningStation $station, array $extra=[]): StationSigningRequest
    {
        $this->actingAs($this->operator)->post(route('station-signing.store',$this->source),$this->data($station,$extra))->assertSessionHasNoErrors()->assertRedirect();
        return StationSigningRequest::latest('created_at')->firstOrFail();
    }
    private function guest(string $credential): static
    {
        auth()->logout();
        return $this->withCredentials()->withCookie(SigningStations::COOKIE,$credential);
    }
    private function drawing(): array
    {
        return ['consent'=>true,'strokes'=>[[[.1,.7],[.2,.2],[.25,.6],[.35,.3],[.4,.7],[.5,.4],[.6,.6]]],
            'placements'=>[['page'=>1,'x'=>.55,'y'=>.8,'width'=>.3,'height'=>.08]]];
    }

    public function test_pairing_is_short_lived_single_use_and_never_grants_an_operator_login(): void
    {
        auth()->logout();
        $this->get(route('signing-terminal.index'))->assertOk()->assertSee('Collega questa postazione');
        $response = $this->post(route('signing-terminal.pair'))->assertRedirect(route('signing-terminal.index'));
        $response->assertCookie(SigningStations::COOKIE);
        $station = SigningStation::sole(); $code = $station->pairing_code;
        $this->assertMatchesRegularExpression('/^[0-9]{8}$/',$code);
        $this->assertStringNotContainsString($code,$station->getRawOriginal('pairing_code'));
        $this->post(route('signing-stations.pair'),['pairing_code'=>$code,'name'=>'Reception'])->assertRedirect(route('login'));
        $this->actingAs($this->operator)->post(route('signing-stations.pair'),['pairing_code'=>$code,'name'=>'Reception'])->assertSessionHasNoErrors();
        $this->assertSame('active',$station->fresh()->status); $this->assertNull($station->fresh()->pairing_code);
        $this->post(route('signing-stations.pair'),['pairing_code'=>$code,'name'=>'Seconda'])->assertSessionHasErrors('pairing_code');
        auth()->logout(); $this->get(route('clients.index'))->assertRedirect(route('login'));
    }

    public function test_expired_codes_inactive_users_and_first_access_cannot_pair_or_dispatch(): void
    {
        $station = SigningStation::create(['credential_hash'=>hash('sha256',Str::random(64)),'pairing_code'=>'12345678','pairing_code_hash'=>hash('sha256','12345678'),'pairing_expires_at'=>now()->subMinute(),'credential_expires_at'=>now()->addDay()]);
        $this->post(route('signing-stations.pair'),['pairing_code'=>'12345678','name'=>'Scaduta'])->assertSessionHasErrors('pairing_code');
        [$active] = $this->device();
        $this->operator->update(['password_changed_at'=>null]);
        $this->actingAs($this->operator->fresh())->post(route('station-signing.store',$this->source),$this->data($active))->assertRedirect(route('password.setup'));
        $this->operator->update(['status'=>'inactive']);
        $this->actingAs($this->operator->fresh())->get(route('signing-stations.index'))->assertRedirect(route('login'));
        $this->assertSame('pending',$station->fresh()->status);
        $this->assertDatabaseCount('station_signing_requests',0);
    }

    public function test_guests_only_read_the_assigned_document_and_keep_the_operator_out_of_the_session(): void
    {
        [$station,$credential] = $this->device(); $job = $this->send($station); $item = $job->items()->sole();
        $this->guest($credential)->getJson(route('signing-terminal.current'))->assertOk()->assertJsonPath('request_id',$job->id);
        $this->get(route('signing-terminal.show',$job))->assertOk()->assertSee($this->client->name)->assertDontSee('Navigazione principale')->assertDontSee('name="identity_confirmed"',false)->assertDontSee('name="token"',false);
        $this->get(route('signing-terminal.document',$item))->assertOk()->assertHeader('Content-Type','application/pdf')->assertContent($this->pdf);
        $this->get($this->source->downloadUrl())->assertRedirect(route('login'));
        $this->get(route('signing-stations.index'))->assertRedirect(route('login'));
        $this->get(route('station-signing.show',$job))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertStringNotContainsString($this->client->name,DB::table('station_signing_requests')->value('signer_name'));
        $this->assertStringNotContainsString($item->prepared_token,$item->getRawOriginal('prepared_token'));
    }

    public function test_other_devices_and_forged_credentials_cannot_read_or_complete_a_request(): void
    {
        [$first] = $this->device(); $job = $this->send($first); $item = $job->items()->sole();
        [, $secondCredential] = $this->device('Seconda postazione');
        $this->guest($secondCredential)->get(route('signing-terminal.document',$item))->assertNotFound();
        $this->postJson(route('signing-terminal.complete',$item),$this->drawing())->assertNotFound();
        $this->withCookie(SigningStations::COOKIE,str_repeat('0',64))->get(route('signing-terminal.document',$item))->assertForbidden();
        $this->assertDatabaseCount('document_signatures',0);
    }

    public function test_a_station_and_a_document_can_have_only_one_active_request(): void
    {
        [$first] = $this->device(); $job = $this->send($first);
        [$second] = $this->device('Seconda postazione');
        $this->post(route('station-signing.store',$this->source),$this->data($second))->assertSessionHasErrors('attachment_ids');
        $other = $this->file();
        $this->post(route('station-signing.store',$other),$this->data($first,['attachment_ids'=>[$other->id]]))->assertSessionHasErrors('signing_station_id');
        $this->post(route('station-signing.cancel',$job))->assertRedirect();
        $this->post(route('station-signing.store',$other),$this->data($first,['attachment_ids'=>[$other->id]]))->assertSessionHasNoErrors();
    }

    public function test_identity_consent_strokes_and_document_scoping_are_checked_on_the_server(): void
    {
        [$station,$credential] = $this->device();
        $this->post(route('station-signing.store',$this->source),$this->data($station,['identity_confirmed'=>0]))->assertSessionHasErrors('identity_confirmed');
        $otherClient = Client::factory()->create(); $otherPolicy = $this->policy->replicate(); $otherPolicy->client_id=$otherClient->id; $otherPolicy->number='OTHER-POLICY'; $otherPolicy->save();
        $otherFile = $this->file($otherClient,$otherPolicy);
        $this->post(route('station-signing.store',$this->source),$this->data($station,['attachment_ids'=>[$otherFile->id]]))->assertSessionHasErrors('attachment_ids');
        $job = $this->send($station); $item = $job->items()->sole(); $this->guest($credential);
        $this->postJson(route('signing-terminal.complete',$item),array_replace($this->drawing(),['consent'=>false]))->assertUnprocessable();
        $this->postJson(route('signing-terminal.complete',$item),array_replace($this->drawing(),['strokes'=>[]]))->assertUnprocessable();
        $this->postJson(route('signing-terminal.complete',$item),array_replace($this->drawing(),['placements'=>[['page'=>1,'x'=>.9,'y'=>.8,'width'=>.3,'height'=>.08]]]))->assertUnprocessable();
        $this->assertDatabaseCount('document_signatures',0);
    }

    public function test_signature_is_saved_once_with_the_confirmed_identity_and_station_evidence(): void
    {
        [$station,$credential] = $this->device(); $job = $this->send($station); $item = $job->items()->sole();
        $this->guest($credential);
        $input = $this->drawing()+['signer_name'=>'Nome alterato dal tablet','signer_role'=>'Qualifica alterata'];
        $this->postJson(route('signing-terminal.complete',$item),$input)->assertOk()->assertJsonPath('url',route('signing-terminal.index'));
        $this->postJson(route('signing-terminal.complete',$item),$input)->assertOk();
        $record = DocumentSignature::sole();
        $this->assertSame($this->operator->id,$record->created_by); $this->assertSame($this->client->name,$record->signer_name);
        $this->assertSame($job->id,$record->evidence['station_request']['request_id']);
        $this->assertSame($station->id,$record->evidence['station_request']['station_id']);
        $this->assertSame($this->pdf,Storage::disk('attachments')->get($this->source->path));
        $this->assertSame($this->operator->id,$record->signedFile->uploaded_by);
        $this->assertSame('completed',$job->fresh()->status); $this->assertGuest();
        $this->get(route('signing-terminal.document',$item))->assertStatus(410);
        $this->getJson(route('signing-terminal.current'))->assertJsonPath('request_id',null);
        $this->get(route('signing-terminal.show',$job))->assertRedirect(route('signing-terminal.index'));
        $this->postJson(route('signing-terminal.complete',$item),array_replace($input,['strokes'=>[[[.1,.6],[.3,.2],[.4,.7],[.5,.3],[.6,.6]]]]))->assertUnprocessable();
        $this->assertDatabaseCount('document_signatures',1);
    }

    public function test_expiry_revocation_cancellation_and_operator_revocation_stop_device_access(): void
    {
        [$station,$credential] = $this->device(); $job = $this->send($station); $item = $job->items()->sole();
        $job->update(['expires_at'=>now()->subSecond()]); $this->guest($credential)->postJson(route('signing-terminal.complete',$item),$this->drawing())->assertStatus(410);
        $job->update(['expires_at'=>now()->addMinutes(5),'status'=>'cancelled']);
        $this->get(route('signing-terminal.document',$item))->assertStatus(410);
        $job->update(['status'=>'pending']); $this->operator->update(['status'=>'inactive']);
        $this->getJson(route('signing-terminal.current'))->assertJsonPath('request_id',null);
        $this->assertSame('blocked',$job->fresh()->status);
        $this->operator->update(['status'=>'active']); $job->refresh()->update(['status'=>'pending']);
        $this->actingAs($this->operator->fresh())->post(route('signing-stations.revoke',$station),['confirmed'=>1])->assertRedirect();
        $this->guest($credential)->get(route('signing-terminal.document',$item))->assertForbidden();
        $this->assertSame('cancelled',$job->fresh()->status); $this->assertDatabaseCount('document_signatures',0);
    }

    public function test_changed_document_blocks_the_request_and_does_not_serve_a_new_version(): void
    {
        [$station,$credential] = $this->device(); $job = $this->send($station); $item = $job->items()->sole();
        Storage::disk('attachments')->put($this->source->path,$this->pdf."\nchanged");
        $this->guest($credential)->get(route('signing-terminal.show',$job))->assertRedirect(route('signing-terminal.index'));
        $this->assertSame('blocked',$job->fresh()->status);
        $this->get(route('signing-terminal.document',$item))->assertStatus(410);
        $this->assertDatabaseCount('document_signatures',0);
    }

    public function test_a_package_is_signed_in_order_and_keeps_the_manual_review_workflow(): void
    {
        $ids = [];
        foreach (['Primo modulo collaudo','Secondo modulo collaudo'] as $title) $ids[] = InsuranceFormTemplate::create(['family'=>(string)Str::uuid(),'version'=>1,'title'=>$title,'category'=>'consistency','scope'=>'policy','body'=>'Documento di collaudo per {{contraente}}. Firma: __________','signature_required'=>true,'status'=>'active'])->id;
        $forms = app(InsuranceForms::class);
        $package = $forms->createPackage($this->client,$this->policy,['context_hash'=>$forms->contextHash($this->client,$this->policy),'request_key'=>(string)Str::uuid(),'templates'=>$ids,'reason'=>'initial','signer_name'=>$this->client->name,'place'=>'Napoli','document_date'=>today()->toDateString()]);
        $files = $package->documents()->get()->map(fn($document)=>$document->generated);
        $this->source = $files->first();
        [$station,$credential] = $this->device();
        $job = $this->send($station,['attachment_ids'=>$files->modelKeys()]);
        [$first,$second] = $job->items->all(); $this->guest($credential);
        $this->postJson(route('signing-terminal.complete',$second),$this->drawing())->assertConflict();
        $this->postJson(route('signing-terminal.complete',$first),$this->drawing())->assertOk();
        $this->assertSame('pending',$job->fresh()->status);
        $this->postJson(route('signing-terminal.complete',$second),$this->drawing())->assertOk();
        $this->assertSame('completed',$job->fresh()->status);
        $this->assertDatabaseCount('insurance_form_submissions',2);
        $this->assertSame(['received','received'],$package->documents()->get()->map(fn($document)=>$document->latestSubmission->status)->all());
        $this->assertDatabaseCount('client_consents',0);
    }

    public function test_production_and_lan_require_https_and_session_forms_keep_csrf_protection(): void
    {
        auth()->logout(); $environment=$this->app['env'];
        try {
            $this->app['env']='production';
            $this->get(route('signing-terminal.index'))->assertStatus(426);
            $this->get('https://localhost/firma')->assertOk();
            $this->app['env']='local';
            $this->post(route('signing-terminal.pair'))->assertStatus(419);
        } finally { $this->app['env']=$environment; }
    }

    public function test_cancelling_a_partially_signed_request_keeps_the_signature_and_the_original_files(): void
    {
        $other = $this->file(); [$station,$credential] = $this->device();
        $job = $this->send($station,['attachment_ids'=>[$this->source->id,$other->id]]); $item = $job->items()->first();
        $this->guest($credential)->postJson(route('signing-terminal.complete',$item),$this->drawing())->assertOk();
        $this->actingAs($this->operator)->post(route('station-signing.cancel',$job))->assertRedirect();
        $this->guest($credential)->get(route('signing-terminal.document',$job->items()->whereNull('document_signature_id')->first()))->assertStatus(410);
        $this->assertSame('cancelled',$job->fresh()->status); $this->assertDatabaseCount('document_signatures',1);
        $this->assertTrue(Storage::disk('attachments')->exists(DocumentSignature::sole()->signedFile->path));
        $this->assertSame($this->pdf,Storage::disk('attachments')->get($this->source->path));
    }

    public function test_pending_requests_protect_originals_before_deletion_can_remove_the_file(): void
    {
        [$station] = $this->device(); $this->send($station);
        try { $this->source->delete(); $this->fail('Il documento assegnato deve restare nello storico.'); }
        catch (\Illuminate\Validation\ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
        $this->assertTrue(Storage::disk('attachments')->exists($this->source->path));
        $this->assertTrue($this->operator->fresh()->hasRetainedHistory());
    }

    public function test_revoking_a_station_preserves_the_state_of_a_request_that_has_already_expired(): void
    {
        [$station] = $this->device(); $job = $this->send($station);
        $job->update(['expires_at'=>now()->subMinute()]);
        $this->post(route('signing-stations.revoke',$station),['confirmed'=>1])->assertRedirect();
        $this->assertSame('Scaduta',$job->fresh()->state_label);
        $this->assertNull($job->fresh()->cancelled_at);
        $this->assertSame('revoked',$station->fresh()->status);
    }
}
