<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Database inatteso.');
config(['session.driver'=>'array','cache.default'=>'array','queue.default'=>'sync']);
$db->statement('SET TRANSACTION READ ONLY'); $db->beginTransaction();
try {
    $admin = App\Models\User::where('email','admin@admin.it')->firstOrFail();
    $backup = json_decode(file_get_contents(storage_path('app/private/supplier-hierarchy-20261014/admin-backup.json')),true,flags:JSON_THROW_ON_ERROR);
    $baseline = $backup['users'][0] ?? $backup[0] ?? $backup;
    $report = ['adminPasswordUnchanged'=>isset($baseline['password']) && hash_equals($baseline['password'],$admin->getRawOriginal('password'))];
    $files = $app['migrator']->getMigrationFiles(database_path('migrations'));
    $report['pendingMigrations'] = array_values(array_diff(array_keys($files),$db->table('migrations')->pluck('migration')->all()));
    foreach (['clients','insurance_policies','supplier_companies','supplier_agencies','supplier_agency_company'] as $table) $report['databaseCounts'][$table] = $db->table($table)->count();
    $preview = app(App\Services\InsuranceImportPreview::class);
    foreach (['summary.json','clients.json','policies.json','issues.json','excluded.json','repeated-policies.json','source-rows.jsonl','review.xlsx'] as $name) $preview->verifiedFile($name);
    $report['batchFilesVerified'] = true;
    $existing = [];
    foreach ($db->table('clients')->get(['id','tax_code','vat_number','merged_into_id']) as $client) foreach (['tax_code','vat_number'] as $field) {
        $key = preg_replace('/\s+/u','',mb_strtoupper($client->$field ?? ''));
        if ($key !== '') $existing[$key][$client->id] = true;
    }
    $candidates = json_decode(file_get_contents($preview->verifiedFile('clients.json')),true,flags:JSON_THROW_ON_ERROR);
    $report['candidateClientsMatchingExisting'] = count(array_filter($candidates,fn($c)=>isset($existing[$c['fiscal_identifier']])));
    $report['importedClientLinksValid'] = $db->table('registry_import_entries')->where('kind','client')->where('registry_import_entries.status','imported')->leftJoin('clients','clients.id','=','registry_import_entries.entity_id')->whereNull('clients.id')->count() === 0;
    $report['importedRegistries'] = $db->table('registry_import_entries')->where('kind','!=','lock')->select('kind','status')->get()->groupBy('kind')->map(fn($rows)=>$rows->countBy('status'));
    $report['agencyCompanyLinksValid'] = $db->table('supplier_agency_company as link')->leftJoin('supplier_agencies as agency','agency.id','=','link.supplier_agency_id')->leftJoin('supplier_companies as company','company.id','=','link.supplier_company_id')->where(fn($q)=>$q->whereNull('agency.id')->orWhereNull('company.id'))->count() === 0;
    $clientId=$db->table('clients')->orderBy('id')->value('id');
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    foreach (['/settings/insurance-import','/settings/insurance-import?section=branches','/settings/insurance-import?section=registry','/settings/insurance-import?section=registry&result=held','/clients','/clients?import_review=1','/clients/'.$clientId.'?tab=registry','/clients/'.$clientId.'/edit','/suppliers','/suppliers/create','/supplier-companies'] as $url) {
        $app['auth']->forgetGuards(); $app['auth']->guard('web')->setUser($admin);
        $response = $kernel->handle(Illuminate\Http\Request::create('http://localhost'.$url,'GET'));
        $report['pages'][$url] = ['status'=>$response->getStatusCode(),'favicon'=>str_contains($response->getContent(),'favicon-32x32.png?v=gs-white-20261015')];
    }
    $report['ok'] = $report['adminPasswordUnchanged'] && $report['importedClientLinksValid'] && $report['agencyCompanyLinksValid'] && !$report['pendingMigrations'] && !array_filter($report['pages'],fn($p)=>$p['status']!==200 || !$p['favicon']);
} finally { $db->rollBack(); }
file_put_contents(__DIR__.'/import-live-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['ok'] ? 0 : 1);
