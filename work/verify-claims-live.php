<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{Artisan, Auth, DB};

if (DB::connection()->getDriverName()!=='mysql' || DB::connection()->getDatabaseName()!=='r4_insurance') throw new RuntimeException('Database inatteso.');
$tables=['clients','insurance_policies','supplier_agencies','supplier_companies','supplier_agency_company','insurance_branches','insurance_subbranches','insurance_titles','insurance_collections','insurance_quote_cases','insurance_quotes','agency_commission_schedules','agency_commission_rates','attachments','insurance_form_templates','insurance_form_packages','insurance_form_documents','insurance_form_submissions','insurance_coverage_checks'];
$fingerprint=function($table) {
    $rows=DB::table($table)->get()->map(function($row) use($table) {if($table==='attachments') unset($row->insurance_claim_id,$row->insurance_claim_event_id);return json_encode($row,JSON_THROW_ON_ERROR);})->sort()->values()->all();
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
};
$before=array_map($fingerprint,$tables);
$result=Artisan::call('migrate',['--force'=>true,'--path'=>'database/migrations/2026_10_18_150000_create_insurance_claims.php']);
$output=Artisan::output();if($result!==0) throw new RuntimeException('Migrazione non completata: '.$output);
if($before!==array_map($fingerprint,$tables)) throw new RuntimeException('Dati preesistenti modificati.');
$admin=App\Models\User::where('role','admin')->where('status','active')->firstOrFail();Auth::login($admin);
$policy=App\Models\InsurancePolicy::firstOrFail();
$report=['database'=>'r4_insurance','migration'=>$output,'existing_records_unchanged'=>true,'clients'=>App\Models\Client::count(),'policies'=>App\Models\InsurancePolicy::count(),'claims'=>App\Models\InsuranceClaim::count(),'pages'=>[]];
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
foreach(['/claims','/claims/create','/claims/create?policy='.$policy->id,'/dashboard','/clients/'.$policy->client_id,'/clients/'.$policy->client_id.'/policies/'.$policy->id,'/deadlines','/audit-logs?auditable_type=claims&action=claims'] as $url) {
    $request=Illuminate\Http\Request::create($url,'GET');$response=$kernel->handle($request);$report['pages'][$url]=$response->getStatusCode();$kernel->terminate($request,$response);
    if($response->getStatusCode()!==200) throw new RuntimeException('Pagina non disponibile: '.$url.' ('.$response->getStatusCode().')');
}
$report['ok']=true;file_put_contents(__DIR__.'/claims-live-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
