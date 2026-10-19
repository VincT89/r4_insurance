<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Database inatteso.');
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
$admin = App\Models\User::where('email', 'admin@admin.it')->firstOrFail();
if (!$admin->canManageSystem()) throw new RuntimeException('Amministratore inatteso.');
auth()->guard('web')->setUser($admin);
$preview = app(App\Services\InsuranceImportPreview::class);
$plan = json_decode(file_get_contents($preview->directory().'/held-review/resolution-plan.json'), true, 512, JSON_THROW_ON_ERROR);
$actions = array_count_values(array_column($plan['operations'], 'action'));
if ($actions !== ['link' => 11, 'create' => 30]) throw new RuntimeException('Il piano non corrisponde alle 41 risoluzioni previste.');
foreach ($preview->summary()['sources'] as $source) {
    if (!hash_equals($source['sha256'], hash_file('sha256', dirname($root).'/'.$source['file']))) throw new RuntimeException('File Excel originale modificato.');
}
if (!in_array('--apply', $argv, true)) {
    echo json_encode(['mode' => 'preview', 'actions' => $actions, 'held' => App\Models\RegistryImportEntry::where('kind', 'client')->where('status', 'held')->count()], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
$backup = storage_path('app/private/held-resolution-backup-'.date('Ymd-His'));
if (file_exists($backup) || !mkdir($backup, 0700, true)) throw new RuntimeException('Backup non disponibile.');
$fingerprints = [];
foreach (['users', 'clients', 'registry_import_entries', 'audit_logs', 'insurance_policies', 'supplier_agencies', 'supplier_companies', 'supplier_agency_company'] as $table) {
    $json = json_encode($db->table($table)->orderBy('id')->get()->all(), JSON_THROW_ON_ERROR);
    if (file_put_contents($backup.'/'.$table.'.json', $json) === false) throw new RuntimeException('Backup non riuscito.');
    $fingerprints[$table] = hash('sha256', $json);
}
$report = app(App\Services\HeldRegistryResolver::class)->apply($plan);
foreach (['users', 'insurance_policies', 'supplier_agencies', 'supplier_companies', 'supplier_agency_company'] as $table) {
    $report[$table.'_unchanged'] = hash_equals($fingerprints[$table], hash('sha256', json_encode($db->table($table)->orderBy('id')->get()->all(), JSON_THROW_ON_ERROR)));
    if (!$report[$table.'_unchanged']) throw new RuntimeException('Verifica successiva non superata: '.$table);
}
$report['backup_created'] = true;
file_put_contents(__DIR__.'/held-resolution-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
