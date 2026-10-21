<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AgencyCommunication, Client, InsuranceBranch, InsurancePolicy, InsurancePolicyChange, InsuranceQuoteCase, SupplierAgency, SupplierCompany, User};
use App\Services\{AgencyCommunicationDocuments, AgencyEmailTransport, AgencyMailConfiguration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class AgencyPolicyChangeCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
    private InsurancePolicy $policy;
    private SupplierAgency $agency;
    private SupplierCompany $company;
    private InsuranceBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        Storage::fake('attachments');
        $this->admin = User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['name'=>'Elena Varesi']);
        $this->agency = SupplierAgency::create(['name'=>'Agenzia Valserena','email'=>'valserena@example.test','status'=>'active']);
        $this->company = SupplierCompany::create(['name'=>'Compagnia Alba','status'=>'active']);
        $this->branch = InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $this->policy = $this->client->policies()->create(['number'=>'SA-2026-3101','category'=>'RC auto','company'=>$this->company->name,
            'supplier_agency_id'=>$this->agency->id,'supplier_company_id'=>$this->company->id,'insurance_branch_id'=>$this->branch->id,
            'starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','status'=>'active','premium'=>'1200.00','payment_frequency'=>'Annuale',
            'payment_method'=>'Bonifico a Intermediario','created_at'=>'2026-01-01 09:00:00']);
        $this->policy->forceFill(['created_at'=>'2026-01-01 09:00:00'])->save();
        $settings = $this->mock(AgencyMailConfiguration::class);
        $settings->shouldReceive('sender')->andReturn(['address'=>'sender@example.test','name'=>'Soluzioni Assicurative SAS']);
        $settings->shouldReceive('ready')->andReturn(true);
        $settings->shouldReceive('transport')->andReturn(['transport'=>'array']);
    }

    private function change(int $premium = 9000, ?InsurancePolicy $policy = null, array $extra = []): InsurancePolicyChange
    {
        $policy ??= $this->policy;
        $case = InsuranceQuoteCase::create(['client_id'=>$policy->client_id,'insurance_branch_id'=>$this->branch->id,'purpose'=>'addition',
            'current_policy_id'=>$policy->id,'subject'=>'Garanzia cristalli','status'=>'closed']);
        $quote = $case->quotes()->create(['supplier_company_id'=>$this->company->id,'supplier_agency_id'=>$policy->supplier_agency_id,
            'requested_at'=>now(),'status'=>'converted','premium_cents'=>$premium]);
        return InsurancePolicyChange::create(array_replace(['source_policy_id'=>$policy->id,'result_policy_id'=>$policy->id,'insurance_quote_id'=>$quote->id,
            'kind'=>'addition','effective_on'=>'2026-10-20','expires_on'=>'2027-01-01','reference'=>'APP-'.$quote->id,
            'description'=>'Garanzia cristalli concordata con il contraente.','premium_cents'=>$premium,'snapshot'=>[],
            'request_key'=>(string)Str::uuid(),'created_by'=>$this->admin->id],$extra));
    }

    private function review(array $keys, array $filters = []): AgencyCommunication
    {
        $this->post(route('agency-communications.review',$this->agency),$filters+['operations'=>$keys,'period'=>'day','date'=>'2026-10-20'])
            ->assertSessionHasNoErrors()->assertRedirect();
        return AgencyCommunication::latest('id')->firstOrFail();
    }

    private function document(string $name, ?InsurancePolicyChange $change = null, ?Client $client = null)
    {
        $path = Str::uuid().'.pdf'; $bytes = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF";
        Storage::disk('attachments')->put($path,$bytes);
        return ($client ?? $this->client)->attachments()->create(['disk'=>'attachments','path'=>$path,'stored_name'=>$path,'original_name'=>$name,'mime_type'=>'application/pdf','size'=>strlen($bytes),
            'uploaded_by'=>$this->admin->id,'insurance_policy_id'=>$change?->result_policy_id,'insurance_policy_change_id'=>$change?->id,'document_category'=>'other']);
    }

    public function test_daily_filters_pagination_and_export_keep_every_appendix_of_an_old_policy_once(): void
    {
        $ids = [];
        for ($i=0; $i<28; $i++) $ids[] = $this->change()->id;
        $this->change(extra:['created_at'=>'2026-10-19 23:59:59','reference'=>'APP-PREVIOUS-DAY']);
        $url = route('agency-communications.index',['date'=>'2026-10-20','agency'=>$this->agency->id,'company'=>$this->company->name]);
        $first = $this->get($url)->assertOk()->assertSee('Premio dell’operazione')->viewData('policies');
        $second = $this->get($url.'&page=2')->assertOk()->viewData('policies');
        $this->assertSame(28,$first->total());
        $this->assertSame($ids,$first->getCollection()->concat($second->getCollection())->map(fn ($p)=>$p->communicationChange->id)->all());
        $all = $this->get($url.'&_table_all=1',['X-Table-Export'=>'1'])->assertOk()->viewData('policies');
        $this->assertSame(28,$all->count());
        $this->get($url.'&operation=created')->assertViewHas('policies',fn ($p)=>$p->total()===0);
        $this->get($url.'&operation=addition&search=APP-'.$ids[27])->assertViewHas('policies',fn ($p)=>$p->total()===1);
        $this->get(route('agency-communications.index',['period'=>'custom','from'=>'2026-10-19','to'=>'2026-10-20','operation'=>'addition']))
            ->assertViewHas('policies',fn ($p)=>$p->total()===29);
        $this->get(route('agency-communications.index',['operation'=>'addition','company'=>'Compagnia diversa']))->assertViewHas('policies',fn ($p)=>$p->total()===0);
    }

    public function test_mixed_review_sends_exact_prices_and_multiple_attachments_only_after_confirmation(): void
    {
        $this->policy->forceFill(['created_at'=>now(),'ad_horas'=>true])->save();
        $change = $this->change();
        $message = $this->review([(string)$this->policy->id,'change-'.$change->id]);
        $this->assertSame([$this->policy->id],$message->payload['policy_ids']);
        $this->assertSame(9000,$message->payload['changes'][0]['premium_cents']);
        $this->assertStringContainsString('Premio della polizza: 1.200,00 €',$message->payload['body']);
        $this->assertStringContainsString('Premio aggiuntivo concordato: 90,00 €',$message->payload['body']);
        $this->assertStringStartsWith('Ad horas',$message->payload['subject']);
        $general = $this->document('identita.pdf'); $appendix = $this->document('appendice.pdf',$change);
        $this->post(route('agency-communications.documents',$message),['attachment_revision'=>0,'document_ids'=>[$general->id,$appendix->id],
            'files'=>[UploadedFile::fake()->image('firma-fronte.png'),UploadedFile::fake()->image('firma-retro.png')]])->assertSessionHasNoErrors();
        $message->refresh();
        $this->assertCount(4,$message->payload['attachments']);
        $this->get(route('agency-communications.show',$message))->assertOk()->assertSee($change->reference)->assertSee('90,00 €')->assertSee('1.200,00 €');
        $this->mock(AgencyEmailTransport::class)->shouldReceive('send')->once()->withArgs(function ($payload) use ($message) {
            $this->assertSame($message->deliveryPayload()['body'],$payload['body']);
            $this->assertCount(4,$payload['_attachments']);
            $this->assertSame('valserena@example.test',$payload['recipient']['address']);
            return true;
        })->andReturn('accepted@example.test');
        $this->post(route('agency-communications.send',$message),['attachment_revision'=>1])->assertSessionHasErrors('reviewed');
        $this->post(route('agency-communications.send',$message),['reviewed'=>1,'attachment_revision'=>0])->assertSessionHasErrors('documents');
        foreach ([1,2] as $attempt) $this->post(route('agency-communications.send',$message),['reviewed'=>1,'attachment_revision'=>1])->assertSessionHasNoErrors();
        $this->assertSame('sent',$message->fresh()->status);
        $this->assertSame(1,DB::table('agency_communication_changes')->whereNotNull('delivery_key')->count());
        $this->assertSame(1,DB::table('agency_communication_policies')->whereNotNull('delivery_key')->count());
        $this->assertStringNotContainsString($change->description,DB::table('agency_communications')->value('payload'));
    }

    public function test_each_appendix_is_independent_from_the_base_policy_and_cannot_be_resent_after_a_contact_change(): void
    {
        $this->policy->forceFill(['created_at'=>now()])->save();
        $one=$this->change(); $two=$this->change(0);
        $base=$this->review([(string)$this->policy->id]);
        $first=$this->review(['change-'.$one->id]); $second=$this->review(['change-'.$two->id]);
        $overlap=$this->review(['change-'.$one->id,'change-'.$two->id]);
        $this->mock(AgencyEmailTransport::class)->shouldReceive('send')->times(3)->andReturn('accepted@example.test');
        foreach ([$base,$first] as $message) $this->post(route('agency-communications.send',$message),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->post(route('agency-communications.send',$overlap),['reviewed'=>1])->assertSessionHasErrors('policies');
        $this->post(route('agency-communications.send',$second),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->assertStringContainsString('0,00 €',$second->payload['body']);
        $this->agency->update(['email'=>'updated@example.test']);
        $duplicate=$this->review(['change-'.$one->id]);
        $this->post(route('agency-communications.send',$duplicate),['reviewed'=>1])->assertSessionHasErrors('policies');
        $this->assertSame(2,DB::table('agency_communication_changes')->whereNotNull('delivery_key')->count());
        $this->assertSame(1,DB::table('agency_communication_policies')->whereNotNull('delivery_key')->count());
        $this->get(route('agency-communications.index'))->assertOk()->assertSee('Inviata al server di posta');
    }

    public function test_appendix_documents_are_scoped_and_cannot_be_taken_from_an_unselected_appendix_or_client(): void
    {
        $one=$this->change(); $two=$this->change();
        $selected=$this->document('selezionata.pdf',$one); $unselected=$this->document('non-selezionata.pdf',$two);
        $general=$this->document('anagrafica.pdf'); $foreign=$this->document('estraneo.pdf',client:Client::factory()->create());
        $message=$this->review(['change-'.$one->id]);
        $this->get(route('agency-communications.show',$message))->assertOk()->assertSee('selezionata.pdf')->assertSee('anagrafica.pdf')->assertDontSee('non-selezionata.pdf')->assertDontSee('estraneo.pdf');
        $this->get(route('agency-communications.history'))->assertOk()->assertSee('<td>0</td><td>1</td>', false);
        $this->assertSame([$selected->id,$general->id],app(AgencyCommunicationDocuments::class)->available($message,$this->admin)->pluck('id')->all());
        foreach ([$unselected,$foreign] as $file) $this->post(route('agency-communications.documents',$message),['attachment_revision'=>0,'document_ids'=>[$file->id]])->assertSessionHasErrors('documents');
        $this->assertSame(0,$message->documents()->count());
    }

    public function test_review_rejects_foreign_agency_wrong_date_duplicate_keys_inactive_contract_and_operator_access(): void
    {
        $change=$this->change();
        $url=route('agency-communications.review',$this->agency);
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
        $this->post($url,['operations'=>['change-'.$change->id],'date'=>'2026-10-19'])->assertSessionHasErrors('policies');
        $this->post($url,['operations'=>['change-'.$change->id,'change-'.$change->id]])->assertSessionHasErrors('operations.0');
        $this->post($url,['operations'=>['change-'.$change->id],'policies'=>[$this->policy->id]])->assertSessionHasErrors('operations');
        $other=SupplierAgency::create(['name'=>'Agenzia Colle','email'=>'colle@example.test','status'=>'active']);
        $this->post(route('agency-communications.review',$other),['operations'=>['change-'.$change->id]])->assertSessionHasErrors('policies');
        $this->policy->update(['status'=>'cancelled']);
        $this->post($url,['operations'=>['change-'.$change->id]])->assertSessionHasErrors('policies');
        $this->policy->update(['status'=>'active']);
        $message=$this->review(['change-'.$change->id]);
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator]));
        $this->get(route('agency-communications.index'))->assertForbidden();
        $this->post($url,['operations'=>['change-'.$change->id]])->assertForbidden();
        $this->get(route('agency-communications.show',$message))->assertForbidden();
        $this->post(route('agency-communications.send',$message),['reviewed'=>1])->assertForbidden();
    }

    public function test_stale_appendix_or_client_and_missing_attachment_require_a_new_control_before_send(): void
    {
        $change=$this->change();
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
        $message=$this->review(['change-'.$change->id]);
        $this->client->update(['name'=>'Elena Varesi aggiornata']);
        $this->post(route('agency-communications.send',$message),['reviewed'=>1])->assertSessionHasErrors('email');
        $message=$this->review(['change-'.$change->id]);
        DB::table('insurance_policy_changes')->where('id',$change->id)->update(['premium_cents'=>9500]);
        $this->post(route('agency-communications.send',$message),['reviewed'=>1])->assertSessionHasErrors('email');
        $message=$this->review(['change-'.$change->id]);
        $doc=$this->document('appendice.pdf',$change);
        $this->post(route('agency-communications.documents',$message),['attachment_revision'=>0,'document_ids'=>[$doc->id]])->assertSessionHasNoErrors();
        Storage::disk('attachments')->delete($message->documents()->sole()->path);
        $this->post(route('agency-communications.send',$message),['reviewed'=>1,'attachment_revision'=>1])->assertSessionHasErrors('documents');
        $this->assertSame(0,DB::table('agency_communication_changes')->whereNotNull('delivery_key')->count());
    }

    public function test_uncertain_appendix_delivery_blocks_retries_until_explicit_resolution(): void
    {
        $change=$this->change(); $first=$this->review(['change-'.$change->id]); $second=$this->review(['change-'.$change->id]);
        $transport=$this->mock(AgencyEmailTransport::class);
        $transport->shouldReceive('send')->once()->andThrow(new \RuntimeException('Simulated connection failure'));
        $this->post(route('agency-communications.send',$first),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->assertSame('uncertain',$first->fresh()->status);
        $this->post(route('agency-communications.send',$second),['reviewed'=>1])->assertSessionHasErrors('policies');
        $this->post(route('agency-communications.resolve',$first),['checked'=>1,'outcome'=>'not_sent','note'=>'Verificato con il servizio di posta'])->assertSessionHasNoErrors();
        $this->assertSame(0,DB::table('agency_communication_changes')->whereNotNull('delivery_key')->count());
        $transport->shouldReceive('send')->once()->andReturn('accepted@example.test');
        $this->post(route('agency-communications.send',$second),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->assertSame('sent',$second->fresh()->status);
    }
}
