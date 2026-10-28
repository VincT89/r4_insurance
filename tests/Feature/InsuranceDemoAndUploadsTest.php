<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Attachment, Client, InsuranceBranch, InsurancePolicy, SupplierAgency, SupplierCompany, User};
use App\Services\InsuranceDemoReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Crypt, DB, Storage};
use Tests\TestCase;

class InsuranceDemoAndUploadsTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    protected function setUp(): void { parent::setUp(); $this->travelTo(now()->setDate(2026,10,16)->setTime(12,0)); $this->admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']); $this->actingAs($this->admin); Storage::fake('attachments'); Storage::fake('local'); }
    private function pdf(string $name): UploadedFile { return UploadedFile::fake()->createWithContent($name,"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"); }
    private function catalog(): array
    {
        $branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active','needs_review'=>false]);
        $branch->subbranches()->create(['name'=>'Autovetture','name_key'=>hash('sha256','AUTOVETTURE'),'status'=>'active','needs_review'=>false]);
        $company=SupplierCompany::create(['name'=>'Compagnia di prova','status'=>'active']);
        $agency=SupplierAgency::create(['name'=>'Agenzia di prova','email'=>'agency@example.test','status'=>'active','supplier_company_id'=>$company->id]);
        return [$branch,$company,$agency];
    }

    public function test_multi_documents_keep_independent_identity_types_dates_and_reject_cross_client_links(): void
    {
        $client=Client::factory()->create(); $other=Client::factory()->create();
        $this->post(route('clients.documents.store',$client),['files'=>[$this->pdf('carta.pdf'),$this->pdf('patente.pdf')],'document_category'=>'identity','file_details'=>[['identity_type'=>'electronic','expires_on'=>'2027-01-01'],['identity_type'=>'driving_licence','expires_on'=>'2026-10-15']]])->assertSessionHasNoErrors()->assertSessionHas('warning');
        $docs=$client->attachments()->orderBy('id')->get();
        $this->assertSame('Documento valido',$docs[0]->validity_label);
        $this->assertSame('Documento non valido — scaduto il 15/10/2026',$docs[1]->validity_label);
        $this->assertSame('driving_licence',$docs[1]->identity_type);
        $this->post(route('clients.documents.store',$client),['files'=>[$this->pdf('illeggibile.pdf')],'document_category'=>'identity'])->assertSessionHas('warning','illeggibile.pdf: Da verificare, data di scadenza non leggibile');
        $this->post(route('clients.documents.store',$other),['files'=>[$this->pdf('a.pdf'),$this->pdf('b.pdf')],'document_category'=>'identity','previous_version_id'=>$docs[0]->id])->assertSessionHasErrors('previous_version_id');
        $this->post(route('clients.documents.store',$client),['files'=>[$this->pdf('a.pdf'),$this->pdf('b.pdf')],'document_category'=>'identity','previous_version_id'=>$docs[0]->id])->assertSessionHasErrors('files');
        $this->assertSame(3,$client->attachments()->count());
    }

    public function test_generic_and_livewire_batches_validate_every_file_and_store_both_valid_files(): void
    {
        $client=Client::factory()->create();
        $base=['attachable_type'=>'client','attachable_id'=>$client->id,'type'=>'document'];
        $this->post(route('attachments.store'),$base+['files'=>[$this->pdf('a.pdf'),$this->pdf('b.pdf')]])->assertSessionHasNoErrors();
        $this->assertSame(2,$client->attachments()->count());
        $this->post(route('attachments.store'),$base+['files'=>[$this->pdf('a.pdf'),UploadedFile::fake()->createWithContent('bad.php','<?php echo 1;')]])->assertSessionHasErrors('files.1');
        $this->assertSame(2,$client->attachments()->count());
        \Livewire\Livewire::test(\App\Livewire\Shared\AttachmentManager::class,['model'=>$client])->set('files',[$this->pdf('c.pdf'),$this->pdf('d.pdf')])->assertHasNoErrors();
        $this->assertSame(4,$client->attachments()->count());
        $this->postJson(route('attachments.store'),['attachable_type'=>['client'],'attachable_id'=>[$client->id]])->assertForbidden();
    }

    public function test_reset_backs_up_dependents_preserves_catalog_and_admin_and_creates_fifty_linked_examples(): void
    {
        [$branch,$company,$agency]=$this->catalog();
        $client=Client::factory()->create();
        $this->post(route('clients.documents.store',$client),['file'=>$this->pdf('identita.pdf'),'document_category'=>'identity'])->assertSessionHasNoErrors();
        $file=$client->attachments()->sole();
        $client->contacts()->create(['name'=>'Contatto da eliminare']);
        $hash=$this->admin->getRawOriginal('password');
        $report=app(InsuranceDemoReset::class)->apply($this->admin);
        $this->assertSame(50,Client::count()); $this->assertSame(50,InsurancePolicy::count());
        $this->assertDatabaseMissing('clients',['id'=>$client->id]); $this->assertDatabaseCount('client_contacts',0); $this->assertDatabaseCount('attachments',0);
        $this->assertSame($hash,$this->admin->fresh()->getRawOriginal('password'));
        $this->assertDatabaseCount('supplier_companies',1); $this->assertDatabaseCount('supplier_agencies',1); $this->assertDatabaseCount('insurance_branches',1);
        $this->assertSame(50,InsurancePolicy::where('supplier_agency_id',$agency->id)->where('supplier_company_id',$company->id)->where('insurance_branch_id',$branch->id)->whereNotNull('merit_class_cu')->count());
        Storage::disk('attachments')->assertMissing($file->path);
        $backup=json_decode(Crypt::decryptString(Storage::disk('local')->get($report['backup'].'/clients.json.enc')),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame($client->name,$backup[0]['name']);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('agency-communications.index',['period'=>'15','company'=>$company->name,'agency'=>$agency->id]))->assertOk()->assertSee('SA-2026-001')->assertDontSee('SA-2026-030');
        $this->expectException(\RuntimeException::class); (new \Database\Seeders\InsuranceDemoSeeder)->run();
    }

