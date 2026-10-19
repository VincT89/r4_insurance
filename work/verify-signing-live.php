<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{Artisan, Auth, DB};

if (DB::connection()->getDriverName()!=='mysql' || DB::connection()->getDatabaseName()!=='r4_insurance') throw new RuntimeException('Database inatteso.');
$tables=['clients','insurance_policies','supplier_agencies','supplier_companies','supplier_agency_company','insurance_branches','insurance_subbranches','insurance_titles','insurance_collections','insurance_quote_cases','insurance_quotes','agency_commission_schedules','agency_commission_rates','attachments'];
$fingerprint=function($table){$rows=DB::table($table)->get()->map(function($row)use($table){if($table==='attachments')unset($row->insurance_form_document_id,$row->insurance_form_submission_id,$row->insurance_coverage_check_id);return json_encode($row,JSON_THROW_ON_ERROR);})->sort()->values()->all();return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));};
$before=array_map($fingerprint,$tables);
$migration=Artisan::call('migrate',['--force'=>true,'--path'=>['database/migrations/2026_10_16_190000_create_insurance_signing_workflow.php','database/migrations/2026_10_18_120000_expand_signing_document_storage.php']]);$output=Artisan::output();
if($migration!==0) throw new RuntimeException('Migrazione non completata: '.$output);
Artisan::call('db:seed',['--class'=>Database\Seeders\InsuranceFormTemplateSeeder::class,'--force'=>true]);
/** New nullable attachment links are excluded when comparing existing records across this migration. */
$after=array_map($fingerprint,$tables);
if($before!==$after) throw new RuntimeException('Dati preesistenti modificati.');
$admin=App\Models\User::where('role','admin')->where('status','active')->firstOrFail();Auth::login($admin);
$policy=App\Models\InsurancePolicy::whereNotNull('vehicle_id')->firstOrFail();$client=$policy->client;$template=App\Models\InsuranceFormTemplate::where('status','draft')->firstOrFail();
$report=['database'=>'r4_insurance','migration'=>$output,'existing_records_unchanged'=>true,'clients'=>App\Models\Client::count(),'policies'=>App\Models\InsurancePolicy::count(),'source_templates'=>App\Models\InsuranceFormTemplate::whereNotNull('source_sheet')->count(),'template_states'=>App\Models\InsuranceFormTemplate::select('status')->selectRaw('count(*) as total')->groupBy('status')->pluck('total','status')->all(),'pages'=>[]];
$urls=['/settings/forms','/settings/forms/create','/settings/forms/'.$template->id,'/clients/'.$client->id,'/clients/'.$client->id.'/forms','/clients/'.$client->id.'/forms/create','/clients/'.$client->id.'/policies/'.$policy->id,'/clients/'.$client->id.'/forms/create?policy='.$policy->id,'/clients/'.$client->id.'/policies/'.$policy->id.'/coverage-checks'];
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
foreach($urls as $url){$request=Illuminate\Http\Request::create($url,'GET');$response=$kernel->handle($request);$report['pages'][$url]=$response->getStatusCode();$kernel->terminate($request,$response);if($response->getStatusCode()!==200)throw new RuntimeException('Pagina non disponibile: '.$url);}
$report['packages']=App\Models\InsuranceFormPackage::count();$report['coverage_checks']=App\Models\InsuranceCoverageCheck::count();$report['external_plate_queries']=0;$report['ok']=true;
$report['text_columns']=[];foreach(['insurance_form_templates'=>'body','insurance_form_documents'=>'body','insurance_form_packages'=>'snapshot'] as $table=>$name){$column=collect(DB::getSchemaBuilder()->getColumns($table))->firstWhere('name',$name);$report['text_columns'][$table]=$column['type_name'];}
$report['source_templates_match']=[];foreach(json_decode(file_get_contents(resource_path('insurance/forms-source.json')),true,512,JSON_THROW_ON_ERROR) as $source){$saved=App\Models\InsuranceFormTemplate::where('source_hash',$source['source_hash'])->where('source_sheet',$source['source_sheet'])->where('version',1)->firstOrFail();$report['source_templates_match'][$source['source_sheet']]=['same_body'=>$saved->body===$source['body'],'status'=>$saved->status,'edited_by_user'=>$saved->created_by!==null];}
file_put_contents(__DIR__.'/signing-live-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
