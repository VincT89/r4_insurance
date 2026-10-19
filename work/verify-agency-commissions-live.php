<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{Artisan, Auth, DB, Schema};

if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'r4_insurance') throw new RuntimeException('Database inatteso.');
$tables = ['clients','insurance_policies','supplier_agencies','supplier_companies','supplier_agency_company','insurance_branches','insurance_subbranches','insurance_titles','insurance_collections','insurance_quote_cases','insurance_quotes'];
$fingerprint = function($table) {
    $rows = DB::table($table)->get()->map(fn($row)=>json_encode($row,JSON_THROW_ON_ERROR))->sort()->values()->all();
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
};
$before = array_map($fingerprint,$tables);
$code = Artisan::call('migrate',['--force'=>true,'--path'=>'database/migrations/2026_10_16_180000_create_agency_commission_schedules.php']);
$output = Artisan::output();
if ($code !== 0) throw new RuntimeException('Migrazione non completata: '.$output);
if ($before !== array_map($fingerprint,$tables)) throw new RuntimeException('Dati preesistenti modificati.');
$admin = App\Models\User::where('role','admin')->where('status','active')->firstOrFail();
Auth::login($admin);
$agency = App\Models\SupplierAgency::whereHas('companies')->where('status','active')->firstOrFail();
$company = $agency->companies()->firstOrFail();
$branch = App\Models\InsuranceBranch::where('status','active')->firstOrFail();
$count = App\Models\AgencyCommissionSchedule::count(); $rates = App\Models\AgencyCommissionRate::count();
$report = ['database'=>'r4_insurance','migration'=>$output,'existing_records_unchanged'=>true,'clients'=>App\Models\Client::count(),'policies'=>App\Models\InsurancePolicy::count(),'schedules_before'=>$count,'rates_before'=>$rates,'pages'=>[]];
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
DB::beginTransaction();
try {
    /** Render a temporary configuration without invented rates; the transaction removes it after checking. */
    $schedule = $agency->commissionSchedules()->create(['supplier_company_id'=>$company->id,'valid_from'=>today(),'created_by'=>$admin->id]);
    $schedule->rates()->create(['insurance_branch_id'=>$branch->id,'scope_key'=>$branch->id.':0']);
    foreach (['/suppliers/'.$agency->id,'/suppliers/'.$agency->id.'/commissions','/suppliers/'.$agency->id.'/commissions/create','/suppliers/'.$agency->id.'/commissions/'.$schedule->id,'/suppliers/'.$agency->id.'/commissions/'.$schedule->id.'/edit'] as $url) {
        $request = Illuminate\Http\Request::create($url,'GET'); $response = $kernel->handle($request);
        $report['pages'][$url] = $response->getStatusCode(); $kernel->terminate($request,$response);
        if ($response->getStatusCode() !== 200) throw new RuntimeException('Pagina non disponibile: '.$url);
    }
} finally { DB::rollBack(); }
if ($before !== array_map($fingerprint,$tables) || $count !== App\Models\AgencyCommissionSchedule::count() || $rates !== App\Models\AgencyCommissionRate::count()) throw new RuntimeException('Registrazioni temporanee non annullate.');
$report['temporary_records_removed'] = true;
$report['automatic_commission_calculation_enabled'] = false;
$report['ok'] = true;
file_put_contents(__DIR__.'/agency-commission-live-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
