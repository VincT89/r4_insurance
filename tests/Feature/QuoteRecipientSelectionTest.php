<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsuranceBranch, InsuranceQuoteEmail, InsuranceQuoteCase, OutboundMailSetting, QuoteRecipientGroup, SupplierAgency, SupplierCompany, User};
use App\Services\{QuoteRecipients, QuoteRequestEmailTransport, QuoteRequestEmailWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteRecipientSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private InsuranceQuoteCase $case;
    private SupplierCompany $company;
    private SupplierCompany $other;
    private SupplierAgency $agency;
    private SupplierAgency $secondAgency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,30)->setTime(12,0));
        Storage::fake('attachments');
        $this->admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->actingAs($this->admin);
        $client=Client::factory()->create();
        $branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $this->case=$client->quoteCases()->create(['insurance_branch_id'=>$branch->id,'subject'=>'Richiesta di prova','purpose'=>'new','status'=>'open','created_by'=>$this->admin->id]);
        $this->company=SupplierCompany::create(['name'=>'Compagnia di prova A','email'=>'company-a@example.test','status'=>'active']);
        $this->other=SupplierCompany::create(['name'=>'Compagnia di prova B','email'=>'company-b@example.test','status'=>'active']);
        $this->agency=SupplierAgency::create(['name'=>'Agenzia di prova uno','email'=>'agency-one@example.test','status'=>'active','supplier_company_id'=>$this->company->id]);
        $this->secondAgency=SupplierAgency::create(['name'=>'Agenzia di prova due','email'=>'agency-two@example.test','status'=>'active','supplier_company_id'=>$this->company->id]);
        $this->agency->companies()->attach($this->other->id);
        OutboundMailSetting::create(['credentials'=>['enabled'=>true,'host'=>'smtp.example.test','port'=>587,'encryption'=>'starttls','username'=>'sender@example.test','password'=>Str::random(32),'from_address'=>'sender@example.test','from_name'=>'Mittente di prova'],'updated_by'=>$this->admin->id]);
    }

    private function keys(): array
    {
        return [QuoteRecipients::key($this->company->id),QuoteRecipients::key($this->company->id,$this->agency->id),QuoteRecipients::key($this->company->id,$this->secondAgency->id)];
    }

    public function test_request_page_stays_available_after_a_response_received_outside_email(): void
    {
        $this->case->quotes()->create(['supplier_company_id'=>$this->company->id, 'supplier_agency_id'=>$this->agency->id,
            'recipient_key'=>QuoteRecipients::quoteKey($this->agency->id), 'status'=>'received', 'requested_at'=>now()->subDay(), 'received_at'=>now()]);
        $this->assertSame(0, $this->case->emails()->count());
        $this->get(route('quotes.emails.create', $this->case))->assertOk()->assertSee($this->secondAgency->name);
        $this->assertSame([QuoteRecipients::key($this->company->id, $this->agency->id)], app(QuoteRequestEmailWorkflow::class)->unavailableKeys($this->case)->all());
    }

    private function input(?array $keys=null, array $extra=[]): array
    {
        return array_replace(['recipient_selection_present'=>1,'recipient_keys'=>$keys ?? $this->keys(),'subject'=>'Richiesta di copertura','body'=>'Buongiorno, richiediamo il preventivo per il cliente e le coperture indicate.'],$extra);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('richiesta.pdf',"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }

    private function review(?array $keys=null, array $extra=[])
    {
        $response=$this->post(route('quotes.emails.review',$this->case),$this->input($keys,$extra))->assertSessionHasNoErrors()->assertRedirect();
        $batch=basename($response->headers->get('Location'));
        return InsuranceQuoteEmail::where('review_batch_id',$batch)->get();
    }

    public function test_group_saves_exact_company_agency_pairs_and_single_class(): void
    {
        $keys=[QuoteRecipients::key($this->company->id,$this->agency->id),QuoteRecipients::key($this->other->id,$this->agency->id)];
        $data=['name'=>'Gruppo CU 14','insurance_branch_id'=>$this->case->insurance_branch_id,'cu_from'=>14,'cu_to'=>null,'status'=>'active','recipient_keys'=>$keys];
        $this->post(route('quote-groups.store'),$data)->assertSessionHasNoErrors();
        $group=QuoteRecipientGroup::sole();
        $this->assertSame($keys,$group->recipientKeys());
        $this->assertSame($keys,\App\Models\AuditLog::where('action','quote_group_updated')->sole()->new_values['recipient_keys']);
        $this->assertSame(14,$group->cu_to);
        $this->assertEqualsCanonicalizing([$this->company->id,$this->other->id],$group->companies->modelKeys());
        $this->get(route('quote-groups.edit',$group))->assertOk()->assertSee('Tramite '.$this->agency->name);
        $this->get(route('quote-groups.index'))->assertOk()->assertSee($this->company->name.' tramite '.$this->agency->name);
        $this->post(route('quote-groups.store'),array_replace($data,['name'=>'Collegamento errato','recipient_keys'=>[QuoteRecipients::key($this->other->id,$this->secondAgency->id)]]))->assertSessionHasErrors('recipient_keys');
        $this->assertSame(1,QuoteRecipientGroup::count());
    }

    public function test_group_is_a_suggestion_and_explicitly_empty_selection_stays_empty_after_validation(): void
    {
        $key=QuoteRecipients::key($this->company->id,$this->agency->id);
        $this->case->update(['recipient_group_snapshot'=>['name'=>'Gruppo di prova','recipient_keys'=>[$key]]]);
        $page=$this->get(route('quotes.emails.create',$this->case))->assertOk()->assertSee($this->other->name)->assertSee('Ogni destinatario riceverà un’email separata.');
        $dom=new \DOMDocument;
        @$dom->loadHTML($page->getContent());
        $xpath=new \DOMXPath($dom);
        $checked=$xpath->query('//input[@name="recipient_keys[]" and @checked]');
        $this->assertCount(1,$checked);
        $this->assertSame($key,$checked->item(0)->getAttribute('value'));
        $this->post(route('quotes.emails.review',$this->case),$this->input([],['recipient_keys'=>[]]))->assertSessionHasErrors('recipient_keys');
        $page=$this->get(route('quotes.emails.create',$this->case))->assertOk();
        @$dom->loadHTML($page->getContent());
        $this->assertCount(0,(new \DOMXPath($dom))->query('//input[@name="recipient_keys[]" and @checked]'));
    }

    public function test_preview_sends_nothing_and_freezes_each_recipient_with_private_copies(): void
    {
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        $messages=$this->review(extra:['files'=>[$this->pdf()]]);
        $this->assertCount(3,$messages);
        $this->assertSame(0,$this->case->quotes()->count());
        $this->assertCount(3,Storage::disk('attachments')->allFiles());
        $this->assertSame(1,$messages->pluck('review_batch_id')->unique()->count());
        foreach($messages as $message) {
            $this->assertSame('review',$message->status);
            $this->assertSame(1,$message->documents()->count());
            $this->assertSame($this->company->name,$message->payload['target']['company_name']);
            $this->get(route('quote-emails.show',$message))->assertOk()->assertSee($message->payload['recipient']['address']);
        }
        $this->assertStringNotContainsString('agency-one@example.test',DB::table('insurance_quote_emails')->get()->toJson());
        $this->get(route('quote-email-batches.show',$messages->first()->review_batch_id))->assertOk()->assertSee('Conferma e invia a 3 destinatari')->assertSee('richiesta.pdf');
    }

    public function test_compose_shows_only_compatible_groups_and_restores_the_manual_choices_after_an_error(): void
    {
        $pair = QuoteRecipients::key($this->company->id, $this->agency->id);
        $extra = QuoteRecipients::key($this->other->id, $this->agency->id);
        $groupData = ['insurance_branch_id'=>$this->case->insurance_branch_id, 'status'=>'active', 'recipient_keys'=>[$pair]];
        $group = QuoteRecipientGroup::create($groupData + ['name'=>'Deleghe CU iniziali', 'cu_from'=>1, 'cu_to'=>5]);
        $otherGroup = QuoteRecipientGroup::create($groupData + ['name'=>'Deleghe altre classi', 'cu_from'=>7, 'cu_to'=>12]);
        $this->case->update(['merit_class_cu'=>3, 'quote_recipient_group_id'=>$group->id, 'recipient_group_snapshot'=>['name'=>$group->name, 'recipient_keys'=>[$pair]]]);
        $url = route('quotes.emails.create', $this->case);
        $this->get($url)->assertOk()->assertSee('Classe CU 3')->assertSee($group->name)->assertDontSee('Deleghe altre classi');
        $this->from($url)->post(route('quotes.emails.review',$this->case), $this->input([$extra], ['recipient_group_choice'=>$group->id, 'body'=>'breve']))
            ->assertSessionHasErrors('body');
        $page = $this->get($url)->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($page->getContent());
        $xpath = new \DOMXPath($dom);
        $spunte = $xpath->query('//input[@name="recipient_keys[]" and @checked]');
        $this->assertCount(1, $spunte);
        $this->assertSame($extra, $spunte->item(0)->getAttribute('value'));
        $this->assertSame((string)$group->id, $xpath->evaluate('string(//select[@name="recipient_group_choice"]/option[@selected]/@value)'));
        $this->post(route('quotes.emails.review',$this->case), $this->input([$extra], ['recipient_group_choice'=>$otherGroup->id]))
            ->assertSessionHasErrors('recipient_group_choice');
        $this->assertDatabaseCount('insurance_quote_emails', 0);
        $message = $this->review([$extra], ['recipient_group_choice'=>$group->id])->sole();
        $this->assertSame($group->id, $message->payload['recipient_group']['id']);
        $this->assertSame($extra, $message->payload['target']['key']);
        $draft = $this->get(route('quotes.emails.create', [$this->case, 'draft'=>$message->id]))->assertOk();
        @$dom->loadHTML($draft->getContent());
        $restored = new \DOMXPath($dom);
        $this->assertSame((string)$group->id, $restored->evaluate('string(//select[@name="recipient_group_choice"]/option[@selected]/@value)'));
        $this->assertSame($extra, $restored->evaluate('string(//input[@name="recipient_keys[]" and @checked]/@value)'));
    }

    public function test_editing_a_preview_preserves_text_recipients_and_files_and_retires_the_previous_batch(): void
    {
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        $messages=$this->review(extra:['files'=>[$this->pdf()]]);
        $draft=app(QuoteRequestEmailWorkflow::class)->editableDraft($this->case,$this->admin,$messages->first()->id)->first();
        $document=$draft->documents()->sole();
        $this->get(route('quotes.emails.create',[$this->case,'draft'=>$draft->id]))->assertOk()
            ->assertViewHas('draftValues',fn($values)=>$values['body']===$this->input()['body'] && $values['document_ids']===[$document->id])
            ->assertViewHas('selectedKeys',fn($keys)=>collect($keys)->sort()->values()->all()===collect($this->keys())->sort()->values()->all());
        $edited=$this->review([$this->keys()[1]],['draft_id'=>$draft->id,'document_ids'=>[$document->id],'subject'=>'Oggetto aggiornato','body'=>'Testo aggiornato della richiesta da controllare prima dell’invio.']);
        $this->assertCount(1,$edited);
        $this->assertSame(3,InsuranceQuoteEmail::where('status','superseded')->count());
        $this->assertSame('Oggetto aggiornato',$edited->first()->payload['subject']);
        $this->assertSame(1,$edited->first()->documents()->count());
        $this->assertNotSame($document->path,$edited->first()->documents()->sole()->path);
        Storage::disk('attachments')->assertExists($document->path);
        $this->post(route('quote-email-batches.send',$draft->review_batch_id),['reviewed'=>1])->assertSessionHasErrors('email');
        $this->get(route('quote-emails.show',$draft))->assertOk()->assertSee('Questa anteprima è stata sostituita');
        $this->assertSame(0,$this->case->quotes()->count());
    }

    public function test_preview_editing_rejects_another_author_case_and_a_partly_confirmed_batch(): void
    {
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        $messages=$this->review(); $draft=$messages->first();
        $other=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->actingAs($other)->get(route('quotes.emails.create',[$this->case,'draft'=>$draft->id]))->assertForbidden();
        $this->actingAs($this->admin);
        $otherCase=$this->case->replicate(); $otherCase->save();
        $this->get(route('quotes.emails.create',[$otherCase,'draft'=>$draft->id]))->assertNotFound();
        $messages->last()->update(['status'=>'sending','delivery_key'=>hash('sha256','confirmed-test-batch')]);
        $this->post(route('quotes.emails.review',$this->case),$this->input(extra:['draft_id'=>$draft->id]))->assertSessionHasErrors('email');
        $this->assertSame(3,InsuranceQuoteEmail::count());
        $this->assertSame(0,InsuranceQuoteEmail::where('status','superseded')->count());
    }

    public function test_batch_sends_separate_messages_once_and_preserves_three_proposals_for_one_company(): void
    {
        $messages=$this->review(extra:['files'=>[$this->pdf()]]);
        $delivered=[];
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->times(3)->andReturnUsing(function($payload,$configuration,$files) use (&$delivered) {
            $delivered[]=$payload['recipient']['address'];
            $this->assertCount(1,$files);
            $this->assertStringStartsWith('%PDF',$files[0]['bytes']);
            if($payload['target']['agency_id']) $this->assertStringStartsWith('Compagnia richiesta: '.$this->company->name,$payload['body']);
            return 'accepted@example.test';
        });
        $route=route('quote-email-batches.send',$messages->first()->review_batch_id);
        $this->post($route)->assertSessionHasErrors('reviewed');
        $this->post($route,['reviewed'=>1])->assertSessionHasNoErrors();
        $this->post($route,['reviewed'=>1])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$this->company->email,$this->agency->email,$this->secondAgency->email],$delivered);
        $this->assertSame(3,$this->case->quotes()->count());
        $this->assertEqualsCanonicalizing(['direct','agency:'.$this->agency->id,'agency:'.$this->secondAgency->id],$this->case->quotes()->pluck('recipient_key')->all());
        $this->assertSame(3,InsuranceQuoteEmail::where('status','sent')->count());
        $this->assertContains(hash('sha256',$this->case->id.'|'.$this->company->id),InsuranceQuoteEmail::pluck('delivery_key')->all());
        $this->get(route('quotes.show',$this->case))->assertOk()->assertSee($this->agency->name)->assertSee($this->secondAgency->name);
    }

    public function test_multimandate_agency_gets_a_distinct_request_for_each_company(): void
    {
        $keys=[QuoteRecipients::key($this->company->id,$this->agency->id),QuoteRecipients::key($this->other->id,$this->agency->id)];
        $messages=$this->review($keys);
        $companies=[];
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->twice()->andReturnUsing(function($payload) use (&$companies) {
            $this->assertSame($this->agency->email,$payload['recipient']['address']);
            $companies[]=$payload['target']['company_name'];
            $this->assertStringStartsWith('Compagnia richiesta: '.$payload['target']['company_name'],$payload['body']);
            return 'accepted@example.test';
        });
        $this->post(route('quote-email-batches.send',$messages->first()->review_batch_id),['reviewed'=>1])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$this->company->name,$this->other->name],$companies);
        $this->assertSame(2,$this->case->quotes()->count());
    }

    public function test_changed_email_status_mandate_or_file_blocks_the_entire_batch_before_sending(): void
    {
        $messages=$this->review(extra:['files'=>[$this->pdf()]]);
        $route=route('quote-email-batches.send',$messages->first()->review_batch_id);
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        $email=$this->agency->email;
        $this->agency->update(['email'=>'changed@example.test']);
        $this->post($route,['reviewed'=>1])->assertSessionHasErrors('email');
        $this->agency->update(['email'=>$email,'status'=>'inactive']);
        $this->post($route,['reviewed'=>1])->assertSessionHasErrors('email');
        $this->agency->update(['status'=>'active']);
        $this->agency->companies()->detach($this->company->id);
        $this->post($route,['reviewed'=>1])->assertSessionHasErrors('email');
        $this->agency->companies()->attach($this->company->id);
        $document=$messages->last()->documents()->sole();
        Storage::disk('attachments')->put($document->path,'Changed');
        $this->post($route,['reviewed'=>1])->assertSessionHasErrors('email');
        $this->assertSame(3,InsuranceQuoteEmail::where('status','review')->whereNull('delivery_key')->count());
        $this->assertSame(0,$this->case->quotes()->count());
    }

    public function test_uncertain_recipient_does_not_repeat_successful_sends_and_can_be_resolved_separately(): void
    {
        $messages=$this->review();
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->times(3)->andReturnUsing(function($payload) {
            if($payload['target']['agency_id']===$this->agency->id) throw new \RuntimeException('Delivery not confirmed');
            return 'accepted@example.test';
        });
        $route=route('quote-email-batches.send',$messages->first()->review_batch_id);
        $this->post($route,['reviewed'=>1])->assertSessionHasNoErrors()->assertSessionHas('warning');
        $this->assertSame(2,InsuranceQuoteEmail::where('status','sent')->count());
        $uncertain=InsuranceQuoteEmail::where('status','uncertain')->sole();
        $this->post($route,['reviewed'=>1])->assertSessionHasErrors('email');
        $this->assertSame(2,$this->case->quotes()->count());
        $this->post(route('quote-emails.resolve',$uncertain),['checked'=>1,'outcome'=>'sent','note'=>'Invio verificato con il destinatario'])->assertSessionHasNoErrors();
        $this->assertSame(3,$this->case->quotes()->count());
        $this->assertSame('agency:'.$this->agency->id,$this->case->quotes()->where('supplier_agency_id',$this->agency->id)->sole()->recipient_key);
        $this->post($route,['reviewed'=>1])->assertSessionHasNoErrors();
    }

    public function test_failed_batch_preparation_removes_copied_files_and_rolls_back_all_previews(): void
    {
        $existing=$this->review([QuoteRecipients::key($this->company->id)])->sole();
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->once()->andReturn('accepted@example.test');
        $this->post(route('quote-emails.send',$existing),['reviewed'=>1])->assertSessionHasNoErrors();
        $keys=[QuoteRecipients::key($this->other->id),QuoteRecipients::key($this->company->id)];
        $this->post(route('quotes.emails.review',$this->case),$this->input($keys,['files'=>[$this->pdf()]]))->assertSessionHasErrors('email');
        $this->assertSame(1,InsuranceQuoteEmail::count());
        $this->assertSame([],Storage::disk('attachments')->allFiles());
    }

    public function test_operator_can_only_confirm_own_batch_and_cannot_choose_a_subset_of_its_messages(): void
    {
        $operator=User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']);
        $this->actingAs($operator);
        $messages=$this->review();
        $route=route('quote-email-batches.send',$messages->first()->review_batch_id);
        $this->mock(QuoteRequestEmailTransport::class)->shouldNotReceive('send');
        $this->actingAs($this->admin)->post($route,['reviewed'=>1,'message_ids'=>[$messages->first()->id]])->assertForbidden();
        $this->assertSame(3,InsuranceQuoteEmail::where('status','review')->whereNull('delivery_key')->count());
        $this->actingAs($operator);
        $this->mock(QuoteRequestEmailTransport::class)->shouldReceive('send')->times(3)->andReturn('accepted@example.test');
        $this->post($route,['reviewed'=>1,'message_ids'=>[$messages->first()->id]])->assertSessionHasNoErrors();
        $this->assertSame(3,InsuranceQuoteEmail::where('status','sent')->where('user_id',$operator->id)->count());
    }

    public function test_invalid_duplicate_and_unavailable_recipients_do_not_create_previews(): void
    {
        $invalid=[QuoteRecipients::key($this->other->id,$this->secondAgency->id)];
        $this->post(route('quotes.emails.review',$this->case),$this->input($invalid))->assertSessionHasErrors('recipient_keys');
        $this->post(route('quotes.emails.review',$this->case),$this->input(['company:'.$this->company->id,'company:'.$this->company->id]))->assertSessionHasErrors('recipient_keys.0');
        $this->post(route('quotes.emails.review',$this->case),$this->input([['company'=>$this->company->id]]))->assertSessionHasErrors('recipient_keys.0');
        $this->get(route('quotes.emails.create',$this->case))->assertOk();
        $this->agency->update(['status'=>'inactive']);
        $this->post(route('quotes.emails.review',$this->case),$this->input([QuoteRecipients::key($this->company->id,$this->agency->id)]))->assertSessionHasErrors('recipient_keys');
        $this->assertSame(0,InsuranceQuoteEmail::count());
    }

    public function test_agency_email_can_be_selected_when_company_email_is_missing_and_missing_targets_are_visible(): void
    {
        $this->company->update(['email'=>null]);
        $keys=[QuoteRecipients::key($this->company->id),QuoteRecipients::key($this->company->id,$this->agency->id),'agency:'.$this->company->id.':999999'];
        $this->case->update(['recipient_group_snapshot'=>['name'=>'Gruppo con anagrafiche da completare','recipient_keys'=>$keys]]);
        $this->get(route('quotes.emails.create',$this->case))->assertOk()->assertSee('non sono disponibili')->assertSee('Email da completare nell’anagrafica');
        $messages=$this->review([QuoteRecipients::key($this->company->id,$this->agency->id)]);
        $this->assertCount(1,$messages);
        $this->assertSame($this->agency->email,$messages->sole()->payload['recipient']['address']);
    }

    public function test_manual_contacts_are_distinct_per_agency_and_the_request_keeps_its_original_recipient(): void
    {
        foreach($this->keys() as $key) $this->post(route('quotes.request',$this->case),['recipient_key'=>$key,'requested_on'=>'2026-10-30','confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertSame(3,$this->case->quotes()->count());
        $this->post(route('quotes.request',$this->case),['recipient_key'=>$this->keys()[1],'requested_on'=>'2026-10-30','confirmed'=>1])->assertSessionHasErrors('quote');
        $this->agency->update(['name'=>'Agenzia di prova rinominata']);
        $this->get(route('quotes.show',$this->case))->assertOk()->assertSee('Agenzia di prova uno');
        $quote=$this->case->quotes()->where('recipient_key','agency:'.$this->agency->id)->sole();
        $this->assertSame('Agenzia di prova uno',$quote->request_recipient['agency_name']);
    }

    public function test_agency_with_email_history_cannot_be_deleted_and_history_keeps_recipient_name(): void
    {
        $message=$this->review([QuoteRecipients::key($this->company->id,$this->agency->id)])->sole();
        $this->delete(route('suppliers.destroy',$this->agency))->assertSessionHasErrors('supplier');
        $this->agency->update(['name'=>'Nuovo nome di prova']);
        $this->get(route('quote-emails.show',$message))->assertOk()->assertSee('Agenzia di prova uno');
        $this->get(route('quotes.show',$this->case))->assertOk()->assertSee('Agenzia di prova uno');
        $this->assertDatabaseHas('supplier_agencies',['id'=>$this->agency->id]);
    }
}
