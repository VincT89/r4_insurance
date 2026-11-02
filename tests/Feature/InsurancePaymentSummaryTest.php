<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsuranceTitle, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsurancePaymentSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_date_groups_match_filters_and_exports_without_counting_historical_receipts_twice(): void
    {
        $this->travelTo(now()->setDate(2026,10,27)->setTime(12,0));
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']));
        $client = Client::factory()->create();
        $policyData = ['number'=>'SA-2026-520','category'=>'Abitazione','company'=>'Nobis','starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','status'=>'active'];
        $policy = $client->policies()->create($policyData);
        $titleData = ['insurance_policy_id'=>$policy->id,'kind'=>'quietance','coverage_from'=>'2026-10-27','coverage_to'=>'2027-10-27','payment_method'=>'Bonifico a Intermediario','payment_frequency'=>'Annuale'];
        $make = fn ($due, $amount, $collected=0, $extra=[]) => InsuranceTitle::create(array_replace($titleData, ['due_on'=>$due,'amount_cents'=>$amount,'collected_cents'=>$collected],$extra));
        for ($i=0; $i<27; $i++) $make('2026-11-01',10000);
        $today = $make('2026-10-27',20000);
        $partialToday = $make('2026-10-27',5000,2000);
        $make('2026-11-01',8000,1000);
        $make('2026-11-20',13000);
        $make('2026-10-26',12000,4000);
        $make('2026-10-26',7000);
        $make('2026-10-26',1000,1000);
        $make('2026-10-26',2000,0,['cancelled_at'=>now()]);
        $merged = Client::factory()->create(['merged_into_id'=>$client->id]);
        $hidden = $merged->policies()->create($policyData);
        $make('2026-10-26',90000,0,['insurance_policy_id'=>$hidden->id]);

        $expected = ['overdue'=>['count'=>2,'balance_cents'=>15000], 'due_soon'=>['count'=>30,'balance_cents'=>300000], 'future'=>['count'=>1,'balance_cents'=>13000]];
        $this->get(route('dashboard'))->assertOk()->assertViewHas('paymentSummary',$expected)->assertDontSee('Polizze non pagate');
        $this->get(route('titles.index'))->assertOk()->assertViewHas('paymentSummary',$expected);
        foreach ($expected as $state=>$totals) {
            $response = $this->get(route('titles.index',['state'=>$state,'policy'=>$policy->id]))->assertOk();
            $this->assertSame($totals['count'],$response->viewData('titles')->total());
            foreach ($response->viewData('titles') as $row) {
                $this->assertSame($state,$row->summary_state);
            }
            $export = $this->get(route('titles.index',['state'=>$state,'policy'=>$policy->id,'_table_all'=>1]),['X-Table-Export'=>'1'])->assertOk();
            $this->assertCount($totals['count'],$export->viewData('titles'));
            $this->assertSame($totals['balance_cents'],$export->viewData('titles')->sum('balance_cents'));
        }
        $this->get(route('titles.index',['state'=>'due_soon','page'=>2]))->assertViewHas('titles',fn($rows)=>$rows->count()===5);
        $this->get(route('titles.index',['state'=>'open']))->assertViewHas('titles',fn($rows)=>$rows->total()===33);
        $this->get(route('titles.index',['state'=>'paid']))->assertViewHas('titles',fn($rows)=>$rows->total()===1);
        $this->get(route('titles.index',['state'=>'cancelled']))->assertViewHas('titles',fn($rows)=>$rows->total()===1);
        $this->get(route('titles.index',['state'=>'due_soon','from'=>'2026-11-02']))->assertViewHas('titles',fn($rows)=>$rows->total()===0);
        $this->travelTo(now()->addDay());
        $this->assertSame('overdue',$today->fresh()->state);
        $this->assertSame('overdue',$partialToday->fresh()->state);
        $this->get(route('dashboard'))->assertViewHas('paymentSummary',fn($rows)=>$rows['overdue']===['count'=>4,'balance_cents'=>38000] && $rows['due_soon']===['count'=>28,'balance_cents'=>277000] && $rows['future']['count']===1);
    }

    public function test_future_totals_stop_at_effective_contract_end_without_cancelling_outstanding_history(): void
    {
        $this->travelTo(now()->setDate(2026,10,31)->setTime(12,0));
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator,'status'=>'active']));
        $client=Client::factory()->create();
        $values=['category'=>'Abitazione','company'=>'Compagnia prova titoli','starts_on'=>'2026-01-01','expires_on'=>'2026-12-02','status'=>'active'];
        $policy=$client->policies()->create($values+['number'=>'TEST-END-01']);
        $policy->forceFill(['work_closed_at'=>now()->subDays(2)])->save();
        $closed=$client->policies()->create(array_replace($values,['number'=>'TEST-END-02','expires_on'=>'2027-01-01','status'=>'replaced']));
        $closed->forceFill(['closed_at'=>'2026-11-25 12:00:00'])->save();
        $make=fn($contract,$due,$collected=0)=>$contract->titles()->create(['kind'=>'quietance','due_on'=>$due,'coverage_from'=>$due,'coverage_to'=>'2027-01-01','amount_cents'=>10000,'collected_cents'=>$collected,'payment_frequency'=>'Annuale','payment_method'=>'Bonifico a Intermediario']);
        $withinExpiry=$make($policy,'2026-12-01');
        $make($policy,'2026-12-02');
        $make($policy,'2026-12-03');
        $withinClosure=$make($closed,'2026-11-24');
        $make($closed,'2026-11-25');
        $make($closed,'2026-11-26');
        $past=$make($closed,'2026-10-30');
        $partial=$make($closed,'2026-11-27',1000);
        $this->get(route('titles.index',['state'=>'future']))->assertOk()->assertViewHas('titles',fn($rows)=>$rows->pluck('id')->all()===[$withinClosure->id,$withinExpiry->id]);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('paymentSummary',fn($summary)=>$summary['future']===['count'=>2,'balance_cents'=>20000] && $summary['due_soon']['count']===0);
        $this->get(route('titles.index',['state'=>'open']))->assertViewHas('titles',fn($rows)=>$rows->total()===8);
        $this->get(route('titles.index',['state'=>'overdue']))->assertViewHas('titles',fn($rows)=>$rows->pluck('id')->all()===[$past->id])->assertSee('Scadenza +15 giorni')->assertSee('14/11/2026');
        $this->get(route('titles.index',['state'=>'partial']))->assertViewHas('titles',fn($rows)=>$rows->pluck('id')->all()===[$partial->id]);
        $this->assertSame('2026-10-30',$past->due_on->toDateString());
        $this->assertDatabaseCount('insurance_titles',8);
        $this->assertSame(0,InsuranceTitle::whereNotNull('cancelled_at')->count());
    }

    public function test_fifteen_day_boundary_separates_pending_and_future_titles_and_excludes_closed_coverage(): void
    {
        $this->travelTo(now()->setDate(2026,10,31)->setTime(12,0));
        $this->actingAs(User::factory()->create(['role'=>UserRole::Operator]));
        $client=Client::factory()->create();
        $policy=$client->policies()->create(['number'=>'DATE-BOUNDARY','category'=>'RC auto','company'=>'Compagnia dimostrativa','starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','status'=>'active']);
        $make=fn($due,$extra=[])=>$policy->titles()->create(array_replace(['kind'=>'quietance','due_on'=>$due,'coverage_from'=>$due,'coverage_to'=>'2027-01-01','amount_cents'=>10000,'collected_cents'=>0,'payment_frequency'=>'Annuale','payment_method'=>'Bonifico a Intermediario'],$extra));
        $past=$make('2026-10-30'); $today=$make('2026-10-31'); $fifteen=$make('2026-11-15'); $sixteen=$make('2026-11-16');
        $make('2026-11-01',['collected_cents'=>10000]); $make('2026-11-02',['cancelled_at'=>now()]);
        $summary=$this->get(route('dashboard'))->assertOk()->viewData('paymentSummary');
        $this->assertSame(['overdue'=>1,'due_soon'=>2,'future'=>1],array_map(fn($group)=>$group['count'],$summary));
        $this->get(route('titles.index',['state'=>'due_soon']))->assertViewHas('titles',fn($rows)=>$rows->modelKeys()===[$today->id,$fifteen->id]);
        $this->get(route('titles.index',['state'=>'overdue']))->assertViewHas('titles',fn($rows)=>$rows->modelKeys()===[$past->id]);
        $this->get(route('titles.index',['state'=>'future']))->assertViewHas('titles',fn($rows)=>$rows->modelKeys()===[$sixteen->id]);
        $policy->forceFill(['closed_at'=>'2026-11-15 18:00:00'])->save();
        $this->get(route('dashboard'))->assertViewHas('paymentSummary',fn($rows)=>$rows['due_soon']['count']===1 && $rows['future']['count']===0);
        $this->get(route('titles.index',['state'=>'open']))->assertViewHas('titles',fn($rows)=>$rows->total()===4);
    }
}
