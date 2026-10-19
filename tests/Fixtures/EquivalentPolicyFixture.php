<?php

namespace Tests\Fixtures;

use App\Models\InsurancePolicyImport;
use App\Services\PolicyExcelImporter;

class EquivalentPolicyFixture
{
    public static function create(string $directory, ?string $changedField = null): array
    {
        $fixture = PolicyImportFixture::create($directory);
        $index = array_search('REPEAT-QA', array_column(array_column($fixture['policies'], 'data'), 'number'), true);
        $candidate = $fixture['policies'][$index];
        $source = collect($fixture['rows'])->firstWhere('row', $candidate['source_rows'][0]);
        foreach ($fixture['rows'] as &$row) {
            if ($row['row'] === $candidate['source_rows'][1]) $row['values'] = array_replace($source['values'], ['Polizze' => 'OLD', 'Data Rata Attuale' => '01/02/2020', 'Data Rata Futura' => '01/03/2020']);
        }
        unset($row);
        $fixture['policies'][$index]['conflicting_fields'] = [];
        $second = $fixture['policies'][$index];
        $second['key'] = hash('sha256', 'SECOND-EQUIVALENT-QA');
        $second['data']['number'] = 'SECOND-EQUIVALENT-QA';
        $second['source_rows'] = [100, 101];
        $fixture['policies'][] = $second;
        foreach ([100, 101] as $number) {
            $values = array_replace($source['values'], ['Numero Polizza' => 'SECOND-EQUIVALENT-QA', 'Data Rata Attuale' => $number === 100 ? '01/01/2020' : '01/02/2020']);
            if ($changedField && $number === 101) $values[$changedField] = 'Dato diverso dimostrativo';
            $fixture['rows'][] = ['source' => 'policies', 'row' => $number, 'values' => $values, 'issues' => []];
        }
        PolicyImportFixture::write($directory, ['policies.json' => $fixture['policies'], 'source-rows.jsonl' => $fixture['rows']]);
        $importer = app(PolicyExcelImporter::class);
        $batch = $importer->plan()['batch_hash'];
        $importer->apply($batch);
        $groups = InsurancePolicyImport::whereIn('number', ['REPEAT-QA', 'SECOND-EQUIVALENT-QA'])->orderBy('id')->get()->map(fn ($entry) => [
            'entry_id' => $entry->id, 'source_key' => $entry->source_key, 'payload_hash' => $entry->payload_hash, 'source_rows' => $entry->source_rows,
        ])->all();
        return $fixture + ['approval' => ['state' => 'approved', 'batch_hash' => $batch, 'groups' => $groups]];
    }
}
