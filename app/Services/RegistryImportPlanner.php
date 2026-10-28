<?php

namespace App\Services;

use App\Models\{InsuranceBranch, InsuranceSubbranch, SupplierAgency, SupplierCompany};
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegistryImportPlanner
{
    public function headers(array $sheet): array
    {
        $headers = [];
        foreach (($sheet['rows'][0]['cells'] ?? []) as $index => $cell) $headers[$index] = trim($cell['value']);
        return $headers;
    }

    public function plan(array $batch, int $sheetIndex, array $mapping): array
    {
        $sheet = $batch['sheets'][$sheetIndex] ?? null;
        if (!$sheet || count($sheet['rows']) < 2) $this->fail('sheet', 'Scegli un foglio con intestazioni e dati.');
        $headers = $this->headers($sheet);
        $fields = RegistryImportSchema::fields($batch['kind']);
        $mapping = array_filter($mapping, fn ($value) => $value !== null && $value !== '');
        if (array_diff(array_keys($mapping), array_keys($fields))) $this->fail('mapping', 'Sono presenti campi non supportati.');
        foreach ($fields as $field => $definition) {
            if (($definition['required'] ?? false) && !array_key_exists($field, $mapping)) $this->fail('mapping.'.$field, 'Associa la colonna '.$definition['label'].'.');
        }
        foreach ($mapping as $field => $index) {
            if (filter_var($index, FILTER_VALIDATE_INT) === false || !array_key_exists((int) $index, $headers) || $headers[(int) $index] === '') $this->fail('mapping.'.$field, 'Scegli una colonna con intestazione.');
            $mapping[$field] = (int) $index;
        }
        if (count($mapping) !== count(array_unique($mapping))) $this->fail('mapping', 'Ogni colonna può essere associata a un solo campo.');
        ksort($mapping);
        $companies = SupplierCompany::orderBy('id')->get();
        $branches = InsuranceBranch::orderBy('id')->get();
        $records = match ($batch['kind']) {
            'company' => $companies, 'branch' => $branches,
            'agency' => SupplierAgency::with('companies')->orderBy('id')->get(),
            'subbranch' => InsuranceSubbranch::orderBy('id')->get(),
        };
        $rows = [];
        foreach (array_slice($sheet['rows'], 1) as $source) {
            $values = []; $messages = [];
            foreach ($mapping as $field => $index) {
                $cell = $source['cells'][$index] ?? ['value' => '', 'type' => 'text'];
                $value = trim($cell['value']);
                if (in_array($cell['type'], ['formula', 'error'])) $messages[] = $fields[$field]['label'].': sostituisci formule o errori con un valore di testo.';
                if ($value !== '' && ($fields[$field]['identifier'] ?? false) && $cell['type'] === 'number') $messages[] = $fields[$field]['label'].': la cella Excel è numerica. Controlla gli zeri iniziali e salvala come testo.';
                if (($fields[$field]['email'] ?? false)) $value = mb_strtolower($value);
                if (in_array($field, ['tax_code', 'vat_number', 'province'])) $value = mb_strtoupper($value);
                if (in_array($field, ['name', 'branch'])) $value = preg_replace('/\s+/u', ' ', $value);
                $values[$field] = $value === '' ? null : $value;
            }
            if (!array_filter($values, fn ($v) => $v !== null) && !$messages) continue;
            $supplied = array_keys(array_filter($values, fn ($v) => $v !== null));
            $values['status'] = match (mb_strtolower($values['status'] ?? 'active')) {
                'active', 'attivo', 'attiva', 'attive', 'attivi' => 'active', 'inactive', 'inattivo', 'inattiva', 'inattive', 'inattivi' => 'inactive', default => $values['status'],
            };
            $rules = [];
            foreach ($fields as $field => $definition) $rules[$field] = [($definition['required'] ?? false) ? 'required' : 'nullable', 'string', 'max:'.$definition['max'], ...(($definition['email'] ?? false) ? ['email:rfc'] : [])];
            $rules['status'][] = 'in:active,inactive';
            $validator = Validator::make($values, $rules, ['required' => 'Il campo :attribute è obbligatorio.', 'string' => 'Il campo :attribute deve contenere testo.', 'max' => 'Il campo :attribute supera :max caratteri.', 'email' => 'Il campo :attribute deve contenere un indirizzo email valido.', 'in' => 'Il campo :attribute deve essere Attivo oppure Inattivo.'], array_map(fn ($f) => $f['label'], $fields));
            $messages = array_merge($messages, $validator->errors()->all());
            $links = [];
            $data = $values;
            if ($batch['kind'] === 'agency') {
                $names = array_values(array_unique(array_filter(array_map(RegistryImportSchema::key(...), explode('|', $values['companies'] ?? '')))));
                $ids = [];
                if (count($names) > 100) $messages[] = 'Sono ammesse al massimo 100 compagnie per agenzia.';
                foreach ($names as $name) {
                    $matches = $companies->filter(fn ($r) => RegistryImportSchema::key($r->name) === $name);
                    if ($matches->count() !== 1) { $messages[] = 'Compagnia «'.$name.'»: '.($matches->isEmpty() ? 'non trovata. Importala prima.' : 'nome ambiguo, correggi il catalogo prima.'); continue; }
                    $company = $matches->first();
                    $ids[] = (int) $company->id;
                    $links[] = ['id' => $company->id, 'name' => $company->name, 'status' => $company->status];
                }
                sort($ids);
                $data['supplier_company_ids'] = $ids;
                unset($data['companies']);
            }
            if ($batch['kind'] === 'subbranch') {
                $matches = $branches->filter(fn ($r) => RegistryImportSchema::key($r->name) === RegistryImportSchema::key($values['branch'] ?? ''));
                if ($matches->count() !== 1) $messages[] = 'Ramo principale '.($matches->isEmpty() ? 'non trovato. Importalo prima.' : 'ambiguo: correggi il catalogo prima.');
                else {
                    $branch = $matches->first();
                    $data['insurance_branch_id'] = $branch->id;
                    $links[] = ['id' => $branch->id, 'name' => $branch->name, 'status' => $branch->status];
                    if ($branch->status !== 'active' && $data['status'] === 'active') $messages[] = 'Il ramo principale è inattivo: riattivalo oppure importa il sottoramo come inattivo.';
                }
                unset($data['branch']);
            }
            if (in_array($batch['kind'], ['branch', 'subbranch'])) $data['needs_review'] = false;
            $identity = RegistryImportSchema::key($values['name'] ?? '');
            if ($batch['kind'] === 'agency') $identity .= '|'.RegistryImportSchema::key($values['agency_code'] ?? '');
            if ($batch['kind'] === 'subbranch') $identity .= '|'.($data['insurance_branch_id'] ?? RegistryImportSchema::key($values['branch'] ?? ''));
            $matches = $records->filter(function ($record) use ($batch, $values, $data) {
                if (RegistryImportSchema::key($record->name) !== RegistryImportSchema::key($values['name'] ?? '')) return false;
                return match ($batch['kind']) {
                    'agency' => RegistryImportSchema::key($record->agency_code) === RegistryImportSchema::key($values['agency_code'] ?? ''),
                    'subbranch' => (int) $record->insurance_branch_id === (int) ($data['insurance_branch_id'] ?? 0),
                    default => true,
                };
            });
            $state = 'new'; $matched = [];
            if ($matches->count() > 1) $messages[] = 'Più voci esistenti corrispondono: occorre correggere il catalogo prima di importare.';
            elseif ($matches->count() === 1) {
                $record = $matches->first();
                $matched = ['id' => $record->id, 'attributes' => $record->only(array_keys($data))];
                $differences = [];
                foreach ($supplied as $field) {
                    if (in_array($field, ['name', 'branch', 'companies', 'agency_code'])) continue;
                    if (RegistryImportSchema::key((string) $record->$field) !== RegistryImportSchema::key((string) ($data[$field] ?? ''))) $differences[] = $fields[$field]['label'];
                }
                if ($batch['kind'] === 'agency') {
                    $existingIds = $record->companies->modelKeys(); sort($existingIds);
                    $matched['companies'] = $existingIds;
                    if ($existingIds !== $data['supplier_company_ids']) $differences[] = 'Compagnie collegate';
                }
                if ($differences) $messages[] = 'Voce già presente con dati diversi: '.implode(', ', $differences).'. Nessuna modifica automatica.';
                else { $state = 'exists'; $messages[] = 'Già presente: sarà conservata senza modifiche.'; }
            }
            if ($batch['kind'] === 'agency' && $matches->isEmpty()) {
                $similar = $records->filter(fn ($r) => (RegistryImportSchema::key($r->name) === RegistryImportSchema::key($values['name'] ?? '') && (blank($r->agency_code) || blank($values['agency_code'] ?? null)))
                    || (filled($values['vat_number'] ?? null) && RegistryImportSchema::key($r->vat_number) === RegistryImportSchema::key($values['vat_number']))
                    || (filled($values['tax_code'] ?? null) && RegistryImportSchema::key($r->tax_code) === RegistryImportSchema::key($values['tax_code'])));
                if ($similar->isNotEmpty()) $messages[] = 'Possibile duplicato di agenzia per nome, partita IVA o codice fiscale: verifica prima di creare una nuova scheda.';
            }
            if ($messages && !($state === 'exists' && count($messages) === 1)) $state = 'review';
            $rows[] = ['row' => $source['number'], 'identity' => $identity, 'values' => $values, 'data' => $data, 'links' => $links, 'matched' => $matched, 'state' => $state, 'messages' => $messages];
        }
        if (!$rows) $this->fail('mapping', 'Le colonne associate non contengono righe da importare.');
        if (count($rows) > RegistryImportReader::MAX_ROWS) $this->fail('sheet', 'Sono ammesse al massimo 2.000 righe per importazione.');
        $groups = [];
        foreach ($rows as $i => $row) $groups[$row['identity']][] = $i;
        foreach ($groups as $indices) {
            if (count($indices) < 2) continue;
            $signatures = array_unique(array_map(function ($i) use ($rows) {
                $data = $rows[$i]['data'];
                foreach (['name', 'agency_code'] as $field) if (isset($data[$field])) $data[$field] = RegistryImportSchema::key($data[$field]);
                return json_encode($data);
            }, $indices));
            if (count($signatures) > 1) {
                foreach ($indices as $i) { $rows[$i]['state'] = 'review'; $rows[$i]['messages'][] = 'Stesso nome/codice ripetuto nel file con dati diversi (righe '.implode(', ', array_map(fn ($n) => $rows[$n]['row'], $indices)).'). Mantieni una sola versione corretta.'; }
            } else foreach (array_slice($indices, 1) as $i) {
                if ($rows[$i]['state'] === 'review') continue;
                $rows[$i]['state'] = 'duplicate'; $rows[$i]['messages'][] = 'Ripete la riga '.$rows[$indices[0]]['row'].': non sarà creata una seconda voce.';
            }
        }
        /** A fiscal identity repeated under different agency names is ambiguous even within the same upload. */
        if ($batch['kind'] === 'agency') foreach (['vat_number', 'tax_code'] as $field) {
            $fiscal = [];
            foreach ($rows as $i => $row) if (filled($row['values'][$field] ?? null)) $fiscal[RegistryImportSchema::key($row['values'][$field])][] = $i;
            foreach ($fiscal as $indices) if (count(array_unique(array_map(fn ($i) => $rows[$i]['identity'], $indices))) > 1) foreach ($indices as $i) {
                $rows[$i]['state'] = 'review'; $rows[$i]['messages'][] = $fields[$field]['label'].' ripetuto nel file per agenzie diverse: verifica le schede.';
            }
        }
        $counts = array_fill_keys(array_keys(RegistryImportSchema::STATES), 0);
        foreach ($rows as &$row) { $row['messages'] = array_values(array_unique($row['messages'])); $counts[$row['state']]++; }
        unset($row);
        return ['sheet' => $sheetIndex, 'mapping' => $mapping, 'rows' => $rows, 'counts' => $counts, 'hash' => hash('sha256', json_encode([$sheetIndex, $mapping, $rows], JSON_THROW_ON_ERROR))];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
