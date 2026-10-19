<?php

namespace App\Services;

use App\Models\{Client, InsuranceBranch, InsuranceCatalogAlias, InsurancePolicy, InsurancePolicyImport, RegistryImportEntry, SupplierAgency, SupplierCompany};
use App\Support\ImportFiscalIdentifier;
use Illuminate\Support\Facades\{DB, Schema, Validator};
use RuntimeException;

class PolicyExcelImporter
{
    private function clean(mixed $value): string { return preg_replace('/^\s+|\s+$/u', '', preg_replace('/\s+/u', ' ', (string) $value)); }
    private function nameKey(mixed $value): string { return mb_strtoupper($this->clean($value)); }
    private function identity(string $company, string $number): string { return json_encode([$this->nameKey($company), $this->nameKey($number)], JSON_THROW_ON_ERROR); }

    public function plan(): array
    {
        $preview = app(InsuranceImportPreview::class);
        $summary = $preview->summary() ?? throw new RuntimeException('Lotto polizze non disponibile.');
        $policiesFile = $preview->verifiedFile('policies.json');
        $clientsFile = $preview->verifiedFile('clients.json');
        $rowsFile = $preview->verifiedFile('source-rows.jsonl');
        $batch = hash('sha256', hash_file('sha256', $policiesFile).hash_file('sha256', $clientsFile).hash_file('sha256', $rowsFile).hash_file('sha256', $preview->verifiedFile('summary.json')));
        $candidates = json_decode(file_get_contents($policiesFile), true, 512, JSON_THROW_ON_ERROR);
        $sourceFile = collect($summary['sources'] ?? [])->first(fn ($source) => in_array('Numero Polizza', $source['headers'] ?? [], true))['file'] ?? 'File Gestionale';
        $clientCandidates = collect(json_decode(file_get_contents($clientsFile), true, 512, JSON_THROW_ON_ERROR))->keyBy('key');
        $rows = [];
        $file = fopen($rowsFile, 'r');
        try { while (($line = fgets($file)) !== false) { $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR); if ($row['source'] === 'policies') $rows[$row['row']] = $row; } }
        finally { fclose($file); }
        $clients = Client::current()->get()->keyBy('id');
        $registry = RegistryImportEntry::whereIn('kind', ['client', 'agency', 'company'])->get()->keyBy(fn ($entry) => $entry->kind.':'.$entry->source_key);
        $companies = SupplierCompany::get()->keyBy('id');
        $agencies = SupplierAgency::with('companies')->get()->keyBy('id');
        $branches = InsuranceBranch::with('subbranches')->get()->keyBy('import_key');
        $aliases = InsuranceCatalogAlias::with(['branch', 'subbranch'])->get()->keyBy('source_key');
        $existingRecords = InsurancePolicy::get(['id', 'client_id', 'company', 'number']);
        $existingIds = $existingRecords->keyBy('id');
        $existing = $existingRecords->groupBy(fn ($policy) => $this->identity($policy->company, $policy->number));
        $previous = Schema::hasTable('insurance_policy_imports') ? InsurancePolicyImport::get(['id', 'source_key', 'insurance_policy_id', 'payload_hash', 'status'])->keyBy('source_key') : collect();
        $candidateIdentities = collect($candidates)->countBy(fn ($item) => $this->identity($item['data']['company'], $item['data']['number']));
        $entries = [];
        foreach ($candidates as $candidate) {
            $sources = [];
            foreach ($candidate['source_rows'] as $rowNumber) $sources[] = $rows[$rowNumber] ?? throw new RuntimeException('Riga sorgente mancante.');
            if (!$sources) throw new RuntimeException('Gruppo polizza senza righe sorgente.');
            $payload = ['source_file' => $sourceFile, 'candidate' => $candidate, 'rows' => $sources];
            $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $prior = $previous->get($candidate['key']);
            if ($prior?->status === 'imported') {
                if (!hash_equals($prior->payload_hash, $payloadHash) || !$existingIds->has($prior->insurance_policy_id)) throw new RuntimeException('Polizza già importata modificata nella fonte o non più disponibile: confrontare prima di proseguire.');
                $entries[] = ['source_key' => $candidate['key'], 'already_imported' => true];
                continue;
            }
            $raw = $sources[0]['values'];
            // Proposed labels in policies.json are not approvals: always resolve the original pair.
            $sourceBranch = $this->clean($raw['Ramo']); $sourceSub = $this->clean($raw['Sottoramo']);
            $alias = $aliases->get(InsuranceCatalogAlias::sourceKey($sourceBranch, $sourceSub));
            $branch = $alias?->branch ?? $aliases->get(InsuranceCatalogAlias::sourceKey($sourceBranch))?->branch ?? $branches->get(InsuranceCatalog::key($sourceBranch));
            $sub = $alias ? $alias->subbranch : $branch?->subbranches->firstWhere('import_key', InsuranceCatalog::key($sourceBranch.'|'.$sourceSub));
            $issues = array_values(array_diff($candidate['issues'], ['classificazione_da_confermare', 'cliente_da_verificare']));
            if (count($sources) !== 1) $issues[] = 'numero_polizza_ripetuto';
            if (!$branch || $branch->status !== 'active' || ($sourceSub !== '' && !$alias && !$sub) || ($sub && ($sub->status !== 'active' || $sub->insurance_branch_id !== $branch->id))) $issues[] = 'collegamento_catalogo_mancante';
            if ($branch?->needs_review || $sub?->needs_review) $issues[] = 'classificazione_da_confermare';
            $clientCandidate = $clientCandidates->get($candidate['client_key']);
            $clientEntry = $registry->get('client:'.$candidate['client_key']);
            $client = $clientEntry && in_array($clientEntry->status, ['imported', 'linked'], true) ? $clients->get($clientEntry->entity_id) : null;
            if (!$client || !$clientCandidate) $issues[] = 'cliente_non_collegabile';
            elseif (!hash_equals($clientEntry->payload_hash, hash('sha256', json_encode($clientCandidate, JSON_THROW_ON_ERROR)))) throw new RuntimeException('La fonte del cliente è cambiata: verificare il registro anagrafiche.');
            $fiscal = preg_replace('/\s+/u', '', $this->nameKey($raw['Cod. Fiscale / P. IVA'] ?? ''));
            if (!ImportFiscalIdentifier::valid($fiscal) || !$clientCandidate || $fiscal !== $clientCandidate['fiscal_identifier']
                || $this->nameKey($raw['Contraente'] ?? '') !== $this->nameKey($clientCandidate['data']['name'] ?? '')
                || !empty($clientCandidate['conflicting_fields'])) $issues[] = 'identita_cliente_da_verificare';
            $currentIdentifiers = $client ? array_filter([$this->nameKey($client->tax_code), $this->nameKey($client->vat_number)]) : [];
            if ($currentIdentifiers && !in_array($fiscal, $currentIdentifiers, true)) $issues[] = 'identita_cliente_da_verificare';
            $companyName = $this->nameKey($raw['Compagnia']); $agencyName = $this->nameKey($raw['Agenzia']);
            $companyEntry = $registry->get('company:'.hash('sha256', $companyName));
            $agencyEntry = $registry->get('agency:'.hash('sha256', $agencyName));
            $company = $companyEntry?->status === 'imported' ? $companies->get($companyEntry->entity_id) : null;
            $agency = $agencyEntry?->status === 'imported' ? $agencies->get($agencyEntry->entity_id) : null;
            if (!$company || !$agency || $company->status !== 'active' || $agency->status !== 'active'
                || $this->nameKey($company->name) !== $companyName || $this->nameKey($agency->name) !== $agencyName || !$agency->companies->contains('id', $company->id)) $issues[] = 'fornitore_non_collegabile';
            $identity = $this->identity($companyName, $candidate['data']['number']);
            if ($candidateIdentities->get($identity) > 1) $issues[] = 'stessa_polizza_intestatari_diversi';
            if ($existing->has($identity)) $issues[] = 'record_esistente_da_confrontare';
            $data = array_intersect_key($candidate['data'], array_flip(['number', 'product', 'insured_subject', 'starts_on', 'expires_on', 'premium', 'notes']));
            $data = array_merge($data, ['category' => $branch?->name, 'subcategory' => $sub?->name, 'company' => $company?->name ?? $companyName,
                'insurance_branch_id' => $branch?->id, 'insurance_subbranch_id' => $sub?->id, 'supplier_company_id' => $company?->id, 'supplier_agency_id' => $agency?->id, 'status' => 'review']);
            if (Validator::make($data, ['category' => 'required|string|max:100', 'subcategory' => 'nullable|string|max:100', 'company' => 'required|string|max:150', 'number' => 'required|string|max:100',
                'product' => 'nullable|string|max:255', 'insured_subject' => 'nullable|string|max:255', 'starts_on' => 'required|date_format:Y-m-d', 'expires_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on',
                'premium' => 'nullable|numeric|min:0|max:9999999999.99|decimal:0,2', 'notes' => 'nullable|string|max:10000'])->fails()) $issues[] = 'dati_polizza_non_validi';
            $entries[] = ['source_key' => $candidate['key'], 'payload_hash' => $payloadHash, 'source_rows' => $candidate['source_rows'], 'source_payload' => $payload,
                'number' => $candidate['data']['number'], 'company' => $companyName, 'agency' => $agencyName, 'client_name' => $this->clean($raw['Contraente'] ?? ''), 'source_status' => $candidate['data']['source_status'],
                'client_id' => $client?->id, 'issues' => array_values(array_unique($issues)), 'data' => $data, 'already_imported' => false];
        }
        return ['batch_hash' => $batch, 'entries' => $entries];
    }

    public function report(array $plan): array
    {
        $report = ['batch_hash' => $plan['batch_hash'], 'ready' => 0, 'held' => 0, 'already_imported' => 0, 'issues' => []];
        foreach ($plan['entries'] as $entry) {
            if ($entry['already_imported']) { $report['already_imported']++; continue; }
            $report[$entry['issues'] ? 'held' : 'ready']++;
            foreach ($entry['issues'] as $code) $report['issues'][$code] = ($report['issues'][$code] ?? 0) + 1;
        }
        arsort($report['issues']);
        return $report;
    }

    public function apply(string $expectedBatch): array
    {
        return DB::transaction(function () use ($expectedBatch) {
            // Serialize imports first, then take the same branch/provider locks as policy writes.
            RegistryImportEntry::where('kind', 'lock')->where('source_key', 'registry')->lockForUpdate()->firstOrFail();
            InsuranceBranch::orderBy('id')->lockForUpdate()->get();
            SupplierCompany::orderBy('id')->lockForUpdate()->get();
            SupplierAgency::orderBy('id')->lockForUpdate()->get();
            Client::orderBy('id')->lockForUpdate()->get(['id']);
            $plan = $this->plan();
            if (!hash_equals($expectedBatch, $plan['batch_hash'])) throw new RuntimeException('Il lotto è cambiato dopo la verifica.');
            $report = $this->report($plan); $report['created'] = 0;
            foreach ($plan['entries'] as $entry) {
                if ($entry['already_imported']) continue;
                $policy = null;
                if (!$entry['issues']) {
                    $policy = Client::findOrFail($entry['client_id'])->policies()->create($entry['data']);
                    $report['created']++;
                }
                unset($entry['data'], $entry['already_imported']);
                foreach (['number', 'company', 'agency', 'client_name'] as $field) $entry[$field] = mb_substr($entry[$field], 0, 255);
                $entry['source_status'] = mb_substr($entry['source_status'], 0, 100);
                InsurancePolicyImport::updateOrCreate(['source_key' => $entry['source_key']], $entry + ['batch_hash' => $plan['batch_hash'], 'insurance_policy_id' => $policy?->id, 'status' => $policy ? 'imported' : 'held']);
            }
            return $report;
        });
    }
}
