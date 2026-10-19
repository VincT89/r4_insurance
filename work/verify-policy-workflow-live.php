<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{Artisan, Auth, DB, Schema};

if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'r4_insurance') throw new RuntimeException('Database inatteso.');
$columns = ['clients' => Schema::getColumnListing('clients'), 'insurance_policies' => Schema::getColumnListing('insurance_policies')];
$fingerprint = fn ($table) => hash('sha256', json_encode(DB::table($table)->orderBy('id')->get($columns[$table])->all(), JSON_THROW_ON_ERROR));
$before = array_map($fingerprint, array_keys($columns));
Artisan::call('migrate', ['--force' => true]);
$migrationOutput = Artisan::output();
if ($before !== array_map($fingerprint, array_keys($columns))) throw new RuntimeException('Dati esistenti modificati in modo inatteso.');
$admin = App\Models\User::where('role', 'admin')->where('status', 'active')->firstOrFail();
Auth::login($admin);
$policy = App\Models\InsurancePolicy::whereHas('client', fn ($query) => $query->current())->firstOrFail();
$report = ['database' => 'r4_insurance', 'migrations' => $migrationOutput, 'existing_records_unchanged' => true, 'clients' => App\Models\Client::count(), 'policies' => App\Models\InsurancePolicy::count(), 'closed_workflows' => App\Models\InsurancePolicy::whereNotNull('work_closed_at')->count(), 'correction_requests' => App\Models\InsurancePolicyCorrection::count(), 'pages' => []];
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
DB::beginTransaction();
try {
    foreach (['/dashboard', '/policies', '/clients/'.$policy->client_id.'/policies/'.$policy->id, '/clients/'.$policy->client_id.'/policies/'.$policy->id.'/edit'] as $url) {
        $request = Illuminate\Http\Request::create($url, 'GET');
        $response = $kernel->handle($request);
        $report['pages'][$url] = $response->getStatusCode();
        $kernel->terminate($request, $response);
        if ($response->getStatusCode() !== 200) throw new RuntimeException('Pagina non disponibile: '.$url);
        if (str_ends_with($url, '/policies/'.$policy->id) && !str_contains($response->getContent(), 'Segnalazioni e correzioni')) throw new RuntimeException('Interfaccia segnalazioni non disponibile.');
    }
} finally {
    DB::rollBack();
}
$report['ok'] = true;
file_put_contents(__DIR__.'/policy-workflow-live-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
