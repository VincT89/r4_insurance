<?php

use App\Models\{Client, InsurancePolicy, User};
use App\Services\{DeadlineReminders, InsuranceDemoReset};
use Illuminate\Support\Facades\{Cache, Crypt, DB, Storage};

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Database inatteso.');
$applied = json_decode(file_get_contents(__DIR__.'/workspace-demo-applied-report.json'), true, flags: JSON_THROW_ON_ERROR);
if (!preg_match('/^insurance-demo-backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$/D', $applied['backup'] ?? '')) throw new RuntimeException('Riferimento backup inatteso.');
$admin = User::where('email', 'admin@admin.it')->firstOrFail();
if (!$admin->isAdmin() || $admin->status !== 'active') throw new RuntimeException('Amministratore non disponibile.');
$report = ['notifications_created' => 0];
if (in_array('--refresh-reminders', $argv, true)) {
    User::where('status', 'active')->eachById(function ($user) use (&$report) {
        $report['notifications_created'] += app(DeadlineReminders::class)->forUser($user);
        Cache::forget('deadline-reminders:'.$user->id);
    });
}
config(['session.driver' => 'array', 'cache.default' => 'array']);
Cache::forever('deadline-reminders:'.$admin->id, true);
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    foreach (['clients','insurance_policies','insurance_policy_imports','client_vehicles','attachments','insurance_deadline_reminders'] as $table) $report['counts'][$table] = $db->table($table)->count();
    $report['counts']['client_import_entries'] = $db->table('registry_import_entries')->where('kind', 'client')->count();
    $report['demo_only'] = Client::count() === 50 && InsurancePolicy::count() === 50
        && Client::whereNotIn('name', \Database\Seeders\InsuranceDemoSeeder::CLIENT_NAMES)->doesntExist()
        && InsurancePolicy::where('number', 'not like', 'SA-%')->doesntExist();
    $report['policy_links_valid'] = !$db->table('insurance_policies as p')
        ->leftJoin('clients as c', 'c.id', '=', 'p.client_id')->leftJoin('insurance_branches as b', 'b.id', '=', 'p.insurance_branch_id')
        ->leftJoin('insurance_subbranches as s', 's.id', '=', 'p.insurance_subbranch_id')->leftJoin('supplier_companies as co', 'co.id', '=', 'p.supplier_company_id')
        ->leftJoin('supplier_agencies as a', 'a.id', '=', 'p.supplier_agency_id')->leftJoin('supplier_agency_company as m', fn ($q) => $q->on('m.supplier_agency_id', '=', 'a.id')->on('m.supplier_company_id', '=', 'co.id'))
        ->where(fn ($q) => $q->whereNull('c.id')->orWhereNull('b.id')->orWhereNull('co.id')->orWhereNull('a.id')->orWhereNull('m.supplier_agency_id')
            ->orWhereColumn('b.name', '!=', 'p.category')->orWhereColumn('co.name', '!=', 'p.company')->orWhereColumn('s.insurance_branch_id', '!=', 'p.insurance_branch_id')
            ->orWhere(fn ($q) => $q->whereNotNull('p.insurance_subbranch_id')->whereNull('s.id'))
            ->orWhereColumn('s.name', '!=', 'p.subcategory'))->exists();
    $report['vehicle_links_valid'] = !$db->table('insurance_policies as p')->leftJoin('client_vehicles as v', 'v.id', '=', 'p.vehicle_id')
        ->whereNotNull('p.vehicle_id')->where(fn ($q) => $q->whereNull('v.id')->orWhereColumn('v.client_id', '!=', 'p.client_id'))->exists();
    $fingerprint = fn ($rows) => hash('sha256', implode("\n", collect($rows)->map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->sort()->values()->all()));
    foreach (InsuranceDemoReset::PRESERVED as $table) {
        $saved = json_decode(Crypt::decryptString(Storage::disk('local')->get($applied['backup'].'/'.$table.'.json.enc')), flags: JSON_THROW_ON_ERROR);
        $report['preserved'][$table] = hash_equals($fingerprint($saved), $fingerprint($db->table($table)->get()->all()));
    }
    $report['statuses'] = InsurancePolicy::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all();
    $report['pending_migrations'] = array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))), $db->table('migrations')->pluck('migration')->all()));
    $policy = InsurancePolicy::firstOrFail();
    $paths = ['/dashboard', '/policies', '/policies/create', '/clients', '/clients/create', '/clients/'.$policy->client_id.'?tab=policies', '/clients/'.$policy->client_id.'?tab=documents', '/clients/'.$policy->client_id.'/policies/'.$policy->id, '/clients/'.$policy->client_id.'/policies/'.$policy->id.'/edit', '/clients/'.$policy->client_id.'/documents/create', '/suppliers', '/suppliers/'.$policy->supplier_agency_id, '/supplier-companies/'.$policy->supplier_company_id, '/deadlines', '/reports', '/agency-communications', '/agency-communications?period=15', '/agency-communications?period=month&month='.today()->format('Y-m'), '/settings/appearance', '/audit-logs'];
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    foreach ($paths as $path) {
        $app['auth']->forgetGuards(); $app['auth']->guard('web')->setUser($admin);
        $response = $kernel->handle(Illuminate\Http\Request::create('http://localhost'.$path, 'GET'));
        $report['pages'][$path] = $response->getStatusCode();
    }
    $report['app_available'] = !$app->isDownForMaintenance();
    $report['ok'] = $report['demo_only'] && $report['policy_links_valid'] && $report['vehicle_links_valid'] && $report['app_available']
        && !in_array(false, $report['preserved'], true) && !$report['pending_migrations']
        && $report['counts']['insurance_policy_imports'] === 0 && $report['counts']['client_import_entries'] === 0
        && !array_filter($report['pages'], fn ($status) => $status !== 200);
} finally { $db->rollBack(); }
file_put_contents(__DIR__.'/workspace-live-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['ok'] ? 0 : 1);
