<?php

namespace App\Services;

use App\Models\{Client, RegistryImportEntry};
use App\Support\ImportFiscalIdentifier;
use RuntimeException;

class RegistryImportComparison
{
    private const CLIENT_FIELDS = ['Titolo', 'Contraente', 'Cod. Fiscale / P. IVA', 'Data Nascita', 'Luogo Nascita', 'Telefono', 'Cellulare', 'FAX', 'E-Mail', 'Target', 'Provincia', 'Città', 'Indirizzo', 'CAP', 'Referente', 'E-Mail Referente', 'Ruolo Referente', 'Telefono Referente', 'Ufficio', 'Consulente', 'Collaboratore', 'Operatore', 'Notizie Salienti'];

    public function forEntry(RegistryImportEntry $entry): array
    {
        $preview = app(InsuranceImportPreview::class);
        $summary = $preview->summary();
        $candidates = collect(json_decode(file_get_contents($preview->verifiedFile('clients.json')), true, 512, JSON_THROW_ON_ERROR))->keyBy('key');
        $candidate = $candidates->get($entry->source_key);
        if (!$candidate || !hash_equals($entry->payload_hash, hash('sha256', json_encode($candidate, JSON_THROW_ON_ERROR)))) throw new RuntimeException('La fonte è cambiata dopo il caricamento.');
        $fields = $entry->source === 'clients' ? self::CLIENT_FIELDS : ['Titolo', 'Contraente', 'Cod. Fiscale / P. IVA'];
        $rows = [];
        $file = fopen($preview->verifiedFile('source-rows.jsonl'), 'r');
        try {
            while (($line = fgets($file)) !== false) {
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if ($row['source'] === $entry->source && in_array($row['row'], $candidate['source_rows'], true)) $rows[$row['row']] = $row['values'];
            }
        } finally { fclose($file); }
        if (count($rows) !== count($candidate['source_rows'])) throw new RuntimeException('Righe originali incomplete.');
        ksort($rows);
        $comparison = [];
        foreach ($fields as $field) {
            $values = array_map(fn ($row) => (string) ($row[$field] ?? ''), $rows);
            if (count(array_filter($values, fn ($value) => $value !== '')) === 0) continue;
            $comparison[] = ['field' => $field, 'values' => $values, 'different' => count(array_unique($values)) > 1];
        }
        usort($comparison, fn ($a, $b) => $b['different'] <=> $a['different']);
        $matches = [];
        if ($entry->status === 'held' && $entry->source === 'policies') {
            $name = HeldRegistryResolver::nameKey($candidate['data']['name']);
            $fiscal = $candidate['fiscal_identifier'];
            foreach (Client::current()->with('importEntry')->get(['id', 'name', 'tax_code', 'vat_number']) as $client) {
                $sourceFiscal = $client->importEntry ? ($candidates->get($client->importEntry->source_key)['fiscal_identifier'] ?? '') : '';
                $identifiers = array_values(array_unique(array_filter([$client->tax_code, $client->vat_number, $sourceFiscal])));
                $clientName = HeldRegistryResolver::nameKey($client->name);
                similar_text($name, $clientName, $similarity);
                $closeFiscal = collect($identifiers)->contains(fn ($value) => $fiscal && abs(strlen($value) - strlen($fiscal)) <= 1 && levenshtein($fiscal, $value) <= 2);
                if ($clientName !== $name && $similarity < 92 && !$closeFiscal) continue;
                $matches[] = ['id' => $client->id, 'name' => $client->name, 'identifiers' => $identifiers,
                    'source_rows' => $client->importEntry?->source_rows ?? [],
                    'reason' => $clientName === $name ? 'Nome coincidente; identificativo da confrontare' : 'Nome o identificativo simile: possibile corrispondenza, da confermare'];
            }
        }
        $source = collect($summary['sources'] ?? [])->first(fn ($source) => ($source['source'] ?? $source['kind'] ?? null) === $entry->source);
        return ['entry' => $entry, 'candidate' => $candidate, 'rows' => $rows, 'comparison' => $comparison, 'matches' => $matches,
            'fiscalValid' => ImportFiscalIdentifier::valid($candidate['fiscal_identifier']),
            'sourceFile' => $source['file'] ?? ($entry->source === 'clients' ? 'File Anagrafica, foglio Dati' : 'File Gestionale, soli dati del contraente')];
    }
}
