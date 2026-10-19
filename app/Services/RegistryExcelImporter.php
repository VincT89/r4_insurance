<?php

namespace App\Services;

use App\Actions\Clients\CreateClientAction;
use App\Models\{Client, RegistryImportEntry, SupplierAgency, SupplierCompany};
use Illuminate\Support\Facades\{DB, Validator};
use RuntimeException;

class RegistryExcelImporter
{
    private const FIELDS = [
        'name' => 'Contraente', 'title' => 'Titolo', 'birth_date' => 'Data di nascita', 'birth_place' => 'Luogo di nascita',
        'phone' => 'Telefono', 'mobile' => 'Cellulare', 'fax' => 'FAX', 'email' => 'Email', 'target' => 'Target',
        'province' => 'Provincia', 'city' => 'Città', 'address' => 'Indirizzo', 'postal_code' => 'CAP',
        'reference_person' => 'Referente', 'reference_email' => 'Email referente', 'reference_role' => 'Ruolo referente',
        'reference_phone' => 'Telefono referente', 'office' => 'Ufficio', 'consultant' => 'Consulente',
        'collaborator' => 'Collaboratore', 'operator' => 'Operatore', 'salient_notes' => 'Notizie salienti',
    ];

    public function plan(): array
    {
        $preview = app(InsuranceImportPreview::class);
        $summary = $preview->summary() ?? throw new RuntimeException('Lotto non disponibile.');
        $file = $preview->verifiedFile('clients.json');
        $preview->verifiedFile('source-rows.jsonl');
        $batch = hash('sha256', hash_file('sha256', $file).hash_file('sha256', $preview->verifiedFile('summary.json')));
        $clients = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $emails = array_count_values(array_filter(array_map(fn ($c) => mb_strtolower(trim($c['data']['email'] ?? '')), $clients)));
        $existing = Client::get(['id', 'name', 'registry_name_key', 'tax_code', 'vat_number', 'email']);
        $fiscals = []; $names = []; $existingEmails = [];
        foreach ($existing as $client) {
            foreach (['tax_code', 'vat_number'] as $field) if ($client->$field) $fiscals[$this->fiscal($client->$field)] = true;
            $names[$this->normalize($client->name)] = true;
            if ($client->email) $existingEmails[mb_strtolower(trim($client->email))] = true;
        }
        $entries = [];
        foreach ($clients as $candidate) {
            $data = array_intersect_key($candidate['data'], self::FIELDS);
            $issues = $candidate['issues']; $deferred = [];
            $held = $candidate['source'] !== 'clients' || !empty($candidate['conflicting_fields']) || empty($data['name']);
            $identifier = $this->fiscal($candidate['fiscal_identifier'] ?? '');
            $type = $candidate['data']['client_type'] ?? null;
            $data['client_type'] = $type === 'person' && !in_array('identificativo_fiscale_da_verificare', $issues, true) ? 'person' : null;
            $data['tax_code'] = $data['client_type'] === 'person' && strlen($identifier) === 16 ? $identifier : null;
            if ($identifier && !$data['tax_code']) $deferred['Cod. Fiscale / P. IVA originale'] = $identifier;
            foreach (self::FIELDS as $field => $label) {
                $value = $data[$field] ?? null;
                if ($value === null || $value === '') { $data[$field] = null; continue; }
                $limit = match ($field) { 'title', 'phone', 'mobile', 'fax', 'reference_phone' => 50, 'city' => 100, 'postal_code' => 10, 'province' => 5, 'salient_notes' => 10000, default => 255 };
                $rules = ['nullable', 'string', 'max:'.$limit];
                if (in_array($field, ['email','reference_email'], true)) { $value = mb_strtolower(trim($value)); $rules[] = 'email'; }
                if ($field === 'birth_date') $rules = ['nullable','date_format:Y-m-d','before_or_equal:today'];
                if ($field === 'province') $rules[] = 'regex:/^[a-zA-Z]{2}$/';
                $invalid = Validator::make([$field => $value], [$field => $rules])->fails();
                $shared = $field === 'email' && (($emails[$value] ?? 0) > 1 || isset($existingEmails[$value]));
                if ($shared && !$invalid) $issues[] = 'email_condivisa';
                if ($invalid) {
                    $deferred[$label.' originale'] = $value; $data[$field] = null;
                    $issues[] = $field === 'province' ? 'provincia_da_verificare' : $field.'_da_verificare';
                    if ($field === 'name') $held = true;
                } else $data[$field] = $field === 'province' ? mb_strtoupper($value) : $value;
            }
            if (isset($fiscals[$identifier]) || isset($names[$this->normalize($data['name'] ?? '')])) {
                $held = true; $issues[] = 'record_esistente_da_confrontare';
            }
            $entries[] = $this->entry('client', $candidate['key'], $candidate['data']['name'] ?? 'Contraente non indicato', $candidate['source'], $candidate['source_rows'], $issues, $deferred, $data, $held, $candidate);
        }
        $companies = []; $agencies = [];
        foreach ($summary['company_agencies'] as $pair) {
            if ($pair['result'] === 'esclusa' || !$pair['company'] || !$pair['agency']) continue;
            $company = $pair['company']; $agency = $pair['agency'];
            $companies[$company]['names'][] = $pair['source_company'];
            $companies[$company]['review'] = ($companies[$company]['review'] ?? false) || $pair['result'] === 'da_confermare';
            $agencies[$agency]['names'][] = $pair['source_agency'];
            $agencies[$agency]['companies'][] = $company;
        }
        foreach ($companies as $name => $source) {
            $issues = $source['review'] ? ['denominazione_compagnia_da_confermare'] : [];
            $held = SupplierCompany::get(['name'])->contains(fn ($c) => $this->normalize($c->name) === $name);
            if ($held) $issues[] = 'record_esistente_da_confrontare';
            $notes = 'Importata dalla colonna Compagnia del file Gestionale. Denominazioni originali: '.implode(', ', array_unique($source['names'])).'.';
            if ($source['review']) $notes .= ' Nome e ruolo del soggetto da verificare; nessun accorpamento eseguito.';
            $entries[] = $this->entry('company', hash('sha256', $name), $name, 'providers', [], $issues, [], ['name' => $name, 'notes' => $notes], $held, $source);
        }
        foreach ($agencies as $name => $source) {
            $source['companies'] = array_values(array_unique($source['companies'])); sort($source['companies']);
            $issues = ['nome_agenzia_da_confermare'];
            $held = SupplierAgency::get(['name'])->contains(fn ($a) => $this->normalize($a->name) === $name);
            if ($held) $issues[] = 'record_esistente_da_confrontare';
            $notes = 'Importata dalla colonna Agenzia del file Gestionale. Nomi originali: '.implode(', ', array_unique($source['names'])).'. Dati identificativi e recapiti da completare. Nessuna subagenzia dedotta.';
            $entries[] = $this->entry('agency', hash('sha256', $name), $name, 'providers', [], $issues, [], ['name' => $name, 'notes' => $notes, 'company_names' => $source['companies']], $held, $source);
        }
        return ['batch_hash' => $batch, 'entries' => $entries];
    }

