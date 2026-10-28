<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AuditLog, Client, InsuranceBranch, InsuranceQuoteCase, InsuranceQuoteEmail, QuoteRecipientGroup, SupplierCompany, User};
use App\Services\{InsuranceQuoteWorkflow, QuoteRequestDocuments};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuoteVehicleGroupsTest extends TestCase
{
    use RefreshDatabase;
    private Client $client;
    private InsuranceBranch $branch;
    private SupplierCompany $allianz;
    private SupplierCompany $tua;
    private SupplierCompany $nobis;
    private QuoteRecipientGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,28)->setTime(12,0));
        Storage::fake('attachments');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']));
        $this->client=Client::factory()->create(['name'=>'Alessandro Varesi']);
        $this->branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $this->allianz=SupplierCompany::create(['name'=>'Allianz','email'=>'allianz@example.test','status'=>'active']);
        $this->tua=SupplierCompany::create(['name'=>'TUA','email'=>'tua@example.test','status'=>'active']);
        $this->nobis=SupplierCompany::create(['name'=>'Nobis','email'=>'nobis@example.test','status'=>'active','quote_priority'=>true]);
        $this->group=QuoteRecipientGroup::create(['name'=>'Classe 14','insurance_branch_id'=>$this->branch->id,'cu_from'=>14,'cu_to'=>14,'status'=>'active']);
        $this->group->companies()->attach([$this->allianz->id,$this->tua->id]);
    }

    private function vehicle(array $extra=[])
    {
        return $this->client->vehicles()->create(array_replace(['plate'=>'GA123BC','make'=>'FIAT','model'=>'PANDA','merit_class_cu'=>14,'merit_class_internal'=>'A2','merit_class_company_id'=>$this->allianz->id],$extra));
    }
    private function input(array $extra=[]): array { return array_replace(['client_id'=>$this->client->id,'insurance_branch_id'=>$this->branch->id,'subject'=>'Copertura autovettura','purpose'=>'new'],$extra); }
    private function createCase(array $extra=[]): InsuranceQuoteCase
    {
        $response=$this->post(route('quotes.store'),$this->input($extra))->assertSessionHasNoErrors()->assertRedirect();
        return InsuranceQuoteCase::findOrFail(basename($response->headers->get('Location')));
    }
    private function pdf(string $name): UploadedFile { return UploadedFile::fake()->createWithContent($name,"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"); }

    public function test_admin_configures_groups_and_operators_can_only_use_them(): void
    {
        $data=['name'=>'Classi 1–5','insurance_branch_id'=>$this->branch->id,'cu_from'=>1,'cu_to'=>5,'status'=>'active','company_ids'=>[$this->nobis->id]];
        $this->post(route('quote-groups.store'),$data)->assertSessionHasNoErrors()->assertRedirect(route('quote-groups.index'));
        $created=QuoteRecipientGroup::where('name','Classi 1–5')->sole();
        $this->assertSame([$this->nobis->id],$created->companies()->pluck('supplier_companies.id')->all());
        $this->assertSame([$this->nobis->id],AuditLog::where('action','quote_group_updated')->latest('id')->first()->new_values['company_ids']);
        $this->get(route('quote-groups.index',['cu'=>14]))->assertOk()->assertSee('Classe 14')->assertDontSee('Classi 1–5');
        $this->get(route('quote-groups.edit',$created))->assertOk();
        $this->post(route('quote-groups.store'),array_replace($data,['name'=>'Errato','cu_from'=>15,'cu_to'=>14]))->assertSessionHasErrors('cu_to');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator]));
        $this->get(route('quote-groups.index'))->assertForbidden();
        $this->post(route('quote-groups.store'),$data)->assertForbidden();
        $this->patch(route('quote-groups.update',$created),$data)->assertForbidden();
    }

    public function test_vehicle_and_group_are_checked_server_side_and_snapshot_is_preserved(): void
    {
        $vehicle=$this->vehicle();
        $this->post(route('quotes.store'),$this->input(['vehicle_id'=>$vehicle->id]))->assertSessionHasErrors('quote_recipient_group_id');
        $other=Client::factory()->create()->vehicles()->create(['plate'=>'GB456DE']);
        $this->post(route('quotes.store'),$this->input(['vehicle_id'=>$other->id,'quote_recipient_group_id'=>$this->group->id]))->assertSessionHasErrors('vehicle_id');
        $this->post(route('quotes.store'),$this->input(['vehicle_id'=>$vehicle->id,'merit_class_cu'=>1,'quote_recipient_group_id'=>$this->group->id]))->assertSessionHasErrors('quote_recipient_group_id');
        $case=$this->createCase(['vehicle_id'=>$vehicle->id,'quote_recipient_group_id'=>$this->group->id,'recipient_group_snapshot'=>['companies'=>[['id'=>$this->nobis->id]]]]);
        $this->assertSame(14,$case->merit_class_cu);
        $this->assertSame([$this->allianz->id,$this->tua->id],$case->availableCompanies()->pluck('id')->all());
        $vehicle->update(['plate'=>'GC789FG','merit_class_cu'=>1]);
        $this->group->companies()->sync([$this->nobis->id]);
        $this->delete(route('supplier-companies.destroy',$this->tua))->assertSessionHasErrors('company');
        $this->assertSame('GA123BC',$case->fresh()->vehicle_snapshot['plate']);
        $this->assertSame([$this->allianz->id,$this->tua->id],$case->fresh()->availableCompanies()->pluck('id')->all());
        $this->get(route('quotes.index',['plate'=>'GA123','cu'=>14]))->assertOk()->assertSee('Copertura autovettura');
        $this->get(route('quotes.index',['plate'=>'GC789']))->assertOk()->assertDontSee('Copertura autovettura');
        $this->delete(route('clients.vehicles.destroy',[$this->client,$vehicle]))->assertSessionHasErrors('vehicle');
    }

    public function test_requests_and_email_reviews_cannot_escape_the_chosen_group(): void
    {
        $vehicle=$this->vehicle(); $case=$this->createCase(['vehicle_id'=>$vehicle->id,'quote_recipient_group_id'=>$this->group->id]);
        $manual=['requested_on'=>today()->toDateString(),'confirmed'=>1];
        $this->post(route('quotes.request',$case),$manual+['supplier_company_id'=>$this->nobis->id])->assertSessionHasErrors('quote');
        $this->post(route('quotes.request',$case),$manual+['supplier_company_id'=>$this->allianz->id])->assertSessionHasNoErrors();
        $email=['subject'=>'Richiesta copertura','body'=>'Buongiorno, richiediamo il preventivo per il veicolo indicato.'];
        $this->post(route('quotes.emails.review',$case),$email+['supplier_company_id'=>$this->nobis->id])->assertSessionHasErrors('email');
        $this->post(route('quotes.emails.review',$case),$email+['supplier_company_id'=>$this->tua->id])->assertSessionHasNoErrors();
        $this->assertSame('review',InsuranceQuoteEmail::sole()->status);
        $this->get(route('quotes.emails.create',$case))->assertOk()->assertSee('Classe 14')->assertDontSee('>Nobis',false);
    }

    public function test_multiple_identity_images_and_registration_files_remain_linked_correctly(): void
    {
        $vehicle=$this->vehicle();
        $case=$this->createCase(['vehicle_id'=>$vehicle->id,'quote_recipient_group_id'=>$this->group->id,
            'identity_files'=>[UploadedFile::fake()->image('fronte.png'),UploadedFile::fake()->image('retro.jpg')],
            'identity_file_details'=>[['identity_type'=>'electronic','expires_on'=>'2031-10-28'],['identity_type'=>'paper','expires_on'=>'2026-10-27']],
            'vehicle_files'=>[$this->pdf('libretto.pdf'),UploadedFile::fake()->image('libretto-retro.png')]]);
        $this->assertSame(4,$case->documents()->count());
        $identity=$case->documents()->where('document_category','identity')->orderBy('id')->get();
        $this->assertSame('Documento valido',$identity[0]->validity_label);
        $this->assertStringContainsString('27/10/2026',$identity[1]->validity_label);
        $this->assertNull($identity[0]->vehicle_id);
        $this->assertSame(2,$case->documents()->where('vehicle_id',$vehicle->id)->count());
        $email=['supplier_company_id'=>$this->allianz->id,'subject'=>'Richiesta copertura','body'=>'Buongiorno, richiediamo il preventivo per il veicolo indicato.','document_ids'=>$case->documents()->pluck('id')->all()];
        $this->post(route('quotes.emails.review',$case),$email)->assertSessionHasNoErrors();
        $copies=InsuranceQuoteEmail::sole()->documents()->get();
        $this->assertCount(4,$copies);
        $this->assertStringEndsWith('.png',$copies->firstWhere('original_name','fronte.png')->path);
    }

    public function test_vehicle_creation_keeps_classes_files_and_rejects_wrong_client_updates(): void
    {
        $this->postJson(route('clients.vehicles.store',$this->client),['plate'=>' ga 123 bc ','make'=>'Fiat','merit_class_cu'=>14])->assertCreated()->assertJsonPath('plate','GA123BC');
        $this->postJson(route('clients.vehicles.store',$this->client),['plate'=>'GA123BC'])->assertUnprocessable()->assertJsonValidationErrors('plate');
        $vehicle=$this->client->vehicles()->sole();
        $this->patch(route('clients.vehicles.update',[$this->client,$vehicle]),['plate'=>'GA123BC','merit_class_internal'=>'A2'])->assertSessionHasErrors('merit_class_company_id');
        $this->patch(route('clients.vehicles.update',[$this->client,$vehicle]),['plate'=>'GA123BC','merit_class_cu'=>14,'merit_class_internal'=>'A2','merit_class_company_id'=>$this->allianz->id,'registration_files'=>[$this->pdf('fronte.pdf'),$this->pdf('retro.pdf')]])->assertSessionHasNoErrors();
        $this->assertSame(2,$vehicle->documents()->count());
        $this->get(route('clients.show',[$this->client,'tab'=>'vehicles']))->assertOk()->assertSee('CU 14')->assertSee('Allianz')->assertSee('Richiedi preventivo');
        $other=Client::factory()->create();
        $this->patch(route('clients.vehicles.update',[$other,$vehicle]),['plate'=>'GB123DE'])->assertNotFound();
    }

    public function test_vehicle_passes_to_policy_but_internal_class_never_crosses_companies(): void
    {
        $vehicle=$this->vehicle();$case=$this->createCase(['vehicle_id'=>$vehicle->id,'quote_recipient_group_id'=>$this->group->id]);
        $quote=$case->quotes()->create(['supplier_company_id'=>$this->tua->id,'status'=>'approved','requested_at'=>today(),'starts_on'=>today(),'expires_on'=>today()->addYear(),'premium_cents'=>80000,'payment_frequency'=>'Annuale','product'=>'Auto completa']);
        $values=app(InsuranceQuoteWorkflow::class)->policyValues($quote);
        $this->assertSame($vehicle->id,$values['vehicle_id']);$this->assertSame(14,$values['merit_class_cu']);$this->assertNull($values['merit_class_internal']);
        $quote->supplier_company_id=$this->allianz->id;$quote->unsetRelation('company');
        $this->assertSame('A2',app(InsuranceQuoteWorkflow::class)->policyValues($quote)['merit_class_internal']);
    }

    public function test_conversion_keeps_vehicle_documents_and_confirmed_contract_classes(): void
    {
        $vehicle=$this->vehicle();
        $case=$this->createCase(['vehicle_id'=>$vehicle->id,'quote_recipient_group_id'=>$this->group->id,'vehicle_files'=>[$this->pdf('libretto.pdf')]]);
        $workflow=app(InsuranceQuoteWorkflow::class);
        $quote=$workflow->request($case,$this->tua->id,'2026-10-28');
        $workflow->change($quote,'receive',['reference'=>'P-1029','product'=>'Auto','received_on'=>'2026-10-28','starts_on'=>'2026-10-29','expires_on'=>'2027-10-29','valid_until'=>'2026-11-30','premium'=>'840.00','payment_frequency'=>'Semestrale'],[$this->pdf('proposta.pdf')]);
        $workflow->change($quote,'submit',[]);
        $workflow->change($quote,'approve',['approved_on'=>'2026-10-28'],[$this->pdf('approvazione.pdf')]);
        $this->get(route('policies.create',['quote_id'=>$quote->id]))->assertOk()->assertDontSee('type="hidden" name="merit_class_internal"',false);
        $input=array_replace($workflow->policyValues($quote->fresh()),['client_id'=>$this->client->id,'entry_mode'=>'new','insurance_quote_id'=>$quote->id,'number'=>'SA-2026-619','status'=>'active','merit_class_cu'=>13,'merit_class_internal'=>'T13']);
        $this->post(route('policies.store'),$input)->assertSessionHasNoErrors()->assertRedirect();
        $policy=$quote->policy()->sole();
        $this->assertSame($vehicle->id,$policy->vehicle_id);
        $this->assertSame(13,$policy->merit_class_cu);
        $this->assertSame('T13',$policy->merit_class_internal);
        $this->assertSame(3,$policy->documents()->count());
        $this->assertSame(14,$case->fresh()->merit_class_cu);
        $this->assertSame('A2',$case->fresh()->merit_class_internal);
    }

    public function test_groups_work_for_non_motor_branches_without_vehicle_or_merit_class(): void
    {
        $home=InsuranceBranch::create(['name'=>'Abitazione','name_key'=>hash('sha256','ABITAZIONE'),'status'=>'active']);
        $fire=$home->subbranches()->create(['name'=>'Incendio','name_key'=>hash('sha256','INCENDIO'),'status'=>'active']);
        $data=['name'=>'Casa e incendio','insurance_branch_id'=>$home->id,'insurance_subbranch_id'=>null,'cu_from'=>null,'cu_to'=>null,'status'=>'active','company_ids'=>[$this->allianz->id]];
        $this->post(route('quote-groups.store'),$data)->assertSessionHasNoErrors();
        $group=QuoteRecipientGroup::where('name','Casa e incendio')->sole();
        $this->assertNull($group->cu_from);
        $this->get(route('quote-groups.index',['branch'=>$home->id]))->assertOk()->assertSee('Casa e incendio')->assertDontSee('Classe 14');
        $this->post(route('quotes.store'),$this->input(['insurance_branch_id'=>$home->id]))->assertSessionHasErrors('quote_recipient_group_id');
        $this->post(route('quotes.store'),$this->input(['insurance_branch_id'=>$home->id,'quote_recipient_group_id'=>$this->group->id]))->assertSessionHasErrors('quote_recipient_group_id');
        $case=$this->createCase(['insurance_branch_id'=>$home->id,'insurance_subbranch_id'=>$fire->id,'quote_recipient_group_id'=>$group->id]);
        $this->assertNull($case->vehicle_id);
        $this->assertNull($case->merit_class_cu);
        $this->assertSame('Abitazione',$case->recipient_group_snapshot['branch']);
        $this->get(route('quotes.emails.create',$case))->assertOk()->assertSee('Casa e incendio')->assertDontSee('· CU ');
        $this->post(route('quotes.request',$case),['supplier_company_id'=>$this->allianz->id,'requested_on'=>'2026-10-28','confirmed'=>1])->assertSessionHasNoErrors();
        $this->post(route('quote-groups.store'),array_replace($data,['name'=>'Sottoramo errato','insurance_branch_id'=>$this->branch->id,'insurance_subbranch_id'=>$fire->id]))->assertSessionHasErrors('insurance_subbranch_id');
    }

    public function test_subbranch_specific_groups_do_not_match_other_subbranches_and_keep_catalog_links(): void
    {
        $branch=InsuranceBranch::create(['name'=>'Infortuni','name_key'=>hash('sha256','INFORTUNI'),'status'=>'active']);
        $first=$branch->subbranches()->create(['name'=>'Professionali','name_key'=>hash('sha256','PROFESSIONALI'),'status'=>'active']);
        $second=$branch->subbranches()->create(['name'=>'Tempo libero','name_key'=>hash('sha256','TEMPO LIBERO'),'status'=>'active']);
        $group=QuoteRecipientGroup::create(['name'=>'Lavoro','insurance_branch_id'=>$branch->id,'insurance_subbranch_id'=>$first->id,'status'=>'active']);
        $group->companies()->attach($this->tua->id);
        $this->post(route('quotes.store'),$this->input(['insurance_branch_id'=>$branch->id,'insurance_subbranch_id'=>$second->id,'quote_recipient_group_id'=>$group->id]))->assertSessionHasErrors('quote_recipient_group_id');
        $case=$this->createCase(['insurance_branch_id'=>$branch->id,'insurance_subbranch_id'=>$first->id,'quote_recipient_group_id'=>$group->id]);
        $this->assertSame([$this->tua->id],$case->availableCompanies()->pluck('id')->all());
        $this->delete(route('insurance-catalog.destroy',['subbranch',$first->id]))->assertSessionHasErrors('catalog');
        $this->patch(route('quote-groups.update',$group),['name'=>'Lavoro','insurance_branch_id'=>$branch->id,'insurance_subbranch_id'=>$first->id,'cu_from'=>1,'cu_to'=>null,'status'=>'active','company_ids'=>[$this->tua->id]])->assertSessionHasErrors('cu_to');
    }
}
