<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsuranceFormPackage, InsuranceFormTemplate, InsurancePolicy, SupplierCompany, User};
use App\Services\{InsuranceForms, PolicyFormReminder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PolicyContractPreparationTest extends TestCase
{
    use RefreshDatabase;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']));
        $this->client=Client::factory()->create(['client_type'=>'person']);
        SupplierCompany::create(['name'=>'Compagnia di collaudo','status'=>'active']);
        Storage::fake('attachments');
    }

    private function template(string $scope='policy', array $extra=[]): InsuranceFormTemplate
    {
        return InsuranceFormTemplate::create($extra+['family'=>(string)Str::uuid(),'version'=>1,'scope'=>$scope,'client_type'=>'all','title'=>'Modulo di collaudo '.$scope,'category'=>'other','status'=>'active','required'=>true,'signature_required'=>false,'body'=>'Contraente {{contraente}} / polizza {{numero_polizza}} / targa {{targa}} / premio {{premio}}','created_by'=>auth()->id()])->fresh();
    }

    private function input(array $extra=[]): array
    {
        return array_replace(['entry_mode'=>'contract','client_id'=>$this->client->id,'category'=>'RC auto','company'=>'Compagnia di collaudo','number'=>'PREPARAZIONE-001','status'=>'active','starts_on'=>'2026-11-10','expires_on'=>'2027-11-10','premium'=>'1200.00','payment_frequency'=>'Semestrale','payment_method'=>'Bonifico a Intermediario','titles_mode'=>'automatic','contract_reviewed'=>1,'contract'=>UploadedFile::fake()->createWithContent('contratto.pdf',"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF")],$extra);
    }

    private function forms(InsuranceFormTemplate ...$templates): array
    {
        return ['mode'=>'prepare','templates'=>array_map(fn($template)=>$template->id,$templates),'revisions'=>collect($templates)->mapWithKeys(fn($template)=>[$template->id=>$template->editKey()])->all(),'document_date'=>today()->toDateString()];
    }

    private function save(array $input)
    {
        if($input['titles_mode']==='automatic') $input['schedule_fingerprint']=$this->postJson(route('policies.contract-schedule'),$input)->assertOk()->json('fingerprint');
        return $this->postJson(route('policies.store'),$input);
    }

    public function test_one_confirmation_creates_the_client_vehicle_contract_titles_and_correctly_scoped_modules(): void
    {
        $clientTemplate=$this->template('client'); $policyTemplate=$this->template();
        $this->save($this->input(['client_id'=>null,'new_client'=>['name'=>'Contraente di collaudo','client_type'=>'person'],'new_vehicle'=>['plate'=>' aa 123 bb ','make'=>'Marca di collaudo','vin'=>' telaio-collaudo '],'contract_forms'=>$this->forms($clientTemplate,$policyTemplate)]))->assertOk()->assertJsonPath('url',route('clients.policies.show',[Client::latest('id')->first(),InsurancePolicy::sole(),'tab'=>'forms']));
        $policy=InsurancePolicy::sole();
        $this->assertSame('Contraente di collaudo',$policy->client->name);
        $this->assertSame('AA123BB',$policy->vehicle->plate);
        $this->assertSame('TELAIO-COLLAUDO',$policy->vehicle->vin);
        $this->assertSame(120000,(int)$policy->titles()->sum('amount_cents'));
        $this->assertDatabaseCount('insurance_form_packages',2);
        $package=InsuranceFormPackage::where('insurance_policy_id',$policy->id)->sole();
        $this->assertStringContainsString('PREPARAZIONE-001',$package->documents->sole()->body);
        $this->assertStringContainsString('AA123BB',$package->documents->sole()->body);
        $clientPackage=InsuranceFormPackage::whereNull('insurance_policy_id')->sole();
        $this->assertSame('client',$clientPackage->reason);
        $this->assertNull(PolicyFormReminder::find($policy));
        $this->assertCount(3,Storage::disk('attachments')->allFiles());
        $this->assertDatabaseCount('insurance_collections',0);
        $this->assertDatabaseCount('document_signatures',0);
    }

    public function test_a_plate_already_registered_for_the_client_is_reused_without_overwriting_its_data(): void
    {
        $vehicle=$this->client->vehicles()->create(['plate'=>'AA123BB','make'=>'Marca conservata','vin'=>'TELAIO-001']);
        $this->save($this->input(['new_vehicle'=>['plate'=>'AA123BB','make'=>'Marca diversa','vin'=>'TELAIO-001']]))->assertOk();
        $this->assertDatabaseCount('client_vehicles',1);
        $this->assertSame($vehicle->id,InsurancePolicy::sole()->vehicle_id);
        $this->assertSame('Marca conservata',$vehicle->fresh()->make);
    }

    public function test_vehicle_conflicts_and_cross_client_links_cannot_bypass_the_final_review(): void
    {
        $this->client->vehicles()->create(['plate'=>'AA123BB','vin'=>'TELAIO-001']);
        $this->save($this->input(['new_vehicle'=>['plate'=>'AA123BB','vin'=>'TELAIO-002']]))->assertUnprocessable()->assertJsonValidationErrors('new_vehicle.vin');
        $other=Client::factory()->create()->vehicles()->create(['plate'=>'CC456DD']);
        $this->save($this->input(['vehicle_id'=>$other->id,'new_vehicle'=>['plate'=>'AA123BB']]))->assertUnprocessable()->assertJsonValidationErrors(['new_vehicle','vehicle_id']);
        $this->assertDatabaseCount('insurance_policies',0);
        $this->assertCount(0,Storage::disk('attachments')->allFiles());
    }

    public function test_a_changed_template_rolls_back_the_entire_practice_and_its_private_files(): void
    {
        $template=$this->template(); $forms=$this->forms($template); $template->update(['body'=>'Testo aggiornato']);
        $before=Client::count();
        $this->save($this->input(['client_id'=>null,'new_client'=>['name'=>'Contraente di collaudo','client_type'=>'person'],'new_vehicle'=>['plate'=>'AA123BB'],'contract_forms'=>$forms]))->assertUnprocessable()->assertJsonValidationErrors('contract_forms.templates');
        $this->assertSame($before,Client::count());
        foreach(['insurance_policies','client_vehicles','insurance_titles','insurance_form_packages'] as $table) $this->assertDatabaseCount($table,0);
        $this->assertCount(0,Storage::disk('attachments')->allFiles());
    }

    public function test_a_failure_after_the_first_module_leaves_no_orphan_pdf_or_partially_saved_policy(): void
    {
        $one=$this->template('client'); $two=$this->template();
        $real=new InsuranceForms; $calls=0;
        $this->partialMock(InsuranceForms::class,function($mock)use($real,&$calls) { $mock->shouldReceive('pdf')->andReturnUsing(function(...$args)use($real,&$calls) { if(++$calls===2) throw new \RuntimeException('Errore PDF di collaudo'); return $real->pdf(...$args); }); });
        $this->withoutExceptionHandling();
        try { $this->save($this->input(['new_vehicle'=>['plate'=>'AA123BB'],'contract_forms'=>$this->forms($one,$two)])); $this->fail('Expected PDF failure.'); } catch(\RuntimeException $error) { $this->assertSame('Errore PDF di collaudo',$error->getMessage()); }
        foreach(['insurance_policies','client_vehicles','insurance_titles','insurance_form_packages','insurance_form_documents'] as $table) $this->assertDatabaseCount($table,0);
        $this->assertCount(0,Storage::disk('attachments')->allFiles());
    }

    public function test_company_signer_role_and_actual_place_are_requested_without_inventing_them(): void
    {
        $this->client->update(['client_type'=>'company']);
        $template=$this->template(extra:['signature_required'=>true,'body'=>'{{firmatario}} / {{luogo}}']);
        $this->save($this->input(['contract_forms'=>$this->forms($template)]))->assertUnprocessable()->assertJsonValidationErrors(['contract_forms.signer_name','contract_forms.signer_role','contract_forms.place']);
        $this->assertDatabaseCount('insurance_policies',0);
        $forms=$this->forms($template)+['signer_name'=>'Firmatario di collaudo','signer_role'=>'Amministratore','place'=>'Luogo di collaudo'];
        $this->save($this->input(['contract_forms'=>$forms]))->assertOk();
        $this->assertDatabaseCount('insurance_form_submissions',0);
    }

    public function test_deferred_module_choices_are_kept_in_the_reminder_without_generating_documents(): void
    {
        $template=$this->template(); $forms=array_replace($this->forms($template),['mode'=>'later']);
        $response=$this->save($this->input(['contract_forms'=>$forms]))->assertOk();
        $policy=InsurancePolicy::sole();
        $this->assertSame([$template->id],PolicyFormReminder::find($policy)->data['selected_templates']);
        $this->assertStringContainsString('tab=overview',$response->json('url'));
        $this->assertDatabaseCount('insurance_form_packages',0);
        $this->assertCount(1,Storage::disk('attachments')->allFiles());
    }
}
