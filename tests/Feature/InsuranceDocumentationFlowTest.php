<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AgencyCommunication, Attachment, Client, InsuranceBranch, InsuranceFormPackage, InsuranceFormTemplate, InsurancePolicy, InsuranceSubbranch, OutboundMailSetting, SupplierAgency, SupplierCompany, User};
use App\Services\{AgencyEmailTransport, ClientDocumentRegistry, DeadlineReminders, InsuranceDocumentation, InsuranceForms};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InsuranceDocumentationFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private InsuranceBranch $branch;
    private InsuranceSubbranch $sub;
    private SupplierCompany $company;
    private SupplierAgency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        Storage::fake('attachments');
        $this->admin = User::factory()->create(['role'=>UserRole::Admin, 'status'=>'active']);
        $this->operator = User::factory()->create(['role'=>UserRole::Operator, 'status'=>'active']);
        $this->actingAs($this->admin);
        $this->branch = InsuranceBranch::create(['name'=>'RC auto', 'name_key'=>hash('sha256', 'RC AUTO'), 'status'=>'active',
            'document_categories'=>['identity'], 'person_document_categories'=>['tax_code'], 'company_document_categories'=>['company']]);
        $this->sub = $this->branch->subbranches()->create(['name'=>'Autovetture', 'name_key'=>hash('sha256', 'AUTOVETTURE'), 'status'=>'active', 'document_categories'=>['vehicle']]);
        $this->company = SupplierCompany::create(['name'=>'Nobis', 'status'=>'active', 'quote_priority'=>true]);
        $this->agency = SupplierAgency::create(['name'=>'Agenzia Valserena', 'email'=>'valserena@example.test', 'status'=>'active']);
        $this->agency->companies()->attach($this->company);
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }

    private function policy(Client $client): InsurancePolicy
    {
        return $client->policies()->create(['insurance_branch_id'=>$this->branch->id, 'insurance_subbranch_id'=>$this->sub->id,
            'category'=>$this->branch->name, 'subcategory'=>$this->sub->name, 'supplier_company_id'=>$this->company->id,
            'company'=>$this->company->name, 'supplier_agency_id'=>$this->agency->id, 'number'=>'SA-2026-'.($client->policies()->count() + 481),
            'starts_on'=>'2026-10-20', 'expires_on'=>'2027-10-20', 'status'=>'active', 'premium'=>'800.00', 'payment_frequency'=>'Semestrale']);
    }

    private function template(string $type, string $scope): InsuranceFormTemplate
    {
        return InsuranceFormTemplate::create(['family'=>(string) Str::uuid(), 'version'=>1, 'title'=>'Modulo '.$scope.' '.$type,
            'category'=>'consistency', 'scope'=>$scope, 'client_type'=>$type, 'insurance_branch_id'=>$scope === 'policy' ? $this->branch->id : null,
            'body'=>"Documento tecnico di collaudo, privo di valore contrattuale.\nContraente: {{contraente}}\nFirmatario: {{firmatario}}\nQualifica: {{qualifica_firmatario}}\nPolizza: {{numero_polizza}}\nFirma: __________________",
            'signature_required'=>true, 'required'=>true, 'status'=>'active', 'approved_by'=>$this->admin->id, 'approved_at'=>now()]);
    }

    private function packageData(Client $client, InsuranceFormTemplate $template, ?InsurancePolicy $policy = null): array
    {
        return ['policy'=>$policy?->id, 'templates'=>[$template->id], 'reason'=>$policy ? 'initial' : 'client',
            'context_hash'=>app(InsuranceForms::class)->contextHash($client->fresh(), $policy?->fresh()), 'request_key'=>(string) Str::uuid(),
            'signer_name'=>$client->client_type === 'company' ? 'Elena Varesi' : $client->name,
            'signer_role'=>$client->client_type === 'company' ? 'Legale rappresentante' : null,
            'place'=>'Napoli', 'document_date'=>'2026-10-20', 'confirmed'=>1];
    }

    private function sign(Client $client, InsuranceFormTemplate $template, ?InsurancePolicy $policy = null): InsuranceFormPackage
    {
        $data = $this->packageData($client, $template, $policy);
        $this->post(route('clients.forms.store', $client), $data)->assertSessionHasNoErrors()->assertRedirect();
        $package = InsuranceFormPackage::latest('id')->firstOrFail();
        $document = $package->documents()->sole();
        $this->get($document->generated->downloadUrl())->assertOk();
        $bytes = Storage::disk('attachments')->get($document->generated->path);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->post(route('clients.forms.submit', [$client, $package, $document]), [
            'request_key'=>(string) Str::uuid(), 'signer_name'=>$data['signer_name'], 'signer_role'=>$data['signer_role'],
            'signed_on'=>'2026-10-20', 'files'=>[UploadedFile::fake()->createWithContent('firma-fronte.pdf', $bytes), UploadedFile::fake()->createWithContent('firma-retro.pdf', $bytes)],
        ])->assertSessionHasNoErrors();
        $submission = $document->submissions()->sole();
        $this->assertSame('Firme da completare', $package->fresh()->state_label);
        $this->post(route('clients.forms.review', [$client, $package, $submission]), ['outcome'=>'validated', 'confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertSame('Documentazione completata', $package->fresh()->state_label);
        return $package->fresh();
    }

    public function test_private_customer_flow_from_quote_to_checked_documents_forms_receipt_and_cumulative_message(): void
    {
        $this->completeFlow('person');
    }

    public function test_company_flow_requires_company_documents_and_representative_and_preserves_every_step(): void
    {
        $this->completeFlow('company');
    }

    private function completeFlow(string $type): void
    {
        foreach (['person', 'company'] as $audience) foreach (['client', 'policy'] as $scope) $this->template($audience, $scope);
        $this->actingAs($this->operator);
        $this->post(route('clients.store'), ['name'=>$type === 'company' ? 'Officine Varesi SRL' : 'Elena Varesi', 'client_type'=>$type, 'status'=>'active'])->assertSessionHasNoErrors();
        $client = Client::latest('id')->firstOrFail();
        $this->post(route('quotes.store'), ['client_id'=>$client->id, 'insurance_branch_id'=>$this->branch->id, 'insurance_subbranch_id'=>$this->sub->id,
            'subject'=>'Copertura autovettura', 'additional_coverages'=>'Assistenza stradale', 'files'=>[$this->pdf('situazione.pdf'), $this->pdf('richiesta.pdf')]])->assertSessionHasNoErrors();
        $case = $client->quoteCases()->sole();
        $this->get(route('quotes.show', $case))->assertOk()->assertSee('Documentazione per il contratto');
        $this->post(route('quotes.request', $case), ['supplier_company_id'=>$this->company->id, 'requested_on'=>'2026-10-20', 'confirmed'=>1])->assertSessionHasNoErrors();
        $quote = $case->quotes()->sole();
        $this->post(route('quotes.change', [$quote, 'receive']), ['supplier_agency_id'=>$this->agency->id, 'reference'=>'N-2026-481', 'product'=>'Auto completa',
            'starts_on'=>'2026-10-20', 'expires_on'=>'2027-10-20', 'valid_until'=>'2026-11-01', 'received_on'=>'2026-10-20',
            'premium'=>'800.00', 'payment_frequency'=>'Semestrale', 'files'=>[$this->pdf('proposta.pdf')]])->assertSessionHasNoErrors();
        $this->post(route('quotes.change', [$quote, 'submit']), ['confirmed'=>1])->assertSessionHasNoErrors();
        $this->post(route('quotes.change', [$quote, 'approve']), ['approved_on'=>'2026-10-20', 'confirmed'=>1, 'files'=>[$this->pdf('approvazione.pdf')]])->assertSessionHasNoErrors();
        $this->get(route('policies.create', ['quote_id'=>$quote->id]))->assertOk();
        $this->post(route('policies.store'), ['client_id'=>$client->id, 'entry_mode'=>'new', 'insurance_quote_id'=>$quote->id,
            'category'=>$this->branch->name, 'subcategory'=>$this->sub->name, 'company'=>$this->company->name, 'number'=>'SA-2026-481',
            'starts_on'=>'2026-10-20', 'expires_on'=>'2027-10-20', 'status'=>'active', 'payment_method'=>'Bonifico a Intermediario'])->assertSessionHasNoErrors();
        $policy = $client->policies()->sole();
        $this->assertSame('800.00', $policy->premium);
        $this->assertSame($policy->id, $quote->fresh()->policy->id);
        $this->get(route('clients.document-requests.create', [$client, 'policy'=>$policy->id]))->assertOk();
        $checklist = ['insurance_branch_id'=>$this->branch->id, 'insurance_policy_id'=>$policy->id, 'due_on'=>'2026-10-20'];
        $this->post(route('clients.document-requests.apply', $client), $checklist)->assertSessionHasNoErrors();
        $this->post(route('clients.document-requests.apply', $client), $checklist)->assertSessionHasNoErrors();
        $requests = $client->documentRequests()->get()->keyBy('document_category');
        $this->assertEqualsCanonicalizing(['identity', 'vehicle', $type === 'company' ? 'company' : 'tax_code'], $requests->keys()->all());
        $this->post(route('clients.documents.store', $client), ['document_category'=>'identity',
            'files'=>[$this->pdf('identita-fronte.pdf'), $this->pdf('identita-retro.pdf')],
            'file_details'=>[['expires_on'=>'2030-10-20', 'identity_type'=>'electronic'], ['expires_on'=>'2030-10-20', 'identity_type'=>'electronic']]])->assertSessionHasNoErrors();
        $identities = $client->attachments()->where('document_category', 'identity')->get();
        $this->post(route('clients.document-requests.link', [$client, $requests['identity']]), ['documents'=>$identities->modelKeys(), 'confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertSame('Ricevuto, da validare', $requests['identity']->fresh()->state_label);
        foreach ($requests as $category => $request) {
            if ($category !== 'identity') {
                $this->post(route('clients.documents.store', $client), ['document_category'=>$category, 'insurance_policy_id'=>$policy->id, 'document_request_id'=>$request->id,
                    'files'=>[$this->pdf($category.'-fronte.pdf'), $this->pdf($category.'-retro.pdf')]])->assertSessionHasNoErrors();
            }
            $files = $request->fresh()->currentDocuments();
            $this->assertCount(2, $files);
            foreach ($files as $file) $this->post(route('clients.documents.validate', [$client, $file]), ['confirmed'=>1])->assertSessionHasNoErrors();
            $this->assertSame('Validato', $request->fresh()->state_label);
        }
        $packages = [];
        foreach (['client', 'policy'] as $scope) {
            $template = InsuranceFormTemplate::where('scope', $scope)->where('client_type', $type)->sole();
            $packages[] = $this->sign($client, $template, $scope === 'policy' ? $policy : null);
        }
        $this->get(route('clients.policies.show', [$client, $policy]))->assertOk()->assertSee('Documenti richiesti e firme')->assertSee('Documentazione completata');
        $this->get(route('clients.show', [$client, 'tab'=>'documents']))->assertOk()->assertSee('Moduli e firme del cliente');
        $preview = $this->get(route('titles.schedule', $policy))->assertOk();
        $this->post(route('titles.generate', $policy), ['fingerprint'=>$preview->viewData('fingerprint'), 'confirmed'=>1])->assertSessionHasNoErrors();
        $titles = $policy->titles()->orderBy('coverage_from')->get();
        $this->assertCount(2, $titles);
        app(DeadlineReminders::class)->forUser($this->operator);
        $this->post(route('titles.collect', $titles[0]), ['received_on'=>'2026-10-20', 'amount'=>'400.00', 'payment_method'=>'Bonifico a Intermediario',
            'recipient'=>'our_agency', 'document_type'=>'transfer', 'idempotency_key'=>(string) Str::uuid(), 'files'=>[$this->pdf('contabile.pdf')]])->assertSessionHasNoErrors();
        $this->assertSame('paid', $titles[0]->fresh()->state);
        $this->assertSame('2027-04-20', $policy->fresh()->coverage_until->toDateString());
        $this->assertSame(0, $this->operator->visibleNotifications()->where('data->kind', 'title')->count());
        foreach ($packages as $package) $this->assertSame('Documentazione completata', $package->fresh()->state_label);
        $this->post(route('clients.policies.close-work', [$client, $policy]), ['confirmed'=>1, 'fingerprint'=>$policy->fresh()->workFingerprint()])->assertSessionHasNoErrors();
        $this->actingAs($this->admin);
        OutboundMailSetting::create(['credentials'=>['enabled'=>true, 'host'=>'smtp.example.test', 'port'=>587, 'encryption'=>'starttls',
            'username'=>'sender@example.test', 'password'=>Str::random(32), 'from_address'=>'sender@example.test', 'from_name'=>'Soluzioni Assicurative SAS'], 'updated_by'=>$this->admin->id]);
        $transport = new class extends AgencyEmailTransport {
            public array $payloads = [];
            public function send(array $payload, array $configuration): string { $this->payloads[] = $payload; return 'simulated@example.test'; }
        };
        app()->instance(AgencyEmailTransport::class, $transport);
        $payment = $titles[0]->collections()->sole();
        $this->post(route('agency-communications.collections-review', $this->agency), ['scope'=>'pending', 'collections'=>[$payment->id]])->assertSessionHasNoErrors();
        $message = AgencyCommunication::latest('created_at')->firstOrFail();
        $this->post(route('agency-communications.documents', $message), ['attachment_revision'=>$message->attachment_revision, 'document_ids'=>$identities->modelKeys()])->assertSessionHasNoErrors();
        $this->post(route('agency-communications.send', $message), ['attachment_revision'=>$message->fresh()->attachment_revision])->assertSessionHasErrors('reviewed');
        $this->assertCount(0, $transport->payloads);
        $this->post(route('agency-communications.send', $message), ['reviewed'=>1, 'attachment_revision'=>$message->fresh()->attachment_revision])->assertSessionHasNoErrors();
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertCount(1, $transport->payloads);
        $this->assertCount(2, $transport->payloads[0]['_attachments']);
        $this->assertDatabaseCount('client_consents', 0);
        $this->assertDatabaseHas('audit_logs', ['action'=>'forms_prepared', 'user_id'=>$this->operator->id]);
        $this->travel(1)->days();
        $this->actingAs($this->operator)->get(route('clients.policies.edit', [$client, $policy]))->assertForbidden();
    }

    public function test_checklist_configuration_is_admin_only_and_preserves_common_requirements(): void
    {
        $url = route('insurance-catalog.update', ['branch', $this->branch->id]);
        $values = ['name'=>'RC auto', 'status'=>'active', 'needs_review'=>0, 'typed_checklist_present'=>1, 'company_document_categories'=>['company', 'mandate']];
        $this->actingAs($this->operator)->patch($url, $values)->assertForbidden();
        $this->actingAs($this->admin)->patch($url, $values)->assertSessionHasNoErrors();
        $branch = $this->branch->fresh();
        $this->assertSame(['identity'], $branch->document_categories);
        $this->assertSame([], $branch->person_document_categories);
        $this->assertSame(['company', 'mandate'], $branch->company_document_categories);
        $this->get(route('insurance-catalog.edit', ['branch', $branch->id]))->assertOk()->assertSee('company_document_categories', false);
        $this->patch($url, array_replace($values, ['company_document_categories'=>['unrecognised']]))->assertSessionHasErrors('company_document_categories.0');
    }

    public function test_linked_documents_are_scoped_revalidated_and_kept_with_their_history(): void
    {
        $client = Client::factory()->create(['client_type'=>'person']);
        $policy = $this->policy($client);
        $request = $client->documentRequests()->create(['label'=>'Identità', 'document_category'=>'identity', 'insurance_policy_id'=>$policy->id, 'due_on'=>'2026-10-20']);
        $document = fn (Client $owner, array $extra = []) => app(ClientDocumentRegistry::class)->upload($owner, $this->pdf('identita.pdf'), $extra + ['document_category'=>'identity', 'expires_on'=>'2030-10-20']);
        $current = $document($client);
        $foreign = $document(Client::factory()->create());
        $wrongCategory = $document($client, ['document_category'=>'vehicle']);
        $anotherPolicy = $this->policy($client);
        $wrongPolicy = $document($client, ['insurance_policy_id'=>$anotherPolicy->id]);
        $url = route('clients.document-requests.link', [$client, $request]);
        foreach ([$foreign, $wrongCategory, $wrongPolicy] as $invalid) $this->post($url, ['documents'=>[$current->id, $invalid->id], 'confirmed'=>1])->assertSessionHasErrors('documents');
        $this->assertDatabaseCount('client_document_request_files', 0);
        app(DeadlineReminders::class)->forUser($this->admin);
        $this->assertSame(1, $this->admin->visibleNotifications()->where('data->kind', 'request')->count());
        $this->post($url, ['documents'=>[$current->id], 'confirmed'=>1])->assertSessionHasNoErrors();
        $this->post($url, ['documents'=>[$current->id], 'confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('client_document_request_files', 1);
        $this->assertSame(0, $this->admin->visibleNotifications()->where('data->kind', 'request')->count());
        $this->post(route('clients.documents.validate', [$client, $current]), ['confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertSame('Validato', $request->fresh()->state_label);
        $this->delete(route('attachments.destroy', $current))->assertSessionHasErrors();
        $this->delete(route('clients.document-requests.destroy', [$client, $request]))->assertSessionHasErrors();
        $this->patch(route('clients.documents.update', [$client, $current]), ['document_category'=>'vehicle'])->assertSessionHasErrors('document_category');
        $this->post(route('clients.documents.store', $client), ['document_category'=>'identity', 'previous_version_id'=>$current->id,
            'files'=>[$this->pdf('nuova-identita.pdf')], 'file_details'=>[['expires_on'=>'2031-10-20']]])->assertSessionHasNoErrors();
        $replacement = $current->newerVersion()->sole();
        $this->assertSame('Documento sostituito: aggiorna collegamento', $request->fresh()->state_label);
        $this->post($url, ['documents'=>[$current->id], 'confirmed'=>1])->assertSessionHasErrors('documents');
        $this->post($url, ['documents'=>[$replacement->id], 'confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertCount(1, $request->fresh()->currentDocuments());
        $this->assertSame('Ricevuto, da validare', $request->fresh()->state_label);
        $this->post(route('clients.documents.validate', [$client, $replacement]), ['confirmed'=>1])->assertSessionHasNoErrors();
        $this->assertSame('Validato', $request->fresh()->state_label);
        $this->patch(route('clients.documents.update', [$client, $replacement]), ['document_category'=>'identity', 'expires_on'=>'2026-10-19'])->assertSessionHasNoErrors();
        $this->assertSame('Documento scaduto', $request->fresh()->state_label);
        $this->post(route('clients.documents.validate', [$client, $replacement]), ['confirmed'=>1])->assertSessionHasErrors('document');
        $this->get(route('clients.document-requests.edit', [$client, $request]))->assertOk()->assertSee('Documento scaduto');
    }

    public function test_templates_cannot_be_forged_for_another_client_type_or_branch_and_company_signer_has_a_role(): void
    {
        $client = Client::factory()->create(['client_type'=>'company']);
        $policy = $this->policy($client);
        $person = $this->template('person', 'policy');
        $company = $this->template('company', 'policy');
        $this->assertSame([$company->id], app(InsuranceForms::class)->available($policy, $client)->modelKeys());
        $this->post(route('clients.forms.store', $client), $this->packageData($client, $person, $policy))->assertSessionHasErrors('forms');
        $data = $this->packageData($client, $company, $policy);
        $data['signer_role'] = null;
        $this->post(route('clients.forms.store', $client), $data)->assertSessionHasErrors('signer_role');
        $this->assertDatabaseCount('insurance_form_packages', 0);
        $this->get(route('form-templates.index', ['client_type'=>'company']))->assertOk()->assertSee($company->title)->assertDontSee($person->title);
        $otherBranch = InsuranceBranch::create(['name'=>'Abitazione', 'name_key'=>hash('sha256', 'ABITAZIONE'), 'status'=>'active']);
        $this->post(route('clients.document-requests.apply', $client), ['insurance_branch_id'=>$otherBranch->id, 'insurance_policy_id'=>$policy->id])->assertSessionHasErrors('insurance_policy_id');
        $this->assertDatabaseCount('client_document_requests', 0);
        $this->assertSame([], app(InsuranceDocumentation::class)->categories($client, $otherBranch, $this->sub));
    }
}
