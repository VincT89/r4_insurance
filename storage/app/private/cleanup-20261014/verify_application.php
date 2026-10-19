<?php
$root = dirname(__DIR__, 4);
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array']);
$connection = Illuminate\Support\Facades\DB::connection();
if ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'r4_insurance') {
    throw new RuntimeException('Unexpected database; verification stopped.');
}
$errors = [];
$results = [];
$connection->statement('SET TRANSACTION READ ONLY');
$connection->beginTransaction();
try {
    $database = $connection->getDatabaseName();
    $columns = $connection->table('information_schema.COLUMNS')->where('TABLE_SCHEMA', $database)
        ->get(['TABLE_NAME as table_name', 'COLUMN_NAME as column_name']);
    $schema = [];
    foreach ($columns as $column) {
        $schema[$column->table_name][] = $column->column_name;
    }
    foreach ([
        'users' => ['id', 'email', 'password', 'role', 'status', 'password_changed_at'],
        'clients' => ['id', 'name', 'company_name', 'tax_code', 'vat_number', 'country_code', 'commercial_user_id'],
        'client_user' => ['id', 'client_id', 'user_id'],
        'attachments' => ['attachable_type', 'attachable_id', 'client_material_category'],
    ] as $table => $required) {
        foreach (array_diff($required, $schema[$table] ?? []) as $missing) {
            $errors[] = "Missing column $table.$missing";
        }
    }
    $foreignKeys = $connection->table('information_schema.KEY_COLUMN_USAGE')
        ->where('TABLE_SCHEMA', $database)->where('REFERENCED_TABLE_SCHEMA', $database)
        ->get(['TABLE_NAME as table_name', 'REFERENCED_TABLE_NAME as foreign_table']);
    foreach ($foreignKeys as $foreign) {
        if (! isset($schema[$foreign->foreign_table])) {
            $errors[] = "Broken reference {$foreign->table_name} -> {$foreign->foreign_table}";
        }
    }
    $files = $app['migrator']->getMigrationFiles(database_path('migrations'));
    $applied = $connection->table('migrations')->pluck('migration')->all();
    $pending = array_values(array_diff(array_keys($files), $applied));
    if ($pending) {
        $errors[] = 'Pending migrations';
    }
    $admin = App\Models\User::where('email', 'admin@admin.it')->firstOrFail();
    $backup = json_decode(file_get_contents(__DIR__.'/admin-backup.json'), true, 512, JSON_THROW_ON_ERROR);
    $saved = array_column($backup, null, 'id')[$admin->id] ?? [];
    foreach (['id', 'email', 'password', 'role', 'status', 'password_changed_at'] as $field) {
        if ($admin->getRawOriginal($field) !== ($saved[$field] ?? null)) {
            $errors[] = "Admin field changed: $field";
        }
    }
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    foreach (['/login', '/dashboard', '/clients', '/clients/create', '/teams', '/teams/create', '/users', '/users/create', '/profile', '/audit-logs', '/daily-notes'] as $path) {
        $app['auth']->forgetGuards();
        if ($path !== '/login') {
            $app['auth']->guard('web')->setUser($admin);
        }
        $request = Illuminate\Http\Request::create('http://localhost'.$path, 'GET');
        $response = $kernel->handle($request);
        $results[$path] = $response->getStatusCode();
        if ($response->getStatusCode() !== 200) {
            $errors[] = "$path returned {$response->getStatusCode()}";
        }
    }
    $retired = json_decode(file_get_contents($root.'/tests/Fixtures/retired-table-names.json'), true, 512, JSON_THROW_ON_ERROR);
    $remaining = array_values(array_intersect($retired, array_keys($schema)));
    if ($remaining) {
        $errors[] = 'Retired tables remain';
    }
    $report = [
        'database' => $database,
        'read_only_transaction' => true,
        'migration_files' => count($files),
        'pending_migrations' => $pending,
        'retired_tables_remaining' => $remaining,
        'table_count' => count($schema),
        'foreign_keys_checked' => count($foreignKeys),
        'pages' => $results,
        'errors' => $errors,
    ];
} finally {
    $connection->rollBack();
}
file_put_contents(__DIR__.'/verifica-finale.json', json_encode($report, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($errors ? 1 : 0);
