<?php

namespace Tests\Fixtures;

use App\Models\{Client, InsuranceBranch, InsuranceCatalogAlias, RegistryImportEntry, SupplierAgency, SupplierCompany};
use App\Services\InsuranceCatalog;
use Illuminate\Support\Facades\File;

class PolicyImportFixture
{
    public static function create(string $directory): array
    {
        config(['insurance-import.directory' => $directory]);
        File::ensureDirectoryExists($directory);
        $client = Client::factory()->create(['name' => 'Cliente dimostrativo polizze', 'tax_code' => 'RSSMRA80A01H501U', 'client_type' => 'person']);
        $company = SupplierCompany::create(['name' => 'COMPAGNIA DIMOSTRATIVA', 'status' => 'active']);
        $agency = SupplierAgency::create(['name' => 'AGENZIA DIMOSTRATIVA', 'status' => 'active', 'supplier_company_id' => $company->id]);
        $branch = InsuranceBranch::create(['name' => 'RC auto', 'name_key' => InsuranceCatalog::key('RC auto'), 'import_key' => InsuranceCatalog::key('RCA'), 'status' => 'active', 'needs_review' => false]);
        $sub = $branch->subbranches()->create(['name' => 'AUTO', 'name_key' => InsuranceCatalog::key('AUTO'), 'import_key' => InsuranceCatalog::key('RCA|AUTO'), 'status' => 'active', 'needs_review' => false]);
        foreach ([null, 'RCA'] as $sourceSub) InsuranceCatalogAlias::create(['source_key' => InsuranceCatalogAlias::sourceKey('RCA', $sourceSub), 'source_branch' => 'RCA', 'source_subbranch' => $sourceSub, 'insurance_branch_id' => $branch->id]);
        $uncertain = InsuranceBranch::create(['name' => 'AUTO', 'name_key' => InsuranceCatalog::key('AUTO'), 'import_key' => InsuranceCatalog::key('AUTO'), 'status' => 'active', 'needs_review' => true]);
        $uncertain->subbranches()->create(['name' => 'CVT', 'name_key' => InsuranceCatalog::key('CVT'), 'import_key' => InsuranceCatalog::key('AUTO|CVT'), 'status' => 'active', 'needs_review' => true]);
        $clientCandidate = ['key' => hash('sha256', 'client-demo'), 'source' => 'clients', 'source_rows' => [8], 'fiscal_identifier' => 'RSSMRA80A01H501U', 'data' => ['name' => $client->name], 'issues' => ['email_da_verificare'], 'conflicting_fields' => []];
        foreach ([['client', $clientCandidate['key'], $client->id, hash('sha256', json_encode($clientCandidate, JSON_THROW_ON_ERROR))], ['company', hash('sha256', $company->name), $company->id, str_repeat('a', 64)], ['agency', hash('sha256', $agency->name), $agency->id, str_repeat('a', 64)], ['lock', 'registry', null, str_repeat('0', 64)]] as [$kind, $key, $id, $hash]) {
            RegistryImportEntry::create(['kind' => $kind, 'source_key' => $key, 'source_name' => 'Fonte dimostrativa', 'source' => $kind === 'client' ? 'clients' : 'providers', 'entity_id' => $id, 'batch_hash' => str_repeat('a', 64), 'payload_hash' => $hash, 'status' => $kind === 'lock' ? 'lock' : 'imported', 'source_rows' => [8], 'issues' => [], 'deferred_data' => []]);
        }
        $policies = []; $rows = [];
        foreach (['000012', '000013', 'REPEAT-QA', 'RAMO-QA', 'MISSING-QA', 'PREMIO-QA', 'COMPANY-QA', 'IDENTITY-QA'] as $index => $number) {
            $row = 8 + count($rows);
            $raw = ['Contraente' => $client->name, 'Cod. Fiscale / P. IVA' => 'RSSMRA80A01H501U', 'Compagnia' => $company->name, 'Agenzia' => $agency->name,
                'Numero Polizza' => $number, 'Ramo' => 'RCA', 'Sottoramo' => $number === '000013' ? 'RCA' : 'AUTO', 'Polizze' => $number === '000013' ? 'OLD' : 'Archivio', 'Protocollo' => 'DEMO-'.$index,
                'Efetto' => '01/01/2020', 'Scadenza' => '31/12/2020', 'Premio annuale' => '720,50', 'Premio Rata' => '60,04', 'Data Rata Attuale' => '01/01/2020', 'Data Rata Futura' => '01/02/2020', 'Frazionamento' => 'Mensile', 'Tacito Rinnovo' => 'Sì', 'Interesse Assic.' => 'Bene dimostrativo', 'Note' => 'Nota originale dimostrativa', 'Documenti Polizza' => 'Riferimento dimostrativo'];
            $data = ['category' => 'RC auto', 'subcategory' => 'Autovetture', 'company' => $company->name, 'agency' => $agency->name, 'number' => $number, 'product' => null, 'insured_subject' => 'Bene dimostrativo', 'notes' => 'Nota originale dimostrativa', 'starts_on' => '2020-01-01', 'expires_on' => '2020-12-31', 'premium' => '720.50', 'installment_premium' => '60.04', 'installment_on' => '2020-01-01', 'next_installment_on' => '2020-02-01', 'frequency' => 'Mensile', 'source_status' => $raw['Polizze']];
            $issues = ['classificazione_da_confermare', 'cliente_da_verificare'];
            $clientKey = $clientCandidate['key']; $sourceRows = [$row]; $conflicts = [];
            if ($number === 'RAMO-QA') { $raw['Ramo'] = 'AUTO'; $raw['Sottoramo'] = 'CVT'; }
            if ($number === 'MISSING-QA') $clientKey = hash('sha256', 'missing');
            if ($number === 'PREMIO-QA') { $data['premium'] = '-2'; $issues[] = 'premio_non_valido'; }
            if ($number === 'COMPANY-QA') $issues[] = 'denominazione_compagnia_da_confermare';
            if ($number === 'IDENTITY-QA') $raw['Contraente'] = 'Nome dimostrativo discordante';
            $rows[] = ['source' => 'policies', 'row' => $row, 'values' => $raw, 'issues' => []];
            if ($number === 'REPEAT-QA') { $raw['Scadenza'] = '31/12/2021'; $sourceRows[] = $row + 1; $rows[] = ['source' => 'policies', 'row' => $row + 1, 'values' => $raw, 'issues' => []]; $issues[] = 'numero_polizza_ripetuto'; $conflicts[] = 'Scadenza'; }
            $policies[] = ['key' => hash('sha256', $number), 'client_key' => $clientKey, 'source_rows' => $sourceRows, 'data' => $data, 'issues' => $issues, 'conflicting_fields' => $conflicts, 'status_review' => true];
        }
        $summary = ['version' => 1, 'mode' => 'preview_only', 'sources' => [], 'taxonomy' => [], 'company_agencies' => [], 'issue_counts' => [], 'mapping' => [], 'counts' => array_fill_keys(['registry_rows', 'client_candidates', 'clients_to_review', 'policy_rows', 'policy_groups', 'policy_repeated_groups', 'financial_rows_excluded', 'policies_needing_status'], 0), 'decisions' => []];
        self::write($directory, ['summary.json' => $summary, 'clients.json' => [$clientCandidate], 'policies.json' => $policies, 'source-rows.jsonl' => $rows]);
        return compact('client', 'company', 'agency', 'branch', 'sub', 'policies', 'rows', 'clientCandidate', 'directory');
    }

    public static function write(string $directory, array $files): void
    {
        $manifest = is_file($directory.'/manifest.json') ? json_decode(file_get_contents($directory.'/manifest.json'), true) : ['files' => []];
        foreach ($files as $name => $value) {
            $content = $name === 'source-rows.jsonl' ? implode("\n", array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $value))."\n" : json_encode($value, JSON_THROW_ON_ERROR);
            file_put_contents($directory.'/'.$name, $content); $manifest['files'][$name] = hash('sha256', $content);
        }
        file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }
}
