<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Database inatteso.');
$preview = app(App\Services\InsuranceImportPreview::class);
$summary = $preview->summary();
foreach ($summary['sources'] as $source) {
    if (!hash_equals($source['sha256'], hash_file('sha256', dirname($root).'/'.$source['file']))) throw new RuntimeException('Fonte modificata.');
}
$normalize = fn($value) => trim(App\Services\InsuranceCatalog::normalize((string) $value));
$sourceGroups = [];
$file = fopen($preview->verifiedFile('source-rows.jsonl'), 'r');
while (($line = fgets($file)) !== false) {
    $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
    if ($row['source'] !== 'policies') continue;
    $values = $row['values'];
    $branch = $normalize($values['Ramo']); $sub = $normalize($values['Sottoramo']);
    $group = &$sourceGroups[$branch][$sub];
    $group ??= ['rows' => 0, 'companies' => [], 'sample_rows' => []];
    $group['rows']++;
    $company = $normalize($values['Compagnia']);
    $group['companies'][$company] = ($group['companies'][$company] ?? 0) + 1;
    if (count($group['sample_rows']) < 3) $group['sample_rows'][] = $row['row'];
    unset($group);
}
fclose($file);
$db->statement('SET TRANSACTION READ ONLY'); $db->beginTransaction();
try {
    $report = ['sources_verified' => true, 'note' => 'Conteggi di righe Excel, non di polizze uniche. Nessuna modifica al database.', 'branches' => []];
    foreach (App\Models\InsuranceBranch::with(['subbranches' => fn($q) => $q->orderBy('name')])->orderBy('name')->get() as $branch) {
        $sources = [];
        foreach ($branch->source_values ?? [] as $pair) {
            $name = $normalize($pair['source_branch']); $sub = $normalize($pair['source_subbranch']);
            $sources[$name.'|'.$sub] = ['branch' => $name, 'subbranch' => $sub] + ($sourceGroups[$name][$sub] ?? []) + ['reason' => $pair['reason']];
        }
        $report['branches'][] = ['id' => $branch->id, 'name' => $branch->name, 'review' => $branch->needs_review, 'status' => $branch->status,
            'subbranches' => $branch->subbranches->map(fn($sub) => ['id' => $sub->id, 'name' => $sub->name, 'review' => $sub->needs_review])->all(), 'sources' => array_values($sources)];
    }
    $report['policies_in_database'] = App\Models\InsurancePolicy::count();
    $report['requests_in_database'] = App\Models\ClientDocumentRequest::count();
} finally { $db->rollBack(); }
file_put_contents(__DIR__.'/insurance-catalog-review.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
foreach ($report['branches'] as $branch) {
    echo json_encode($branch, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
}
echo json_encode(['sources_verified' => true, 'policies_in_database' => $report['policies_in_database'], 'requests_in_database' => $report['requests_in_database']]).PHP_EOL;
