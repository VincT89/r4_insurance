<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Database inatteso.');
config(['session.driver'=>'array','cache.default'=>'array','queue.default'=>'sync']);
$admin = App\Models\User::where('email','admin@admin.it')->firstOrFail();
if (!$admin->canManageSystem()) throw new RuntimeException('Account amministratore inatteso.');
auth()->guard('web')->setUser($admin);
$password = $admin->getRawOriginal('password');
$reader = json_decode(file_get_contents(__DIR__.'/source-reader-verification.json'),true,flags:JSON_THROW_ON_ERROR);
$summary = app(App\Services\InsuranceImportPreview::class)->summary();
if (($reader['ok'] ?? false) !== true || count($reader['sources']) !== count($summary['sources'])) throw new RuntimeException('Manca il confronto indipendente delle fonti.');
foreach ($summary['sources'] as $i=>$source) {
    if (!hash_equals($source['sha256'],hash_file('sha256',dirname($root).'/'.$source['file'])) || !hash_equals($source['sha256'],$reader['sources'][$i]['source_sha256']) || $reader['sources'][$i]['mismatches'] !== 0) throw new RuntimeException('File sorgenti modificati o non verificati.');
}
$service = app(App\Services\RegistryExcelImporter::class);
$plan = $service->plan();
$previous = Illuminate\Support\Facades\Schema::hasTable('registry_import_entries')
    ? App\Models\RegistryImportEntry::where('status','imported')->get()->keyBy(fn($entry)=>$entry->kind.':'.$entry->source_key) : collect();
$preview = ['mode'=>'preview','ready'=>[],'held'=>[],'already_imported'=>0,'fields_to_review'=>0,'deferred_fields'=>[]];
foreach($plan['entries'] as $entry) {
    $prior=$previous->get($entry['kind'].':'.$entry['source_key']);
    if ($prior && hash_equals($prior->payload_hash,$entry['payload_hash'])) { $preview['already_imported']++; continue; }
    $key = $entry['held'] ? 'held' : 'ready';
    $preview[$key][$entry['kind']] = ($preview[$key][$entry['kind']] ?? 0)+1;
    if ($entry['deferred_data']) $preview['fields_to_review']++;
    foreach(array_keys($entry['deferred_data']) as $field) $preview['deferred_fields'][$field]=($preview['deferred_fields'][$field]??0)+1;
}
if (!in_array('--apply',$argv,true)) { echo json_encode($preview,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL; exit; }
$migration = '2026_10_15_190000_create_registry_import_entries';
$pending = array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))),$db->table('migrations')->pluck('migration')->all()));
if ($pending && $pending !== [$migration]) throw new RuntimeException('Migrazioni pendenti inattese.');
$tables = ['users','clients','supplier_companies','supplier_agencies','supplier_agency_company','audit_logs','insurance_policies'];
if (Illuminate\Support\Facades\Schema::hasTable('registry_import_entries')) $tables[]='registry_import_entries';
$backup = storage_path('app/private/registry-import-backup-'.date('Ymd-His'));
if (file_exists($backup) || !mkdir($backup,0700,true)) throw new RuntimeException('Backup non disponibile.');
$snapshots = [];
foreach ($tables as $table) {
    $data = $db->table($table)->get()->map(fn($r)=>(array)$r)->all();
    $json = json_encode($data,JSON_THROW_ON_ERROR);
    if (file_put_contents($backup.'/'.$table.'.json',$json) === false) throw new RuntimeException('Backup non riuscito.');
    $snapshots[$table] = ['count'=>count($data),'sha256'=>hash('sha256',$json)];
}
file_put_contents($backup.'/counts-and-hashes.json',json_encode($snapshots,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
if ($pending) {
    $code = Illuminate\Support\Facades\Artisan::call('migrate',['--path'=>'database/migrations/'.$migration.'.php','--force'=>true]);
    if ($code !== 0) throw new RuntimeException('Migrazione registro importazioni non completata.');
}
$report = $service->apply($plan);
$report['admin_unchanged'] = hash_equals($password,$admin->fresh()->getRawOriginal('password'));
$report['users_unchanged'] = hash_equals($snapshots['users']['sha256'],hash('sha256',json_encode($db->table('users')->get()->map(fn($r)=>(array)$r)->all(),JSON_THROW_ON_ERROR)));
$report['policies_unchanged'] = hash_equals($snapshots['insurance_policies']['sha256'],hash('sha256',json_encode($db->table('insurance_policies')->get()->map(fn($r)=>(array)$r)->all(),JSON_THROW_ON_ERROR)));
foreach(['clients','supplier_companies','supplier_agencies','supplier_agency_company','insurance_policies'] as $table) $report['database_counts'][$table]=$db->table($table)->count();
$report['source_readers_match']=true; $report['backup_created']=true;
$report['ok']=$report['admin_unchanged'] && $report['users_unchanged'] && $report['policies_unchanged'];
file_put_contents(__DIR__.'/registry-import-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['ok'] ? 0 : 1);
