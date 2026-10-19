<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Operazione riservata al database locale r4_insurance.');
}
$migration = '2026_10_15_140000_add_appearance_to_users';
$pending = array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))), $db->table('migrations')->pluck('migration')->all()));
$schema = Illuminate\Support\Facades\Schema::connection($db->getName());
if (!$pending && $schema->hasColumn('users', 'appearance')) {
    echo json_encode(['ok' => true, 'alreadyApplied' => true, 'pending' => []]).PHP_EOL;
    exit;
}
if ($pending !== [$migration] || $schema->hasColumn('users', 'appearance')) {
    throw new RuntimeException('Schema o migrazioni pendenti inattesi: nessuna modifica.');
}
$users = $db->table('users')->orderBy('id')->get()->map(fn ($row) => (array)$row)->all();
if (!$db->table('users')->where('email', 'admin@admin.it')->exists()) throw new RuntimeException('Admin atteso assente.');
$backup = storage_path('app/private/appearance-'.date('Ymd-His'));
if (file_exists($backup) || !mkdir($backup, 0700, true)) throw new RuntimeException('Backup non disponibile.');
foreach (['users.json' => $users, 'users-schema.json' => $db->selectOne('SHOW CREATE TABLE users')] as $name => $value) {
    if (file_put_contents($backup.'/'.$name, json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)) === false) throw new RuntimeException('Backup non completato.');
}
$counts = [];
foreach (['clients', 'insurance_policies', 'attachments', 'supplier_companies', 'supplier_agencies'] as $table) $counts[$table] = $db->table($table)->count();
$exit = Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => 'database/migrations/'.$migration.'.php', '--force' => true]);
if ($exit !== 0) throw new RuntimeException('Migrazione non completata.');
$after = $db->table('users')->orderBy('id')->get()->map(function ($row) {
    $row = (array)$row; unset($row['appearance']); return $row;
})->all();
$report = ['migrationApplied' => $schema->hasColumn('users', 'appearance'), 'existingUsersUnchanged' => $after === $users, 'businessCountsUnchanged' => true, 'backupCreated' => true];
foreach ($counts as $table => $count) if ($db->table($table)->count() !== $count) $report['businessCountsUnchanged'] = false;
$report['ok'] = !in_array(false, $report, true);
file_put_contents(__DIR__.'/appearance-migration-report.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
exit($report['ok'] ? 0 : 1);
