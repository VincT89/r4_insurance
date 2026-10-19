<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance'
    || ! in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Database inatteso.');
}
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $counts = [];
    foreach (['clients', 'client_vehicles', 'insurance_policies', 'attachments', 'supplier_companies', 'supplier_agencies'] as $table) $counts[$table] = $db->table($table)->count();
    $fkChecks = [];
    $keys = $db->table('information_schema.KEY_COLUMN_USAGE')->where('TABLE_SCHEMA', 'r4_insurance')->whereNotNull('REFERENCED_TABLE_NAME')->get();
    foreach ($keys as $key) {
        $fkChecks[$key->TABLE_NAME.'.'.$key->COLUMN_NAME] = $db->table($key->TABLE_NAME.' as child')
            ->leftJoin($key->REFERENCED_TABLE_NAME.' as parent', 'parent.'.$key->REFERENCED_COLUMN_NAME, '=', 'child.'.$key->COLUMN_NAME)
            ->whereNotNull('child.'.$key->COLUMN_NAME)->whereNull('parent.'.$key->REFERENCED_COLUMN_NAME)->count();
    }
    $links = [];
    $links['policy_vehicle_client'] = $db->table('insurance_policies as p')->join('client_vehicles as v', 'v.id', '=', 'p.vehicle_id')->whereColumn('p.client_id', '!=', 'v.client_id')->count();
    $links['document_client'] = $db->table('attachments as d')->leftJoin('clients as c', 'c.id', '=', 'd.attachable_id')->where('d.attachable_type', App\Models\Client::class)->whereNull('c.id')->count();
    foreach (['insurance_policy_id' => 'insurance_policies', 'vehicle_id' => 'client_vehicles'] as $column => $table) {
        $links['document_'.$column] = $db->table('attachments as d')->join($table.' as target', 'target.id', '=', 'd.'.$column)
            ->where(fn ($q) => $q->where('d.attachable_type', '!=', App\Models\Client::class)->orWhereColumn('d.attachable_id', '!=', 'target.client_id'))->count();
    }
    $links['document_policy_vehicle'] = $db->table('attachments as d')->join('insurance_policies as p', 'p.id', '=', 'd.insurance_policy_id')->whereNotNull('d.vehicle_id')
        ->where(fn ($q) => $q->whereNull('p.vehicle_id')->orWhereColumn('d.vehicle_id', '!=', 'p.vehicle_id'))->count();
    $links['supplier_hierarchy'] = $db->table('supplier_agencies as child')->join('supplier_agencies as parent', 'parent.id', '=', 'child.parent_agency_id')
        ->where(fn ($q) => $q->whereColumn('parent.id', 'child.id')->orWhereNotNull('parent.parent_agency_id')
            ->orWhereNull('child.supplier_company_id')->orWhereNull('parent.supplier_company_id')
            ->orWhereColumn('child.supplier_company_id', '!=', 'parent.supplier_company_id'))->count();
    $missingFiles = 0;
    foreach ($db->table('attachments')->where('attachable_type', App\Models\Client::class)->get(['disk', 'path']) as $file) {
        if (! Illuminate\Support\Facades\Storage::disk($file->disk)->exists($file->path)) $missingFiles++;
    }
    $admin = App\Models\User::where('email', 'admin@admin.it')->firstOrFail();
    $backup = json_decode(file_get_contents($root.'/storage/app/private/supplier-hierarchy-20261014/admin-backup.json'), true, flags: JSON_THROW_ON_ERROR);
    $adminUnchanged = true;
    foreach ($backup as $field => $value) {
        if ($admin->getRawOriginal($field) != $value) $adminUnchanged = false;
    }
    $paths = ['/clients', '/clients/create', '/supplier-companies', '/supplier-companies/create', '/suppliers', '/suppliers/create', '/dashboard', '/audit-logs'];
    foreach ($db->table('clients')->orderBy('id')->limit(20)->pluck('id') as $id) {
        foreach (['registry', 'policies', 'vehicles', 'documents', 'deadlines'] as $tab) $paths[] = '/clients/'.$id.'?tab='.$tab;
        $paths[] = '/clients/'.$id.'/edit';
    }
    foreach ($db->table('supplier_companies')->orderBy('id')->limit(20)->pluck('id') as $id) $paths[] = '/supplier-companies/'.$id;
    foreach ($db->table('supplier_agencies')->orderBy('id')->limit(20)->pluck('id') as $id) $paths[] = '/suppliers/'.$id;
    $pages = [];
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    foreach ($paths as $path) {
        $app['auth']->forgetGuards();
        $app['auth']->guard('web')->setUser($admin);
        $pages[$path] = $kernel->handle(Illuminate\Http\Request::create('http://localhost'.$path, 'GET'))->getStatusCode();
    }
    $files = $app['migrator']->getMigrationFiles(database_path('migrations'));
    $pending = array_values(array_diff(array_keys($files), $db->table('migrations')->pluck('migration')->all()));
    $retired = json_decode(file_get_contents($root.'/tests/Fixtures/retired-table-names.json'), true, flags: JSON_THROW_ON_ERROR);
    $remainingRetired = array_values(array_intersect($retired, $db->table('information_schema.TABLES')->where('TABLE_SCHEMA', 'r4_insurance')->pluck('TABLE_NAME')->all()));
    $ok = $adminUnchanged && ! array_sum($fkChecks) && ! array_sum($links) && ! $missingFiles && ! $pending && ! $remainingRetired
        && ! array_filter($pages, fn ($status) => $status !== 200);
    $report = compact('ok', 'counts', 'fkChecks', 'links', 'missingFiles', 'adminUnchanged', 'pages', 'pending', 'remainingRetired');
} finally {
    $db->rollBack();
}
file_put_contents(__DIR__.'/qa-live-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($ok ? 0 : 1);
