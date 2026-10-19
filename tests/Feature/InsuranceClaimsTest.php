<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Attachment, Client, InsuranceBranch, InsuranceClaim, InsuranceClaimEvent, InsurancePolicy, SupplierAgency, SupplierCompany, User};
use App\Services\{ClaimReminders, InsuranceClaims};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class InsuranceClaimsTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $operator;
    private Client $client;
    private InsurancePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp(); $this->travelTo(now()->setDate(2026,10,18)->setTime(12,0)); Storage::fake('attachments');
        $this->admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active','name'=>'Luca Moretti']);
        $this->client=Client::factory()->create(['client_type'=>'person','name'=>'Paolo Ferri','commercial_user_id'=>$this->operator->id]);
        $company=SupplierCompany::create(['name'=>'Nobis','status'=>'active']); $agency=SupplierAgency::create(['name'=>'Agenzia Valdoro','status'=>'active']); $agency->companies()->attach($company);
        $branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $sub=$branch->subbranches()->create(['name'=>'Autovetture','name_key'=>hash('sha256','AUTO'),'status'=>'active']);
        $vehicle=$this->client->vehicles()->create(['plate'=>'AB123CD']);
        $this->policy=$this->client->policies()->create(['number'=>'SA-2026-501','company'=>'Nobis','supplier_company_id'=>$company->id,'supplier_agency_id'=>$agency->id,'insurance_branch_id'=>$branch->id,'insurance_subbranch_id'=>$sub->id,'category'=>'RC auto','subcategory'=>'Autovetture','vehicle_id'=>$vehicle->id,'starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','status'=>'active','premium'=>'1200.00']);
        $this->actingAs($this->admin);
    }

    private function data(array $extra=[]): array
    {
        return array_replace(['number'=>'SN-2026-15','event_on'=>'2026-10-15','reported_on'=>'2026-10-16','description'=>'Danno al veicolo in sosta.','assigned_to'=>$this->operator->id,'policy_id'=>$this->policy->id,'context_hash'=>app(InsuranceClaims::class)->context($this->policy->fresh()),'request_key'=>(string)Str::uuid(),'activity_label'=>'Richiedere il verbale','activity_due_on'=>'2026-10-21'],$extra);
    }

    private function claim(array $extra=[]): InsuranceClaim
    {
        $this->post(route('claims.store'),$this->data($extra))->assertSessionHasNoErrors()->assertRedirect();
        return InsuranceClaim::latest('id')->firstOrFail();
    }

    private function change(InsuranceClaim $claim,string $action,array $extra=[])
    {
        return $this->post(route('claims.change',[$claim,$action]),array_replace(['version'=>$claim->fresh()->version,'request_key'=>(string)Str::uuid()],$extra));
    }

    private function pdf(string $name='verbale.pdf'): UploadedFile { return UploadedFile::fake()->create($name,20,'application/pdf'); }

    public function test_creation_links_files_and_history_without_changing_policy_or_ledger(): void
    {
        $policyBefore=$this->policy->fresh()->getRawOriginal();
        $claim=$this->claim(['files'=>[$this->pdf(),$this->pdf('constatazione.pdf')]]);
        $this->assertSame($this->client->id,$claim->client_id); $this->assertSame('open',$claim->status); $this->assertSame(1,$claim->version);
        $this->assertCount(2,$claim->files); $this->assertCount(1,$claim->events); $this->assertSame('2026-10-21',$claim->nextActivity->due_on->toDateString());
        $this->assertSame('Agenzia Valdoro',$claim->policy_snapshot['agency']);
        foreach($claim->files as $file) { $this->assertSame($claim->client_id,$file->attachable_id);$this->assertSame($this->policy->id,$file->insurance_policy_id);$this->get($file->downloadUrl())->assertOk()->assertHeader('X-Content-Type-Options','nosniff'); }
        $this->assertStringNotContainsString('Danno al veicolo',DB::table('insurance_claims')->value('description'));
        $this->assertStringNotContainsString('Danno al veicolo',DB::table('insurance_claim_events')->value('details'));
        $this->assertStringNotContainsString('Richiedere',DB::table('insurance_claim_activities')->value('label'));
        $this->assertStringNotContainsString('Danno al veicolo',DB::table('audit_logs')->get()->toJson());
        $this->assertSame($policyBefore,$this->policy->fresh()->getRawOriginal());$this->assertDatabaseCount('insurance_collections',0);$this->assertDatabaseCount('insurance_titles',0);
        $this->get(route('clients.show',[$this->client,'tab'=>'documents']))->assertOk()->assertSee('Sinistro #'.$claim->id)->assertSee('Conservato nel fascicolo sinistro');
        $this->get(route('clients.policies.show',[$this->client,$this->policy]))->assertOk()->assertSee('Consulta sinistri');
    }

    public function test_all_operators_access_claims_but_inactive_accounts_are_blocked_on_every_endpoint(): void
    {
        $claim=$this->claim(['files'=>[$this->pdf()]]);
        $activity=$claim->activities()->first();
        $operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($operator)->get(route('claims.show',$claim))->assertOk()->assertSee('Modifica dati');
        $this->get(route('claims.create'))->assertOk();
        $this->get(route('claims.activity',[$claim,$activity]))->assertOk();
        $this->get(route('claims.index'))->assertOk()->assertSee('SN-2026-15');
        $this->get(route('claims.edit',$claim))->assertOk();
        $this->get(route('claims.create',['policy'=>$this->policy->id]))->assertOk();
        $this->get($claim->files->first()->downloadUrl())->assertOk();
        $this->change($claim,'note',['note'=>'Documento ricevuto'])->assertSessionHasNoErrors();
        $operator->update(['status'=>'inactive']);
        foreach (['claims.show','claims.edit'] as $route) $this->actingAs($operator)->getJson(route($route,$claim))->assertForbidden();
        $this->actingAs($operator)->getJson($claim->files->first()->downloadUrl())->assertForbidden();
        foreach (['updated','status','note','activity_added','activity_completed','activity_rescheduled','activity_cancelled'] as $action) {
            $this->actingAs($operator)->postJson(route('claims.change',[$claim,$action]),[])->assertForbidden();
        }
    }

    public function test_invalid_dates_assignment_and_uploads_are_rejected_atomically(): void
    {
        foreach([['event_on'=>'2026-10-19'],['reported_on'=>'2026-10-14'],['reported_on'=>'2026-10-19'],['activity_label'=>'Azione','activity_due_on'=>null],['activity_label'=>null,'activity_due_on'=>'2026-10-21'],['assigned_to'=>999999],['description'=>['array']],['files'=>[$this->pdf(),UploadedFile::fake()->create('script.svg',1,'image/svg+xml')]]] as $extra) {
            $this->post(route('claims.store'),$this->data($extra))->assertSessionHasErrors();
            $this->assertDatabaseCount('insurance_claims',0);$this->assertCount(0,Storage::disk('attachments')->allFiles());
        }
        $this->post(route('claims.store'),$this->data(['files'=>array_map(fn($i)=>$this->pdf('file'.$i.'.pdf'),range(1,11))]))->assertSessionHasErrors('files');
        $this->post(route('claims.store'),$this->data(['files'=>[UploadedFile::fake()->create('large.pdf',11000,'application/pdf')]]))->assertSessionHasErrors('files.0');
        $large=array_map(fn($i)=>UploadedFile::fake()->create('batch'.$i.'.pdf',9000,'application/pdf'),range(1,6));
        $this->post(route('claims.store'),$this->data(['files'=>$large]))->assertSessionHasErrors('files');$this->assertDatabaseCount('insurance_claim_events',0);
        $reader=User::factory()->create(['role'=>UserRole::Operator,'status'=>'inactive']);$this->post(route('claims.store'),$this->data(['assigned_to'=>$reader->id]))->assertSessionHasErrors('assigned_to');
    }

    public function test_double_submit_duplicate_number_and_changed_policy_context_do_not_create_duplicates(): void
    {
        $data=$this->data();$this->post(route('claims.store'),$data)->assertSessionHasNoErrors();$this->post(route('claims.store'),$data)->assertSessionHasErrors('claim');
        $this->post(route('claims.store'),$this->data(['number'=>' sn-2026-15 ']))->assertSessionHasErrors('number');
        $stale=$this->data(['number'=>'SN-2026-16']);$this->policy->update(['number'=>'SA-2026-502']);
        $this->post(route('claims.store'),$stale)->assertSessionHasErrors('claim');$this->assertDatabaseCount('insurance_claims',1);
        $this->claim(['number'=>null]);$this->claim(['number'=>null]);$this->assertDatabaseCount('insurance_claims',3);
    }

    public function test_history_keeps_old_values_and_snapshot_with_optimistic_concurrency(): void
    {
        $claim=$this->claim();$key=(string)Str::uuid();$firstVersion=$claim->version;
        $this->change($claim,'updated',$this->data(['description'=>'Descrizione corretta','reason'=>'Correzione del luogo','request_key'=>$key]))->assertSessionHasNoErrors();
        $event=$claim->events()->latest('id')->first();$this->assertSame('Danno al veicolo in sosta.',$event->details['prima']['descrizione']);$this->assertSame('Descrizione corretta',$event->details['dopo']['descrizione']);
        $this->change($claim,'note',['note'=>'Nota vecchia','version'=>$firstVersion])->assertSessionHasErrors('claim');
        $this->change($claim,'note',['note'=>'Doppione','request_key'=>$key])->assertSessionHasErrors('claim');
        $this->policy->update(['number'=>'Numero corretto']);$this->assertSame('SA-2026-501',$claim->fresh()->policy_snapshot['number']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);$event->update(['details'=>['nota'=>'riscritta']]);
    }

    public function test_operator_can_manage_claim_on_a_closed_policy_but_only_admin_can_cancel_or_reopen_claim(): void
    {
        $this->policy->forceFill(['work_closed_at'=>today()->subDay()])->save();$this->actingAs($this->operator);$claim=$this->claim();
        foreach(['waiting_documents','in_management','waiting_settlement','closed_paid'] as $status) $this->change($claim,'status',['status'=>$status,'reason'=>'Esito comunicato dalla compagnia'])->assertSessionHasNoErrors();
        $this->assertTrue($claim->fresh()->isClosed());$this->assertSame('cancelled',$claim->activities()->first()->status);
        $this->change($claim,'note',['note'=>'modifica'])->assertForbidden();$this->change($claim,'status',['status'=>'open','reason'=>'riapertura'])->assertForbidden();
        $this->actingAs($this->admin);$this->change($claim,'status',['status'=>'open','reason'=>'Documentazione integrativa'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled',$claim->activities()->first()->status);$this->assertNull($claim->fresh()->closed_at);
        $this->actingAs($this->operator);$this->change($claim,'status',['status'=>'cancelled','reason'=>'Inserimento errato'])->assertForbidden();
        $this->actingAs($this->admin);$this->change($claim,'status',['status'=>'cancelled','reason'=>'Inserimento errato'])->assertSessionHasNoErrors();
        $this->assertSame('active',$this->policy->fresh()->status);$this->assertDatabaseCount('insurance_collections',0);
    }

    public function test_reminders_are_sent_three_days_before_on_due_date_and_stay_overdue_without_duplicates(): void
    {
        $claim=$this->claim(['activity_due_on'=>'2026-10-22']);$reminders=app(ClaimReminders::class);
        $this->assertSame(0,$reminders->forUser($this->operator));$this->travelTo(now()->addDay());
        $this->assertSame(1,$reminders->forUser($this->operator));$this->assertSame(0,$reminders->forUser($this->operator));$this->assertSame(1,$reminders->forUser($this->admin));
        $this->change($claim,'note',['note'=>'Nota senza modificare l’attività'])->assertSessionHasNoErrors();$this->assertSame(1,$this->operator->visibleNotifications()->count());
        $this->travelTo(now()->addDays(3));$this->assertSame(1,$reminders->forUser($this->operator));$this->assertSame(0,$reminders->forUser($this->operator));
        $this->travelTo(now()->addDay());$this->assertSame(0,$reminders->forUser($this->operator));
        $this->get(route('claims.index',['reminder'=>'overdue']))->assertOk()->assertSee('SN-2026-15')->assertSee('In arretrato');
        $this->get(route('dashboard'))->assertOk()->assertSee('Richiedere il verbale')->assertSee('In arretrato');
        $activity=$claim->activities()->first();$this->change($claim,'activity_completed',['activity_id'=>$activity->id,'reason'=>'Verbale ricevuto'])->assertSessionHasNoErrors();
        $this->assertSame(0,$this->operator->visibleNotifications()->count());$this->get(route('claims.index',['reminder'=>'overdue']))->assertDontSee('SN-2026-15');
        $this->assertSame('completed',$activity->fresh()->status);
    }

    public function test_reschedule_clears_old_alert_and_generates_new_one_with_history(): void
    {
        $claim=$this->claim();$activity=$claim->activities()->first();$reminders=app(ClaimReminders::class);
        $this->assertSame(1,$reminders->forUser($this->operator));
        $this->change($claim,'activity_rescheduled',['activity_id'=>$activity->id,'due_on'=>'2026-11-01','reason'=>'In attesa del perito'])->assertSessionHasNoErrors();
        $this->assertSame(0,$this->operator->visibleNotifications()->count());$this->assertSame(0,$reminders->forUser($this->operator));$this->assertSame(2,$activity->fresh()->revision);
        $event=$claim->events()->latest('id')->first();$this->assertSame('21/10/2026',$event->details['data precedente']);
        $this->travelTo(now()->setDate(2026,10,29));$this->assertSame(1,$reminders->forUser($this->operator));
        $this->change($claim,'activity_cancelled',['activity_id'=>$activity->id,'reason'=>'Non più necessaria'])->assertSessionHasNoErrors();$this->assertSame(0,$this->operator->visibleNotifications()->count());
        $this->change($claim,'activity_completed',['activity_id'=>$activity->id,'reason'=>'doppia operazione'])->assertSessionHasErrors('claim');
    }

    public function test_reminders_follow_assignment_scope_revocation_and_closure(): void
    {
        $claim=$this->claim();$reminders=app(ClaimReminders::class);$reminders->forUser($this->operator);
        $other=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);$reader=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->assertSame(0,$reminders->forUser($other));$this->assertSame(0,$reminders->forUser($reader));
        $this->change($claim,'updated',$this->data(['assigned_to'=>$other->id,'reason'=>'Riassegnazione']))->assertSessionHasNoErrors();
        $this->assertSame(0,$this->operator->visibleNotifications()->count());$this->assertSame(1,$reminders->forUser($other));
        $this->change($claim,'status',['status'=>'closed_unpaid','reason'=>'Chiusura comunicata'])->assertSessionHasNoErrors();$this->assertSame(0,$other->visibleNotifications()->count());
        $another=$this->claim(['number'=>'SN-2026-20']);$reminders->forUser($this->operator);$this->client->update(['commercial_user_id'=>null]);
        $this->assertSame(1,$this->operator->visibleNotifications()->count());$this->assertSame(0,$reminders->forUser($this->operator));
    }

    public function test_filters_scope_exports_search_dates_and_pagination_work_together(): void
    {
        $claim=$this->claim();$this->claim(['number'=>'SN-2026-16','event_on'=>'2026-10-12','reported_on'=>null,'activity_label'=>null,'activity_due_on'=>null]);
        $filters=['q'=>'SN-2026-15','agency'=>$this->policy->supplier_agency_id,'company'=>$this->policy->supplier_company_id,'branch'=>$this->policy->insurance_branch_id,'subbranch'=>$this->policy->insurance_subbranch_id,'operator'=>$this->operator->id,'from'=>'2026-10-14','to'=>'2026-10-18','status'=>'open_only'];
        $this->get(route('claims.index',$filters))->assertOk()->assertSee('SN-2026-15')->assertDontSee('SN-2026-16');
        $this->get(route('claims.index',['to'=>'2026-10-13']))->assertOk()->assertDontSee('SN-2026-15')->assertSee('SN-2026-16');
        $this->get(route('claims.index',['date_basis'=>'activity','from'=>'2026-10-20','to'=>'2026-10-22']))->assertOk()->assertSee('SN-2026-15')->assertDontSee('SN-2026-16');
        $this->get(route('claims.index',['reminder'=>'overdue']))->assertDontSee('SN-2026-15');
        $this->get(route('claims.create',['q'=>'AB123CD']))->assertOk()->assertSee('SA-2026-501');
        $this->get(route('claims.index',['status'=>['invalid']]))->assertSessionHasErrors();
        for($i=0;$i<21;$i++) $this->claim(['number'=>'SN-BATCH-'.$i,'activity_label'=>null,'activity_due_on'=>null]);
        $this->get(route('claims.index'))->assertViewHas('claims',fn($p)=>$p->count()===20);
        $this->get(route('claims.index',['_table_all'=>1]),['X-Table-Export'=>'1'])->assertViewHas('claims',fn($p)=>$p->count()===23);
        $this->get(route('audit-logs.index',['auditable_type'=>'claims','action'=>'claims']))->assertOk()->assertSee('Apertura sinistro');
    }

    public function test_claim_files_cannot_be_deleted_replaced_or_reclassified_from_generic_document_routes(): void
    {
        $claim=$this->claim(['files'=>[$this->pdf()]]);$file=$claim->files->first();
        $this->delete(route('attachments.destroy',$file))->assertSessionHasErrors('document');Storage::disk('attachments')->assertExists($file->path);
        $this->patch(route('clients.documents.update',[$this->client,$file]),['document_category'=>'other','description'=>'Riscrittura'])->assertSessionHasErrors('document');
        $this->post(route('clients.documents.store',$this->client),['document_category'=>'claim','previous_version_id'=>$file->id,'files'=>[$this->pdf('nuova.pdf')]])->assertSessionHasErrors('previous_version_id');
        $this->delete(route('clients.policies.destroy',[$this->client,$this->policy]))->assertSessionHasErrors('policy');
        $this->delete(route('clients.destroy',$this->client))->assertSessionHasErrors('client');
        $this->assertDatabaseHas('insurance_claims',['id'=>$claim->id]);$this->assertDatabaseCount('attachments',1);
    }

    public function test_activity_ids_cannot_be_moved_between_claims_and_closed_tasks_are_not_overwritten(): void
    {
        $a=$this->claim();$b=$this->claim(['number'=>'SN-2026-99']);$activity=$b->activities()->first();
        $this->get(route('claims.activity',[$a,$activity]))->assertNotFound();
        $this->change($a,'activity_completed',['activity_id'=>$activity->id,'reason'=>'Sbagliata'])->assertNotFound();
        $this->post(route('claims.activity.update',[$a,$activity]),['operation'=>'activity_completed'])->assertNotFound();
        $this->assertSame('pending',$activity->fresh()->status);
        $this->get(route('claims.activity',[$b,$activity]))->assertOk();
    }

    public function test_missing_policy_number_out_of_period_event_and_closed_work_do_not_invent_coverage_decisions(): void
    {
        $claim=$this->claim(['number'=>null,'event_on'=>'2025-10-01','reported_on'=>null]);
        $this->get(route('claims.show',$claim))->assertOk()->assertSee('fuori dal periodo')->assertSee('Pratica #'.$claim->id);
        $this->assertSame('open',$claim->status);$this->assertSame('active',$this->policy->fresh()->status);
        $this->get(route('claims.edit',$claim))->assertOk();$this->get(route('claims.create',['policy'=>$this->policy->id]))->assertOk();
    }

    public function test_client_merge_moves_claim_owner_and_documents_but_preserves_original_snapshot(): void
    {
        $claim=$this->claim(['files'=>[$this->pdf()]]);$target=Client::factory()->create(['client_type'=>'person','name'=>'Paolo Ferri corrente','commercial_user_id'=>$this->operator->id]);
        $action=app(\App\Domain\Core\Actions\MergeClientsAction::class);$preview=$action->preview($target->fresh(),$this->client->fresh());
        $choices=array_fill_keys(array_keys($preview['conflicts']),'target');$action->execute($target,$this->client,$preview['token'],$choices);
        $this->assertSame($target->id,$claim->fresh()->client_id);$this->assertSame($target->id,$claim->files()->first()->attachable_id);$this->assertSame('Paolo Ferri',$claim->fresh()->policy_snapshot['client']);
        $this->actingAs($this->operator)->get(route('claims.show',$claim))->assertOk()->assertSee('Paolo Ferri corrente');
    }

    public function test_html_is_escaped_in_notes_and_history_and_reset_preserves_claims(): void
    {
        $claim=$this->claim(['description'=>'<script>alert(1)</script>']);
        $this->change($claim,'note',['note'=>'<img src=x onerror=alert(2)>'])->assertSessionHasNoErrors();
        $this->get(route('claims.show',$claim))->assertOk()->assertDontSee('<script>alert(1)</script>',false)->assertSee('&lt;script&gt;',false)->assertDontSee('<img src=x onerror',false);
        $this->expectException(\RuntimeException::class);$this->expectExceptionMessage('sinistri');app(\App\Services\InsuranceDemoReset::class)->apply($this->admin);
    }
}
