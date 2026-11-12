<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AiSetting, AiUsage, User};
use App\Services\{AiContractReader, AiPricing};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class AiUsageTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $operator;
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->key='sk-'.Str::random(40);
        $this->actingAs($this->admin);
    }

    private function enable(array $extra=[]): AiSetting
    {
        $setting=AiSetting::findOrFail(1);
        $setting->update($extra+['enabled'=>true,'api_key'=>$this->key]);
        return $setting;
    }

    private function pdf(string $suffix=''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('contratto.pdf',"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n".$suffix);
    }

    private function response(array $extra=[]): array
    {
        $extraction=['document_kind'=>'policy','fields'=>[['key'=>'number','value'=>'AI-COLLAUDO-001','page'=>1,'quote'=>'Numero polizza AI-COLLAUDO-001','certainty'=>'certain']],'warnings'=>[]];
        return array_replace_recursive(['id'=>'resp_test','status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>json_encode($extraction)]]]],'usage'=>['input_tokens'=>6000,'input_tokens_details'=>['cached_tokens'=>2000],'output_tokens'=>1000,'output_tokens_details'=>['reasoning_tokens'=>250],'total_tokens'=>7000]],$extra);
    }

    private function fake(array $response=[]): void
    {
        Http::fake(['api.openai.com/v1/responses/input_tokens'=>Http::response(['object'=>'response.input_tokens','input_tokens'=>6000]),'api.openai.com/v1/responses'=>Http::response($this->response($response))]);
    }

    public function test_only_admins_can_view_consumption_or_configure_the_key(): void
    {
        $this->get(route('ai.index'))->assertOk()->assertSee('Token letti')->assertSee('Token generati')->assertSee('Token totali')->assertSee('Nessun consumo AI');
        $this->actingAs($this->operator)->get(route('ai.index'))->assertForbidden();
        $this->patch(route('ai.update'),[])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_the_key_is_encrypted_never_echoed_and_configuration_does_not_make_a_paid_call(): void
    {
        $data=['enabled'=>1,'ai_api_key'=>$this->key,'model'=>'gpt-6.1-terra','monthly_budget'=>'10.00','document_budget'=>'0.25'];
        $this->patch(route('ai.update'),$data)->assertRedirect(route('ai.index'));
        $setting=AiSetting::findOrFail(1);
        $this->assertSame($this->key,$setting->api_key);
        $this->assertStringNotContainsString($this->key,$setting->getRawOriginal('api_key'));
        $this->assertArrayNotHasKey('api_key',$setting->toArray());
        $this->get(route('ai.index'))->assertDontSee($this->key,false)->assertSee('Chiave salvata');
        $this->patch(route('ai.update'),array_replace($data,['model'=>'unapproved-model']))->assertSessionHasErrors('model')->assertSessionMissing('_old_input.ai_api_key');
        $this->assertStringNotContainsString($this->key,json_encode(DB::table('audit_logs')->get()));
        Http::assertNothingSent();
    }

    public function test_disabled_ai_never_sends_a_document(): void
    {
        $this->postJson(route('ai.contracts.read'),['contract'=>$this->pdf(),'ai_processing'=>1])->assertUnprocessable();
        $this->assertDatabaseCount('ai_usages',0);
        Http::assertNothingSent();
    }

    public function test_operator_reading_records_real_api_tokens_cached_discount_and_cost_once(): void
    {
        $this->enable(); $this->fake(); $this->actingAs($this->operator);
        $input=['contract'=>$this->pdf(),'ai_processing'=>1];
        $this->postJson(route('ai.contracts.read'),$input)->assertOk()->assertJsonPath('usage.tokens',7000)->assertJsonPath('usage.cost','0,0091 USD')->assertJsonPath('usage.cached',false);
        $usage=AiUsage::sole();
        $this->assertSame(9100,$usage->cost_microusd);
        $this->assertEquals(250,$usage->reasoning_tokens);
        $this->assertEquals(2000,$usage->cached_input_tokens);
        $this->assertSame(0,$usage->reserved_microusd);
        $this->assertSame('complete',$usage->state);
        $this->assertStringNotContainsString('AI-COLLAUDO-001',$usage->getRawOriginal('result'));
        $this->postJson(route('ai.contracts.read'),$input)->assertOk()->assertJsonPath('usage.cached',true);
        $this->assertDatabaseCount('ai_usages',1);
        $this->assertDatabaseCount('insurance_policies',0);
        Http::assertSentCount(2);
        Http::assertSent(fn($request)=>$request->url()==='https://api.openai.com/v1/responses' && $request['store']===false && $request['input'][0]['content'][0]['filename']==='contratto.pdf');
        $this->actingAs($this->admin)->get(route('ai.index'))->assertOk()->assertSee('7.000')->assertSee('0,0091 USD');
    }

    public function test_document_and_monthly_limits_include_pending_reservations_before_any_model_call(): void
    {
        $setting=$this->enable(['document_budget_microusd'=>1]); $this->fake();
        $this->postJson(route('ai.contracts.read'),['contract'=>$this->pdf(),'ai_processing'=>1])->assertUnprocessable();
        $this->assertSame('rejected',AiUsage::sole()->state);
        Http::assertSentCount(1);
        $setting->update(['revision'=>(string)Str::uuid(),'document_budget_microusd'=>250000,'monthly_budget_microusd'=>30000]);
        $this->postJson(route('ai.contracts.read'),['contract'=>$this->pdf(),'ai_processing'=>1])->assertUnprocessable();
        Http::assertSentCount(2);
        $this->assertSame(0,(int)AiUsage::sum('cost_microusd'));
    }

    public function test_timeouts_are_not_reported_as_free_and_cannot_silently_charge_a_second_time(): void
    {
        $this->enable(); $calls=0;
        Http::fake(function($request)use(&$calls) { $calls++; if(str_ends_with($request->url(),'/input_tokens')) return Http::response(['input_tokens'=>6000]); throw new ConnectionException('Timeout di collaudo'); });
        $input=['contract'=>$this->pdf(),'ai_processing'=>1];
        $this->postJson(route('ai.contracts.read'),$input)->assertUnprocessable();
        $usage=AiUsage::sole();
        $this->assertSame('uncertain',$usage->state);
        $this->assertNull($usage->cost_microusd);
        $this->assertGreaterThan(0,$usage->reserved_microusd);
        $this->postJson(route('ai.contracts.read'),$input)->assertUnprocessable();
        $this->assertSame(2,$calls);
        $this->get(route('ai.index'))->assertOk()->assertSee('Costo da verificare');
    }

    public function test_a_paid_refusal_preserves_the_returned_usage_instead_of_zeroing_the_counter(): void
    {
        $this->enable(); $this->fake(['output'=>[['content'=>[['type'=>'refusal','refusal'=>'Documento non elaborabile']]]]]);
        $this->postJson(route('ai.contracts.read'),['contract'=>$this->pdf(),'ai_processing'=>1])->assertUnprocessable();
        $usage=AiUsage::sole();
        $this->assertSame('failed',$usage->state);
        $this->assertSame(9100,$usage->cost_microusd);
        $this->assertEquals(7000,$usage->total_tokens);
    }

    public function test_sensitive_error_responses_never_reach_the_ui_or_consumption_record(): void
    {
        $this->enable();
        Http::fake(['api.openai.com/v1/responses/input_tokens'=>Http::response(['error'=>['message'=>'Invalid key '.$this->key]],401)]);
        $response=$this->postJson(route('ai.contracts.read'),['contract'=>$this->pdf(),'ai_processing'=>1])->assertUnprocessable();
        $response->assertDontSee($this->key,false);
        $this->assertStringNotContainsString($this->key,json_encode(AiUsage::sole()->getRawOriginal()));
    }

    public function test_costs_use_the_correct_long_context_rates_and_never_double_count_reasoning(): void
    {
        $this->assertSame(11000,AiPricing::cost(AiPricing::rates('gpt-6.1-terra',6000),6000,1000));
        $this->assertSame(607500,AiPricing::cost(AiPricing::rates('gpt-6.1-terra',300000),300000,1000));
    }

    public function test_an_explicit_retry_keeps_each_attempt_and_does_not_duplicate_an_uncertain_call(): void
    {
        $this->enable();
        Http::fake(['api.openai.com/v1/responses/input_tokens'=>Http::response(['input_tokens'=>6000]),'api.openai.com/v1/responses'=>Http::sequence()->push(['error'=>['message'=>'Non disponibile']],429)->push($this->response())]);
        $input=['contract'=>$this->pdf(),'ai_processing'=>1];
        $this->postJson(route('ai.contracts.read'),$input)->assertUnprocessable()->assertJsonPath('ai_retry_allowed',true);
        $this->postJson(route('ai.contracts.read'),$input+['retry'=>1])->assertOk();
        $this->assertDatabaseCount('ai_usages',2);
        $this->assertSame(1,AiUsage::where('state','complete')->count());
        $this->assertSame(9100,(int)AiUsage::sum('cost_microusd'));
    }

    public function test_unknown_costs_can_only_be_reconciled_by_admin_and_cannot_be_rewritten_twice(): void
    {
        $this->enable(); $response=$this->response(); unset($response['usage']);
        Http::fake(['api.openai.com/v1/responses/input_tokens'=>Http::response(['input_tokens'=>6000]),'api.openai.com/v1/responses'=>Http::response($response)]);
        $this->postJson(route('ai.contracts.read'),['contract'=>$this->pdf(),'ai_processing'=>1])->assertOk()->assertJsonPath('usage.uncertain',true)->assertJsonPath('usage.cost','Da verificare');
        $usage=AiUsage::sole();
        $values=['input_tokens'=>6000,'output_tokens'=>1000,'cached_input_tokens'=>0,'reported_cost_usd'=>'0.011','reconciliation_note'=>'Verificato sul riepilogo della piattaforma del fornitore.'];
        $this->actingAs($this->operator)->post(route('ai.reconcile',$usage),$values)->assertForbidden();
        $this->actingAs($this->admin)->post(route('ai.reconcile',$usage),$values)->assertRedirect(route('ai.index'));
        $this->assertSame(11000,$usage->fresh()->cost_microusd);
        $this->assertSame(0,$usage->fresh()->reserved_microusd);
        $this->assertSame('complete',$usage->fresh()->state);
        $this->post(route('ai.reconcile',$usage),$values)->assertSessionHasErrors('reconciliation_note');
        $this->assertSame(11000,$usage->fresh()->cost_microusd);
    }

    public function test_pending_reservations_reduce_the_budget_available_to_other_requests(): void
    {
        $this->enable(['monthly_budget_microusd'=>50000]); $this->fake();
        AiUsage::create(['user_id'=>$this->admin->id,'feature'=>'policy_contract','model'=>'gpt-6.1-terra','state'=>'running','request_key'=>str_repeat('a',64),'document_hash'=>str_repeat('b',64),'input_rate'=>1000000,'cached_rate'=>50000,'output_rate'=>5000000,'reserved_microusd'=>30000,'started_at'=>now()]);
        $this->postJson(route('ai.contracts.read'),['contract'=>$this->pdf(),'ai_processing'=>1])->assertUnprocessable();
        Http::assertSentCount(1);
        $this->assertSame(30000,(int)AiUsage::sum('reserved_microusd'));
    }

    public function test_unexpected_cache_writes_are_not_silently_charged_at_the_wrong_rate(): void
    {
        $this->enable(); $this->fake(['usage'=>['input_tokens_details'=>['cache_write_tokens'=>3000]]]);
        $this->postJson(route('ai.contracts.read'),['contract'=>$this->pdf(),'ai_processing'=>1])->assertOk()->assertJsonPath('usage.uncertain',true);
        $usage=AiUsage::sole();
        $this->assertEquals(7000,$usage->total_tokens);
        $this->assertNull($usage->cost_microusd);
        $this->assertGreaterThan(0,$usage->reserved_microusd);
        Http::assertSent(fn($request)=>$request->url()==='https://api.openai.com/v1/responses' && $request['prompt_cache_options']['mode']==='explicit' && $request['service_tier']==='default');
    }

    public function test_a_budget_rejection_can_be_explicitly_retried_after_changing_only_the_limits(): void
    {
        $setting=$this->enable(['document_budget_microusd'=>1]); $this->fake();
        $input=['contract'=>$this->pdf(),'ai_processing'=>1];
        $this->postJson(route('ai.contracts.read'),$input)->assertUnprocessable()->assertJsonPath('ai_retry_allowed',true);
        $revision=$setting->revision;
        $setting->update(['document_budget_microusd'=>250000]);
        $this->postJson(route('ai.contracts.read'),$input+['retry'=>1])->assertOk();
        $this->assertSame($revision,$setting->fresh()->revision);
        $this->assertDatabaseCount('ai_usages',2);
        $this->assertSame(1,AiUsage::where('state','rejected')->count());
        $this->assertSame(9100,(int)AiUsage::sum('cost_microusd'));
        Http::assertSentCount(3);
    }

    public function test_ai_migration_resumes_without_replacing_existing_configuration_or_history(): void
    {
        $setting=$this->enable(); $before=$setting->fresh()->getRawOriginal();
        (require database_path('migrations/2026_11_10_140000_create_ai_usage.php'))->up();
        $this->assertSame($before,$setting->fresh()->getRawOriginal());
    }

    public function test_interrupted_workers_preserve_unknown_charges_and_keep_manual_verification_available(): void
    {
        $this->enable();
        $base=['user_id'=>$this->admin->id,'feature'=>'policy_contract','model'=>'gpt-6.1-terra','document_hash'=>str_repeat('b',64),'input_rate'=>1000000,'cached_rate'=>50000,'output_rate'=>5000000,'started_at'=>now()->subMinutes(11)];
        $running=AiUsage::create($base+['state'=>'running','request_key'=>str_repeat('c',64),'reserved_microusd'=>30000]);
        $preparing=AiUsage::create($base+['state'=>'counting','request_key'=>str_repeat('d',64)]);
        $recent=AiUsage::create(array_replace($base,['started_at'=>now()])+['state'=>'running','request_key'=>str_repeat('e',64),'reserved_microusd'=>30000]);
        $this->get(route('ai.index'))->assertOk()->assertSee('Verifica consumo');
        $this->assertSame('uncertain',$running->fresh()->state);
        $this->assertNull($running->fresh()->cost_microusd);
        $this->assertSame(30000,$running->fresh()->reserved_microusd);
        $this->assertSame('failed',$preparing->fresh()->state);
        $this->assertSame(0,$preparing->fresh()->cost_microusd);
        $this->assertSame('running',$recent->fresh()->state);
        Http::assertNothingSent();
    }

    public function test_ai_tables_compile_for_mysql_without_long_names_or_implicit_timestamp_defaults(): void
    {
        $mysql=new \Illuminate\Database\MySqlConnection(fn()=>throw new \LogicException('No database connection expected.')); $mysql->useDefaultSchemaGrammar();
        $queries=[];
        \Illuminate\Support\Facades\Schema::shouldReceive('hasTable')->andReturn(false);
        \Illuminate\Support\Facades\Schema::shouldReceive('hasColumns')->andReturn(true);
        \Illuminate\Support\Facades\Schema::shouldReceive('hasIndex')->andReturn(true);
        \Illuminate\Support\Facades\Schema::shouldReceive('create')->times(2)->andReturnUsing(function($table,$definition)use($mysql,&$queries) {
            $blueprint=new \Illuminate\Database\Schema\Blueprint($mysql,$table); $blueprint->create(); $definition($blueprint); $queries[]=implode("\n",$blueprint->toSql());
        });
        (require database_path('migrations/2026_11_10_140000_create_ai_usage.php'))->up();
        preg_match_all('/(?:index|key|constraint|unique)\\s+`([^`]+)`/i',implode("\n",$queries),$names);
        foreach($names[1] as $name) $this->assertLessThanOrEqual(64,strlen($name),$name);
        $this->assertDoesNotMatchRegularExpression('/timestamp(?:\\(\\d+\\))? not null/i',implode("\n",$queries));
    }
}
