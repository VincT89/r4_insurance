<?php

use App\Models\{InsurancePolicy, InsurancePolicyImport, User};
use App\Services\{ApprovedEquivalentPolicyImport, PolicyExcelImporter};
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Database inatteso.');
config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'sync']);
$file = storage_path('app/private/insurance-import/held-policy-review/equivalent-proposal.json');
$approvedHash = 'f91c4133b08db3cbdd1b8a874475ae79e7407438a66609a39f013dd4a28d75ce';
if (!hash_equals($approvedHash, hash_file('sha256', $file))) throw new RuntimeException('L’elenco dei 281 gruppi approvati è cambiato.');
$approval = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
if (count($approval['groups']) !== 281 || array_sum(array_map(fn ($group) => count($group['source_rows']), $approval['groups'])) !== 564) throw new RuntimeException('Quantità diversa da quella approvata.');
$service = app(ApprovedEquivalentPolicyImport::class);
$apply = in_array('--apply', $argv, true);
if (!$apply) {
    $db->statement('SET TRANSACTION READ ONLY'); $db->beginTransaction();
    try { $preview = $service->preview($approval); unset($preview['entries']); }
    finally { $db->rollBack(); }
    echo json_encode(['mode' => 'preview'] + $preview, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
$actor = User::where('email', 'admin@admin.it')->firstOrFail();
$app['auth']->guard('web')->setUser($actor);
$approval['state'] = 'approved';
$approval['approved_rule'] = ApprovedEquivalentPolicyImport::RULE;
$approval['authorization'] = 'Sì, importa questi 281 gruppi';
$approval['proposal_hash'] = $approvedHash;
$approval['registered_at'] = now()->toIso8601String();
$approval['registered_by'] = $actor->id;
$ids = array_column($approval['groups'], 'entry_id');
$backup = storage_path('app/private/equivalent-policy-backup-'.date('Ymd-His'));
if (file_exists($backup) || !mkdir($backup, 0700, true)) throw new RuntimeException('Copia preventiva non disponibile.');
file_put_contents($backup.'/approval.json', json_encode($approval, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents($backup.'/users.json', $db->table('users')->orderBy('id')->get()->toJson(JSON_THROW_ON_ERROR));
file_put_contents($backup.'/approved-entries-before.json', $db->table('insurance_policy_imports')->whereIn('id', $ids)->orderBy('id')->get()->toJson(JSON_THROW_ON_ERROR));
$snapshot = function (string $table) use ($db): string {
    $query = $db->table($table)->orderBy($table === 'supplier_agency_company' ? 'supplier_agency_id' : 'id');
    if ($table === 'supplier_agency_company') $query->orderBy('supplier_company_id');
    return hash('sha256', $query->get()->toJson(JSON_THROW_ON_ERROR));
};
$report = DB::transaction(function () use ($db, $service, $approval, $actor, $ids, $snapshot) {
    $before = [];
    foreach (['users', 'clients', 'registry_import_entries', 'supplier_companies', 'supplier_agencies', 'supplier_agency_company', 'insurance_branches', 'insurance_subbranches', 'insurance_catalog_aliases', 'attachments', 'client_document_requests', 'client_vehicles', 'client_assets'] as $table) $before[$table] = $snapshot($table);
    $existingIds = InsurancePolicy::pluck('id');
    $existing = fn () => hash('sha256', $db->table('insurance_policies')->whereIn('id', $existingIds)->orderBy('id')->get()->toJson(JSON_THROW_ON_ERROR));
    $others = fn () => hash('sha256', $db->table('insurance_policy_imports')->whereNotIn('id', $ids)->orderBy('id')->get()->toJson(JSON_THROW_ON_ERROR));
    $sources = fn () => hash('sha256', $db->table('insurance_policy_imports')->whereIn('id', $ids)->orderBy('id')->get(['id', 'source_key', 'batch_hash', 'payload_hash', 'client_id', 'source_rows', 'source_payload'])->toJson(JSON_THROW_ON_ERROR));
    $originalPolicies = $existing(); $originalOthers = $others(); $originalSources = $sources();
    $report = $service->apply($approval, $actor);
    if ($report['created'] + $report['already_imported'] !== 281) throw new RuntimeException('Numero di gruppi elaborati inatteso.');
    foreach ($before as $table => $hash) {
        $report['unchanged'][$table] = hash_equals($hash, $snapshot($table));
        if (!$report['unchanged'][$table]) throw new RuntimeException('Modifica fuori ambito: '.$table);
    }
    $report['existing_policies_unchanged'] = hash_equals($originalPolicies, $existing());
    $report['other_import_entries_unchanged'] = hash_equals($originalOthers, $others());
    $report['all_original_sources_unchanged'] = hash_equals($originalSources, $sources());
    if (!$report['existing_policies_unchanged'] || !$report['other_import_entries_unchanged'] || !$report['all_original_sources_unchanged']) throw new RuntimeException('Modifica inattesa alle polizze o alle fonti.');
    $repeat = $service->preview($approval);
    $report['rerun'] = array_intersect_key($repeat, array_flip(['ready', 'already_imported', 'source_rows']));
    if ($repeat['ready'] !== 0 || $repeat['already_imported'] !== 281) throw new RuntimeException('Ripetizione non sicura.');
    $report['counts'] = ['policies' => InsurancePolicy::count(), 'imported' => InsurancePolicyImport::where('status', 'imported')->count(), 'held' => InsurancePolicyImport::where('status', 'held')->count(), 'review' => InsurancePolicy::where('status', 'review')->count()];
    $report['no_duplicates'] = !$db->table('insurance_policies')->select('company', 'number')->groupBy('company', 'number')->havingRaw('COUNT(*) > 1')->exists();
    if (!$report['no_duplicates']) throw new RuntimeException('Numero polizza duplicato.');
    $report['ok'] = true;
    return $report;
});
$report['approval_hash'] = $approvedHash;
file_put_contents(__DIR__.'/equivalent-policy-import-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
unset($report['policy_ids']);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
