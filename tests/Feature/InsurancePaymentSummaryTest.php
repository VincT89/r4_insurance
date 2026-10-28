<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{Client, InsuranceTitle, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsurancePaymentSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_filters_and_exports_use_disjoint_groups_and_residuals(): void
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
        $make('2026-10-26',12000,4000);
        $make('2026-10-26',7000);
        $make('2026-10-26',1000,1000);
        $make('2026-10-26',2000,0,['cancelled_at'=>now()]);
        $merged = Client::factory()->create(['merged_into_id'=>$client->id]);
        $hidden = $merged->policies()->create($policyData);
        $make('2026-10-26',90000,0,['insurance_policy_id'=>$hidden->id]);

        $expected = ['future'=>['count'=>27,'balance_cents'=>270000], 'partial'=>['count'=>2,'balance_cents'=>10000], 'due_today'=>['count'=>1,'balance_cents'=>20000], 'overdue'=>['count'=>2,'balance_cents'=>15000]];
        $this->get(route('dashboard'))->assertOk()->assertViewHas('paymentSummary',$expected)->assertDontSee('Polizze non pagate');
        $this->get(route('titles.index'))->assertOk()->assertViewHas('paymentSummary',$expected);
        foreach ($expected as $state=>$totals) {
            $response = $this->get(route('titles.index',['state'=>$state,'policy'=>$policy->id]))->assertOk();
            $this->assertSame($totals['count'],$response->viewData('titles')->total());
            foreach ($response->viewData('titles') as $row) $this->assertSame($state,$row->state);
            $export = $this->get(route('titles.index',['state'=>$state,'policy'=>$policy->id,'_table_all'=>1]),['X-Table-Export'=>'1'])->assertOk();
            $this->assertCount($totals['count'],$export->viewData('titles'));
            $this->assertSame($totals['balance_cents'],$export->viewData('titles')->sum('balance_cents'));
        }
        $this->get(route('titles.index',['state'=>'future','page'=>2]))->assertViewHas('titles',fn($rows)=>$rows->count()===2);
        $this->get(route('titles.index',['state'=>'open']))->assertViewHas('titles',fn($rows)=>$rows->total()===32);
        $this->get(route('titles.index',['state'=>'paid']))->assertViewHas('titles',fn($rows)=>$rows->total()===1);
        $this->get(route('titles.index',['state'=>'cancelled']))->assertViewHas('titles',fn($rows)=>$rows->total()===1);
        $this->get(route('titles.index',['state'=>'future','from'=>'2026-11-02']))->assertViewHas('titles',fn($rows)=>$rows->total()===0);
        $this->travelTo(now()->addDay());
        $this->assertSame('overdue',$today->fresh()->state);
        $this->assertSame('overdue',$partialToday->fresh()->state);
        $this->get(route('dashboard'))->assertViewHas('paymentSummary',fn($rows)=>$rows['overdue']===['count'=>4,'balance_cents'=>38000] && $rows['partial']['count']===1 && $rows['due_today']['count']===0);
    }
}
