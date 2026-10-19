<?php

use App\Models\{InsurancePolicy, InsurancePolicyImport, User};
use App\Services\InsuranceImportPreview;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Database inatteso.');
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
$db->statement('SET TRANSACTION READ ONLY'); $db->beginTransaction();
try {
    $admin = User::where('email', 'admin@admin.it')->firstOrFail();
    $summary = app(InsuranceImportPreview::class)->summary();
    $report = ['counts' => []];
    foreach (['insurance_policies', 'insurance_policy_imports', 'clients', 'supplier_companies', 'supplier_agencies', 'supplier_agency_company', 'attachments', 'client_document_requests', 'client_vehicles'] as $table) $report['counts'][$table] = $db->table($table)->count();
    $report['statuses'] = InsurancePolicy::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();
    $report['import_statuses'] = InsurancePolicyImport::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();
    $report['clients_held'] = $db->table('registry_import_entries')->where('kind', 'client')->where('status', 'held')->count();
    $report['source_rows_preserved'] = InsurancePolicyImport::get(['source_rows'])->sum(fn ($entry) => count($entry->source_rows)) === $summary['counts']['policy_rows'];
    $report['groups_preserved'] = InsurancePolicyImport::count() === $summary['counts']['policy_groups'];
    $report['policy_links_valid'] = !$db->table('insurance_policies as p')
        ->leftJoin('clients as c', 'c.id', '=', 'p.client_id')->leftJoin('insurance_branches as b', 'b.id', '=', 'p.insurance_branch_id')
        ->leftJoin('insurance_subbranches as s', 's.id', '=', 'p.insurance_subbranch_id')->leftJoin('supplier_companies as co', 'co.id', '=', 'p.supplier_company_id')
        ->leftJoin('supplier_agencies as a', 'a.id', '=', 'p.supplier_agency_id')->leftJoin('supplier_agency_company as m', fn ($q) => $q->on('m.supplier_agency_id', '=', 'a.id')->on('m.supplier_company_id', '=', 'co.id'))
        ->where(fn ($q) => $q->whereNull('c.id')->orWhereNull('b.id')->orWhereNull('co.id')->orWhereNull('a.id')->orWhereNull('m.supplier_agency_id')
            ->orWhereColumn('b.name', '!=', 'p.category')->orWhereColumn('s.insurance_branch_id', '!=', 'p.insurance_branch_id')
            ->orWhere(fn ($q) => $q->whereNotNull('p.insurance_subbranch_id')->whereNull('s.id'))
            ->orWhere(fn ($q) => $q->whereNotNull('p.subcategory')->whereNull('p.insurance_subbranch_id'))
            ->orWhereColumn('s.name', '!=', 'p.subcategory'))->exists();
    $report['import_links_valid'] = !$db->table('insurance_policy_imports as i')->leftJoin('insurance_policies as p', 'p.id', '=', 'i.insurance_policy_id')
        ->where(fn ($q) => $q->where(fn ($q) => $q->where('i.status', 'imported')->where(fn ($q) => $q->whereNull('p.id')->orWhereColumn('p.client_id', '!=', 'i.client_id')))
            ->orWhere(fn ($q) => $q->where('i.status', 'held')->whereNotNull('i.insurance_policy_id')))->exists();
    $report['no_policy_duplicates'] = !$db->table('insurance_policies')->select('company', 'number')->groupBy('company', 'number')->havingRaw('COUNT(*) > 1')->exists();
    $report['pending_migrations'] = array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))), $db->table('migrations')->pluck('migration')->all()));
    $policy = InsurancePolicy::with('importEntry')->firstOrFail();
    $held = InsurancePolicyImport::where('status', 'held')->firstOrFail();
    $paths = ['/settings/insurance-import?section=policies', '/settings/insurance-import?section=policies&result=imported', '/settings/insurance-import?section=policies&result=held',
        '/settings/insurance-import/policies/'.$policy->importEntry->id, '/settings/insurance-import/policies/'.$held->id,
        '/clients/'.$policy->client_id.'?tab=policies', '/clients/'.$policy->client_id.'/policies/'.$policy->id, '/clients/'.$policy->client_id.'/policies/'.$policy->id.'/edit',
        '/clients/'.$policy->client_id.'?tab=deadlines', '/deadlines?period=all&kind=policy', '/suppliers/'.$policy->supplier_agency_id, '/supplier-companies/'.$policy->supplier_company_id];
    $consolidated = InsurancePolicyImport::whereHas('auditLogs', fn ($q) => $q->where('action', \App\Services\ApprovedEquivalentPolicyImport::ACTION))->with('policy')->get();
    $report['consolidated_groups'] = $consolidated->count();
    $report['consolidated_source_rows'] = $consolidated->sum(fn ($entry) => count($entry->source_rows));
    $report['consolidated_links_valid'] = $consolidated->every(fn ($entry) => $entry->status === 'imported' && $entry->policy && (int) $entry->policy->client_id === (int) $entry->client_id);
    if ($example = $consolidated->first()) {
        $paths[] = '/settings/insurance-import/policies/'.$example->id;
        $paths[] = '/clients/'.$example->client_id.'/policies/'.$example->insurance_policy_id;
    }
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    foreach ($paths as $path) {
        $app['auth']->forgetGuards(); $app['auth']->guard('web')->setUser($admin);
        $response = $kernel->handle(Illuminate\Http\Request::create('http://localhost'.$path, 'GET'));
        $report['pages'][$path] = $response->getStatusCode();
    }
    $report['ok'] = $report['source_rows_preserved'] && $report['groups_preserved'] && $report['policy_links_valid'] && $report['import_links_valid'] && $report['no_policy_duplicates'] && $report['consolidated_links_valid'] && !$report['pending_migrations'] && !array_filter($report['pages'], fn ($status) => $status !== 200);
} finally { $db->rollBack(); }
file_put_contents(__DIR__.'/policy-import-live-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['ok'] ? 0 : 1);
