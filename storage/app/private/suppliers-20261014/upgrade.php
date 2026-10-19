<?php

$root = dirname(__DIR__, 4);
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance'
    || ! in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Database inatteso: aggiornamento annullato.');
}
$adminFields = ['id', 'email', 'password', 'role', 'status', 'password_changed_at'];
$admin = (array) $db->table('users')->where('email', 'admin@admin.it')->first($adminFields);
if (! $admin || $admin['role'] !== 'admin') throw new RuntimeException('Admin atteso non trovato.');
if (! is_file(__DIR__.'/admin-backup.json')) {
    file_put_contents(__DIR__.'/admin-backup.json', json_encode($admin, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}
$counts = function () use ($db): array {
    $result = [];
    foreach (['clients', 'insurance_policies', 'client_vehicles', 'attachments'] as $table) $result[$table] = $db->table($table)->count();
    return $result;
};
$before = $counts();
$exit = Illuminate\Support\Facades\Artisan::call('migrate', [
    '--path' => ['database/migrations/2026_10_14_160000_create_supplier_agencies_table.php'], '--force' => true,
]);
echo Illuminate\Support\Facades\Artisan::output();
if ($exit !== 0) exit($exit);
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $after = $counts();
    $adminUnchanged = $admin === (array) $db->table('users')->where('id', $admin['id'])->first($adminFields);
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $pages = [];
    foreach (['/suppliers', '/suppliers/create', '/clients', '/dashboard', '/audit-logs'] as $path) {
        $app['auth']->forgetGuards();
        $app['auth']->guard('web')->setUser(App\Models\User::findOrFail($admin['id']));
        $pages[$path] = $kernel->handle(Illuminate\Http\Request::create('http://localhost'.$path, 'GET'))->getStatusCode();
    }
    $files = $app['migrator']->getMigrationFiles(database_path('migrations'));
    $pending = array_values(array_diff(array_keys($files), $db->table('migrations')->pluck('migration')->all()));
    $report = ['admin_unchanged' => $adminUnchanged, 'existing_record_counts_unchanged' => $before === $after,
        'agencies_count' => $db->table('supplier_agencies')->count(), 'pages' => $pages, 'pending_migrations' => $pending];
} finally {
    $db->rollBack();
}
file_put_contents(__DIR__.'/verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($adminUnchanged && $before === $after && ! $pending && count(array_filter($pages, fn ($status) => $status !== 200)) === 0 ? 0 : 1);
