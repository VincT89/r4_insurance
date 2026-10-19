<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{Artisan, Auth, DB, Schema};

if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'r4_insurance') throw new RuntimeException('Database inatteso.');
$columns = [];
foreach (['clients','insurance_policies','insurance_quote_cases','insurance_quotes','supplier_companies','supplier_agencies','attachments'] as $table) $columns[$table] = Schema::getColumnListing($table);
$fingerprint = fn ($table) => hash('sha256',json_encode(DB::table($table)->orderBy('id')->get($columns[$table])->all(),JSON_THROW_ON_ERROR));
$before = array_map($fingerprint,array_keys($columns));
$code = Artisan::call('migrate',['--force'=>true,'--path'=>'database/migrations/2026_10_16_170000_create_quote_request_emails.php']);
$migrationOutput = Artisan::output();
if ($code !== 0) throw new RuntimeException('Migrazione non completata: '.$migrationOutput);
if ($before !== array_map($fingerprint,array_keys($columns))) throw new RuntimeException('Dati esistenti modificati in modo inatteso.');
$admin = App\Models\User::where('role','admin')->where('status','active')->firstOrFail();
Auth::login($admin);
$client = App\Models\Client::current()->firstOrFail();
$branch = App\Models\InsuranceBranch::where('status','active')->firstOrFail();
$company = App\Models\SupplierCompany::where('status','active')->where('quote_priority',true)->firstOrFail();
$configuration = app(App\Services\AgencyMailConfiguration::class);
$report = ['database'=>'r4_insurance','migrations'=>$migrationOutput,'existing_records_unchanged'=>true,'clients'=>App\Models\Client::count(),'policies'=>App\Models\InsurancePolicy::count(),'smtp_configured'=>$configuration->ready(),'priority_companies'=>App\Models\SupplierCompany::where('quote_priority',true)->get()->map(fn ($c)=>['id'=>$c->id,'name'=>$c->name,'active'=>$c->status==='active','email_present'=>(bool)filter_var($c->email,FILTER_VALIDATE_EMAIL)])->all(),'emails_before'=>App\Models\InsuranceQuoteEmail::count(),'pages'=>[]];
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
DB::beginTransaction();
try {
    /** Temporary records render the new screens and are rolled back; the transport is never invoked. */
    $case = $client->quoteCases()->create(['insurance_branch_id'=>$branch->id,'subject'=>'Verifica temporanea interfaccia','status'=>'open','created_by'=>$admin->id]);
    $message = $case->emails()->create(['supplier_company_id'=>$company->id,'user_id'=>$admin->id,'status'=>'review','payload'=>['from'=>$configuration->sender(),'recipient'=>['address'=>$company->email ?: '','name'=>$company->name],'subject'=>$case->subject,'body'=>'Verifica temporanea: questa richiesta non viene inviata.','attachments'=>[]],'fingerprint'=>str_repeat('0',64),'review_expires_at'=>now()->addMinutes(30)]);
    foreach (['/quotes','/quotes/create','/quotes/'.$case->id,'/quotes/'.$case->id.'/emails/create','/quote-emails/'.$message->id] as $url) {
        $request = Illuminate\Http\Request::create($url,'GET');
        $response = $kernel->handle($request);
        $report['pages'][$url] = $response->getStatusCode();
        $kernel->terminate($request,$response);
        if ($response->getStatusCode() !== 200) throw new RuntimeException('Pagina non disponibile: '.$url);
    }
} finally { DB::rollBack(); }
if ($before !== array_map($fingerprint,array_keys($columns)) || $report['emails_before'] !== App\Models\InsuranceQuoteEmail::count()) throw new RuntimeException('La verifica ha lasciato dati temporanei.');
$report['temporary_records_removed'] = true;
$report['real_emails_sent'] = 0;
$report['ok'] = true;
file_put_contents(__DIR__.'/quote-email-live-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
