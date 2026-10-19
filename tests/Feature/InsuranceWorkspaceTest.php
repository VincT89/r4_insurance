<?php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Attachment, Client, InsurancePolicy, InsurancePolicyImport, SupplierAgency, SupplierCompany, User};
use App\Services\{DeadlineReminders, PolicyOperationalDetails};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class InsuranceWorkspaceTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->travelTo(now()->setDate(2026,10,16)->setTime(12,0)); $this->actingAs(User::factory()->create(['role'=>UserRole::Admin, 'status'=>'active'])); }
    private function policy(Client $client, array $extra=[]): InsurancePolicy
    {
        return $client->policies()->create(array_replace(['number'=>'DEMO-'.uniqid(), 'category'=>'RC auto', 'company'=>'Compagnia demo', 'starts_on'=>'2026-01-01', 'expires_on'=>'2026-10-31', 'status'=>'active', 'premium'=>'100.00', 'payment_frequency'=>'Semestrale'], $extra));
    }
    private function pdf(string $name='allegato.pdf'): UploadedFile { return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"); }

    public function test_dashboard_today_contains_only_open_policies_created_today_with_their_premiums(): void
    {
        $client=Client::factory()->create();
        $open=$this->policy($client,['number'=>'SA-100','premium'=>'1234.50']);
        $midnight=$this->policy($client,['number'=>'SA-101','status'=>'renewed','premium'=>'0.00','expires_on'=>'2026-10-16']);
        $midnight->forceFill(['created_at'=>today()])->save();
        $futureCoverage=$this->policy($client,['number'=>'SA-102','starts_on'=>'2026-11-01','expires_on'=>'2027-11-01','premium'=>null]);
        foreach (['suspended','cancelled','expired','replaced','ceased','review'] as $status) $this->policy($client,['status'=>$status]);
        $this->policy($client,['status'=>'active','expires_on'=>'2026-10-15']);
        $this->policy($client,['status'=>'renewed','expires_on'=>'2026-10-15']);
        $yesterday=$this->policy($client,['starts_on'=>'2026-10-16']);
        $yesterday->forceFill(['created_at'=>today()->subSecond()])->save();
        $tomorrow=$this->policy($client); $tomorrow->forceFill(['created_at'=>today()->addDay()])->save();
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('todayPolicies',fn($rows)=>$rows->pluck('id')->sort()->values()->all()===collect([$open->id,$midnight->id,$futureCoverage->id])->sort()->values()->all())
            ->assertSee('Premio di polizza')->assertSee('1.234,50 €')->assertSee('0,00 €')->assertSee('Non indicato');
        $employee=User::factory()->create(['role'=>UserRole::Operator]);
        $this->actingAs($employee)->get(route('dashboard'))->assertOk()->assertViewHas('todayPolicies',fn($rows)=>$rows->pluck('id')->sort()->values()->all()===collect([$open->id,$midnight->id,$futureCoverage->id])->sort()->values()->all());
    }

    public function test_direct_form_client_lookup_quick_creation_and_policy_attachments(): void
    {
        Storage::fake('attachments');
        $this->get(route('policies.create',['entry_mode'=>'existing']))->assertOk()->assertSee('Cerca cliente')->assertSee('Documenti allegati')->assertSee('Tacito rinnovo');
        $created=$this->postJson(route('api.clients.quick-store'), ['name'=>'Contraente demo', 'client_type'=>'company', 'vat_number'=>'DEMO-101'])->assertCreated();
        $client=Client::findOrFail($created->json('id'));
        $this->getJson(route('api.clients.search',['q'=>'DEMO-101']))->assertOk()->assertJsonFragment(['id'=>$client->id]);
        $this->post(route('policies.store'), (['client_id'=>$client->id, 'number'=>'NUOVA-DEMO', 'category'=>'RC auto', 'company'=>'Compagnia demo', 'starts_on'=>'2026-10-16', 'expires_on'=>'2027-10-16', 'status'=>'active', 'payment_frequency'=>'Semestrale', 'documents'=>[$this->pdf()]]) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasNoErrors()->assertRedirect();
        $policy=$client->policies()->sole();
        $this->assertTrue($policy->tacit_renewal);
        $file=$policy->documents()->sole();
        $this->assertEquals($client->id,$file->attachable_id); Storage::disk('attachments')->assertExists($file->path);
        $this->get(route('clients.show',[$client,'tab'=>'documents']))->assertSee('allegato.pdf');
        $this->get(route('dashboard'))->assertOk()->assertSee('NUOVA-DEMO')->assertSee('Polizze inserite oggi');
    }

    public function test_portfolio_context_and_global_save_enforce_client_scope_and_links(): void
    {
        $employee=User::factory()->create(['role'=>UserRole::Operator]);
        $owned=Client::factory()->create(['commercial_user_id'=>$employee->id]);
        $other=Client::factory()->create(); $hidden=$this->policy($other,['number'=>'RISERVATA-DEMO']);
        $vehicle=$other->vehicles()->create(['plate'=>'PRIVATE']);
        $this->actingAs($employee);
        $this->get(route('policies.index'))->assertOk()->assertSee($hidden->number);
        $this->getJson(route('clients.policy-context',$other))->assertOk();
        $data=['category'=>'RC auto','company'=>'Demo','number'=>'FORBIDDEN','starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','status'=>'active'];
        $this->postJson(route('policies.store'),$data+['client_id'=>$other->id,'entry_mode'=>'new'])->assertUnprocessable()->assertJsonValidationErrors('insurance_quote_id');
        $this->postJson(route('policies.store'),$data+['client_id'=>$owned->id,'vehicle_id'=>$vehicle->id])->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('agency-communications.index'))->assertForbidden();
        \Livewire\Livewire::test(\App\Livewire\Dashboard\RecentActivity::class)->assertForbidden();
    }

    public function test_month_report_includes_last_day_and_excludes_next_month_with_distinct_agencies(): void
    {
        $client=Client::factory()->create();
        $one=SupplierAgency::create(['name'=>'Agenzia demo', 'status'=>'active']);
        $two=SupplierAgency::create(['name'=>'Agenzia demo', 'status'=>'active']);
        $this->policy($client,['supplier_agency_id'=>$one->id,'expires_on'=>'2026-10-31','premium'=>'50.00']);
        $this->policy($client,['supplier_agency_id'=>$two->id,'expires_on'=>'2026-10-01','premium'=>'60.00']);
        $this->policy($client,['expires_on'=>'2026-11-01','premium'=>'900.00']);
        $response=$this->get(route('reports.index',['month'=>'2026-10','group'=>'agency']));
        $response->assertOk()->assertViewHas('totals',fn($v)=>(int)$v->policies===2 && (float)$v->premiums===110.0)->assertViewHas('rows',fn($v)=>$v->total()===2);
        $this->get(route('reports.index',['month'=>'2026-13']))->assertSessionHasErrors('month');
    }

    public function test_reminders_cover_fifteen_days_once_respect_scope_and_ignore_replaced_documents(): void
    {
        $user=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $client=Client::factory()->create(['commercial_user_id'=>$user->id]);
        $other=Client::factory()->create();
        $p=$this->policy($client,['expires_on'=>'2026-10-31']);
        $this->policy($client,['expires_on'=>'2026-11-01']);
        $this->policy($other,['expires_on'=>'2026-10-31']);
        $this->policy($client,['expires_on'=>'2026-10-30','status'=>'expired']);
        $service=app(DeadlineReminders::class);
        $this->assertSame(2,$service->forUser($user)); $this->assertSame(0,$service->forUser($user));
        $this->assertSame(2,$user->visibleNotifications()->count());
        $user->notifications()->delete(); $this->assertSame(0,$service->forUser($user));
        $p->update(['expires_on'=>'2026-10-30']); $this->assertSame(1,$service->forUser($user));
        $client->update(['commercial_user_id'=>null]); $this->assertSame(1,$user->visibleNotifications()->count());
    }

    public function test_daily_agency_email_is_a_reviewed_draft_scoped_by_agency_and_date(): void
    {
        $client=Client::factory()->create();
        $agency=SupplierAgency::create(['name'=>'Agenzia demo','email'=>'agenzia@example.test','status'=>'active']);
        $p=$this->policy($client,['supplier_agency_id'=>$agency->id,'number'=>'EMAIL-DEMO','ad_horas'=>true]);
        $other=$this->policy($client,['number'=>'ALTRA-AGENZIA']);
        $url=route('agency-communications.draft',$agency);
        $this->post($url,['date'=>'2026-10-16','policies'=>[$p->id]])->assertSessionHasErrors('reviewed');
        $this->post($url,['date'=>'2026-10-16','policies'=>[$other->id],'reviewed'=>1])->assertSessionHasErrors('policies');
        $result=$this->post($url,['date'=>'2026-10-16','policies'=>[$p->id],'reviewed'=>1])->assertOk()->assertHeader('Content-Type','message/rfc822');
        $this->assertStringContainsString('X-Unsent: 1',$result->getContent());
        $this->assertStringContainsString('EMAIL-DEMO',quoted_printable_decode($result->getContent()));
        $this->get(route('agency-communications.index'))->assertOk()->assertSee('Ad horas: gestire subito');
        $this->assertDatabaseHas('audit_logs',['action'=>'agency_email_draft']);
    }

    public function test_expired_identity_warns_unknown_cannot_be_validated_and_non_pdf_policy_upload_is_rejected(): void
    {
        Storage::fake('attachments'); $client=Client::factory()->create();
        $this->post(route('clients.documents.store',$client),['document_category'=>'identity','expires_on'=>'2026-10-15','file'=>$this->pdf()])->assertSessionHas('warning');
        $doc=$client->attachments()->sole();
        $this->post(route('clients.documents.validate',[$client,$doc]))->assertSessionHasErrors('document');
        $doc->update(['expires_on'=>null]);
        $this->post(route('clients.documents.validate',[$client,$doc]))->assertSessionHasErrors('document');
        $this->post(route('clients.store'),['name'=>'Cliente con allegato','client_type'=>'person','documents'=>[$this->pdf('cliente.pdf')]])->assertSessionHasNoErrors();
        $this->assertSame(1,Client::where('name','Cliente con allegato')->sole()->attachments()->count());
        Storage::disk('attachments')->put('invalid-test.txt','<script>bad</script>');
        $fake = new UploadedFile(Storage::disk('attachments')->path('invalid-test.txt'),'falso.pdf',null,null,true);
        $this->post(route('policies.store'),(['client_id'=>$client->id,'category'=>'RC auto','company'=>'Demo','number'=>'MIME','status'=>'active','starts_on'=>'2026-10-16','expires_on'=>'2027-10-16','documents'=>[$fake]]) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('documents.0');
    }

    public function test_source_status_frequency_and_method_do_not_invent_paid_or_resolve_mixed_groups(): void
    {
        $service=app(PolicyOperationalDetails::class);
        $base=['Frazionamento'=>'Semestrale','Tipo Pagamento'=>'Bonifico a Compagnia','Premio Rata'=>'125,50','Tacito Rinnovo'=>'Si'];
        $active=$service->values([['values'=>$base+['Polizze'=>'Archivio']]]);
        $this->assertSame('active',$active['status']); $this->assertArrayNotHasKey('payment_status',$active); $this->assertSame('125.50',$active['installment_premium']);
        $this->assertSame('unpaid',$service->values([['values'=>$base+['Polizze'=>'OLD']]])['payment_status']);
        $mixed=$service->values([['values'=>$base+['Polizze'=>'Archivio']],['values'=>$base+['Polizze'=>'OLD']]]);
        $this->assertArrayNotHasKey('status',$mixed); $this->assertSame('Semestrale',$mixed['payment_frequency']);
        $p=$this->policy(Client::factory()->create(),['status'=>'expired','expires_on'=>'2027-01-01']);
        $this->get(route('deadlines.index',['period'=>'overdue','search'=>$p->number]))->assertOk()->assertSee($p->number)->assertSee('Semestrale')->assertSee('Scaduta');
    }
}
