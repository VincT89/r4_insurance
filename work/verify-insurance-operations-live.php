<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Artisan,Auth};
if(DB::connection()->getDriverName()!=='mysql' || DB::connection()->getDatabaseName()!=='r4_insurance')throw new RuntimeException('Database inatteso.');
$fingerprint=fn($table)=>hash('sha256',json_encode(DB::table($table)->orderBy('id')->get()->all(),JSON_THROW_ON_ERROR));
$before=['clients'=>$fingerprint('clients'),'policies'=>$fingerprint('insurance_policies')];
Artisan::call('migrate',['--force'=>true]);
$migrationOutput=Artisan::output();
$admin=App\Models\User::where('role','admin')->where('status','active')->firstOrFail();Auth::login($admin);
$priorities=['EUROPE ASSISTANCE','NOBIS COMPAGNIA DI ASSICURAZIONI SPA'];
DB::transaction(function()use($priorities){foreach($priorities as $name){$company=App\Models\SupplierCompany::where('name',$name)->where('status','active')->lockForUpdate()->sole();if(!$company->quote_priority){$company->update(['quote_priority'=>true]);app(App\Services\AuditLogService::class)->log('updated',$company,description:'Compagnia prioritaria per i preventivi, come confermato dal cliente.');}}});
if($before!==['clients'=>$fingerprint('clients'),'policies'=>$fingerprint('insurance_policies')])throw new RuntimeException('Variazione inattesa nei clienti o nelle polizze.');
$report=['database'=>'r4_insurance','migrations'=>$migrationOutput,'priorities'=>App\Models\SupplierCompany::where('quote_priority',true)->pluck('name')->all(),'clients'=>App\Models\Client::count(),'policies'=>App\Models\InsurancePolicy::count(),'quote_cases'=>App\Models\InsuranceQuoteCase::count(),'titles'=>App\Models\InsuranceTitle::count(),'collections'=>App\Models\InsuranceCollection::count(),'records_unchanged'=>true,'smtp_configured'=>app(App\Services\AgencyMailConfiguration::class)->ready(),'pages'=>[]];
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
DB::beginTransaction();
try {
 foreach(['/dashboard','/quotes','/quotes/create','/policies/create','/titles','/collections','/deadlines?kind=title','/reports','/agency-communications/collections','/agency-communications/history'] as $url){
    $request=Illuminate\Http\Request::create($url,'GET');$response=$kernel->handle($request);$report['pages'][$url]=$response->getStatusCode();$kernel->terminate($request,$response);if($response->getStatusCode()!==200)throw new RuntimeException('Pagina non disponibile: '.$url.' / '.$response->getStatusCode());
 }
} finally {DB::rollBack();}
$report['ok']=true;file_put_contents(__DIR__.'/insurance-operations-live-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
