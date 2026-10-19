<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance'
    || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Operazione riservata al database locale r4_insurance.');
}
$migration = '2026_10_15_100000_expand_client_registry';
$files = $app['migrator']->getMigrationFiles(database_path('migrations'));
$pending = array_values(array_diff(array_keys($files), $db->table('migrations')->pluck('migration')->all()));
if ($pending !== [$migration]) throw new RuntimeException('Migrazioni pendenti diverse da quella verificata. Nessuna modifica effettuata.');

$adminBefore = (array)$db->table('users')->where('email', 'admin@admin.it')->first();
if (!$adminBefore) throw new RuntimeException('Admin atteso assente. Nessuna modifica effettuata.');
$backup = storage_path('app/private/registry-'.date('Ymd-His'));
if (file_exists($backup) || !mkdir($backup, 0700, true)) throw new RuntimeException('Impossibile creare una nuova cartella di backup.');
$tables = $db->table('information_schema.TABLES')->where('TABLE_SCHEMA', 'r4_insurance')->where('TABLE_TYPE', 'BASE TABLE')->orderBy('TABLE_NAME')->pluck('TABLE_NAME');
$schema = []; $countsBefore = [];
$db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    foreach ($tables as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) throw new RuntimeException('Nome tabella inatteso.');
        $schema[$table] = (array)$db->selectOne('SHOW CREATE TABLE `'.$table.'`');
        $rows = $db->table($table)->get()->all();
        $countsBefore[$table] = count($rows);
        if (file_put_contents($backup.'/'.$table.'.json', json_encode($rows, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)) === false) throw new RuntimeException('Backup dati non riuscito.');
    }
    file_put_contents($backup.'/schema.json', json_encode($schema, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
} finally { $db->rollBack(); }
if (!is_file($backup.'/schema.json')) throw new RuntimeException('Backup schema assente.');

$exit = Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => 'database/migrations/'.$migration.'.php', '--force' => true]);
echo Illuminate\Support\Facades\Artisan::output();
if ($exit !== 0) throw new RuntimeException('Migrazione non completata: consultare il backup prima di intervenire.');
$adminUnchanged = $adminBefore === (array)$db->table('users')->where('email', 'admin@admin.it')->first();
$countsUnchanged = true;
foreach ($countsBefore as $table => $count) {
    if ($table !== 'migrations' && $db->table($table)->count() !== $count) $countsUnchanged = false;
}
$report = ['migration' => $migration, 'backup' => str_replace('\\', '/', $backup), 'adminUnchanged' => $adminUnchanged, 'existingRowCountsUnchanged' => $countsUnchanged, 'newTablesEmpty' => true];
foreach (['client_contacts', 'client_assets', 'client_interactions', 'client_consents', 'client_relationships', 'client_households', 'client_household_members', 'client_merges'] as $table) {
    if ($db->table($table)->count() !== 0) $report['newTablesEmpty'] = false;
}
$report['ok'] = $adminUnchanged && $countsUnchanged && $report['newTablesEmpty'];
file_put_contents(__DIR__.'/registry-migration-report.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
exit($report['ok'] ? 0 : 1);
