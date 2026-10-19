<?php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{User,Client,InsurancePolicy,InsuranceBranch,SupplierCompany,SupplierAgency};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FullAuditVolumeTest extends TestCase
{
    use RefreshDatabase;
    public function test_lists_with_ten_years_of_policy_volume(): void
    {
        $this->travelTo(now()->setDate(2026,10,18)->setTime(12,0));
        $admin=User::factory()->create(['role'=>UserRole::Admin,'status'=>'active']);
        $this->actingAs($admin);
        $client=Client::factory()->create(['name'=>'Marco Riva','email'=>null]);
        $branch=InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
        $company=SupplierCompany::create(['name'=>'Nobis','status'=>'active']);
        $agency=SupplierAgency::create(['name'=>'Agenzia Valserena','status'=>'active','supplier_company_id'=>$company->id]);
        $policy=$client->policies()->create(['number'=>'SA-2026-000001','category'=>'RC auto','insurance_branch_id'=>$branch->id,'company'=>$company->name,'supplier_company_id'=>$company->id,'supplier_agency_id'=>$agency->id,'starts_on'=>'2026-01-01','expires_on'=>'2027-01-01','status'=>'active','premium'=>'1000.00','payment_frequency'=>'Annuale','payment_method'=>'Bonifico a Intermediario']);
        $clientBase=$client->getAttributes();unset($clientBase['id']);
        $policyBase=$policy->getAttributes();unset($policyBase['id']);
        DB::transaction(function()use($clientBase,$policyBase){
            for($offset=2;$offset<=10000;$offset+=200){$rows=[];for($i=$offset;$i<min($offset+200,10001);$i++)$rows[]=array_replace($clientBase,['id'=>$i,'name'=>'Anagrafica di collaudo '.$i,'slug'=>'collaudo-volume-'.$i]);DB::table('clients')->insert($rows);}
            for($offset=2;$offset<=40000;$offset+=200){$rows=[];for($i=$offset;$i<min($offset+200,40001);$i++){$year=2017+(int)floor(($i-1)/4000);$rows[]=array_replace($policyBase,['id'=>$i,'client_id'=>1+(int)floor(($i-1)/4),'number'=>'SA-'.$year.'-'.str_pad((string)$i,6,'0',STR_PAD_LEFT),'starts_on'=>$year.'-01-01','expires_on'=>($year+1).'-01-01','created_at'=>$year.'-01-01 10:00:00','updated_at'=>$year.'-01-01 10:00:00']);}DB::table('insurance_policies')->insert($rows);}
        });
        $report=['engine'=>'SQLite in memoria; processo PHP locale; una richiesta per pagina; non è un test di carico concorrente','clients'=>Client::count(),'policies'=>InsurancePolicy::count(),'requests'=>[]];
        foreach(['/clients','/policies','/dashboard','/reports?month=2026-01&date_basis=created_at','/agency-communications?period=custom&from=2026-01-01&to=2026-10-18'] as $url){
            DB::flushQueryLog();DB::enableQueryLog();$start=microtime(true);$response=$this->get($url);$elapsed=microtime(true)-$start;$queries=DB::getQueryLog();DB::disableQueryLog();
            $report['requests'][]=['url'=>$url,'status'=>$response->status(),'seconds'=>round($elapsed,3),'queries'=>count($queries),'sql_ms'=>round(array_sum(array_column($queries,'time')),1),'html_bytes'=>strlen($response->getContent()),'peak_memory_mb'=>round(memory_get_peak_usage(true)/1048576,1)];
            $response->assertOk();
        }
        file_put_contents(base_path('work/full-audit-2026-10-18/volume-report.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }
}
