<?php

use App\Models\{InsuranceBranch, InsuranceCatalogAlias, InsurancePolicy, InsuranceSubbranch, User};
use App\Services\{ApprovedInsuranceCatalogMerges, InsuranceCatalog, InsuranceImportPreview};
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
if (InsurancePolicy::exists()) throw new RuntimeException('Sono presenti polizze: verificare i collegamenti prima di accorpare.');
$summary = app(InsuranceImportPreview::class)->summary() ?? throw new RuntimeException('Fonte non disponibile.');
foreach ($summary['sources'] as $source) {
    if (basename($source['file']) !== $source['file'] || !hash_equals($source['sha256'], hash_file('sha256', dirname($root).'/'.$source['file']))) throw new RuntimeException('Fonte modificata.');
}
$migration = '2026_10_15_220000_create_insurance_catalog_aliases';
$pending = array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))), $db->table('migrations')->pluck('migration')->all()));
if ($pending && $pending !== [$migration]) throw new RuntimeException('Migrazioni inattese: '.implode(', ', $pending));
if (!in_array('--apply', $argv, true)) { echo json_encode(['mode' => 'preview', 'pending' => $pending, 'groups' => array_column(ApprovedInsuranceCatalogMerges::GROUPS, 'name')]).PHP_EOL; exit; }

$backup = storage_path('app/private/catalog-merges-backup-'.date('Ymd-His'));
if (file_exists($backup) || !mkdir($backup, 0700, true)) throw new RuntimeException('Copia preventiva non disponibile.');
$snapshot = function (string $table) use ($db): string {
    $query = $db->table($table)->orderBy($table === 'supplier_agency_company' ? 'supplier_agency_id' : 'id');
    if ($table === 'supplier_agency_company') $query->orderBy('supplier_company_id');
    return json_encode($query->get()->all(), JSON_THROW_ON_ERROR);
};
foreach (['users', 'insurance_branches', 'insurance_subbranches', ...(Schema::hasTable('insurance_catalog_aliases') ? ['insurance_catalog_aliases'] : [])] as $table) {
    if (file_put_contents($backup.'/'.$table.'.json', $snapshot($table)) === false) throw new RuntimeException('Copia preventiva non riuscita.');
}
if ($pending && Artisan::call('migrate', ['--path' => 'database/migrations/'.$migration.'.php', '--force' => true]) !== 0) throw new RuntimeException('Migrazione non completata.');
$report = DB::transaction(function () use ($db, $snapshot) {
    InsuranceBranch::orderBy('id')->lockForUpdate()->get();
    $protected = ['users', 'clients', 'insurance_policies', 'registry_import_entries', 'supplier_companies', 'supplier_agencies', 'supplier_agency_company', 'attachments', 'client_document_requests'];
    $fingerprints = [];
    foreach ($protected as $table) $fingerprints[$table] = hash('sha256', $snapshot($table));
    $affectedKeys = collect(ApprovedInsuranceCatalogMerges::GROUPS)->flatMap(fn ($group) => $group['sources'])->map(fn ($name) => InsuranceCatalog::key($name));
    $untouchedIds = InsuranceBranch::whereNotIn('import_key', $affectedKeys)->pluck('id');
    $untouched = fn () => InsuranceBranch::whereIn('id', $untouchedIds)->with('subbranches')->orderBy('id')->get()->toJson();
    $before = $untouched();
    $report = app(ApprovedInsuranceCatalogMerges::class)->apply();
    if ($before !== $untouched()) throw new RuntimeException('Modificata una voce fuori dagli accorpamenti approvati.');
    // Try the original import inside a savepoint, then always undo the trial.
    $db->beginTransaction();
    try { $repeat = app(InsuranceCatalog::class)->importPreview(); }
    finally { $db->rollBack(); }
    if ($repeat['branches_created'] || $repeat['subbranches_created']) throw new RuntimeException('Il catalogo ricreerebbe dei doppioni.');
    $report['repeat_import'] = $repeat;
    $report['other_catalog_entries_unchanged'] = true;
    foreach ($fingerprints as $table => $fingerprint) {
        $report['unchanged'][$table] = hash_equals($fingerprint, hash('sha256', $snapshot($table)));
        if (!$report['unchanged'][$table]) throw new RuntimeException('Dati modificati fuori ambito: '.$table);
    }
    $report['aliases_valid'] = !DB::table('insurance_catalog_aliases as a')->join('insurance_subbranches as s', 's.id', '=', 'a.insurance_subbranch_id')->whereColumn('a.insurance_branch_id', '!=', 's.insurance_branch_id')->exists();
    if (!$report['aliases_valid']) throw new RuntimeException('Collegamento incoerente.');
    return $report;
});
$report['counts'] = ['branches' => InsuranceBranch::count(), 'subbranches' => InsuranceSubbranch::count(), 'aliases' => InsuranceCatalogAlias::count(), 'branches_to_review' => InsuranceBranch::where('needs_review', true)->count(), 'subbranches_to_review' => InsuranceSubbranch::where('needs_review', true)->count(), 'policies' => InsurancePolicy::count()];
$report['pending_migrations'] = array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))), $db->table('migrations')->pluck('migration')->all()));
$report['ok'] = !$report['pending_migrations'] && $report['counts']['policies'] === 0;
file_put_contents(__DIR__.'/approved-catalog-merges-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['ok'] ? 0 : 1);
