<?php

$root = dirname(__DIR__, 4);
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array']);
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance') throw new RuntimeException('Database inatteso.');
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
$errors = [];
try {
    $columns = $db->table('information_schema.COLUMNS')->where('TABLE_SCHEMA', $db->getDatabaseName())->get(['TABLE_NAME as table_name', 'COLUMN_NAME as column_name']);
    $schema = [];
    foreach ($columns as $column) $schema[$column->table_name][] = $column->column_name;
    foreach ([
        'clients' => ['client_type', 'title', 'birth_date', 'birth_place', 'mobile', 'fax', 'target', 'reference_email', 'reference_role', 'reference_phone', 'office', 'consultant', 'collaborator', 'operator', 'salient_notes'],
        'client_vehicles' => ['client_id', 'plate', 'make', 'model', 'vin', 'registered_on'],
        'insurance_policies' => ['client_id', 'vehicle_id', 'category', 'company', 'number', 'starts_on', 'expires_on', 'status'],
        'attachments' => ['document_category', 'insurance_policy_id', 'vehicle_id', 'expires_on'],
    ] as $table => $required) {
        foreach (array_diff($required, $schema[$table] ?? []) as $missing) $errors[] = 'Campo mancante: '.$table.'.'.$missing;
    }
    $files = $app['migrator']->getMigrationFiles(database_path('migrations'));
    $pending = array_values(array_diff(array_keys($files), $db->table('migrations')->pluck('migration')->all()));
    if ($pending) $errors[] = 'Migrazioni pendenti.';
    $admin = App\Models\User::where('email', 'admin@admin.it')->firstOrFail();
    $backup = json_decode(file_get_contents(__DIR__.'/admin-backup.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach (['id', 'email', 'password', 'role', 'status', 'password_changed_at'] as $field) {
        if ($admin->getRawOriginal($field) !== ($backup[$field] ?? null)) $errors[] = 'Campo admin modificato: '.$field;
    }
    $orphanPolicies = $db->table('insurance_policies as p')->leftJoin('clients as c', 'c.id', '=', 'p.client_id')->whereNull('c.id')->count();
    $wrongVehicles = $db->table('insurance_policies as p')->join('client_vehicles as v', 'v.id', '=', 'p.vehicle_id')->whereColumn('v.client_id', '!=', 'p.client_id')->count();
    if ($orphanPolicies || $wrongVehicles) $errors[] = 'Collegamenti incoerenti.';
    $paths = ['/login', '/dashboard', '/clients', '/clients/create', '/users', '/profile', '/audit-logs'];
    if ($client = App\Models\Client::first()) {
        foreach (['policies', 'registry', 'vehicles', 'documents', 'deadlines'] as $tab) $paths[] = '/clients/'.$client->id.'?tab='.$tab;
        $paths[] = '/clients/'.$client->id.'/edit';
        $paths[] = '/clients/'.$client->id.'/policies/create';
        $paths[] = '/clients/'.$client->id.'/vehicles/create';
        $paths[] = '/clients/'.$client->id.'/documents/create';
    }
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $pages = [];
    foreach ($paths as $path) {
        $app['auth']->forgetGuards();
        if ($path !== '/login') $app['auth']->guard('web')->setUser($admin);
        $response = $kernel->handle(Illuminate\Http\Request::create('http://localhost'.$path, 'GET'));
        $pages[$path] = $response->getStatusCode();
        if ($response->getStatusCode() !== 200) $errors[] = 'Pagina non disponibile: '.$path;
    }
    $report = ['read_only' => true, 'database' => $db->getDatabaseName(), 'pending_migrations' => $pending,
        'client_count' => $db->table('clients')->count(), 'policy_count' => $db->table('insurance_policies')->count(),
        'vehicle_count' => $db->table('client_vehicles')->count(), 'attachment_count' => $db->table('attachments')->count(),
        'pages' => $pages, 'errors' => $errors];
} finally {
    $db->rollBack();
}
file_put_contents(__DIR__.'/verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($errors ? 1 : 0);
