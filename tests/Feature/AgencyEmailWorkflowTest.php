<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AgencyCommunication, Client, InsurancePolicy, OutboundMailSetting, SupplierAgency, User};
use App\Services\{AgencyEmailTransport, AgencyMailConfiguration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgencyEmailWorkflowTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private SupplierAgency $agency;
    private Client $client;
    private array $smtp;

    protected function setUp(): void
    {
        parent::setUp(); $this->travelTo(now()->setDate(2026,10,16)->setTime(12,0));
        $this->admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']); $this->actingAs($this->admin);
        $this->agency=SupplierAgency::create(['name'=>'Agenzia Valdoro','email'=>'agenzia@example.test','status'=>'active']);
        $this->client=Client::factory()->create(['name'=>'Alessandro Varesi']);
        $this->smtp=['enabled'=>true,'host'=>'smtp.example.test','port'=>587,'encryption'=>'starttls','username'=>'sender@example.test','password'=>'testing-only-secret','from_address'=>'sender@example.test','from_name'=>'Soluzioni Assicurative SAS'];
        OutboundMailSetting::create(['credentials'=>$this->smtp,'updated_by'=>$this->admin->id]);
    }

    private function policy(array $extra=[]): InsurancePolicy
    {
        return $this->client->policies()->create(array_replace(['number'=>'SA-'.random_int(1000,9999),'category'=>'RC auto','company'=>'Compagnia Valdoro','supplier_agency_id'=>$this->agency->id,'starts_on'=>'2026-10-01','expires_on'=>'2027-10-01','status'=>'active','payment_frequency'=>'Semestrale','payment_method'=>'Bonifico a Compagnia'],$extra));
    }

    private function review(array $policies): AgencyCommunication
    {
        $this->post(route('agency-communications.review',$this->agency),['policies'=>array_map(fn($p)=>$p->id,$policies),'period'=>'day','date'=>'2026-10-16'])->assertSessionHasNoErrors()->assertRedirect();
        return AgencyCommunication::latest('id')->firstOrFail();
    }

    public function test_exact_reviewed_cumulative_message_is_sent_once_and_audited(): void
    {
        $a=$this->policy(['number'=>'SA-001','ad_horas'=>true]); $b=$this->policy(['number'=>'SA-002']);
        $transport=$this->mock(AgencyEmailTransport::class);
        $transport->shouldReceive('send')->once()->withArgs(function($payload,$config) use($a,$b) {
            $this->assertSame('agenzia@example.test',$payload['recipient']['address']);
            $this->assertSame([$a->id,$b->id],array_column($payload['policies'],'id'));
            $this->assertStringContainsString('Bonifico a Compagnia',$payload['body']);
            $this->assertTrue($config['require_tls']); $this->assertTrue($config['verify_peer']);
            return true;
        })->andReturn('accepted@example.test');
        $review=$this->review([$a,$b]);
        $this->assertSame('review',$review->status);
        $this->delete(route('clients.policies.destroy',[$this->client,$a]))->assertSessionHasErrors('policy');
        $this->get(route('agency-communications.show',$review))->assertOk()->assertSee('SA-001')->assertSee('SA-002')->assertSee('Conferma e invia email');
        $this->post(route('agency-communications.send',$review))->assertSessionHasErrors('reviewed');
        $this->post(route('agency-communications.send',$review),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->post(route('agency-communications.send',$review),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->assertSame('sent',$review->fresh()->status);
        $this->assertSame('accepted@example.test',$review->fresh()->message_id);
        $this->assertDatabaseHas('audit_logs',['action'=>'agency_email_sent','auditable_id'=>$this->agency->id,'user_id'=>$this->admin->id]);
        $this->assertSame(2,DB::table('agency_communication_policies')->whereNotNull('delivery_key')->count());
        $this->assertStringNotContainsString('Alessandro',DB::table('agency_communications')->value('payload'));
        $this->get(route('agency-communications.history'))->assertOk()->assertSee('Inviata al server di posta');
    }

    public function test_changed_policy_client_recipient_or_sender_requires_a_new_review(): void
    {
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
        $policy=$this->policy(); $review=$this->review([$policy]);
        $policy->update(['payment_method'=>'Contanti a Compagnia']);
        $this->post(route('agency-communications.send',$review),['reviewed'=>1])->assertSessionHasErrors('email');
        $review=$this->review([$policy]); $this->client->update(['name'=>'Giulia Montelari']);
        $this->post(route('agency-communications.send',$review),['reviewed'=>1])->assertSessionHasErrors('email');
        $review=$this->review([$policy]); $this->agency->update(['email'=>'changed@example.test']);
        $this->post(route('agency-communications.send',$review),['reviewed'=>1])->assertSessionHasErrors('email');
        $review=$this->review([$policy]); OutboundMailSetting::first()->update(['credentials'=>array_replace($this->smtp,['from_address'=>'other@example.test'])]);
        $this->post(route('agency-communications.send',$review),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->assertSame(0,DB::table('agency_communication_policies')->whereNotNull('delivery_key')->count());
    }

    public function test_overlap_of_two_different_reviews_does_not_repeat_a_policy(): void
    {
        $a=$this->policy();$b=$this->policy();$first=$this->review([$a]);$second=$this->review([$a,$b]);
        $this->mock(AgencyEmailTransport::class)->shouldReceive('send')->once()->andReturn('accepted@example.test');
        $this->post(route('agency-communications.send',$first),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->post(route('agency-communications.send',$second),['reviewed'=>1])->assertSessionHasErrors('policies');
        $this->assertSame('review',$second->fresh()->status);
        $this->assertSame(1,DB::table('agency_communication_policies')->whereNotNull('delivery_key')->count());
    }

    public function test_uncertain_delivery_never_retries_until_an_operator_confirms_non_delivery(): void
    {
        $a=$this->policy();$first=$this->review([$a]);$second=$this->review([$a]);
        $this->mock(AgencyEmailTransport::class)->shouldReceive('send')->once()->andThrow(new \RuntimeException('server diagnostic testing-only-secret'));
        $this->post(route('agency-communications.send',$first),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->assertSame('uncertain',$first->fresh()->status);
        $this->post(route('agency-communications.send',$second),['reviewed'=>1])->assertSessionHasErrors('policies');
        $this->post(route('agency-communications.resolve',$first),['outcome'=>'not_sent','note'=>'Verifica con il servizio di posta'])->assertSessionHasErrors('checked');
        $this->post(route('agency-communications.resolve',$first),['outcome'=>'not_sent','note'=>'Verifica con il servizio di posta','checked'=>1])->assertSessionHasNoErrors();
        $this->assertSame(0,DB::table('agency_communication_policies')->whereNotNull('delivery_key')->count());
        $this->assertSame('failed',$first->fresh()->status);
        $this->assertStringNotContainsString('testing-only-secret',DB::table('audit_logs')->get()->toJson());
        $this->get(route('agency-communications.show',$first))->assertOk()->assertDontSee('server diagnostic');
    }

    public function test_expired_review_inactive_agency_foreign_policy_and_different_operator_are_blocked(): void
    {
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
        $a=$this->policy();$review=$this->review([$a]);
        $this->travel(31)->minutes();
        $this->post(route('agency-communications.send',$review),['reviewed'=>1])->assertSessionHasErrors('email');
        $review=$this->review([$a]); $this->agency->update(['status'=>'inactive']);
        $this->post(route('agency-communications.send',$review),['reviewed'=>1])->assertSessionHasErrors('agency');
        $this->agency->update(['status'=>'active']);
        $b=$this->policy(['supplier_agency_id'=>null]);
        $this->post(route('agency-communications.review',$this->agency),['policies'=>[$a->id,$b->id]])->assertSessionHasErrors('policies');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']));
        $this->post(route('agency-communications.send',$review),['reviewed'=>1])->assertForbidden();
    }

    public function test_employee_cannot_read_or_change_mail_settings_or_send(): void
    {
        $review=$this->review([$this->policy()]);
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']));
        foreach (['email-settings.edit','agency-communications.index','agency-communications.history'] as $route) $this->get(route($route))->assertForbidden();
        $this->get(route('agency-communications.show',$review))->assertForbidden();
        $this->patch(route('email-settings.update'),[])->assertForbidden();
        $this->post(route('agency-communications.send',$review),['reviewed'=>1])->assertForbidden();
    }

    public function test_smtp_password_stays_encrypted_and_is_never_rendered_or_flashed(): void
    {
        $this->get(route('email-settings.edit'))->assertOk()->assertDontSee($this->smtp['password']);
        $data=$this->smtp; unset($data['password']); $data['smtp_password']='  another-testing-secret  ';
        $this->patch(route('email-settings.update'),$data)->assertSessionHasNoErrors();
        $this->assertSame($data['smtp_password'],app(AgencyMailConfiguration::class)->values()['password']);
        $this->assertStringNotContainsString('another-testing-secret',DB::table('outbound_mail_settings')->value('credentials'));
        $this->assertArrayNotHasKey('credentials',OutboundMailSetting::first()->toArray());
        $this->from(route('email-settings.edit'))->patch(route('email-settings.update'),array_replace($data,['host'=>'bad/host']))->assertSessionHasErrors('host')->assertSessionMissing('_old_input.smtp_password');
        $data['smtp_password']='';$this->patch(route('email-settings.update'),$data)->assertSessionHasNoErrors();
        $this->assertSame('  another-testing-secret  ',app(AgencyMailConfiguration::class)->values()['password']);
    }

    public function test_disabled_configuration_cannot_pretend_to_send_using_log_mailer(): void
    {
        config(['mail.default'=>'log']); OutboundMailSetting::first()->update(['credentials'=>array_replace($this->smtp,['enabled'=>false])]);
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
        $review=$this->review([$this->policy()]);
        $this->post(route('agency-communications.send',$review),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->assertSame('review',$review->fresh()->status);
    }

    public function test_deactivated_existing_session_cannot_send_or_read_reserved_pages(): void
    {
        $review=$this->review([$this->policy()]);
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
        $this->admin->update(['status'=>'inactive']);
        $this->postJson(route('agency-communications.send',$review),['reviewed'=>1])->assertForbidden();
        $this->assertGuest();
        $this->assertSame('review',$review->fresh()->status);
    }

    public function test_real_mail_builder_preserves_only_the_reviewed_recipient_and_body(): void
    {
        $review=$this->review([$this->policy()]);
        $captured=null;
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSent::class,function($event) use (&$captured) { $captured=$event->message; });
        $id=app(AgencyEmailTransport::class)->send($review->payload,['transport'=>'array']);
        $this->assertNotEmpty($id);
        $this->assertSame('agenzia@example.test',$captured->getTo()[0]->getAddress());
        $this->assertCount(1,$captured->getTo()); $this->assertSame([],$captured->getCc()); $this->assertSame([],$captured->getBcc());
        $this->assertSame($review->payload['body'],$captured->getTextBody());
    }

    public function test_notifications_cannot_redirect_outside_the_gestionale(): void
    {
        foreach (['https://outside.example/phishing','//outside.example','/\\outside.example','/%2foutside.example', ['unexpected']] as $url) {
            $this->assertSame('/dashboard',\App\Support\NotificationDestination::local($url));
        }
        $this->assertSame('/clients/1?tab=documents',\App\Support\NotificationDestination::local('/clients/1?tab=documents'));
        $note=$this->admin->notifications()->create(['id'=>(string)\Illuminate\Support\Str::uuid(),'type'=>'test','data'=>['url'=>'https://outside.example']]);
        $this->post(route('notifications.read',$note->id))->assertRedirect('/dashboard');
    }
}
