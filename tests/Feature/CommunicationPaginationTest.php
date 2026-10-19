<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AgencyCommunication, Client, InsuranceCollection, InsurancePolicy, InsuranceTitle, SupplierAgency, SupplierCompany, User};
use App\Services\AgencyEmailTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunicationPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
    private SupplierAgency $agency;
    private SupplierCompany $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));
        $this->admin = User::factory()->create(['role'=>UserRole::Admin, 'status'=>'active']);
        $this->actingAs($this->admin);
        $this->client = Client::factory()->create(['name'=>'Elena Varesi']);
        $this->agency = SupplierAgency::create(['name'=>'Agenzia Valserena', 'email'=>'valserena@example.test', 'status'=>'active']);
        $this->company = SupplierCompany::create(['name'=>'Compagnia Alba', 'status'=>'active']);
        $this->mock(AgencyEmailTransport::class)->shouldNotReceive('send');
    }

    private function policies(int $count = 527): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) $rows[] = [
            'client_id'=>$this->client->id, 'supplier_agency_id'=>$this->agency->id, 'supplier_company_id'=>$this->company->id,
            'number'=>'SA-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'category'=>'RC auto', 'company'=>$this->company->name,
            'starts_on'=>'2026-10-01', 'expires_on'=>'2027-10-01', 'status'=>'active', 'premium'=>'1234.56',
            'payment_frequency'=>'Semestrale', 'payment_method'=>'Bonifico a Intermediario', 'created_at'=>now(), 'updated_at'=>now(),
        ];
        foreach (array_chunk($rows, 100) as $batch) DB::table('insurance_policies')->insert($batch);
        return InsurancePolicy::orderBy('id')->pluck('id')->all();
    }

    private function collections(int $count = 527): array
    {
        $id = $this->policies(1)[0];
        $title = InsuranceTitle::create(['insurance_policy_id'=>$id, 'kind'=>'emission', 'due_on'=>today(), 'coverage_from'=>today(), 'coverage_to'=>today()->addYear(), 'amount_cents'=>100000000, 'created_by'=>$this->admin->id, 'payment_frequency'=>'Annuale', 'payment_method'=>'Bonifico a Intermediario']);
        $rows = [];
        for ($i = 1; $i <= $count; $i++) $rows[] = [
            'insurance_title_id'=>$title->id, 'received_on'=>today(), 'amount_cents'=>123456, 'payment_method'=>'Bonifico a Intermediario',
            'recipient'=>'our_agency', 'document_type'=>'receipt', 'idempotency_key'=>(string) Str::uuid(),
            'request_fingerprint'=>hash('sha256', (string) $i), 'created_by'=>$this->admin->id, 'created_at'=>now(), 'updated_at'=>now(),
        ];
        foreach (array_chunk($rows, 100) as $batch) DB::table('insurance_collections')->insert($batch);
        return InsuranceCollection::orderBy('id')->pluck('id')->all();
    }

    public function test_policy_pages_and_export_batches_keep_every_filtered_record_once(): void
    {
        $ids = $this->policies();
        $filters = ['period'=>'day', 'date'=>'2026-10-19', 'agency'=>$this->agency->id, 'company'=>$this->company->name, 'search'=>'SA-', 'status'=>'active'];
        $url = route('agency-communications.index', $filters);
        $first = $this->get($url)->assertOk()->viewData('policies');
        $second = $this->get($url.'&page=2')->assertOk()->viewData('policies');
        $this->assertSame(527, $first->total());
        $this->assertSame(array_slice($ids, 0, 25), $first->pluck('id')->all());
        $this->assertSame(array_slice($ids, 25, 25), $second->pluck('id')->all());
        $this->assertStringContainsString('search=SA-', $second->url(3));
        $this->get($url.'&_table_all=1')->assertViewHas('policies', fn ($p) => $p->count() === 25);
        $batch1 = $this->get($url.'&_table_all=1', ['X-Table-Export'=>'1'])->assertOk()->viewData('policies');
        $batch2 = $this->get($url.'&_table_all=1&page=2', ['X-Table-Export'=>'1'])->assertOk()->viewData('policies');
        $this->assertSame(500, $batch1->count());
        $this->assertSame($ids, array_merge($batch1->pluck('id')->all(), $batch2->pluck('id')->all()));
        $this->get(route('agency-communications.index', $filters+['page'=>22]))->assertViewHas('policies', fn ($p) => $p->count() === 2);
        $this->get(route('agency-communications.index', array_replace($filters, ['company'=>'Altra compagnia'])))->assertViewHas('policies', fn ($p) => $p->total() === 0);
        $this->get(route('agency-communications.index', array_replace($filters, ['date'=>'2026-10-18'])))->assertViewHas('policies', fn ($p) => $p->total() === 0);
    }

    public function test_cross_page_policy_review_revalidates_agency_dates_status_and_uniqueness(): void
    {
        $ids = $this->policies(); $selected = [$ids[0], $ids[25], $ids[526]];
        $url = route('agency-communications.review', $this->agency);
        $data = ['policies'=>$selected, 'period'=>'day', 'date'=>'2026-10-19'];
        $this->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        $message = AgencyCommunication::latest('created_at')->firstOrFail();
        $this->assertSame($selected, array_column($message->payload['policies'], 'id'));
        $this->assertSame('review', $message->status);
        $this->post($url, array_replace($data, ['date'=>'2026-10-18']))->assertSessionHasErrors('policies');
        $this->post($url, array_replace($data, ['policies'=>[$ids[0], $ids[0]]]))->assertSessionHasErrors('policies.0');
        $this->post($url, array_replace($data, ['policies'=>array_slice($ids, 0, 501)]))->assertSessionHasErrors('policies');
        InsurancePolicy::whereKey($ids[526])->update(['status'=>'cancelled']);
        $this->post($url, $data)->assertSessionHasErrors('policies');
        InsurancePolicy::whereKey($ids[526])->update(['status'=>'active', 'supplier_agency_id'=>null]);
        $this->post($url, $data)->assertSessionHasErrors('policies');
        $this->assertSame(1, AgencyCommunication::count());
    }

    public function test_collection_pages_exports_and_reviews_use_the_same_filter_scope(): void
    {
        $ids = $this->collections();
        $filters = ['from'=>'2026-10-19', 'to'=>'2026-10-19', 'agency'=>$this->agency->id, 'company'=>$this->company->id, 'search'=>'SA-'];
        $url = route('agency-communications.collections', $filters);
        $first = $this->get($url)->assertOk()->viewData('payments');
        $second = $this->get($url.'&page=2')->assertOk()->viewData('payments');
        $this->assertSame(527, $first->total());
        $this->assertSame(array_slice($ids, 25, 25), $second->pluck('id')->all());
        $export = $this->get($url.'&_table_all=1', ['X-Table-Export'=>'1'])->assertOk()->viewData('payments');
        $last = $this->get($url.'&_table_all=1&page=2', ['X-Table-Export'=>'1'])->assertOk()->viewData('payments');
        $this->assertSame($ids, array_merge($export->pluck('id')->all(), $last->pluck('id')->all()));
        $selected = [$ids[0], $ids[25], $ids[526]];
        $review = route('agency-communications.collections-review', $this->agency);
        $this->post($review, $filters+['collections'=>$selected])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($selected, array_column(AgencyCommunication::firstOrFail()->payload['collections'], 'id'));
        InsuranceCollection::whereKey($ids[526])->update(['voided_at'=>now()]);
        $this->post($review, $filters+['collections'=>$selected])->assertSessionHasErrors('collections');
        $this->get($url)->assertViewHas('payments', fn ($p) => $p->total() === 526);
        $this->get(route('agency-communications.collections', ['to'=>'2026-10-18']))->assertSessionHasErrors('to');
        $other = SupplierCompany::create(['name'=>'Compagnia Borgo', 'status'=>'active']);
        $this->get(route('agency-communications.collections', array_replace($filters, ['company'=>$other->id])))->assertViewHas('payments', fn ($p) => $p->total() === 0);
    }

    public function test_history_exports_include_all_pages_with_stable_order_and_no_permission_bypass(): void
    {
        for ($i=0; $i<30; $i++) AgencyCommunication::create(['supplier_agency_id'=>$this->agency->id, 'user_id'=>$this->admin->id, 'status'=>'review', 'payload'=>['recipient'=>['name'=>$this->agency->name, 'address'=>$this->agency->email], 'policies'=>[]], 'fingerprint'=>hash('sha256', (string) $i), 'review_expires_at'=>now()->addMinutes(30)]);
        $url = route('agency-communications.history', ['month'=>'2026-10', 'agency'=>$this->agency->id, 'state'=>'review']);
        $first = $this->get($url)->assertOk()->viewData('messages');
        $last = $this->get($url.'&page=2')->assertOk()->viewData('messages');
        $this->assertCount(25, $first); $this->assertCount(5, $last);
        $this->assertSame([], array_values(array_intersect($first->pluck('id')->all(), $last->pluck('id')->all())));
        $this->get($url.'&_table_all=1', ['X-Table-Export'=>'1'])->assertViewHas('messages', fn ($p) => $p->count() === 30);
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator, 'status'=>'active']));
        foreach (['agency-communications.index', 'agency-communications.collections', 'agency-communications.history'] as $route) $this->get(route($route, ['_table_all'=>1]), ['X-Table-Export'=>'1'])->assertForbidden();
    }

    public function test_selection_survives_empty_filter_fields_and_back_from_review_but_not_a_changed_search(): void
    {
        foreach (['agency-communications.index', 'agency-communications.collections'] as $route) {
            $scope = $this->get(route($route))->assertOk()->viewData('selectionScope');
            $same = $this->get(route($route, ['agency'=>'', 'company'=>'', 'search'=>'', 'page'=>2]))->assertOk()->viewData('selectionScope');
            $different = $this->get(route($route, ['search'=>'SA-00001']))->assertOk()->viewData('selectionScope');
            $this->assertSame($scope, $same); $this->assertNotSame($scope, $different);
        }
    }
}
