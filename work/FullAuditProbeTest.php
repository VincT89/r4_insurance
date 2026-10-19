<?php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client,User,InsurancePolicy,InsuranceBranch,SupplierCompany,InsuranceQuoteCase};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FullAuditProbeTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $operator;
    private Client $client;
    private InsurancePolicy $policy;
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,18)->setTime(12,0));
        Storage::fake('attachments');
        $this->admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->operator=User::factory()->create(['role'=>UserRole::Commercial,'status'=>'active']);
        $this->actingAs($this->admin);
        $this->client=Client::factory()->create(['commercial_user_id'=>$this->operator->id]);
        $branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $company=SupplierCompany::create(['name'=>'Nobis','status'=>'active','quote_priority'=>true]);
        $this->policy=$this->client->policies()->create(['category'=>'RC auto','insurance_branch_id'=>$branch->id,'company'=>'Nobis','supplier_company_id'=>$company->id,'number'=>'SA-2026-870','starts_on'=>'2026-10-01','expires_on'=>'2027-10-01','status'=>'active','premium'=>'1000.00','payment_frequency'=>'Semestrale','payment_method'=>'Bonifico a Intermediario','notes'=>'Nota iniziale']);
    }
    private function data(): array
    {
        return $this->policy->only(['category','company','number','status','notes'])+['starts_on'=>'2026-10-01','expires_on'=>'2027-10-01'];
    }
    public function test_employee_must_not_delete_a_contact(): void
    {
        $contact=$this->client->contacts()->create(['name'=>'Marco Riva']);
        $this->actingAs($this->operator)->delete(route('clients.records.destroy',[$this->client,'contacts',$contact->id]))->assertForbidden();
    }
    public function test_employee_must_not_delete_an_unlinked_vehicle(): void
    {
        $vehicle=$this->client->vehicles()->create(['plate'=>'AB123CD']);
        $this->actingAs($this->operator)->delete(route('clients.vehicles.destroy',[$this->client,$vehicle]))->assertForbidden();
    }
    public function test_stale_policy_form_must_not_overwrite_a_later_change(): void
    {
        $stale=$this->data();
        $this->policy->update(['notes'=>'Nota aggiornata da un altro operatore']);
        $this->actingAs($this->operator)->patch(route('clients.policies.update',[$this->client,$this->policy]),$stale);
        $this->assertSame('Nota aggiornata da un altro operatore',$this->policy->fresh()->notes);
    }
    public function test_outsider_cannot_download_or_open_another_client(): void
    {
        $other=User::factory()->create(['role'=>UserRole::Commercial]);
        $this->actingAs($other)->get(route('clients.show',$this->client))->assertForbidden();
        $this->get(route('clients.policies.show',[$this->client,$this->policy]))->assertForbidden();
    }
    public function test_policy_forms_have_no_global_client_data_for_unassigned_operator(): void
    {
        $other=User::factory()->create(['role'=>UserRole::Commercial]);
        $this->actingAs($other)->get('/api/clients/search?q='.urlencode($this->client->name))->assertOk()->assertJsonMissing(['id'=>$this->client->id]);
    }
    public function test_collection_resubmission_does_not_create_a_second_payment(): void
    {
        $ledger=app(\App\Services\InsuranceLedger::class);
        $ledger->generate($this->policy,hash('sha256',json_encode($ledger->plan($this->policy))));
        $title=$this->policy->titles()->first();
        $data=['amount'=>'100.00','received_on'=>'2026-10-18','payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'transfer','idempotency_key'=>(string)\Illuminate\Support\Str::uuid()];
        $this->post(route('titles.collect',$title),$data)->assertSessionHasNoErrors();
        $this->post(route('titles.collect',$title),$data)->assertSessionHasNoErrors();
        $this->assertSame(1,$title->collections()->count());
        $this->assertSame(10000,$title->fresh()->collected_cents);
    }
    public function test_admin_correction_of_policy_number_keeps_quote_and_title_links(): void
    {
        $ledger=app(\App\Services\InsuranceLedger::class);
        $ledger->generate($this->policy,hash('sha256',json_encode($ledger->plan($this->policy))));
        $this->patch(route('clients.policies.update',[$this->client,$this->policy]),array_replace($this->data(),['number'=>'SA-2026-871']))->assertSessionHasNoErrors();
        $this->assertSame(2,$this->policy->titles()->count());
    }
    public function test_employee_cannot_modify_a_policy_closed_yesterday(): void
    {
        $this->policy->forceFill(['work_closed_at'=>now()->subDay(),'work_closed_by'=>$this->operator->id])->save();
        $this->actingAs($this->operator)->patch(route('clients.policies.update',[$this->client,$this->policy]),array_replace($this->data(),['notes'=>'Modifica tardiva']))->assertForbidden();
    }
    public function test_audit_author_should_survive_user_deletion(): void
    {
        $this->actingAs($this->operator);
        app(\App\Services\InsuranceLedger::class)->log($this->policy,'collection_recorded','registrazione di collaudo');
        $event=$this->client->auditLogs()->where('action','collection_recorded')->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->delete(route('users.destroy',$this->operator))->assertRedirect();
        $this->assertSame($this->operator->name,$event->fresh()->user?->name ?? $event->fresh()->actor_name,'Lo storico dell’incasso non conserva più l’autore dopo la cancellazione dell’utente.');
    }
    public function test_paid_title_should_leave_unread_actionable_reminders(): void
    {
        $this->policy->update(['starts_on'=>'2026-10-18','expires_on'=>'2027-10-18']);
        $ledger=app(\App\Services\InsuranceLedger::class);
        $ledger->generate($this->policy,hash('sha256',json_encode($ledger->plan($this->policy))));
        $title=$this->policy->titles()->orderBy('coverage_from')->firstOrFail();
        app(\App\Services\DeadlineReminders::class)->forUser($this->admin);
        $notification=$this->admin->visibleNotifications()->get()->first(fn($n)=>($n->data['kind']??null)==='title'&&($n->data['source_id']??null)===$title->id);
        $this->assertNotNull($notification);
        $ledger->collect($title,['received_on'=>'2026-10-18','amount_cents'=>$title->amount_cents,'payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'transfer','consultant_id'=>$this->admin->id,'idempotency_key'=>(string)\Illuminate\Support\Str::uuid()]);
        $this->assertFalse($this->admin->visibleNotifications()->whereKey($notification->id)->whereNull('read_at')->exists(),'L’avviso resta non letto e operativo anche dopo il saldo del titolo.');
    }
    public function test_browser_selection_does_not_allow_creation_without_approved_quote(): void
    {
        $this->actingAs($this->operator)->post(route('policies.store'),$this->data()+['entry_mode'=>'new','client_id'=>$this->client->id])->assertSessionHasErrors('insurance_quote_id');
    }
}
