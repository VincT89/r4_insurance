<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Database inatteso.');
$migration = '2026_10_15_180000_add_agency_company_mandates';
$schema = Illuminate\Support\Facades\Schema::connection($db->getName());
$pending = array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))), $db->table('migrations')->pluck('migration')->all()));
if (!$pending && $schema->hasTable('supplier_agency_company')) { echo json_encode(['ok'=>true,'alreadyApplied'=>true]).PHP_EOL; exit; }
if ($pending !== [$migration] || $schema->hasTable('supplier_agency_company')) throw new RuntimeException('Schema o migrazioni pendenti inattesi.');
$users = $db->table('users')->orderBy('id')->get()->map(fn($r)=>(array)$r)->all();
if (!$db->table('users')->where('email', 'admin@admin.it')->exists()) throw new RuntimeException('Admin atteso assente.');
$backup = storage_path('app/private/mandates-'.date('Ymd-His'));
if (file_exists($backup) || !mkdir($backup,0700,true)) throw new RuntimeException('Backup non disponibile.');
foreach (['users','supplier_companies','supplier_agencies'] as $table) {
    if (file_put_contents($backup.'/'.$table.'.json', json_encode($db->table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Backup non riuscito.');
}
$counts = [];
foreach (['clients','insurance_policies','attachments','supplier_companies','supplier_agencies'] as $table) $counts[$table] = $db->table($table)->count();
$code = Illuminate\Support\Facades\Artisan::call('migrate', ['--path'=>'database/migrations/'.$migration.'.php','--force'=>true]);
if ($code !== 0) throw new RuntimeException('Migrazione non completata.');
$report = ['migrationApplied'=>$schema->hasTable('supplier_agency_company'), 'existingUsersUnchanged'=>$users === $db->table('users')->orderBy('id')->get()->map(fn($r)=>(array)$r)->all(), 'businessCountsUnchanged'=>true, 'backupCreated'=>true];
foreach ($counts as $table=>$count) if ($db->table($table)->count() !== $count) $report['businessCountsUnchanged'] = false;
$report['preservedMandates'] = $db->table('supplier_agency_company')->count() === $db->table('supplier_agencies')->whereNotNull('supplier_company_id')->count();
$report['ok'] = !in_array(false,$report,true);
file_put_contents(__DIR__.'/mandates-migration-report.json', json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['ok'] ? 0 : 1);
