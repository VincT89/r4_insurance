<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsurancePolicy, SupplierCompany, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PolicyContractCreationTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator, 'status'=>'active']));
        $this->client = Client::factory()->create();
        SupplierCompany::create(['name'=>'Compagnia dimostrativa', 'status'=>'active']);
        Storage::fake('attachments');
    }

    private function input(array $extra = []): array
    {
        return array_replace([
            'entry_mode'=>'contract', 'client_id'=>$this->client->id, 'category'=>'RC auto', 'company'=>'Compagnia dimostrativa',
            'number'=>'CONTRATTO-2026-001', 'status'=>'active', 'starts_on'=>'2026-11-02', 'expires_on'=>'2027-11-02',
            'premium'=>'1200.01', 'payment_frequency'=>'Semestrale', 'payment_method'=>'Bonifico a Intermediario',
            'contract'=>UploadedFile::fake()->createWithContent('contratto.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"),
            'contract_reviewed'=>1, 'titles_mode'=>'automatic',
        ], $extra);
    }

    private function preview(array $input): string
    {
        return $this->postJson(route('policies.contract-schedule'), $input)->assertOk()->json('fingerprint');
    }

    public function test_real_unipol_first_and_subsequent_premiums_create_the_exact_six_titles(): void
    {
        $input=$this->input(['starts_on'=>'2023-07-31','expires_on'=>'2026-07-31','premium'=>'164850.45','first_installment_premium'=>'27475.00','installment_premium'=>'27475.09']);
        $input['schedule_fingerprint']=$this->preview($input);
        $this->postJson(route('policies.store'),$input)->assertOk();
        $amounts=InsurancePolicy::sole()->titles()->orderBy('due_on')->pluck('amount_cents')->map(fn($value)=>(int)$value)->all();
        $this->assertSame([2747500,2747509,2747509,2747509,2747509,2747509],$amounts);
        $this->assertSame(16485045,array_sum($amounts));
        $this->assertDatabaseCount('insurance_collections',0);
    }

    public function test_explicit_short_first_period_creates_no_titles_after_contract_expiry(): void
    {
        $input=$this->input(['starts_on'=>'2016-06-01','expires_on'=>'2016-12-31','premium'=>'5000.00','first_installment_premium'=>'5000.00','payment_frequency'=>'Annuale']);
        $input['schedule_fingerprint']=$this->preview($input);
        $this->postJson(route('policies.store'),$input)->assertOk();
        $title=InsurancePolicy::sole()->titles()->sole();
        $this->assertSame(500000,(int)$title->amount_cents);
        $this->assertSame('2016-12-31',$title->coverage_to->toDateString());
    }

    public function test_a_changed_first_premium_cannot_reuse_a_reviewed_schedule(): void
    {
        $input=$this->input(['premium'=>'1200.00','first_installment_premium'=>'500.00','installment_premium'=>'700.00']);
        $input['schedule_fingerprint']=$this->preview($input);
        $input['first_installment_premium']='600.00'; $input['installment_premium']='600.00';
        $this->postJson(route('policies.store'),$input)->assertUnprocessable()->assertJsonValidationErrors('schedule_fingerprint');
        $this->assertDatabaseCount('insurance_policies',0);
        $this->assertDatabaseCount('insurance_titles',0);
    }

    public function test_first_and_subsequent_premiums_must_reconcile_to_the_total(): void
    {
        $input=$this->input(['premium'=>'1200.00','first_installment_premium'=>'500.00','installment_premium'=>'600.00']);
        $this->postJson(route('policies.contract-schedule'),$input)->assertUnprocessable()->assertJsonValidationErrors('ledger');
        $this->assertDatabaseCount('insurance_titles',0);
    }

    public function test_an_undisclosed_renewal_clause_is_not_changed_to_yes(): void
    {
        $input=$this->input(['titles_mode'=>'manual','tacit_renewal'=>null]);
        $this->postJson(route('policies.store'),$input)->assertOk();
        $this->assertNull(InsurancePolicy::sole()->tacit_renewal);
    }

    public function test_creation_starts_with_contract_with_or_without_a_quote(): void
    {
        $this->get(route('policies.create'))->assertOk()->assertSee('Carica il contratto')->assertSee('data-contract-fields hidden', false)->assertSee('name="contract"', false);
        $this->get(route('clients.policies.create', $this->client))->assertOk()->assertSee('Carica il contratto');
        $this->assertDatabaseCount('insurance_policies', 0);
    }

    public function test_operator_confirms_contract_and_creates_policy_private_document_and_exact_titles_atomically(): void
    {
        $input = $this->input();
        $input['schedule_fingerprint'] = $this->preview($input);
        $this->assertDatabaseCount('insurance_titles', 0);
        $response = $this->postJson(route('policies.store'), $input)->assertOk();
        $policy = InsurancePolicy::sole();
        $this->assertSame('CONTRATTO-2026-001', $policy->number);
        $this->assertSame('Semestrale', $policy->payment_frequency);
        $this->assertEquals([60000,60001], $policy->titles()->orderBy('due_on')->pluck('amount_cents')->all());
        $this->assertEquals(['2026-11-02','2027-05-02'], $policy->titles()->orderBy('due_on')->get()->map(fn($title)=>$title->due_on->toDateString())->all());
        $this->assertDatabaseCount('insurance_collections', 0);
        $document = $policy->contracts()->sole();
        Storage::disk('attachments')->assertExists($document->path);
        $this->assertEquals($this->client->id, $document->attachable_id);
        $this->assertSame(\App\Services\PolicyFormReminder::url($policy), $response->json('url'));
        $this->get($response->json('url'))->assertOk()->assertSee($policy->number)->assertSee('Moduli della nuova polizza')->assertSee('Più tardi');
        $this->postJson(route('policies.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('number');
        $this->assertDatabaseCount('insurance_policies', 1);
    }

    public function test_contract_and_human_confirmation_are_required_and_fake_pdf_is_rejected(): void
    {
        $this->postJson(route('policies.store'), $this->input(['contract'=>null, 'contract_reviewed'=>0]))->assertUnprocessable()->assertJsonValidationErrors(['contract','contract_reviewed']);
        $this->postJson(route('policies.store'), $this->input(['contract'=>UploadedFile::fake()->createWithContent('falso.pdf', '<script>alert(1)</script>')->mimeType('text/html')]))->assertUnprocessable()->assertJsonValidationErrors('contract');
        $this->assertDatabaseCount('insurance_policies', 0);
        $this->assertCount(0, Storage::disk('attachments')->allFiles());
    }

    public function test_changed_preview_rolls_back_policy_client_and_file(): void
    {
        $count = Client::count();
        $input = $this->input(['client_id'=>null, 'new_client'=>['name'=>'Nuovo contraente demo','client_type'=>'person']]);
        $input['schedule_fingerprint'] = $this->preview($input);
        $input['premium'] = '1500.00';
        $this->postJson(route('policies.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('schedule_fingerprint');
        $this->assertEquals($count, Client::count());
        $this->assertDatabaseCount('insurance_policies', 0);
        $this->assertDatabaseCount('insurance_titles', 0);
        $this->assertCount(0, Storage::disk('attachments')->allFiles());
    }

    public function test_new_client_and_policy_are_saved_together_and_fiscal_duplicates_cannot_be_created(): void
    {
        $input = $this->input(['client_id'=>null, 'new_client'=>['name'=>'Contraente dimostrativo','client_type'=>'person','tax_code'=>' rssmra80a01h501u ']]);
        $input['schedule_fingerprint'] = $this->preview($input);
        $this->postJson(route('policies.store'), $input)->assertOk();
        $policy = InsurancePolicy::sole();
        $this->assertSame('RSSMRA80A01H501U', $policy->client->tax_code);
        $this->assertSame('Contraente dimostrativo', $policy->client->name);
        $input['number'] = 'CONTRATTO-2026-002';
        $this->postJson(route('policies.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('new_client.tax_code');
        $this->assertDatabaseCount('insurance_policies', 1);
    }

    public function test_both_fiscal_identifiers_are_checked_and_conflicting_clients_are_not_merged(): void
    {
        $this->client->update(['vat_number'=>'01234567897']);
        Client::factory()->create(['tax_code'=>'RSSMRA80A01H501U']);
        $input = $this->input(['client_id'=>null, 'titles_mode'=>'manual', 'new_client'=>['name'=>'Dati in conflitto','vat_number'=>'01234567897','tax_code'=>'RSSMRA80A01H501U']]);
        $this->postJson(route('policies.store'), $input)->assertUnprocessable()->assertJsonValidationErrors(['new_client.tax_code','new_client.vat_number']);
        $this->assertDatabaseCount('insurance_policies', 0);
        $input['new_client'] = ['name'=>'Dati non validi','vat_number'=>'01234567890','tax_code'=>'RSSMRA80A01H501A'];
        $this->postJson(route('policies.store'), $input)->assertUnprocessable()->assertJsonValidationErrors(['new_client.tax_code','new_client.vat_number']);
    }

    public function test_company_name_is_preserved_when_creating_the_contracting_company(): void
    {
        $input = $this->input(['client_id'=>null, 'titles_mode'=>'manual', 'new_client'=>['name'=>'Società dimostrativa SRL','client_type'=>'company','vat_number'=>'01234567897']]);
        $this->postJson(route('policies.store'), $input)->assertOk();
        $client = InsurancePolicy::sole()->client;
        $this->assertSame('Società dimostrativa SRL', $client->company_name);
        $this->assertSame('01234567897', $client->vat_number);
    }

    public function test_manual_schedule_is_an_explicit_choice_for_irregular_contracts(): void
    {
        $input = $this->input(['expires_on'=>'2027-03-02']);
        $this->postJson(route('policies.contract-schedule'), $input)->assertUnprocessable()->assertJsonValidationErrors('ledger');
        $input['titles_mode'] = 'manual';
        $this->postJson(route('policies.store'), $input)->assertOk();
        $this->assertDatabaseCount('insurance_titles', 0);
        $this->assertTrue(InsurancePolicy::sole()->client->auditLogs()->where('action','titles_pending')->exists());
    }

    public function test_unrelated_vehicle_and_tampered_client_fields_cannot_be_attached(): void
    {
        $other = Client::factory()->create();
        $vehicle = $other->vehicles()->create(['plate'=>'AA123BB']);
        $input = $this->input(['titles_mode'=>'manual', 'vehicle_id'=>$vehicle->id]);
        $this->postJson(route('policies.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
        $input = $this->input(['client_id'=>null, 'titles_mode'=>'manual', 'new_client'=>['name'=>'Contraente demo','commercial_user_id'=>999]]);
        $this->postJson(route('policies.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('new_client');
        $this->assertDatabaseCount('insurance_policies', 0);
    }
}
