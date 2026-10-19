<?php

$root = dirname(__DIR__, 4);
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = Illuminate\Support\Facades\DB::connection();
if ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'r4_insurance'
    || ! in_array($connection->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Database inatteso: aggiornamento annullato.');
}
$admin = (array) $connection->table('users')->where('email', 'admin@admin.it')->first();
if (! $admin || ($admin['role'] ?? null) !== 'admin') {
    throw new RuntimeException('Admin atteso non trovato.');
}
$before = ['clients' => $connection->table('clients')->count(), 'attachments' => $connection->table('attachments')->count()];
if (! is_file(__DIR__.'/admin-backup.json')) {
    file_put_contents(__DIR__.'/admin-backup.json', json_encode($admin, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}
$exit = Illuminate\Support\Facades\Artisan::call('migrate', [
    '--path' => ['database/migrations/2026_10_14_150000_add_insurance_client_registry.php'],
    '--force' => true,
]);
echo Illuminate\Support\Facades\Artisan::output();
if ($exit !== 0) exit($exit);
$after = ['clients' => $connection->table('clients')->count(), 'attachments' => $connection->table('attachments')->count()];
$sameAdmin = $admin === (array) $connection->table('users')->where('id', $admin['id'])->first();
echo json_encode(['existing_record_counts_before' => $before, 'existing_record_counts_after' => $after, 'admin_unchanged' => $sameAdmin, 'policies' => $connection->table('insurance_policies')->count(), 'vehicles' => $connection->table('client_vehicles')->count()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($sameAdmin && $before === $after ? 0 : 1);
