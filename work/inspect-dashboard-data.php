<?php

use App\Models\InsurancePolicyImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Database inatteso.');
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $report = ['teams' => [], 'source_statuses' => [], 'payment_fields' => []];
    foreach (['teams', 'team_user'] as $table) $report['teams'][$table] = Schema::hasTable($table) ? $db->table($table)->count() : null;
    $report['team_dependencies'] = $db->select("SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IN ('teams','team_user')");
    foreach (InsurancePolicyImport::where('status', 'imported')->with('policy')->get() as $entry) {
        $key = $entry->source_status ?: '(vuoto)';
        $report['source_statuses'][$key] ??= ['policies' => 0, 'past_expiry' => 0, 'future_start' => 0];
        $report['source_statuses'][$key]['policies']++;
        if ($entry->policy->expires_on->lt(today())) $report['source_statuses'][$key]['past_expiry']++;
        if ($entry->policy->starts_on->gt(today())) $report['source_statuses'][$key]['future_start']++;
        $rows = $entry->source_payload['rows'] ?? $entry->source_payload;
        if (!is_array($rows)) continue;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $fields = $row['values'] ?? $row['data'] ?? $row;
            foreach ($fields as $field => $value) {
                if (is_string($field) && preg_match('/pagam|pagat|frazion|rata/i', $field) && !preg_match('/premio|data/i', $field) && is_scalar($value)) {
                    $report['payment_fields'][$field][(string)$value] = ($report['payment_fields'][$field][(string)$value] ?? 0) + 1;
                }
            }
        }
    }
    $first = InsurancePolicyImport::where('status', 'imported')->first();
    $payload = $first->source_payload;
    $report['payload_structure'] = array_keys($payload);
    $report['payload_first_item_keys'] = is_array(reset($payload)) ? array_keys(reset($payload)) : [];
} finally { $db->rollBack(); }
file_put_contents(__DIR__.'/dashboard-data-review.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
