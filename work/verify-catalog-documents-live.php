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
    $report = ['appUrl' => config('app.url')];
    foreach (['clients', 'insurance_policies', 'insurance_branches', 'insurance_subbranches', 'insurance_catalog_aliases', 'supplier_companies', 'supplier_agencies', 'supplier_agency_company', 'attachments', 'client_document_requests'] as $table) $report['counts'][$table] = $db->table($table)->count();
    $report['clientsHeld'] = $db->table('registry_import_entries')->where('kind', 'client')->where('status', 'held')->count();
    $report['pendingMigrations'] = array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))), $db->table('migrations')->pluck('migration')->all()));
    $report['branchReview'] = $db->table('insurance_branches')->where('needs_review', 1)->orderBy('name')->pluck('name')->all();
    $report['subbranchReviewCount'] = $db->table('insurance_subbranches')->where('needs_review', 1)->count();
    $report['subbranchLinksValid'] = !$db->table('insurance_subbranches as s')->leftJoin('insurance_branches as b', 'b.id', '=', 's.insurance_branch_id')->whereNull('b.id')->exists();
    $report['catalogAliasLinksValid'] = !$db->table('insurance_catalog_aliases as a')->leftJoin('insurance_branches as b', 'b.id', '=', 'a.insurance_branch_id')->leftJoin('insurance_subbranches as s', 's.id', '=', 'a.insurance_subbranch_id')
        ->where(fn ($q) => $q->whereNull('b.id')->orWhere(fn ($q) => $q->whereNotNull('a.insurance_subbranch_id')->where(fn ($q) => $q->whereNull('s.id')->orWhereColumn('s.insurance_branch_id', '!=', 'a.insurance_branch_id'))))->exists();
    $report['agencyLinksValid'] = !$db->table('supplier_agency_company as x')->leftJoin('supplier_agencies as a', 'a.id', '=', 'x.supplier_agency_id')->leftJoin('supplier_companies as c', 'c.id', '=', 'x.supplier_company_id')->where(fn($q) => $q->whereNull('a.id')->orWhereNull('c.id'))->exists();
    $report['importLinksValid'] = !$db->table('registry_import_entries as i')->leftJoin('clients as c', 'c.id', '=', 'i.entity_id')->where('i.kind', 'client')->where('i.status', 'imported')->whereNull('c.id')->exists();
    $client = App\Models\Client::current()->orderBy('id')->firstOrFail();
    $branch = App\Models\InsuranceBranch::orderBy('id')->firstOrFail();
    $subbranch = App\Models\InsuranceSubbranch::orderBy('id')->firstOrFail();
    $agency = App\Models\SupplierAgency::orderBy('id')->firstOrFail();
    $paths = ['/settings/insurance-catalog', '/settings/insurance-catalog?kind=subbranch', '/settings/insurance-catalog?review=1',
        '/settings/insurance-catalog/branch/'.$branch->id.'/edit', '/settings/insurance-catalog/subbranch/'.$subbranch->id.'/edit',
        '/suppliers', '/suppliers/'.$agency->id, '/suppliers/'.$agency->id.'/edit', '/supplier-companies', '/deadlines', '/deadlines?period=all',
        '/clients/'.$client->id.'?tab=documents', '/clients/'.$client->id.'?tab=deadlines', '/clients/'.$client->id.'/documents/create', '/clients/'.$client->id.'/document-requests/create', '/clients/'.$client->id.'/policies/create'];
    $paths[] = '/settings/insurance-import?section=branches';
    foreach (App\Services\ApprovedInsuranceCatalogMerges::GROUPS as $group) {
        $merged = App\Models\InsuranceBranch::where('name', $group['name'])->firstOrFail();
        $paths[] = '/settings/insurance-catalog/branch/'.$merged->id.'/edit';
    }
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    foreach ($paths as $path) {
        $app['auth']->forgetGuards();
        $app['auth']->guard('web')->setUser($admin);
        $response = $kernel->handle(Illuminate\Http\Request::create('http://localhost'.$path, 'GET'));
        $report['pages'][$path] = $response->getStatusCode();
    }
    $report['ok'] = !$report['pendingMigrations'] && $report['subbranchLinksValid'] && $report['catalogAliasLinksValid'] && $report['agencyLinksValid'] && $report['importLinksValid'] && !array_filter($report['pages'], fn($status) => $status !== 200);
} finally { $db->rollBack(); }
file_put_contents(__DIR__.'/catalog-documents-live-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['ok'] ? 0 : 1);
