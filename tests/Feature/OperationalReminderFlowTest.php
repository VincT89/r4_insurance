<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Notifications\NotificationDropdown;
use App\Models\{AgencyCommunication, Client, InsuranceCollection, InsurancePolicy, InsuranceTitle, OutboundMailSetting, SupplierAgency, User};
use App\Services\{AgencyEmailTransport, CollectionCommunicationReminders, DeadlineReminders, InsuranceLedger};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalReminderFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private SupplierAgency $agency;
    private InsurancePolicy $policy;
    private InsuranceTitle $title;

    public function test_table_export_keeps_authorization_and_does_not_regenerate_the_notification_menu(): void
    {
        $this->get(route('policies.index',['_table_all'=>1]),['X-Table-Export'=>'1'])->assertOk()->assertSee($this->policy->number);
        $this->assertSame(0,DB::table('insurance_deadline_reminders')->count());
        $this->assertSame(0,$this->admin->notifications()->count());
        $this->get(route('policies.index'))->assertOk()->assertSee('Caricamento notifiche');
        $this->assertSame(0,DB::table('insurance_deadline_reminders')->count());
        Livewire::test(NotificationDropdown::class)->assertViewHas('unreadNotificationsCount', 1);
        $this->assertSame(1,DB::table('insurance_deadline_reminders')->count());
    }

    public function test_backlog_crosses_batch_boundary_without_losing_or_duplicating_reminders(): void
    {
        $rows=[];
        for ($i=0;$i<260;$i++) $rows[]=[
            'client_id'=>$this->policy->client_id,'number'=>'ARCHIVE-'.$i,'category'=>'RC auto','company'=>'Compagnia Alba',
            'starts_on'=>today()->subYear()->toDateString(),'expires_on'=>today()->addDays(14)->toDateString(),'status'=>'active',
        ];
        DB::table('insurance_policies')->insert($rows);
        $reminders=app(DeadlineReminders::class);
        $this->assertSame(261,$reminders->forUser($this->admin));
        $this->assertSame(0,$reminders->forUser($this->admin));
        $this->assertSame(261,$this->admin->notifications()->where('type',DeadlineReminders::TYPE)->count());
        $this->assertSame(261,DB::table('insurance_deadline_reminders')->where('user_id',$this->admin->id)->count());
    }

    public function test_existing_reminders_are_linked_without_resetting_read_history(): void
    {
        app(DeadlineReminders::class)->forUser($this->admin);
        $notification=$this->admin->notifications()->sole();
        $notification->markAsRead();
        $migration=require database_path('migrations/2026_10_20_150000_index_deadline_notification_links.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('insurance_deadline_reminders',['user_id'=>$this->admin->id,'notification_id'=>$notification->id]);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(1,$this->admin->visibleNotifications()->count());
        $this->assertSame(0,app(DeadlineReminders::class)->forUser($this->admin));
    }

    public function test_failed_notification_insert_does_not_leave_a_marker_that_suppresses_the_reminder(): void
    {
        $fail=true;
        DB::listen(function($event) use(&$fail) {
            if ($fail && str_starts_with($event->sql,'insert into "notifications"')) throw new \RuntimeException('Simulated storage failure');
        });
        try { app(DeadlineReminders::class)->forUser($this->admin); $this->fail('Expected simulated failure.'); }
        catch (\RuntimeException $exception) { $this->assertSame('Simulated storage failure',$exception->getMessage()); }
        $fail=false;
        $this->assertSame(0,DB::table('insurance_deadline_reminders')->count());
        $this->assertSame(0,$this->admin->notifications()->count());
        $this->assertSame(1,app(DeadlineReminders::class)->forUser($this->admin));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,19)->setTime(12,0));
        $this->admin = User::factory()->create(['role'=>UserRole::Admin, 'status'=>'active']);
        $this->operator = User::factory()->create(['role'=>UserRole::Operator, 'status'=>'active']);
        $this->actingAs($this->admin);
        $this->agency = SupplierAgency::create(['name'=>'Agenzia Valserena', 'email'=>'valserena@example.test', 'status'=>'active']);
        $client = Client::factory()->create(['name'=>'Elena Varesi']);
        $this->policy = $client->policies()->create(['number'=>'SA-2026-0186', 'category'=>'RC auto', 'company'=>'Compagnia Alba',
            'supplier_agency_id'=>$this->agency->id, 'starts_on'=>'2026-10-01', 'expires_on'=>'2027-10-01', 'status'=>'active',
            'payment_frequency'=>'Semestrale', 'payment_method'=>'Bonifico a Intermediario', 'premium'=>'200.00']);
        $this->title = InsuranceTitle::create(['insurance_policy_id'=>$this->policy->id, 'kind'=>'emission', 'due_on'=>today()->addDays(15),
            'coverage_from'=>'2026-10-01', 'coverage_to'=>'2027-04-01', 'amount_cents'=>10000, 'created_by'=>$this->admin->id,
            'payment_frequency'=>'Semestrale', 'payment_method'=>'Bonifico a Intermediario']);
        OutboundMailSetting::create(['credentials'=>['enabled'=>true, 'host'=>'smtp.example.test', 'port'=>587, 'encryption'=>'starttls',
            'username'=>'sender@example.test', 'password'=>Str::random(32), 'from_address'=>'sender@example.test', 'from_name'=>'Soluzioni Assicurative SAS'], 'updated_by'=>$this->admin->id]);
    }

    private function payment(int $cents, array $extra = []): InsuranceCollection
    {
        return app(InsuranceLedger::class)->collect($this->title, array_replace(['received_on'=>today()->toDateString(), 'amount_cents'=>$cents,
            'payment_method'=>'Bonifico a Intermediario', 'recipient'=>'our_agency', 'document_type'=>'transfer', 'consultant_id'=>auth()->id(),
            'idempotency_key'=>(string)Str::uuid()], $extra));
    }

    private function review(array $ids): AgencyCommunication
    {
        $response = $this->post(route('agency-communications.collections-review', $this->agency), ['scope'=>'pending', 'collections'=>$ids]);
        $response->assertSessionHasNoErrors()->assertRedirect();
        return AgencyCommunication::where('status','review')->latest('created_at')->latest('id')->firstOrFail();
    }

    private function visible(string $type)
    {
        return $this->admin->visibleNotifications()->where('type', $type);
    }

    public function test_overdue_catchup_and_fifteen_day_boundary_do_not_recreate_history_or_paid_titles(): void
    {
        $past = $this->title->replicate(); $past->due_on = today()->subDays(40); $past->save();
        $future = $this->title->replicate(); $future->due_on = today()->addDays(16); $future->save();
        $paid = $this->title->replicate(); $paid->collected_cents = $paid->amount_cents; $paid->save();
        $cancelled = $this->title->replicate(); $cancelled->cancelled_at = now(); $cancelled->save();
        $reminders = app(DeadlineReminders::class);
        $this->assertSame(2, $reminders->forUser($this->admin));
        $this->assertSame(0, $reminders->forUser($this->admin));
        $this->assertEqualsCanonicalizing([$past->id,$this->title->id], $this->visible(DeadlineReminders::TYPE)->get()->map(fn($n)=>$n->data['source_id'])->all());
        $this->visible(DeadlineReminders::TYPE)->delete();
        $this->assertSame(0, $reminders->forUser($this->admin));
        $this->travel(1)->days();
        $this->assertSame(1, $reminders->forUser($this->admin));
        $this->assertSame($future->id, $this->visible(DeadlineReminders::TYPE)->sole()->data['source_id']);
    }

    public function test_pending_communications_remain_visible_among_many_new_deadline_notifications(): void
    {
        $this->payment(4000);
        app(CollectionCommunicationReminders::class)->forUser($this->admin);
        $this->travel(1)->minutes();
        for ($i=0;$i<12;$i++) { $title=$this->title->replicate(); $title->save(); }
        app(DeadlineReminders::class)->forUser($this->admin);
        $this->assertGreaterThan(10,$this->admin->visibleNotifications()->count());
        Livewire::test(NotificationDropdown::class)->assertSee('Incassi da comunicare')->assertSee('40,00 €');
    }

    public function test_menu_count_covers_more_than_ten_rows_and_updates_after_reading_and_payment(): void
    {
        for ($i=0; $i<14; $i++) { $title=$this->title->replicate(); $title->save(); }
        app(DeadlineReminders::class)->forUser($this->admin);
        $component=Livewire::test(NotificationDropdown::class)
            ->assertViewHas('unreadNotificationsCount', 15)
            ->assertViewHas('latestNotifications', fn ($rows) => $rows->count()===10);
        $component->call('markAllAsRead')->assertViewHas('unreadNotificationsCount', 0);
        $payment=$this->payment(10000);
        $component->call('$refresh')->assertViewHas('unreadNotificationsCount', 1)->assertSee('Incassi da comunicare');
        app(InsuranceLedger::class)->void($payment, 'Correzione della rata prima di comunicare');
        $component->call('$refresh')->assertViewHas('unreadNotificationsCount', 1)->assertSee('Residuo 100,00 €');
        $this->assertSame(0, $this->visible(CollectionCommunicationReminders::TYPE)->count());
        $this->actingAs($this->operator);
        Livewire::test(NotificationDropdown::class)->assertDontSee('Incassi da comunicare');
    }

    public function test_delivery_set_keeps_latest_state_and_current_agency_without_multiplying_payments(): void
    {
        $one=$this->payment(100); $two=$this->payment(100); $three=$this->payment(100);
        $other=SupplierAgency::create(['name'=>'Agenzia Fontana','email'=>'fontana@example.test','status'=>'active']);
        $claim=function ($payment, $agency, $state, $date, $claimed=true) {
            $message=AgencyCommunication::create(['supplier_agency_id'=>$agency->id,'user_id'=>$this->admin->id,'status'=>$state,
                'payload'=>[],'fingerprint'=>hash('sha256',Str::uuid()),'review_expires_at'=>now()->addMinutes(30),
                'created_at'=>$date,'sending_at'=>now()->subMinutes(10)]);
            DB::table('agency_communication_collections')->insert(['agency_communication_id'=>$message->id,
                'insurance_collection_id'=>$payment->id,'delivery_key'=>$claimed ? hash('sha256',Str::uuid()) : null]);
        };
        $claim($one,$this->agency,'sent',now()->subHour());
        $claim($one,$this->agency,'uncertain',now());
        $claim($two,$other,'sent',now());
        $claim($three,$this->agency,'review',now(),false);
        $status=app(\App\Services\CollectionCommunicationStatus::class);
        $rows=$status->withDeliveryState($status->pending($status->payments($this->admin)))->orderBy('insurance_collections.id')->get();
        $this->assertSame([$one->id,$two->id,$three->id],$rows->modelKeys());
        $this->assertSame(['uncertain',null,null],$rows->pluck('communication_state')->all());
        $this->assertSame([$one->id],$status->attention($status->payments($this->admin))->pluck('insurance_collections.id')->all());
        $claim($one,$this->agency,'sent',now()->subDay());
        $this->assertSame('uncertain',$status->withDeliveryState($status->payments($this->admin))->where('insurance_collections.id',$one->id)->sole()->communication_state);
        $this->policy->update(['supplier_agency_id'=>$other->id]);
        $this->assertEqualsCanonicalizing([$one->id,$three->id],$status->pending($status->payments($this->admin))->pluck('insurance_collections.id')->all());
    }

    public function test_partial_payment_full_payment_review_send_and_reversal_keep_each_alert_consistent(): void
    {
        app(DeadlineReminders::class)->forUser($this->admin);
        $original = $this->visible(DeadlineReminders::TYPE)->sole()->id;
        $this->actingAs($this->operator);
        $a = $this->payment(4000);
        $this->actingAs($this->admin);
        $followup = app(CollectionCommunicationReminders::class);
        $this->assertSame(1, $followup->forUser($this->admin));
        $this->assertSame(0, $followup->forUser($this->operator));
        Livewire::test(NotificationDropdown::class)->assertSee('Residuo 60,00 €')->assertSee('Incassi da comunicare');
        $group = $this->visible(CollectionCommunicationReminders::TYPE)->sole();
        $group->markAsRead();
        $followup->forUser($this->admin);
        $this->assertNotNull($group->fresh()->read_at);
        $b = $this->payment(6000, ['payment_method'=>'Bonifico a Compagnia', 'recipient'=>'company']);
        $followup->forUser($this->admin);
        $this->assertSame(0, $this->visible(DeadlineReminders::TYPE)->count());
        $this->assertNull($group->fresh()->read_at);
        $this->assertStringContainsString('100,00 €', $group->fresh()->data['message']);
        $message = $this->review([$a->id,$b->id]);
        $this->assertSame(1, $this->visible(CollectionCommunicationReminders::TYPE)->count());
        $this->mock(AgencyEmailTransport::class)->shouldReceive('send')->once()->withArgs(fn($payload)=>count($payload['collections'])===2 && str_contains($payload['body'],'Bonifico a Compagnia'))->andReturn('accepted@example.test');
        $this->post(route('agency-communications.send',$message),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->visible(CollectionCommunicationReminders::TYPE)->count());
        $this->get(route('agency-communications.collections',['scope'=>'pending']))->assertViewHas('payments',fn($rows)=>$rows->total()===0);
        app(InsuranceLedger::class)->void($a,'Pagamento attribuito alla rata errata');
        $this->assertSame($original, $this->visible(DeadlineReminders::TYPE)->sole()->id);
        $this->assertSame(4000, $this->title->fresh()->balance_cents);
        $this->assertSame(1, $this->visible(CollectionCommunicationReminders::REVERSAL_TYPE)->count());
        $this->get(route('agency-communications.show',$message))->assertOk()->assertSee('Incassi stornati dopo la preparazione');
        $this->assertSame('sent', $message->fresh()->status);
        $this->actingAs($this->operator);
        $this->assertSame(0,$this->operator->visibleNotifications()->whereIn('type',[CollectionCommunicationReminders::TYPE,CollectionCommunicationReminders::REVERSAL_TYPE])->count());
    }

    public function test_uncertain_send_remains_actionable_and_requires_resolution_before_another_review(): void
    {
        $payment=$this->payment(10000);
        $message=$this->review([$payment->id]);
        $transport=$this->mock(AgencyEmailTransport::class);
        $transport->shouldReceive('send')->once()->andThrow(new \RuntimeException('Connection interrupted'));
        $this->post(route('agency-communications.send',$message),['reviewed'=>1])->assertSessionHasNoErrors();
        app(CollectionCommunicationReminders::class)->forUser($this->admin);
        $this->assertSame('uncertain',$message->fresh()->status);
        $this->assertSame(1,$this->visible(CollectionCommunicationReminders::TYPE)->count());
        $this->get(route('agency-communications.collections',['scope'=>'attention']))->assertOk()->assertViewHas('payments',fn($rows)=>$rows->total()===1);
        $this->post(route('agency-communications.collections-review',$this->agency),['scope'=>'pending','collections'=>[$payment->id]])->assertSessionHasErrors('collections');
        $this->post(route('agency-communications.resolve',$message),['checked'=>1,'outcome'=>'not_sent','note'=>'Confermato mancato invio dal servizio di posta'])->assertSessionHasNoErrors();
        $this->get(route('agency-communications.collections',['scope'=>'attention']))->assertViewHas('payments',fn($rows)=>$rows->total()===0);
        $this->get(route('agency-communications.collections',['scope'=>'pending']))->assertViewHas('payments',fn($rows)=>$rows->total()===1);
        $retry=$this->review([$payment->id]);
        $transport->shouldReceive('send')->once()->andReturn('accepted@example.test');
        $this->post(route('agency-communications.send',$retry),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->assertSame(0,$this->visible(CollectionCommunicationReminders::TYPE)->count());
    }

    public function test_backdated_collections_remain_in_pending_pages_exports_and_selection(): void
    {
        $ids=[];
        for($i=0;$i<28;$i++)$ids[]=$this->payment(100,['received_on'=>'2025-01-02'])->id;
        $filters=['scope'=>'pending','agency'=>$this->agency->id,'search'=>$this->policy->number];
        $this->get(route('agency-communications.collections'))->assertViewHas('payments',fn($rows)=>$rows->total()===0);
        $this->get(route('agency-communications.collections',$filters))->assertOk()->assertViewHas('payments',fn($rows)=>$rows->total()===28 && $rows->count()===25);
        $this->get(route('agency-communications.collections',$filters+['page'=>2]))->assertViewHas('payments',fn($rows)=>$rows->pluck('id')->all()===array_slice($ids,25));
        $this->get(route('agency-communications.collections',$filters+['_table_all'=>1]),['X-Table-Export'=>'1'])->assertViewHas('payments',fn($rows)=>$rows->count()===28);
        $message=$this->review([$ids[0],$ids[27]]);
        $this->assertCount(2,$message->payload['collections']);
        $this->assertSame('02/01/2025 – 02/01/2025',$message->payload['range']);
        $this->post(route('agency-communications.collections-review',$this->agency),['collections'=>[$ids[0]]])->assertSessionHasErrors('collections');
    }

    public function test_missing_agency_and_inactive_accounts_never_hide_an_uncommunicated_payment(): void
    {
        $payment=$this->payment(100);
        DB::table('insurance_policies')->where('id',$this->policy->id)->update(['supplier_agency_id'=>null]);
        $reminders=app(CollectionCommunicationReminders::class);
        $this->assertSame(1,$reminders->forUser($this->admin));
        $notification=$this->visible(CollectionCommunicationReminders::TYPE)->sole();
        $this->assertStringContainsString('unassigned=1',$notification->data['url']);
        $this->get($notification->data['url'])->assertOk()->assertSee('Assegna l’agenzia')->assertViewHas('payments',fn($rows)=>$rows->total()===1);
        $this->admin->update(['status'=>'inactive']);
        $this->assertSame(0,$reminders->forUser($this->admin));
        $this->assertSame(0,$this->visible(CollectionCommunicationReminders::TYPE)->count());
    }

    public function test_contact_change_does_not_reoffer_a_confirmed_collection_to_the_same_agency(): void
    {
        $payment=$this->payment(10000);$message=$this->review([$payment->id]);
        $this->mock(AgencyEmailTransport::class)->shouldReceive('send')->once()->andReturn('accepted@example.test');
        $this->post(route('agency-communications.send',$message),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->agency->update(['email'=>'new-address@example.test']);
        $this->get(route('agency-communications.collections',['scope'=>'pending']))->assertViewHas('payments',fn($rows)=>$rows->total()===0);
        $this->get(route('agency-communications.collections'))->assertViewHas('deliveries',fn($rows)=>$rows->has($payment->id));
        $this->post(route('agency-communications.collections-review',$this->agency),['collections'=>[$payment->id]])->assertSessionHasErrors('collections');
    }

    public function test_scheduled_checks_record_success_and_never_send_an_email(): void
    {
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
        $this->title->update(['due_on'=>today()->subDays(20)]);
        $this->payment(100);
        $this->artisan('insurance:deadline-reminders')->assertSuccessful();
        $count=DB::table('notifications')->count();
        $this->artisan('insurance:deadline-reminders')->assertSuccessful();
        $this->assertSame($count,DB::table('notifications')->count());
        $this->assertDatabaseHas('system_heartbeats',['name'=>'insurance-deadline-reminders','last_seen_at'=>now()]);
        $this->assertSame(2,DB::table('system_command_runs')->where('command','insurance:deadline-reminders')->where('status','succeeded')->count());
        $this->get(route('deadlines.index',['period'=>'overdue','kind'=>'title','agency'=>$this->agency->id]))->assertOk()->assertSee('Ultimo controllo notifiche')->assertViewHas('deadlines',fn($rows)=>$rows->total()===1 && (int)$rows->first()->balance_cents===9900);
        $this->assertSame(0,AgencyCommunication::count());
    }

    public function test_document_alerts_identify_each_file_and_keep_read_history_when_the_deadline_passes(): void
    {
        foreach (['identita-fronte.pdf','identita-retro.pdf'] as $name) {
            $this->policy->client->attachments()->create(['type'=>'document','uploaded_by'=>$this->admin->id,'document_category'=>'identity','original_name'=>$name,
                'stored_name'=>$name,'disk'=>'attachments','directory'=>'reminders','path'=>'reminders/'.$name,
                'mime_type'=>'application/pdf','extension'=>'pdf','size'=>10,'expires_on'=>today()->addDays(15)]);
        }
        $this->artisan('insurance:deadline-reminders')->assertSuccessful();
        $documents=$this->admin->visibleNotifications()->where('data->kind','document')->get();
        $this->assertCount(2,$documents);
        $documents->first()->markAsRead();
        Livewire::test(NotificationDropdown::class)->assertSee('identita-fronte.pdf')->assertSee('identita-retro.pdf')->assertSee('Documento in scadenza');
        $this->travel(16)->days();
        $this->artisan('insurance:deadline-reminders')->assertSuccessful();
        Livewire::test(NotificationDropdown::class)->assertSee('Documento scaduto')->assertSee('identita-fronte.pdf')->assertSee('identita-retro.pdf')->assertSee('is-alert',false);
        $this->assertNotNull($documents->first()->fresh()->read_at);
        $this->assertSame(2,$this->admin->visibleNotifications()->where('data->kind','document')->count());
    }
}
