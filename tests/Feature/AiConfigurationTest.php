<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AiSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private string $key;
    private AiSetting $setting;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']));
        $this->key = 'sk-test-'.Str::random(40);
        $this->setting = AiSetting::findOrFail(1);
        $this->setting->update(['api_key'=>$this->key,'enabled'=>false]);
    }

    private function settings(array $extra = []): array
    {
        return $extra + ['enabled'=>0,'model'=>$this->setting->model,'monthly_budget'=>'10.00','document_budget'=>'0.25'];
    }

    private function verify(array $extra = [])
    {
        return $this->postJson(route('ai.connection.check'), $extra + ['revision'=>$this->setting->revision]);
    }

    public function test_saved_key_is_visible_as_a_state_and_survives_other_configuration_changes(): void
    {
        $this->get(route('ai.index'))->assertOk()->assertSee('Chiave salvata')->assertSee('Modifica')->assertSee('Verifica collegamento')->assertDontSee($this->key, false);
        $this->patch(route('ai.update'), $this->settings(['monthly_budget'=>'20.00','ai_api_key'=>'']))->assertRedirect(route('ai.index'));
        $this->assertSame($this->key, $this->setting->fresh()->api_key);
        $this->assertSame($this->setting->revision, $this->setting->fresh()->revision);
        $this->assertSame(20000000, $this->setting->fresh()->monthly_budget_microusd);
        Http::assertNothingSent();
    }

    public function test_replacement_is_atomic_and_invalid_changes_leave_the_saved_key_in_place(): void
    {
        $replacement = 'sk-test-'.Str::random(40);
        $this->patch(route('ai.update'), $this->settings(['replace_api_key'=>1,'ai_api_key'=>'']))->assertSessionHasErrors('ai_api_key');
        $this->assertSame($this->key, $this->setting->fresh()->api_key);
        $this->patch(route('ai.update'), $this->settings(['replace_api_key'=>1,'ai_api_key'=>$replacement,'document_budget'=>'30']))->assertSessionHasErrors('document_budget')->assertSessionMissing('_old_input.ai_api_key');
        $this->assertSame($this->key, $this->setting->fresh()->api_key);
        $this->patch(route('ai.update'), $this->settings(['replace_api_key'=>1,'ai_api_key'=>$replacement]))->assertRedirect(route('ai.index'));
        $saved = $this->setting->fresh();
        $this->assertSame($replacement, $saved->api_key);
        $this->assertNotSame($this->setting->revision, $saved->revision);
        $this->assertStringNotContainsString($replacement, $saved->getRawOriginal('api_key'));
        $this->get(route('ai.index'))->assertDontSee($replacement, false)->assertDontSee($this->key, false)->assertSee('Chiave salvata');
        $this->assertStringNotContainsString($replacement, json_encode(DB::table('audit_logs')->get()));
        Http::assertNothingSent();
    }

    public function test_connection_check_uses_only_saved_credentials_and_does_not_create_usage_or_enable_ai(): void
    {
        $before = $this->setting->fresh()->getRawOriginal();
        Http::fake(['api.openai.com/v1/models/*'=>Http::response(['id'=>$this->setting->model,'object'=>'model'])]);
        $response = $this->verify(['ai_api_key'=>'not-the-saved-key','model'=>'other-model','url'=>'https://example.test']);
        $response->assertOk()->assertJsonPath('state','success')->assertJsonPath('code','connected')->assertDontSee($this->key, false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Http::assertSent(fn($request)=>$request->method()==='GET' && $request->url()==='https://api.openai.com/v1/models/'.$this->setting->model && $request->hasHeader('Authorization','Bearer '.$this->key) && $request->body()==='');
        Http::assertSentCount(1);
        $this->assertSame($before, $this->setting->fresh()->getRawOriginal());
        $this->assertDatabaseCount('ai_usages',0);
    }

    public function test_operators_cannot_check_credentials_and_missing_keys_do_not_send_requests(): void
    {
        $this->setting->update(['api_key'=>null]);
        $this->verify()->assertOk()->assertJsonPath('code','missing_key');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']));
        $this->verify()->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_obsolete_configuration_and_unapproved_models_do_not_send_requests(): void
    {
        $this->verify(['revision'=>(string)Str::uuid()])->assertOk()->assertJsonPath('code','configuration_changed');
        $this->setting->update(['model'=>'../unapproved-model']);
        $this->verify()->assertOk()->assertJsonPath('code','invalid_model');
        Http::assertNothingSent();
    }

    public function test_missing_configuration_is_reported_without_a_request_to_openai(): void
    {
        Schema::drop('ai_settings');
        $this->verify()->assertOk()->assertJsonPath('code','not_installed');
        Http::assertNothingSent();
    }

    public static function providerErrors(): array
    {
        return [[401,'invalid_key'],[403,'permission_denied'],[404,'model_unavailable'],[429,'rate_limited'],[503,'provider_unavailable'],[302,'provider_unavailable']];
    }

    #[DataProvider('providerErrors')]
    public function test_provider_errors_are_actionable_and_do_not_echo_secrets(int $status, string $code): void
    {
        Http::fake(['api.openai.com/v1/models/*'=>Http::response(['error'=>['message'=>'Secret '.$this->key]], $status, ['Location'=>'https://example.test'])]);
        $this->verify()->assertOk()->assertJsonPath('state','error')->assertJsonPath('code',$code)->assertDontSee($this->key, false);
        Http::assertSentCount(1);
        $this->assertSame($this->key, $this->setting->fresh()->api_key);
        $this->assertDatabaseCount('ai_usages',0);
    }

    public function test_timeouts_are_safe(): void
    {
        Http::fake(fn()=>throw new ConnectionException('Private detail '.$this->key));
        $this->verify()->assertOk()->assertJsonPath('code','connection_error')->assertDontSee($this->key, false);
    }

    public static function unexpectedResponses(): array
    {
        return [[['id'=>'another-model','object'=>'model']], ['<html>Not a model</html>']];
    }

    #[DataProvider('unexpectedResponses')]
    public function test_unexpected_responses_cannot_report_success(array|string $body): void
    {
        Http::fake(['api.openai.com/v1/models/*'=>Http::response($body)]);
        $this->verify()->assertOk()->assertJsonPath('code','unexpected_response');
    }

    public function test_a_concurrent_key_change_invalidates_the_connection_result(): void
    {
        Http::fake(function () {
            AiSetting::findOrFail(1)->update(['revision'=>(string)Str::uuid(),'api_key'=>'sk-test-'.Str::random(40)]);
            return Http::response(['id'=>$this->setting->model,'object'=>'model']);
        });
        $this->verify()->assertOk()->assertJsonPath('code','configuration_changed');
    }

    public function test_verification_has_a_separate_rate_limit_and_a_non_javascript_fallback(): void
    {
        Http::fake(['api.openai.com/v1/models/*'=>Http::response(['id'=>$this->setting->model,'object'=>'model'])]);
        $this->post(route('ai.connection.check'), ['revision'=>$this->setting->revision])->assertRedirect(route('ai.index').'#ai-configuration')->assertSessionHas('ai_connection_check.code','connected');
        $this->get(route('ai.index'))->assertSee('Collegamento riuscito')->assertDontSee($this->key, false);
        for ($attempt = 0; $attempt < 4; $attempt++) $this->verify()->assertOk();
        $this->verify()->assertStatus(429);
        Http::assertSentCount(5);
    }
}
