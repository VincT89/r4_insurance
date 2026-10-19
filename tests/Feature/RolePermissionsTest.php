<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Dashboard\UserDailyNotes;
use App\Livewire\Notifications\NotificationDropdown;
use App\Livewire\Shared\AttachmentManager;
use App\Models\{Attachment, AuditLog, Client, InsuranceBranch, InsuranceCollection, InsurancePolicy, SupplierAgency, SupplierCompany, User};
use App\Services\InsuranceLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private Client $client;
    private InsurancePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));
        Storage::fake('attachments');
        $this->admin = User::factory()->create(['role'=>UserRole::Admin]);
        $this->operator = User::factory()->create(['role'=>UserRole::Operator]);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['commercial_user_id'=>$this->admin->id]);
        $this->policy = $this->client->policies()->create(['category'=>'RC auto', 'company'=>'Compagnia di prova', 'number'=>'SA-2026-501', 'starts_on'=>'2026-10-19', 'expires_on'=>'2027-10-19', 'status'=>'active', 'premium'=>'1000.00', 'payment_frequency'=>'Semestrale', 'payment_method'=>'Bonifico a Intermediario']);
    }

    public function test_only_admin_and_operator_can_be_selected_and_old_roles_are_rejected(): void
    {
        $this->assertSame(['admin','operator'], array_column(UserRole::cases(),'value'));
        $this->get(route('users.create'))->assertOk()->assertSee('Operatore')->assertSee('Non può eliminare dati')->assertDontSee('value="developer"', false);
        foreach (['developer','commercial','marketing','operations_manager','administration','photographer','graphic_designer'] as $role) {
            $this->post(route('users.store'), ['name'=>'Utente prova', 'email'=>'utente@example.test', 'role'=>$role, 'password'=>'Test-password-123', 'password_confirmation'=>'Test-password-123'])->assertSessionHasErrors('role');
        }
        $this->assertDatabaseCount('users', 2);
    }

    public function test_operator_manages_all_clients_without_assignment_and_cannot_escalate_permissions(): void
    {
        $other = Client::factory()->create(['name'=>'Cliente senza referente', 'commercial_user_id'=>null]);
        $this->actingAs($this->operator);
        $this->get(route('clients.index'))->assertOk()->assertSee($this->client->name)->assertSee($other->name);
        $this->get(route('clients.show', $this->client))->assertOk()->assertDontSee('Elimina cliente');
        $this->patch(route('clients.update', $other), ['name'=>$other->name, 'status'=>'active', 'notes'=>'Contatto aggiornato'])->assertSessionHasNoErrors();
        $this->assertSame('Contatto aggiornato', $other->fresh()->notes);
        $this->patch(route('profile.update'), ['name'=>$this->operator->name, 'email'=>$this->operator->email, 'role'=>'admin', 'status'=>'active'])->assertSessionHasErrors(['role','status']);
        $this->assertSame(UserRole::Operator, $this->operator->fresh()->role);
        foreach (['users.index','users.create','audit-logs.index','email-settings.edit','form-templates.index','insurance-import.index'] as $route) $this->get(route($route))->assertForbidden();
        $this->patch(route('users.update',$this->operator), ['role'=>'admin'])->assertForbidden();
        $this->post(route('users.store'), ['role'=>'admin'])->assertForbidden();
    }

    public function test_operator_cannot_delete_clients_policies_agencies_companies_records_or_requests(): void
    {
        $agency = SupplierAgency::create(['name'=>'Agenzia prova']);
        $company = SupplierCompany::create(['name'=>'Compagnia prova']);
        $branch = InsuranceBranch::create(['name'=>'Ramo prova', 'name_key'=>hash('sha256','RAMO PROVA')]);
        $contact = $this->client->contacts()->create(['name'=>'Referente prova']);
        $request = $this->client->documentRequests()->create(['label'=>'Identità', 'document_category'=>'identity']);
        $this->actingAs($this->operator);
        foreach ([['clients.destroy',[$this->client]], ['clients.policies.destroy',[$this->client,$this->policy]], ['suppliers.destroy',[$agency]], ['supplier-companies.destroy',[$company]], ['insurance-catalog.destroy',['branch',$branch->id]], ['clients.records.destroy',[$this->client,'contacts',$contact->id]], ['clients.document-requests.destroy',[$this->client,$request]], ['profile.destroy',[]]] as [$name,$params]) {
            $this->delete(route($name,$params))->assertForbidden();
        }
        $this->assertDatabaseHas('clients',['id'=>$this->client->id]);
        $this->assertDatabaseHas('insurance_policies',['id'=>$this->policy->id]);
        $this->assertDatabaseHas('client_contacts',['id'=>$contact->id]);
        $this->assertDatabaseHas('client_document_requests',['id'=>$request->id]);
        $this->assertDatabaseHas('supplier_agencies',['id'=>$agency->id]);
        $this->assertDatabaseHas('supplier_companies',['id'=>$company->id]);
        $this->assertDatabaseHas('insurance_branches',['id'=>$branch->id]);
        $this->get(route('insurance-catalog.edit',['branch',$branch->id]))->assertOk()->assertDontSee('Elimina ramo');
        $this->get(route('clients.show',[$this->client,'tab'=>'documents']))->assertOk()->assertDontSee('Rimuovi richiesta');
    }

    public function test_operator_can_upload_multiple_documents_but_cannot_delete_own_files_via_http_or_livewire(): void
    {
        $this->actingAs($this->operator);
        $files = [UploadedFile::fake()->createWithContent('uno.pdf', "%PDF-1.4\nDocumento uno"), UploadedFile::fake()->createWithContent('due.pdf', "%PDF-1.4\nDocumento due")];
        $this->post(route('clients.documents.store',$this->client), ['files'=>$files,'document_category'=>'other'])->assertSessionHasNoErrors();
        $this->assertSame(2,$this->client->attachments()->count());
        $file=$this->client->attachments()->first();
        $this->get(route('attachments.download',$file))->assertOk();
        $this->delete(route('attachments.destroy',$file))->assertForbidden();
        Livewire::test(AttachmentManager::class,['model'=>$this->client])->call('deleteAttachment',$file->id)->assertForbidden();
        $this->assertDatabaseHas('attachments',['id'=>$file->id]);
        Storage::disk('attachments')->assertExists($file->path);
    }

    public function test_operator_cannot_delete_notes_or_notifications_but_can_mark_them_read(): void
    {
        $note=$this->operator->dailyNotes()->create(['date'=>today()]);
        $entry=$note->entries()->create(['content'=>'Annotazione da conservare','sort_order'=>1]);
        $item=$entry->checklistItems()->create(['label'=>'Controllo documenti','sort_order'=>1]);
        $notification=$this->operator->notifications()->create(['id'=>(string)Str::uuid(),'type'=>'test','data'=>['message'=>'Avviso di prova']]);
        $this->actingAs($this->operator);
        Livewire::test(UserDailyNotes::class)->call('deleteEntry',$entry->id)->assertForbidden();
        Livewire::test(UserDailyNotes::class)->call('deleteChecklistItem',$item->id)->assertForbidden();
        Livewire::test(NotificationDropdown::class)->call('deleteNotification',$notification->id)->assertForbidden();
        $this->delete(route('notifications.destroy',$notification->id))->assertForbidden();
        Livewire::test(NotificationDropdown::class)->call('markAsRead',$notification->id);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertNotNull($entry->fresh());
        $this->assertNotNull($item->fresh());
    }

    public function test_operator_generates_collects_reverses_and_cancels_with_attributed_immutable_history(): void
    {
        $this->actingAs($this->operator);
        $preview=$this->get(route('titles.schedule',$this->policy))->assertOk();
        $this->post(route('titles.generate',$this->policy),['confirmed'=>1,'fingerprint'=>$preview->viewData('fingerprint')])->assertSessionHasNoErrors();
        $title=$this->policy->titles()->orderBy('id')->first();
        $this->get(route('titles.show',$title))->assertOk()->assertSee('Annulla titolo');
        $data=['received_on'=>'2026-10-19','amount'=>'120.00','payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'transfer','idempotency_key'=>(string)Str::uuid(),'consultant_id'=>$this->admin->id,'created_by'=>$this->admin->id];
        $this->post(route('titles.collect',$title),$data)->assertSessionHasNoErrors();
        $this->post(route('titles.collect',$title),$data)->assertSessionHasNoErrors();
        $collection=$title->collections()->sole();
        $this->assertSame($this->operator->id,$collection->created_by);
        $this->assertSame($this->operator->id,$collection->consultant_id);
        $this->assertSame(1,AuditLog::where('action','collection_recorded')->count());
        $this->post(route('titles.cancel',$title),['reason'=>'Incasso ancora presente'])->assertSessionHasErrors('ledger');
        $this->post(route('collections.void',$collection),['reason'=>''])->assertSessionHasErrors('reason');
        $this->post(route('collections.void',$collection),['reason'=>'Importo attribuito al movimento errato'])->assertSessionHasNoErrors();
        $this->post(route('titles.cancel',$title),['reason'=>'Rata registrata per errore'])->assertSessionHasNoErrors();
        $this->assertSame(0,$title->fresh()->collected_cents);
        $this->assertNotNull($collection->fresh()->voided_at);
        $this->assertNotNull($title->fresh()->cancelled_at);
        foreach (['titles_generated','collection_recorded','collection_voided','title_cancelled'] as $action) $this->assertSame($this->operator->id,AuditLog::where('action',$action)->sole()->user_id);
        $log=AuditLog::where('action','collection_voided')->sole();
        $this->assertSame(12000,$log->old_values['collected_cents']);
        $this->assertSame(0,$log->new_values['collected_cents']);
        $this->assertSame($collection->id,$log->new_values['insurance_collection_id']);
        $this->actingAs($this->admin)->get(route('audit-logs.index',['action'=>'reversals','user_id'=>$this->operator->id]))->assertOk()->assertSee('Importo attribuito al movimento errato')->assertSee('120,00')->assertSee('Prima:');
        $this->assertDatabaseCount('insurance_collections',1);
        $this->assertDatabaseCount('insurance_titles',2);
    }

    public function test_operator_cannot_bypass_next_day_policy_lock_through_renewal(): void
    {
        $this->policy->forceFill(['work_closed_at'=>today()->subDay(),'work_closed_by'=>$this->operator->id])->save();
        $this->actingAs($this->operator);
        $this->get(route('titles.renewal',$this->policy))->assertForbidden();
        $this->post(route('titles.renew',$this->policy),[])->assertForbidden();
        $this->assertFalse($this->policy->editableBy($this->operator));
        $this->assertTrue($this->policy->editableBy($this->admin));
    }

    public function test_role_and_status_changes_are_logged_without_passwords(): void
    {
        $this->patch(route('users.update',$this->operator),['name'=>$this->operator->name,'email'=>$this->operator->email,'role'=>'admin','status'=>'inactive'])->assertSessionHasNoErrors();
        $log=AuditLog::where('auditable_type',User::class)->where('auditable_id',$this->operator->id)->where('action','updated')->sole();
        $this->assertSame($this->admin->id,$log->user_id);
        $this->assertSame('operator',$log->old_values['role']);
        $this->assertSame('admin',$log->new_values['role']);
        $this->assertArrayNotHasKey('password',$log->new_values);
        $this->actingAs($this->operator->fresh())->getJson(route('titles.index'))->assertForbidden();
    }


}
