<?php

namespace App\Services;

use App\Actions\Clients\CreateClientAction;
use App\Models\{Client, RegistryImportEntry};
use App\Support\ImportFiscalIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class HeldRegistryResolver
{
    public static function nameKey(string $name): string
    {
        return preg_replace('/[^A-Z0-9]/', '', Str::ascii(mb_strtoupper($name)));
    }

    public function apply(array $plan): array
    {
        if (($plan['version'] ?? null) !== 1 || empty($plan['operations'])) throw new RuntimeException('Piano di risoluzione non valido.');
        $preview = app(InsuranceImportPreview::class);
        foreach (['clients.json', 'summary.json', 'source-rows.jsonl'] as $name) {
            if (!hash_equals($plan['source_hashes'][$name] ?? '', hash_file('sha256', $preview->verifiedFile($name)))) throw new RuntimeException('La fonte è cambiata dopo il confronto.');
        }
        $candidates = collect(json_decode(file_get_contents($preview->verifiedFile('clients.json')), true, 512, JSON_THROW_ON_ERROR))->keyBy('key');
        $sourceRows = [];
        $file = fopen($preview->verifiedFile('source-rows.jsonl'), 'r');
        try {
            while (($line = fgets($file)) !== false) {
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if ($row['source'] === 'policies') $sourceRows[$row['row']] = $row['values'];
            }
        } finally { fclose($file); }

        return DB::transaction(function () use ($plan, $candidates, $sourceRows) {
            RegistryImportEntry::where('kind', 'lock')->where('source_key', 'registry')->lockForUpdate()->firstOrFail();
            $operations = collect($plan['operations']);
            if ($operations->pluck('entry_id')->unique()->count() !== $operations->count()) throw new RuntimeException('Risoluzioni duplicate nel piano.');
            $entries = RegistryImportEntry::whereIn('id', $operations->pluck('entry_id'))->lockForUpdate()->get()->keyBy('id');
            $pending = [];
            $report = ['linked' => 0, 'created' => 0, 'already_resolved' => 0];
            foreach ($operations as $op) {
                $entry = $entries->get($op['entry_id']);
                $candidate = $candidates->get($op['source_key']);
                if (!$entry || !$candidate || $entry->kind !== 'client' || $entry->source !== 'policies' || $candidate['source'] !== 'policies'
                    || !in_array($op['action'], ['link', 'create'], true) || $entry->source_key !== $op['source_key']
                    || !hash_equals($entry->payload_hash, $op['payload_hash'])
                    || !hash_equals($entry->payload_hash, hash('sha256', json_encode($candidate, JSON_THROW_ON_ERROR)))) {
                    throw new RuntimeException('La riga da risolvere non corrisponde alla fonte verificata.');
                }
                $status = $op['action'] === 'link' ? 'linked' : 'imported';
                if ($entry->status === $status) {
                    if (!Client::current()->whereKey($entry->entity_id)->exists() || ($op['action'] === 'link' && $entry->entity_id != $op['target_id'])) throw new RuntimeException('Il precedente collegamento non è più disponibile.');
                    $report['already_resolved']++;
                    continue;
                }
                if ($entry->status !== 'held' || $entry->entity_id !== null) throw new RuntimeException('La riga è stata risolta in un altro modo.');
                foreach ($op['expected_entry'] as $field => $expected) {
                    $actual = $entry->getRawOriginal($field);
                    if (in_array($field, ['source_rows', 'issues', 'deferred_data'], true)) {
                        $actual = json_decode($actual, true, 512, JSON_THROW_ON_ERROR);
                        $expected = is_string($expected) ? json_decode($expected, true, 512, JSON_THROW_ON_ERROR) : $expected;
                    }
                    if ($actual !== $expected) throw new RuntimeException('Il registro è cambiato dopo il confronto.');
                }
                $pending[] = [$entry, $candidate, $op];
            }
            if ($pending) {
                // Abort on changes since review, including edits to possible matching clients.
                $expected = collect($plan['expected_clients'])->sortBy('id')->values()->all();
                if (!$expected) throw new RuntimeException('Manca il confronto con le anagrafiche esistenti.');
                $columns = array_keys($expected[0]);
                $current = DB::table('clients')->whereNull('merged_into_id')->orderBy('id')->lockForUpdate()->get($columns)->map(fn ($r) => (array) $r)->all();
                if ($current !== $expected) throw new RuntimeException('Le anagrafiche sono cambiate dopo il confronto: aggiornare la verifica.');
            }
            $policiesBefore = $this->fingerprint('insurance_policies');
            $usersBefore = $this->fingerprint('users');
            foreach ($pending as [$entry, $candidate, $op]) {
                $identifier = $candidate['fiscal_identifier'];
                $rows = [];
                foreach ($candidate['source_rows'] as $row) {
                    if (!isset($sourceRows[$row])) throw new RuntimeException('Riga originale non disponibile.');
                    $values = $sourceRows[$row];
                    $rowIdentifier = mb_strtoupper(preg_replace('/\s+/u', '', (string) ($values['Cod. Fiscale / P. IVA'] ?? '')));
                    if ($rowIdentifier !== $identifier) throw new RuntimeException('Identificativo non coerente nelle righe originali.');
                    $rows[] = $values;
                }
                $names = array_unique(array_map(fn ($row) => self::nameKey((string) $row['Contraente']), $rows));
                if (count($names) !== 1 || !$names[0]) throw new RuntimeException('Nomi discordanti: serve una scelta esplicita.');
                if ($op['action'] === 'link') {
                    $client = Client::current()->findOrFail($op['target_id']);
                    $original = $client->importEntry;
                    $target = $original ? $candidates->get($original->source_key) : null;
                    if (!$target || !hash_equals($original->payload_hash, hash('sha256', json_encode($target, JSON_THROW_ON_ERROR)))
                        || !preg_match('/^[0-9]{10}$/D', $identifier) || $target['fiscal_identifier'] !== '0'.$identifier
                        || !ImportFiscalIdentifier::valid($target['fiscal_identifier']) || self::nameKey($client->name) !== $names[0]
                        || self::nameKey($target['data']['name']) !== $names[0]) {
                        throw new RuntimeException('Il collegamento non soddisfa il confronto autorizzato di nome e zero iniziale.');
                    }
                    $entry->status = 'linked';
                    $entry->issues = [];
                    $entry->deferred_data = array_merge($entry->deferred_data ?? [], [
                        'Criterio di collegamento' => 'Nome coincidente; identificativo della fonte privo dello zero iniziale presente in Anagrafica. Collegamento autorizzato, scheda cliente non modificata.',
                    ]);
                    app(AuditLogService::class)->log('import_linked', $client, description: 'Collegata la voce di importazione '.$entry->id.' alla scheda esistente: nome coincidente e zero iniziale mancante nella fonte. Nessun dato anagrafico sovrascritto.');
                    $report['linked']++;
                } else {
                    if (!ImportFiscalIdentifier::valid($identifier)) throw new RuntimeException('Identificativo non valido: creazione sospesa.');
                    $name = $candidate['data']['name'];
                    if (self::nameKey($name) !== $names[0] || mb_strlen($name) > 255) throw new RuntimeException('Nome non coerente con la fonte.');
                    $duplicateName = Client::current()->get(['name'])->contains(fn ($c) => self::nameKey($c->name) === $names[0]);
                    $duplicateFiscal = Client::where('tax_code', $identifier)->orWhere('vat_number', $identifier)->exists()
                        || RegistryImportEntry::where('kind', 'client')->whereIn('status', ['imported', 'linked'])
                            ->get(['deferred_data'])->contains(fn ($e) => ($e->deferred_data['Cod. Fiscale / P. IVA originale'] ?? null) === $identifier);
                    if ($duplicateName || $duplicateFiscal) throw new RuntimeException('È comparsa una possibile scheda corrispondente.');
                    $titles = array_values(array_unique(array_filter(array_map(fn ($row) => trim((string) ($row['Titolo'] ?? '')), $rows))));
                    $title = count(array_unique(array_map('mb_strtoupper', $titles))) === 1 && mb_strlen($titles[0]) <= 50 ? $titles[0] : null;
                    $client = app(CreateClientAction::class)->execute([
                        'name' => $name, 'title' => $title,
                        'client_type' => strlen($identifier) === 16 ? 'person' : null,
                        'tax_code' => strlen($identifier) === 16 ? $identifier : null,
                    ]);
                    $issues = array_values(array_diff($candidate['issues'], ['cliente_assente_in_anagrafica', 'identificativo_fiscale_da_verificare']));
                    $issues[] = 'anagrafica_incompleta';
                    $deferred = $entry->deferred_data ?? [];
                    if (strlen($identifier) === 11) {
                        $issues[] = 'distinguere_codice_fiscale_e_partita_iva';
                        $deferred['Cod. Fiscale / P. IVA originale'] = $identifier;
                    }
                    if ($title === null && $titles) {
                        $issues[] = 'titolo_da_verificare';
                        $deferred['Titoli presenti nell’Excel'] = implode(' / ', $titles);
                    }
                    $entry->status = 'imported';
                    $entry->issues = array_values(array_unique($issues));
                    $entry->deferred_data = $deferred;
                    $report['created']++;
                }
                $entry->entity_id = $client->id;
                $entry->save();
            }
            if ($policiesBefore !== $this->fingerprint('insurance_policies') || $usersBefore !== $this->fingerprint('users')) throw new RuntimeException('Controllo di integrità di polizze e utenti non superato.');
            $report['policies_unchanged'] = $report['users_unchanged'] = true;
            $report['clients'] = Client::current()->count();
            $report['remaining_held'] = RegistryImportEntry::where('kind', 'client')->where('status', 'held')->count();
            return $report;
        });
    }

    private function fingerprint(string $table): string
    {
        return hash('sha256', json_encode(DB::table($table)->orderBy('id')->get()->all(), JSON_THROW_ON_ERROR));
    }
}
