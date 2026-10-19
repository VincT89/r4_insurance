<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Database inatteso.');
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $admin = App\Models\User::where('email', 'admin@admin.it')->firstOrFail();
    $backup = json_decode(file_get_contents(storage_path('app/private/supplier-hierarchy-20261014/admin-backup.json')), true, flags: JSON_THROW_ON_ERROR);
    $baseline = $backup['users'][0] ?? $backup[0] ?? $backup;
    $adminUnchanged = isset($baseline['password']) && hash_equals($baseline['password'], $admin->getRawOriginal('password'));
    $columnExists = Illuminate\Support\Facades\Schema::hasColumn('users', 'appearance');
    $pages = [];
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    foreach (['/profile', '/settings/appearance', '/clients', '/suppliers'] as $path) {
        $app['auth']->forgetGuards(); $app['auth']->guard('web')->setUser($admin);
        $response = $kernel->handle(Illuminate\Http\Request::create('http://localhost'.$path, 'GET'));
        $pages[$path] = ['status' => $response->getStatusCode(), 'signature' => str_contains($response->getContent(), 'images/agency-signature.svg'), 'theme' => str_contains($response->getContent(), '--ap-login-panel:#000000;')];
    }
    $files = $app['migrator']->getMigrationFiles(database_path('migrations'));
    $pending = array_values(array_diff(array_keys($files), $db->table('migrations')->pluck('migration')->all()));
    $ok = $adminUnchanged && $columnExists && !$pending && !array_filter($pages, fn ($p) => $p['status'] !== 200 || !$p['signature'] || !$p['theme']);
    $report = compact('ok', 'adminUnchanged', 'columnExists', 'pending', 'pages');
} finally { $db->rollBack(); }
file_put_contents(__DIR__.'/appearance-live-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($ok ? 0 : 1);
