<?php

use App\Models\InsurancePolicyImport;
use App\Services\PolicyExcelImporter;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Database inatteso.');
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
$increment = function (array &$map, string $key) { $map[$key] = ($map[$key] ?? 0) + 1; };
try {
    $plan = app(PolicyExcelImporter::class)->plan();
    $currentPlan = collect($plan['entries'])->keyBy('source_key');
    $report = ['checked_at' => now()->toIso8601String(), 'current_plan' => app(PolicyExcelImporter::class)->report($plan),
        'counts' => [], 'issues' => [], 'only_issue' => [], 'combinations' => [], 'single_combinations' => [],
        'companies_to_confirm' => [], 'classifications_to_confirm' => [], 'repeat_patterns' => [], 'repeat_changed_fields' => [],
        'money_patterns' => [], 'only_company' => [], 'only_classification' => [], 'examples' => [], 'items' => [],
        'equivalent_proposal' => ['state' => 'pending_decision', 'groups' => [], 'group_count' => 0, 'source_row_count' => 0]];
    $report['counts']['imported'] = InsurancePolicyImport::where('status', 'imported')->count();
    $report['counts']['held'] = InsurancePolicyImport::where('status', 'held')->count();
    $report['counts']['clients_held'] = $db->table('registry_import_entries')->where('kind', 'client')->where('status', 'held')->count();
    $core = ['Contraente', 'Cod. Fiscale / P. IVA', 'Compagnia', 'Agenzia', 'Numero Polizza', 'Ramo', 'Sottoramo', 'Interesse Assic.', 'Efetto', 'Scadenza', 'Premio annuale', 'Frazionamento', 'Tacito Rinnovo'];
    foreach (InsurancePolicyImport::where('status', 'held')->orderBy('id')->cursor() as $entry) {
        $payload = $entry->source_payload;
        if (!hash_equals($entry->payload_hash, hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)))) throw new RuntimeException('Fonte registrata modificata.');
        $planned = $currentPlan[$entry->source_key] ?? throw new RuntimeException('Gruppo non più presente nel lotto.');
        $issues = $entry->issues; sort($issues);
        $currentIssues = $planned['issues'] ?? []; sort($currentIssues);
        if ($issues !== $currentIssues) throw new RuntimeException('I rilievi correnti differiscono dal registro: aggiornare il confronto.');
        foreach ($issues as $issue) $increment($report['issues'], $issue);
        if (count($issues) === 1) $increment($report['only_issue'], $issues[0]);
        $combination = implode(' | ', $issues);
        $increment($report['combinations'], $combination);
        $rows = $payload['rows'];
        $changed = [];
        foreach (array_keys($rows[0]['values']) as $field) {
            if (count(array_unique(array_map(fn ($row) => json_encode($row['values'][$field] ?? null), $rows))) > 1) $changed[] = $field;
        }
        $item = ['entry_id' => $entry->id, 'source_key' => $entry->source_key, 'payload_hash' => $entry->payload_hash, 'number' => $entry->number, 'company' => $entry->company, 'agency' => $entry->agency,
            'source_rows' => $entry->source_rows, 'issues' => $issues, 'differing_fields' => $changed,
            'rows' => array_map(fn ($row) => ['row' => $row['row'], 'values' => array_intersect_key($row['values'], array_flip(array_merge($core, ['Polizze', 'Protocollo', 'Data Rata Attuale', 'Data Rata Futura', 'Premio Rata', 'Note'])))], $rows)];
        if ($issues === ['numero_polizza_ripetuto'] && !array_intersect($changed, array_merge($core, ['Note', 'Premio Rata']))) {
            $report['equivalent_proposal']['groups'][] = array_intersect_key($item, array_flip(['entry_id', 'source_key', 'payload_hash', 'source_rows']));
            $report['equivalent_proposal']['group_count']++;
            $report['equivalent_proposal']['source_row_count'] += count($rows);
        }
        if ($issues === ['denominazione_compagnia_da_confermare']) $increment($report['only_company'], $entry->company);
        if ($issues === ['classificazione_da_confermare']) $increment($report['only_classification'], trim($rows[0]['values']['Ramo']).' / '.trim($rows[0]['values']['Sottoramo']));
        if (count($rows) === 1) {
            $increment($report['counts'], 'single_row_groups');
            $increment($report['single_combinations'], $combination);
        } else {
            $increment($report['counts'], 'multiple_row_groups');
            foreach ($changed as $field) $increment($report['repeat_changed_fields'], $field);
            $pattern = !$changed ? 'righe_interamente_identiche'
                : (!array_diff($changed, ['Protocollo']) ? 'solo_protocollo_diverso'
                    : (!array_intersect($changed, $core) ? 'contratto_uguale_altri_campi_diversi' : 'dati_contratto_diversi'));
            $item['repeat_pattern'] = $pattern;
            $increment($report['repeat_patterns'], $pattern);
            if (count($report['examples'][$pattern] ?? []) < 3) $report['examples'][$pattern][] = $item;
        }
        if (in_array('denominazione_compagnia_da_confermare', $issues, true)) $increment($report['companies_to_confirm'], $entry->company);
        if (in_array('classificazione_da_confermare', $issues, true)) {
            foreach (array_unique(array_map(fn ($row) => trim($row['values']['Ramo']).' / '.trim($row['values']['Sottoramo']), $rows)) as $pair) $increment($report['classifications_to_confirm'], $pair);
        }
        if (in_array('premio_non_valido', $issues, true)) {
            $data = $rows[0]['values'];
            $negativeAnnual = is_numeric($data['Premio annuale']) && (float) $data['Premio annuale'] < 0;
            $negativeInstallment = is_numeric($data['Premio Rata']) && (float) $data['Premio Rata'] < 0;
            $pattern = $negativeAnnual ? 'premio_annuo_negativo' : ($negativeInstallment ? 'solo_premio_rata_negativo' : 'formato_o_valore_da_verificare');
            $increment($report['money_patterns'], $pattern);
            if (count($report['examples'][$pattern] ?? []) < 3) $report['examples'][$pattern][] = $item;
        }
        foreach (['scadenza_precedente_decorrenza', 'dati_polizza_non_validi'] as $problem) if (in_array($problem, $issues, true)) $report['examples'][$problem][] = $item;
        $report['items'][] = $item;
    }
    foreach (['issues', 'only_issue', 'combinations', 'single_combinations', 'companies_to_confirm', 'classifications_to_confirm', 'repeat_patterns', 'repeat_changed_fields', 'money_patterns', 'only_company', 'only_classification'] as $key) arsort($report[$key]);
    $report['ok'] = count($report['items']) === $report['counts']['held'] && ($report['counts']['single_row_groups'] + $report['counts']['multiple_row_groups']) === $report['counts']['held'];
} finally { $db->rollBack(); }
$directory = storage_path('app/private/insurance-import/held-policy-review');
if (!is_dir($directory)) mkdir($directory, 0700, true);
file_put_contents($directory.'/analysis.json', json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents($directory.'/equivalent-proposal.json', json_encode(['batch_hash' => $plan['batch_hash'], 'checked_at' => $report['checked_at']] + $report['equivalent_proposal'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
unset($report['items'], $report['examples'], $report['equivalent_proposal']['groups']);
file_put_contents(__DIR__.'/held-policy-review-summary.json', json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
unset($report['combinations'], $report['single_combinations'], $report['repeat_changed_fields']);
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['ok'] ? 0 : 1);