    public function apply(array $plan): array
    {
        // The lock entry serializes concurrent runs even on databases without advisory locks.
        return DB::transaction(function () use ($plan) {
            DB::table('registry_import_entries')->insertOrIgnore(['kind'=>'lock','source_key'=>'registry','batch_hash'=>str_repeat('0',64),'payload_hash'=>str_repeat('0',64),'source'=>'system','source_name'=>'Registro importazione','status'=>'lock','source_rows'=>'[]','issues'=>'[]','deferred_data'=>'[]','created_at'=>now(),'updated_at'=>now()]);
            RegistryImportEntry::where('kind','lock')->lockForUpdate()->firstOrFail();
            $policiesBefore = DB::table('insurance_policies')->count();
            $report = ['batch_hash' => $plan['batch_hash'], 'created' => ['client'=>0,'agency'=>0,'company'=>0], 'held' => ['client'=>0,'agency'=>0,'company'=>0], 'already_imported'=>0];
            $companyIds = [];
            $entries = collect($plan['entries'])->sortBy(fn ($e) => ['company'=>0,'agency'=>1,'client'=>2][$e['kind']]);
            foreach ($entries as $item) {
                $entry = RegistryImportEntry::where('kind', $item['kind'])->where('source_key', $item['source_key'])->first();
                $class = ['client'=>Client::class,'agency'=>SupplierAgency::class,'company'=>SupplierCompany::class][$item['kind']];
                if (in_array($entry?->status, ['imported', 'linked'], true)) {
                    if (!hash_equals($entry->payload_hash, $item['payload_hash'])) throw new RuntimeException('La fonte contiene modifiche a record già importati. Confrontare il lotto prima di continuare.');
                    if (!$class::whereKey($entry->entity_id)->exists()) throw new RuntimeException('Una precedente anagrafica importata non è più disponibile. Verificare il registro.');
                    if ($item['kind'] === 'company') $companyIds[$item['source_name']] = $entry->entity_id;
                    $report['already_imported']++; continue;
                }
                $data = $item['data'];
                if (!$item['held'] && $item['kind'] === 'client') {
                    $duplicate = !empty($data['tax_code']) && Client::where('tax_code', $data['tax_code'])->orWhere('vat_number', $data['tax_code'])->exists();
                    if ($duplicate) { $item['held'] = true; $item['issues'][] = 'record_esistente_da_confrontare'; }
                }
                if (!$item['held'] && $item['kind'] === 'agency' && array_diff($data['company_names'], array_keys($companyIds))) {
                    $item['held'] = true; $item['issues'][] = 'compagnia_collegata_da_verificare';
                }
                if ($item['held']) {
                    $id = null; $report['held'][$item['kind']]++;
                } else {
                    if ($item['kind'] === 'client') $model = app(CreateClientAction::class)->execute($data);
                    elseif ($item['kind'] === 'company') {
                        $model = SupplierCompany::create($data); $companyIds[$item['source_name']] = $model->id;
                    } else {
                        $data['supplier_company_ids'] = array_map(fn ($name) => $companyIds[$name], $data['company_names']); unset($data['company_names']);
                        $model = app(SupplierAgencyRegistry::class)->save(new SupplierAgency, $data);
                    }
                    $id = $model->id; $report['created'][$item['kind']]++;
                }
                RegistryImportEntry::updateOrCreate(['kind'=>$item['kind'],'source_key'=>$item['source_key']], [
                    'batch_hash'=>$plan['batch_hash'], 'payload_hash'=>$item['payload_hash'], 'source'=>$item['source'],
                    'source_name'=>$item['source_name'], 'status'=>$item['held'] ? 'held' : 'imported', 'entity_id'=>$id,
                    'source_rows'=>$item['source_rows'], 'issues'=>array_values(array_unique($item['issues'])), 'deferred_data'=>$item['deferred_data'],
                ]);
            }
            if (DB::table('insurance_policies')->count() !== $policiesBefore) throw new RuntimeException('Controllo esclusione polizze non superato.');
            $report['policies_unchanged'] = true;
            return $report;
        });
    }

    private function entry(string $kind, string $key, string $name, string $source, array $rows, array $issues, array $deferred, array $data, bool $held, array $original): array
    {
        return ['kind'=>$kind,'source_key'=>$key,'source_name'=>$name,'source'=>$source,'source_rows'=>$rows,'issues'=>array_values(array_unique($issues)), 'deferred_data'=>$deferred,'data'=>$data,'held'=>$held,'payload_hash'=>hash('sha256',json_encode($original,JSON_THROW_ON_ERROR))];
    }

    private function normalize(string $value): string { return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($value))); }
    private function fiscal(string $value): string { return mb_strtoupper(preg_replace('/\s+/u', '', $value)); }
}
