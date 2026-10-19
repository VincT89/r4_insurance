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
    foreach (['clients', 'insurance_policies', 'client_vehicles', 'attachments', 'supplier_agencies'] as $table) {
        $result[$table] = $db->table($table)->count();
    }
    return $result;
};
$agencyFingerprint = function () use ($db): string {
    $records = $db->table('supplier_agencies')->orderBy('id')->get()->map(function ($record) {
        $row = (array) $record;
        unset($row['supplier_company_id'], $row['parent_agency_id']);
        return $row;
    })->all();
    return hash('sha256', json_encode($records, JSON_THROW_ON_ERROR));
};
$before = $counts();
$agenciesBefore = $agencyFingerprint();
$exit = Illuminate\Support\Facades\Artisan::call('migrate', [
    '--path' => ['database/migrations/2026_10_14_170000_add_supplier_company_hierarchy.php'], '--force' => true,
]);
echo Illuminate\Support\Facades\Artisan::output();
if ($exit !== 0) exit($exit);
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $after = $counts();
    $adminUnchanged = $admin === (array) $db->table('users')->where('id', $admin['id'])->first($adminFields);
    $agenciesUnchanged = $agenciesBefore === $agencyFingerprint();
    $invalidParents = $db->table('supplier_agencies as child')->leftJoin('supplier_agencies as parent', 'parent.id', '=', 'child.parent_agency_id')
        ->whereNotNull('child.parent_agency_id')->where(function ($query) {
            $query->whereNull('parent.id')->orWhereColumn('parent.id', 'child.id')->orWhereNotNull('parent.parent_agency_id')
                ->orWhereNull('child.supplier_company_id')->orWhereNull('parent.supplier_company_id')
                ->orWhereColumn('child.supplier_company_id', '!=', 'parent.supplier_company_id');
        })->count();
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $pages = [];
    $paths = ['/supplier-companies', '/supplier-companies/create', '/suppliers', '/suppliers/create', '/clients', '/dashboard', '/audit-logs'];
    if ($agencyId = $db->table('supplier_agencies')->orderBy('id')->value('id')) {
        $paths[] = '/suppliers/'.$agencyId;
        $paths[] = '/suppliers/'.$agencyId.'/edit';
    }
    if ($companyId = $db->table('supplier_companies')->orderBy('id')->value('id')) {
        $paths[] = '/supplier-companies/'.$companyId;
        $paths[] = '/supplier-companies/'.$companyId.'/edit';
    }
    foreach ($paths as $path) {
        $app['auth']->forgetGuards();
        $app['auth']->guard('web')->setUser(App\Models\User::findOrFail($admin['id']));
        $pages[$path] = $kernel->handle(Illuminate\Http\Request::create('http://localhost'.$path, 'GET'))->getStatusCode();
    }
    $files = $app['migrator']->getMigrationFiles(database_path('migrations'));
    $pending = array_values(array_diff(array_keys($files), $db->table('migrations')->pluck('migration')->all()));
    $report = ['admin_unchanged' => $adminUnchanged, 'existing_record_counts_unchanged' => $before === $after,
        'agency_details_unchanged' => $agenciesUnchanged, 'companies_count' => $db->table('supplier_companies')->count(),
        'agencies_count' => $after['supplier_agencies'], 'invalid_parent_links' => $invalidParents,
        'pages' => $pages, 'pending_migrations' => $pending];
} finally {
    $db->rollBack();
}
file_put_contents(__DIR__.'/verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($adminUnchanged && $before === $after && $agenciesUnchanged && $invalidParents === 0
    && ! $pending && count(array_filter($pages, fn ($status) => $status !== 200)) === 0 ? 0 : 1);
