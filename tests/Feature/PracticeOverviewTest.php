<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsurancePolicy, InsuranceTitle, User};
use App\Services\PolicyWorkOverview;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function policy(Client $client, string $number, array $extra = []): InsurancePolicy
    {
        $policy = $client->policies()->create(array_replace(['number'=>$number, 'category'=>'RC auto', 'company'=>'Compagnia di collaudo', 'starts_on'=>'2026-01-01', 'expires_on'=>'2027-01-01', 'status'=>'active'], $extra));
        if (array_key_exists('work_closed_at', $extra)) $policy->forceFill(['work_closed_at'=>$extra['work_closed_at']])->save();
        return $policy;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,11,10)->setTime(12,0));
        $this->actingAs(User::factory()->create(['role'=>UserRole::Admin, 'status'=>'active']));
    }

    public function test_open_practices_keep_search_and_exclude_closed_contracts_and_merged_clients(): void
    {
        $client = Client::factory()->create();
        $open = $this->policy($client, 'DA-FINIRE');
        $closed = $this->policy($client, 'CHIUSA', ['work_closed_at'=>now()]);
        $this->policy($client, 'CESSATA', ['status'=>'ceased']);
        $merged = Client::factory()->create(['merged_into_id'=>$client->id]);
        $this->policy($merged, 'ACCORPATA');
        $this->get(route('policies.index', ['work'=>'open']))->assertOk()
            ->assertViewHas('policies', fn ($rows) => $rows->modelKeys() === [$open->id]);
        $this->get(route('policies.index', ['work'=>'closed']))->assertOk()
            ->assertViewHas('policies', fn ($rows) => $rows->modelKeys() === [$closed->id]);
        $this->get(route('policies.index', ['work'=>'open', 'search'=>'CHIUSA']))->assertOk()
            ->assertViewHas('policies', fn ($rows) => $rows->isEmpty());
    }

    public function test_dashboard_collection_action_opens_the_exact_title_with_existing_data(): void
    {
        $client = Client::factory()->create();
        $policy = $this->policy($client, 'INCASSO-DIRETTO');
        $title = InsuranceTitle::create(['insurance_policy_id'=>$policy->id, 'kind'=>'quietance', 'direction'=>'receipt', 'due_on'=>today(), 'coverage_from'=>today(), 'coverage_to'=>today()->addYear(), 'amount_cents'=>12345, 'payment_method'=>'Bonifico a Intermediario', 'payment_frequency'=>'Annuale']);
        $target = route('clients.policies.show', [$client, $policy, 'tab'=>'payments', 'title'=>$title->id]);
        $this->get(route('dashboard'))->assertOk()->assertSee(e($target.'#payment-entry'), false);
        $this->get($target)->assertOk()->assertViewHas('selectedPaymentTitle', (string) $title->id)
            ->assertViewHas('payableTitles', fn ($rows) => $rows->modelKeys() === [$title->id]);
        $this->assertDatabaseCount('insurance_collections', 0);
    }

    public function test_work_summary_does_not_request_existing_documents_or_change_the_work_fingerprint(): void
    {
        $client = Client::factory()->create();
        $policy = $this->policy($client, 'DOCUMENTI-COLLEGATI');
        $request = $client->documentRequests()->create(['insurance_policy_id'=>$policy->id, 'label'=>'Documento', 'document_category'=>'other']);
        $file = $client->attachments()->create(['insurance_policy_id'=>$policy->id, 'type'=>'document', 'document_category'=>'other', 'disk'=>'attachments', 'path'=>'not-read.pdf', 'directory'=>'', 'stored_name'=>'not-read.pdf', 'original_name'=>'not-read.pdf', 'mime_type'=>'application/pdf', 'extension'=>'pdf', 'size'=>1, 'uploaded_by'=>auth()->id()]);
        $request->linkedDocuments()->attach($file->id, ['linked_by'=>auth()->id(), 'created_at'=>now()]);
        $fingerprint = $policy->workFingerprint();
        $tasks = app(PolicyWorkOverview::class)->forPolicies(new Collection([$policy]), auth()->user())[$policy->id];
        $this->assertNotContains('Ricevi documenti', array_column($tasks, 'label'));
        $this->assertSame($fingerprint, $policy->workFingerprint());
        $client->documentRequests()->create(['insurance_policy_id'=>$policy->id, 'label'=>'Ancora da ricevere', 'document_category'=>'other']);
        $tasks = app(PolicyWorkOverview::class)->forPolicies(new Collection([$policy]), auth()->user())[$policy->id];
        $this->assertContains('Ricevi documenti', array_column($tasks, 'label'));
    }

    public function test_locked_operator_is_directed_to_the_existing_correction_flow(): void
    {
        $operator = User::factory()->create(['role'=>UserRole::Operator, 'status'=>'active']);
        $this->actingAs($operator);
        $client = Client::factory()->create();
        $policy = $this->policy($client, 'BLOCCATA', ['status'=>'review', 'work_closed_at'=>today()->subDay()]);
        $tasks = app(PolicyWorkOverview::class)->forPolicies(new Collection([$policy]), $operator)[$policy->id];
        $this->assertStringContainsString('tab=history#policy-corrections', $tasks[0]['url']);
        $this->assertNotContains('Controlla e chiudi', array_column($tasks, 'label'));
        $this->get(route('clients.policies.edit', [$client, $policy]))->assertForbidden();
    }

    public function test_dashboard_keeps_the_practice_preview_bounded_and_prioritises_unclear_status(): void
    {
        $client = Client::factory()->create();
        $review = $this->policy($client, 'DA-VERIFICARE', ['status'=>'review', 'updated_at'=>now()->subDays(5)]);
        for ($index=0; $index<7; $index++) $this->policy($client, 'APERTA-'.$index);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('openPractices', fn ($rows) => $rows->count()===3 && $rows->first()->is($review));
    }
}
