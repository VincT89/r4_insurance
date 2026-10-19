<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Database inatteso.');
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $known = $app['migrator']->getMigrationFiles(database_path('migrations'));
    $applied = $db->table('migrations')->orderBy('id')->get(['migration', 'batch'])->toArray();
    $pending = array_values(array_diff(array_keys($known), array_column($applied, 'migration')));
    $columns = $db->table('information_schema.COLUMNS')->where('TABLE_SCHEMA', 'r4_insurance')->whereIn('TABLE_NAME', ['clients', 'insurance_policies', 'client_consents'])->orderBy('TABLE_NAME')->orderBy('ORDINAL_POSITION')->get(['TABLE_NAME', 'COLUMN_NAME', 'DATA_TYPE']);
    $tables = $db->table('information_schema.TABLES')->where('TABLE_SCHEMA', 'r4_insurance')->where('TABLE_NAME', 'like', 'client%')->pluck('TABLE_NAME')->all();
    echo json_encode(compact('pending', 'applied', 'tables', 'columns'), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} finally { $db->rollBack(); }
