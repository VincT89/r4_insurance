<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AiSetting, AiUsage, Client, InsuranceBranch, InsuranceQuote, InsuranceQuoteCase, InsuranceQuoteReading, InsuranceQuoteReview, SupplierCompany, User};
use App\Services\{AiQuoteSchema, ClientDocumentRegistry, InsuranceQuoteWorkflow, QuoteComparison};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteComparisonTest extends TestCase
{
    use RefreshDatabase;
    private InsuranceQuoteCase $case;
    private InsuranceQuote $first;
    private InsuranceQuote $second;

    protected function beforeRefreshingDatabase(): void
    {
        if(config('database.default')!=='sqlite' || config('database.connections.sqlite.database')!==':memory:') throw new \LogicException('I collaudi dei preventivi richiedono SQLite in memoria.');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,11,11)->setTime(12,0));
        Storage::fake('attachments');
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']));
        $client=Client::factory()->create(['name'=>'Cliente dimostrativo confronto']);
        $branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $this->case=InsuranceQuoteCase::create(['client_id'=>$client->id,'insurance_branch_id'=>$branch->id,'subject'=>'Pratica dimostrativa confronto','vehicle_plate'=>'AA111AA','vehicle_snapshot'=>['plate'=>'AA111AA']]);
        $this->first=$this->offer('Compagnia dimostrativa A',62000);
        $this->second=$this->offer('Compagnia dimostrativa B',57000);
    }

    private function offer(string $name,int $premium,?InsuranceQuoteCase $case=null): InsuranceQuote
    {
        $company=SupplierCompany::create(['name'=>$name,'status'=>'active']);
        return ($case??$this->case)->quotes()->create(['supplier_company_id'=>$company->id,'status'=>'received','requested_at'=>now()->subDay(),'received_at'=>now(),'reference'=>'DEMO-'.$company->id,'product'=>'Prodotto dimostrativo','premium_cents'=>$premium,'starts_on'=>'2027-01-01','expires_on'=>'2027-12-31','valid_until'=>'2026-11-30','payment_frequency'=>'Unico']);
    }

    private function document(InsuranceQuote $quote,string $suffix='')
    {
        return app(ClientDocumentRegistry::class)->upload($quote->quoteCase->client,UploadedFile::fake()->createWithContent('offerta.pdf',"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n".$suffix),['document_category'=>'quote','insurance_quote_id'=>$quote->id]);
    }

    private function extraction(array $fields=[],array $coverages=[]): array { return ['document_kind'=>'quotation','fields'=>$fields,'coverages'=>$coverages,'warnings'=>[]]; }
    private function field(string $key,?string $value,?int $page=1,string $quote='Citazione dimostrativa'): array { return ['key'=>$key,'value'=>$value,'page'=>$page,'quote'=>$quote,'certainty'=>'certain']; }
    private function reading(InsuranceQuote $offer,array $extraction)
    {
        $document=$this->document($offer);
        InsuranceQuoteReading::create(['insurance_quote_id'=>$offer->id,'attachment_id'=>$document->id,'extraction'=>$extraction]);
        return $document;
    }

    public function test_summary_and_comparison_render_real_records_without_merging_workflow_states(): void
    {
        $this->document($this->first); $this->document($this->second);
        $this->get(route('quotes.offers'))->assertOk()->assertSee('Compagnia dimostrativa A')->assertSee('Da verificare')->assertSee('620,00');
        $this->get(route('quotes.compare',['quotes'=>[$this->first->id,$this->second->id]]))->assertOk()->assertSee('Mostra solo differenze')->assertSee('Non indicata')->assertSee('Stessa pratica e stesso periodo');
        $order=[$this->second->id,$this->first->id];
        $this->post(route('quotes.comparison.update',[$this->second,'prefer']),['selected'=>1,'comparison_ids'=>$order])->assertRedirect(route('quotes.compare',['quotes'=>$order]))->assertSessionHasNoErrors();
        $this->assertSame($this->second->id,$this->case->fresh()->preferred_quote_id);
        $this->assertSame('open',$this->case->fresh()->status);
        $this->assertSame('received',$this->second->fresh()->status);
        $this->assertDatabaseCount('insurance_policies',0);
    }

    public function test_cross_case_selection_and_unknown_ids_cannot_be_compared(): void
    {
        $other=$this->case->replicate(); $other->subject='Altra pratica'; $other->save();
        $third=$this->offer('Compagnia dimostrativa C',70000,$other);
        $this->document($this->first); $this->document($third);
        $this->getJson(route('quotes.compare',['quotes'=>[$this->first->id,$third->id]]))->assertUnprocessable();
        $this->get(route('quotes.compare',['quotes'=>[$this->first->id,999999]]))->assertNotFound();
        $this->second->update(['expires_on'=>'2027-06-30']); $this->document($this->second);
        $this->get(route('quotes.compare',['quotes'=>[$this->first->id,$this->second->id]]))->assertOk()->assertSee('Periodi diversi')->assertSee('570,00');
    }

    public function test_sources_stay_attached_to_their_document_and_untrusted_text_is_escaped(): void
    {
        $doc=$this->reading($this->first,$this->extraction([$this->field('driver_conditions','Solo conducente dichiarato',3,'<script>alert(1)</script>')]));
        $url=route('quotes.compare',['quotes'=>[$this->first->id],'source_quote'=>$this->first->id,'field'=>'driver_conditions','document'=>$doc->id,'page'=>3]);
        $response=$this->get($url)->assertOk()->assertSee('data-initial-page="3"',false)->assertDontSee('<script>alert(1)</script>',false)->assertSee('&lt;script&gt;',false);
        $this->assertStringNotContainsString('Solo conducente',InsuranceQuoteReading::sole()->getRawOriginal('extraction'));
        $foreign=$this->document($this->second);
        $this->get(route('quotes.compare',['quotes'=>[$this->first->id],'source_quote'=>$this->first->id,'field'=>'driver_conditions','document'=>$foreign->id,'page'=>3]))->assertNotFound();
        $this->getJson($url)->assertOk()->assertJsonStructure(['html']);
    }

    public function test_missing_excluded_and_conflicting_guarantees_have_different_meanings(): void
    {
        $this->reading($this->first,$this->extraction([], [['code'=>'cristalli','name'=>'Cristalli','fields'=>[$this->field('status','included',2),$this->field('deductible','€100',3)]]]));
        $this->reading($this->second,$this->extraction([], [['code'=>'cristalli','name'=>'Cristalli','fields'=>[$this->field('status','excluded',2)]]]));
        $service=app(QuoteComparison::class); $a=$service->cells($this->first); $b=$service->cells($this->second);
        $this->assertSame('Inclusa',$a['coverage.cristalli.status']['display']);
        $this->assertSame('Esclusa',$b['coverage.cristalli.status']['display']);
        $this->assertSame('Non indicata',$a['coverage.assistenza.status']['display']);
        $this->assertSame([],$a['coverage.assistenza.status']['sources']);
        $this->reading($this->first,$this->extraction([], [['code'=>'cristalli','name'=>'Cristalli','fields'=>[$this->field('deductible','€250',4)]]]));
        $this->assertTrue($service->cells($this->first->fresh())['coverage.cristalli.deductible']['uncertain']);
    }

    public function test_corrections_preserve_originals_and_stale_verification_is_rejected(): void
    {
        $this->reading($this->first,$this->extraction([$this->field('premium','620.00')]));
        $this->post(route('quotes.comparison.update',[$this->first,'correct']),['revision'=>0,'field'=>'premium','value'=>'610.00'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(62000,$this->first->fresh()->premium_cents);
        $this->postJson(route('quotes.comparison.update',[$this->first,'verify']),['revision'=>0,'confirmed'=>1,'verification_note'=>'Controllato il PDF.'])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->postJson(route('quotes.comparison.update',[$this->first,'verify']),['revision'=>1,'confirmed'=>1])->assertUnprocessable()->assertJsonValidationErrors('verification_note');
        $this->post(route('quotes.comparison.update',[$this->first,'verify']),['revision'=>1,'confirmed'=>1,'verification_note'=>'Controllato il rischio; garanzie non indicate da chiedere all’agenzia.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(61000,$this->first->fresh()->premium_cents);
        $this->assertSame('620.00',InsuranceQuoteReading::sole()->extraction['fields'][0]['value']);
        $review=InsuranceQuoteReview::sole(); $this->assertNotNull($review->verified_at);
        $this->assertSame('620.00',$review->history[0]['before']);
        $this->assertStringNotContainsString('610.00',$review->getRawOriginal('overrides'));
        $this->post(route('quotes.comparison.update',[$this->first,'correct']),['revision'=>2,'field'=>'premium','value'=>'600.00'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($review->fresh()->verified_at);
    }

    public function test_uploads_are_private_scoped_and_do_not_create_policies_or_paid_calls(): void
    {
        $upload=fn()=>UploadedFile::fake()->createWithContent('proposta.pdf',"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
        $this->post(route('quotes.offers.store',$this->case),['files'=>[$this->first->id=>[$upload()],$this->second->id=>[$upload()]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attachments',2); $this->assertDatabaseCount('insurance_quote_reviews',2);
        foreach($this->first->fresh()->documents as $document) { $this->assertSame('attachments',$document->disk); Storage::disk('attachments')->assertExists($document->path); }
        $this->postJson(route('quotes.offers.store',$this->case),['files'=>[999999=>[$upload()]]])->assertUnprocessable();
        $this->postJson(route('quotes.offers.store',$this->case),['files'=>[$this->first->id=>[UploadedFile::fake()->createWithContent('bad.pdf','<html>script</html>')->mimeType('text/html')]]])->assertUnprocessable();
        $this->assertDatabaseCount('insurance_policies',0); Http::assertNothingSent();
    }

    public function test_quote_ai_reading_uses_existing_budgets_and_records_consumption_once(): void
    {
        AiSetting::findOrFail(1)->update(['enabled'=>true,'api_key'=>'sk-'.Str::random(40)]);
        $doc=$this->document($this->first);
        $result=$this->extraction([$this->field('premium','620.00'),$this->field('vehicle.plate','ZZ999ZZ')]);
        Http::fake(['api.openai.com/v1/responses/input_tokens'=>Http::response(['input_tokens'=>6000]),'api.openai.com/v1/responses'=>Http::response(['id'=>'resp_quote_demo','status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($result)]]]],'usage'=>['input_tokens'=>6000,'output_tokens'=>1000]])]);
        $input=['document_id'=>$doc->id,'ai_processing'=>1];
        $this->postJson(route('quotes.comparison.read',$this->first),$input)->assertOk()->assertJsonPath('usage.tokens',7000);
        $this->postJson(route('quotes.comparison.read',$this->first),$input)->assertOk()->assertJsonPath('cached',true);
        Http::assertSentCount(2); $this->assertDatabaseCount('ai_usages',1);
        $usage=AiUsage::sole(); $this->assertSame('insurance_quote',$usage->feature); $this->assertEquals($this->first->id,$usage->insurance_quote_id); $this->assertEquals($doc->id,$usage->attachment_id);
        $this->get(route('ai.index'))->assertOk()->assertSee('Lettura preventivi')->assertSee('Offerta #'.$this->first->id);
        $this->get(route('quotes.compare',['quotes'=>[$this->first->id]]))->assertOk()->assertSee('diverso dai dati della pratica');
        Http::assertSent(fn($request)=>str_ends_with($request->url(),'/responses') && $request['store']===false && $request['text']['format']['name']==='insurance_quote');
    }

    public function test_budget_failure_never_starts_a_model_read_and_no_consent_sends_nothing(): void
    {
        $doc=$this->document($this->first);
        $this->postJson(route('quotes.comparison.read',$this->first),['document_id'=>$doc->id])->assertUnprocessable(); Http::assertNothingSent();
        AiSetting::findOrFail(1)->update(['enabled'=>true,'api_key'=>'sk-'.Str::random(40),'document_budget_microusd'=>1]);
        Http::fake(['api.openai.com/v1/responses/input_tokens'=>Http::response(['input_tokens'=>6000])]);
        $this->postJson(route('quotes.comparison.read',$this->first),['document_id'=>$doc->id,'ai_processing'=>1])->assertUnprocessable();
        Http::assertSentCount(1); $this->assertSame('rejected',AiUsage::sole()->state); $this->assertDatabaseCount('insurance_quote_readings',0);
    }

    public function test_another_offers_document_cannot_be_sent_to_ai_and_closed_quotes_cannot_be_modified(): void
    {
        $document=$this->document($this->second);
        $this->postJson(route('quotes.comparison.read',$this->first),['document_id'=>$document->id,'ai_processing'=>1])->assertNotFound();
        $this->first->update(['status'=>'pending']);
        $this->postJson(route('quotes.comparison.update',[$this->first,'correct']),['revision'=>0,'field'=>'premium','value'=>'1'])->assertUnprocessable();
        $this->case->update(['status'=>'approved']);
        $this->postJson(route('quotes.comparison.update',[$this->second,'prefer']),['selected'=>1])->assertUnprocessable(); Http::assertNothingSent();
        auth()->logout(); $this->get(route('quotes.offers'))->assertRedirect(route('login'));
    }

    public function test_schema_rejects_fabricated_sources_for_absence_and_requires_sources_for_inclusion(): void
    {
        $schema=app(AiQuoteSchema::class);
        $valid=$this->extraction([], [['code'=>'assistenza','name'=>'Assistenza','fields'=>[$this->field('status','not_indicated',null,'')]]]);
        $this->assertEquals($valid,$schema->validate($valid));
        $invalid=$valid; $invalid['coverages'][0]['fields'][0]['value']='included';
        $this->expectException(\UnexpectedValueException::class); $schema->validate($invalid);
    }

    public function test_equivalent_amounts_keep_the_original_source_without_a_false_conflict(): void
    {
        $this->reading($this->first,$this->extraction([$this->field('premium','620')]));
        $cell=app(QuoteComparison::class)->cells($this->first)['premium'];
        $this->assertFalse($cell['uncertain']);
        $this->assertSame('620',$cell['sources'][0]['value']);
        $this->assertSame('620.00',$cell['value']);
    }

    public function test_verification_registers_a_received_offer_without_approving_it(): void
    {
        $this->first->update(['status'=>'requested','received_at'=>null,'premium_cents'=>null,'reference'=>null,'product'=>null,'starts_on'=>null,'expires_on'=>null,'valid_until'=>null,'payment_frequency'=>null]);
        $values=['premium'=>'620.00','reference'=>'DEMO-PDF','product'=>'Prodotto dimostrativo','starts_on'=>'2027-01-01','expires_on'=>'2027-12-31','valid_until'=>'2026-11-30','payment_frequency'=>'Unico'];
        $this->reading($this->first,$this->extraction(collect($values)->map(fn($value,$key)=>$this->field($key,$value))->values()->all()));
        $this->post(route('quotes.comparison.update',[$this->first,'verify']),['revision'=>0,'confirmed'=>1,'verification_note'=>'Verificati i dati nel PDF; garanzie mancanti da chiarire.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('received',$this->first->fresh()->status);
        $this->assertSame(62000,$this->first->fresh()->premium_cents);
        $this->assertSame('open',$this->case->fresh()->status);
        app(InsuranceQuoteWorkflow::class)->change($this->first->fresh(),'submit',[]);
        $this->assertSame('pending',$this->first->fresh()->status);
        $this->assertDatabaseCount('insurance_policies',0);
    }

    public function test_unverified_comparison_blocks_submission_and_missing_pdf_blocks_verification(): void
    {
        $document=$this->document($this->first);
        InsuranceQuoteReview::create(['insurance_quote_id'=>$this->first->id,'revision'=>0]);
        try {
            app(InsuranceQuoteWorkflow::class)->change($this->first,'submit',[]);
            $this->fail('Una proposta non verificata non deve essere presentabile.');
        } catch(\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('quote',$exception->errors());
        }
        Storage::disk($document->disk)->delete($document->path);
        $this->postJson(route('quotes.comparison.update',[$this->first,'verify']),['revision'=>0,'confirmed'=>1,'verification_note'=>'Controllato'])->assertUnprocessable()->assertJsonValidationErrors('quote');
        $this->assertNull(InsuranceQuoteReview::sole()->verified_at);
    }
}