    public function test_reset_failure_rolls_back_clients_and_leaves_files_untouched(): void
    {
        $client=Client::factory()->create();
        $this->post(route('clients.documents.store',$client),['file'=>$this->pdf('conservare.pdf'),'document_category'=>'identity']);
        $file=$client->attachments()->sole();
        try { app(InsuranceDemoReset::class)->apply($this->admin); $this->fail('Il catalogo vuoto deve bloccare il reset.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Servono rami',$e->getMessage()); }
        $this->assertDatabaseHas('clients',['id'=>$client->id]); $this->assertDatabaseHas('attachments',['id'=>$file->id]); Storage::disk('attachments')->assertExists($file->path);
    }

    public function test_reset_refuses_existing_policy_changes_without_touching_their_history(): void
    {
        [$branch,$company,$agency]=$this->catalog();
        $client=Client::factory()->create();
        $policy=$client->policies()->create(['number'=>'SA-2026-501','category'=>$branch->name,'company'=>$company->name,'starts_on'=>today(),'expires_on'=>today()->addYear(),'status'=>'active']);
        $case=$client->quoteCases()->create(['insurance_branch_id'=>$branch->id,'subject'=>'Copertura cristalli','purpose'=>'addition','current_policy_id'=>$policy->id,'status'=>'closed','created_by'=>$this->admin->id]);
        $quote=$case->quotes()->create(['supplier_company_id'=>$company->id,'status'=>'converted','priority'=>false,'requested_at'=>today()]);
        $change=\App\Models\InsurancePolicyChange::create(['source_policy_id'=>$policy->id,'result_policy_id'=>$policy->id,'insurance_quote_id'=>$quote->id,'kind'=>'addition','effective_on'=>today(),'expires_on'=>today()->addYear(),'reference'=>'APP-501','description'=>'Aggiunta cristalli','premium_cents'=>9000,'snapshot'=>[],'request_key'=>(string)\Illuminate\Support\Str::uuid(),'created_by'=>$this->admin->id]);
        try { app(InsuranceDemoReset::class)->apply($this->admin); $this->fail('Lo storico delle variazioni deve impedire il reset.'); }
        catch (\RuntimeException $exception) { $this->assertStringContainsString('variazioni contrattuali',$exception->getMessage()); }
        $this->assertDatabaseHas('insurance_policy_changes',['id'=>$change->id]);
        $this->assertDatabaseHas('insurance_policies',['id'=>$policy->id,'number'=>'SA-2026-501']);
        $this->assertDatabaseHas('clients',['id'=>$client->id]);
        $this->assertSame([],Storage::disk('local')->allFiles());
    }

    public function test_merit_classes_are_validated_saved_and_displayed(): void
    {
        $client=Client::factory()->create();
        $data=['client_id'=>$client->id,'category'=>'RC auto','company'=>'Compagnia demo','number'=>'CU-DEMO','starts_on'=>'2026-10-16','expires_on'=>'2027-10-16','status'=>'active','merit_class_internal'=>'1A'];
        $this->post(route('policies.store'),($data+['merit_class_cu'=>19]) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasErrors('merit_class_cu');
        $this->post(route('policies.store'),($data+['merit_class_cu'=>1]) + ['entry_mode'=>'existing','existing_reason'=>'Registrazione da archivio di collaudo'])->assertSessionHasNoErrors();
        $p=$client->policies()->sole(); $this->assertSame(1,$p->merit_class_cu); $this->assertSame('1A',$p->merit_class_internal);
        $this->get(route('clients.policies.show',[$client,$p]))->assertOk()->assertSee('1A')->assertSee('Classe universale CU');
    }

    public function test_legacy_payment_schema_receives_renewal_fields_without_changing_existing_policies(): void
    {
        \Illuminate\Support\Facades\Schema::table('insurance_policies',fn($table)=>$table->dropColumn(['tacit_renewal','ad_horas']));
        $client=Client::factory()->create();
        $policy=$client->policies()->create(['number'=>'LEGACY','category'=>'RC auto','company'=>'Demo','starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','status'=>'active','payment_frequency'=>'Semestrale']);
        $migration=require database_path('migrations/2026_10_16_105000_complete_policy_renewal_fields.php');
        $migration->up(); $migration->up();
        $this->assertNull($policy->fresh()->tacit_renewal); $this->assertFalse($policy->fresh()->ad_horas);
        $this->assertSame('Semestrale',$policy->fresh()->payment_frequency); $this->assertSame(1,InsurancePolicy::count());
    }
}
