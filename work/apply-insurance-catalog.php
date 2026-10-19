<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Database inatteso.');
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
$admin = App\Models\User::where('email', 'admin@admin.it')->firstOrFail();
if (!$admin->canManageSystem()) throw new RuntimeException('Amministratore inatteso.');
auth()->guard('web')->setUser($admin);
$preview = app(App\Services\InsuranceImportPreview::class);
foreach ($preview->summary()['sources'] as $source) if (!hash_equals($source['sha256'], hash_file('sha256', dirname($root).'/'.$source['file']))) throw new RuntimeException('Fonte Excel modificata.');
$migration = '2026_10_15_200000_create_insurance_catalog';
$pending = array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))), $db->table('migrations')->pluck('migration')->all()));
if ($pending && $pending !== [$migration]) throw new RuntimeException('Migrazioni inattese: '.implode(', ', $pending));
if (!in_array('--apply', $argv, true)) { echo json_encode(['mode' => 'preview', 'pending' => $pending]).PHP_EOL; exit; }
$backup = storage_path('app/private/catalog-backup-'.date('Ymd-His'));
if (file_exists($backup) || !mkdir($backup, 0700, true)) throw new RuntimeException('Backup non disponibile.');
$before = []; $columns = [];
$tables = ['users', 'clients', 'insurance_policies', 'registry_import_entries', 'supplier_companies', 'supplier_agencies', 'supplier_agency_company'];
foreach ($tables as $table) {
    $columns[$table] = Illuminate\Support\Facades\Schema::getColumnListing($table);
    $rows = $db->table($table)->orderBy($table === 'supplier_agency_company' ? 'supplier_agency_id' : 'id');
    if ($table === 'supplier_agency_company') $rows->orderBy('supplier_company_id');
    $json = json_encode($rows->get($columns[$table])->all(), JSON_THROW_ON_ERROR);
    if (file_put_contents($backup.'/'.$table.'.json', $json) === false) throw new RuntimeException('Backup non riuscito.');
    $before[$table] = hash('sha256', $json);
}
if ($pending && Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => 'database/migrations/'.$migration.'.php', '--force' => true]) !== 0) throw new RuntimeException('Migrazione non completata.');
$report = app(App\Services\InsuranceCatalog::class)->importPreview();
foreach ($tables as $table) {
    $rows = $db->table($table)->orderBy($table === 'supplier_agency_company' ? 'supplier_agency_id' : 'id');
    if ($table === 'supplier_agency_company') $rows->orderBy('supplier_company_id');
    $report['unchanged'][$table] = hash_equals($before[$table], hash('sha256', json_encode($rows->get($columns[$table])->all(), JSON_THROW_ON_ERROR)));
    if (!$report['unchanged'][$table]) throw new RuntimeException('Verifica dati esistenti non superata: '.$table);
}
$report['branches'] = App\Models\InsuranceBranch::count();
$report['subbranches'] = App\Models\InsuranceSubbranch::count();
$report['clients_held'] = App\Models\RegistryImportEntry::where('kind', 'client')->where('status', 'held')->count();
$report['policies'] = App\Models\InsurancePolicy::count();
file_put_contents(__DIR__.'/catalog-apply-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
