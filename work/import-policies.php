<?php

use App\Models\{InsurancePolicy, InsurancePolicyImport, User};
use App\Services\{InsuranceImportPreview, PolicyExcelImporter};
use Illuminate\Support\Facades\{Artisan, DB, Schema};

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Database inatteso.');
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
$admin = User::where('email', 'admin@admin.it')->firstOrFail();
if (!$admin->canManageSystem()) throw new RuntimeException('Amministratore inatteso.');
$app['auth']->guard('web')->setUser($admin);
$summary = app(InsuranceImportPreview::class)->summary() ?? throw new RuntimeException('Lotto non disponibile.');
$reader = json_decode(file_get_contents(__DIR__.'/source-reader-verification.json'), true, flags: JSON_THROW_ON_ERROR);
if (($reader['ok'] ?? false) !== true || count($reader['sources']) !== count($summary['sources'])) throw new RuntimeException('Manca la verifica indipendente delle fonti.');
foreach ($summary['sources'] as $i => $source) {
    if (basename($source['file']) !== $source['file'] || !hash_equals($source['sha256'], hash_file('sha256', dirname($root).'/'.$source['file']))
        || !hash_equals($source['sha256'], $reader['sources'][$i]['source_sha256']) || $reader['sources'][$i]['mismatches'] !== 0) throw new RuntimeException('Fonte non verificata o modificata.');
}
$service = app(PolicyExcelImporter::class);
$plan = $service->plan();
$report = $service->report($plan);
$batch = $plan['batch_hash'];
unset($plan);
file_put_contents(__DIR__.'/policy-import-plan.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
if (!in_array('--apply', $argv, true)) { echo json_encode(['mode' => 'preview'] + $report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL; exit; }
$migration = '2026_10_15_230000_add_policy_imports';
$pending = array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))), $db->table('migrations')->pluck('migration')->all()));
if ($pending && $pending !== [$migration]) throw new RuntimeException('Migrazioni inattese.');
$backup = storage_path('app/private/policy-import-backup-'.date('Ymd-His'));
if (file_exists($backup) || !mkdir($backup, 0700, true)) throw new RuntimeException('Copia preventiva non disponibile.');
$snapshot = function (string $table) use ($db): string {
    $query = $db->table($table)->orderBy($table === 'supplier_agency_company' ? 'supplier_agency_id' : 'id');
    if ($table === 'supplier_agency_company') $query->orderBy('supplier_company_id');
    return json_encode($query->get()->all(), JSON_THROW_ON_ERROR);
};
foreach (['users', 'insurance_policies', ...(Schema::hasTable('insurance_policy_imports') ? ['insurance_policy_imports'] : [])] as $table) {
    if (file_put_contents($backup.'/'.$table.'.json', $snapshot($table)) === false) throw new RuntimeException('Copia preventiva non riuscita.');
}
if ($pending && Artisan::call('migrate', ['--path' => 'database/migrations/'.$migration.'.php', '--force' => true]) !== 0) throw new RuntimeException('Migrazione non completata.');
$report = DB::transaction(function () use ($db, $service, $batch, $snapshot) {
    $protected = ['users', 'clients', 'registry_import_entries', 'supplier_companies', 'supplier_agencies', 'supplier_agency_company', 'insurance_branches', 'insurance_subbranches', 'insurance_catalog_aliases', 'attachments', 'client_document_requests', 'client_vehicles', 'client_assets'];
    $before = [];
    foreach ($protected as $table) $before[$table] = hash('sha256', $snapshot($table));
    $existingIds = InsurancePolicy::pluck('id');
    $existing = fn () => $db->table('insurance_policies')->whereIn('id', $existingIds)->orderBy('id')->get()->toJson();
    $existingSnapshot = $existing();
    $report = $service->apply($batch);
    foreach ($before as $table => $hash) {
        $report['unchanged'][$table] = hash_equals($hash, hash('sha256', $snapshot($table)));
        if (!$report['unchanged'][$table]) throw new RuntimeException('Dati modificati fuori ambito: '.$table);
    }
    if ($existingSnapshot !== $existing()) throw new RuntimeException('Polizze preesistenti modificate.');
    $report['existing_policies_unchanged'] = true;
    $repeat = $service->report($service->plan());
    if ($repeat['ready'] !== 0) throw new RuntimeException('Il lotto ricreerebbe delle polizze.');
    $report['repeat_import'] = $repeat;
    $report['counts'] = ['policies' => InsurancePolicy::count(), 'status_review' => InsurancePolicy::where('status', 'review')->count(), 'imported' => InsurancePolicyImport::where('status', 'imported')->count(), 'held' => InsurancePolicyImport::where('status', 'held')->count()];
    return $report;
});
$report['pending_migrations'] = array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))), $db->table('migrations')->pluck('migration')->all()));
$report['ok'] = !$report['pending_migrations'];
file_put_contents(__DIR__.'/policy-import-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
