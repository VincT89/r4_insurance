<?php

namespace App\Services;

use App\Models\{Client, InsuranceBranch, InsurancePolicyImport, RegistryImportEntry, SupplierAgency, SupplierCompany, User};
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovedEquivalentPolicyImport
{
    public const RULE = 'same_contract_and_notes_v1';
    public const ACTION = 'policy_import_consolidated';
    public const FIELDS = ['Contraente', 'Cod. Fiscale / P. IVA', 'Compagnia', 'Agenzia', 'Numero Polizza', 'Ramo', 'Sottoramo',
        'Interesse Assic.', 'Efetto', 'Scadenza', 'Premio annuale', 'Premio Rata', 'Frazionamento', 'Tacito Rinnovo', 'Note'];

    public function preview(array $approval): array
    {
        $groups = $approval['groups'] ?? [];
        if (!$groups || count($groups) !== count(array_unique(array_column($groups, 'entry_id')))
            || count($groups) !== count(array_unique(array_column($groups, 'source_key')))) throw new RuntimeException('Elenco approvato vuoto o duplicato.');
        $plan = app(PolicyExcelImporter::class)->plan();
        if (!hash_equals($plan['batch_hash'], $approval['batch_hash'] ?? '')) throw new RuntimeException('Il lotto è cambiato dopo l’approvazione.');
        $candidates = collect($plan['entries'])->keyBy('source_key');
        $stored = InsurancePolicyImport::with(['policy', 'auditLogs' => fn ($q) => $q->where('action', self::ACTION)])
            ->whereIn('id', array_column($groups, 'entry_id'))->get()->keyBy('id');
        $entries = []; $skipped = 0; $sourceRows = 0;
        foreach ($groups as $approved) {
            $entry = $stored->get($approved['entry_id']);
            if (!$entry || $entry->source_key !== $approved['source_key'] || !hash_equals($entry->payload_hash, $approved['payload_hash'])
                || $entry->source_rows !== $approved['source_rows']) throw new RuntimeException('Un gruppo non corrisponde all’elenco approvato.');
            $candidate = $candidates->get($entry->source_key) ?? throw new RuntimeException('Gruppo assente dalla fonte.');
            if ($entry->status === 'imported') {
                $trace = $entry->auditLogs->first(fn ($log) => ($log->new_values['resolution_rule'] ?? null) === self::RULE
                    && ($log->new_values['payload_hash'] ?? null) === $entry->payload_hash
                    && ($log->new_values['source_rows'] ?? null) === $entry->source_rows
                    && (int) ($log->new_values['insurance_policy_id'] ?? 0) === (int) $entry->insurance_policy_id);
                if (!$entry->policy || !$trace || empty($candidate['already_imported'])) throw new RuntimeException('Gruppo già importato senza una risoluzione corrispondente.');
                $skipped++;
                continue;
            }
            if ($entry->status !== 'held' || $entry->insurance_policy_id || $entry->issues !== ['numero_polizza_ripetuto']
                || ($candidate['issues'] ?? []) !== ['numero_polizza_ripetuto'] || $candidate['already_imported']) throw new RuntimeException('Il gruppo presenta altri rilievi: nessuna importazione.');
            if (!hash_equals($entry->payload_hash, $candidate['payload_hash']) || $entry->source_payload != $candidate['source_payload']
                || (int) $entry->client_id !== (int) $candidate['client_id']) throw new RuntimeException('La fonte o il collegamento del gruppo sono cambiati.');
            $rows = $candidate['source_payload']['rows'];
            if (count($rows) < 2) throw new RuntimeException('Il gruppo non contiene righe ripetute.');
            foreach ($rows as $row) {
                foreach (self::FIELDS as $field) {
                    if (!array_key_exists($field, $row['values']) || !array_key_exists($field, $rows[0]['values'])
                        || $row['values'][$field] !== $rows[0]['values'][$field]) throw new RuntimeException('Dati non identici tra le righe: '.$field.'.');
                }
            }
            $statuses = array_values(array_unique(array_map(fn ($row) => (string) ($row['values']['Polizze'] ?? ''), $rows)));
            $statusText = implode(' / ', array_filter($statuses, fn ($value) => $value !== ''));
            if (mb_strlen($statusText) > 100) throw new RuntimeException('Etichette originali troppo lunghe.');
            $entries[] = ['entry' => $entry, 'data' => $candidate['data'], 'statuses' => $statuses, 'source_status' => $statusText];
            $sourceRows += count($rows);
        }
        return ['entries' => $entries, 'ready' => count($entries), 'already_imported' => $skipped, 'source_rows' => $sourceRows];
    }

    public function apply(array $approval, User $actor): array
    {
        if (!$actor->canManageSystem() || $actor->status !== 'active') throw new RuntimeException('Serve un amministratore attivo.');
        if (($approval['state'] ?? null) !== 'approved') throw new RuntimeException('L’elenco non è stato approvato.');
        return DB::transaction(function () use ($approval, $actor) {
            RegistryImportEntry::where('kind', 'lock')->where('source_key', 'registry')->lockForUpdate()->firstOrFail();
            InsuranceBranch::orderBy('id')->lockForUpdate()->get();
            SupplierCompany::orderBy('id')->lockForUpdate()->get();
            SupplierAgency::orderBy('id')->lockForUpdate()->get();
            Client::orderBy('id')->lockForUpdate()->get(['id']);
            InsurancePolicyImport::whereIn('id', array_column($approval['groups'] ?? [], 'entry_id'))->orderBy('id')->lockForUpdate()->get(['id']);
            $plan = $this->preview($approval);
            $report = ['created' => 0, 'already_imported' => $plan['already_imported'], 'source_rows' => $plan['source_rows'], 'policy_ids' => []];
            foreach ($plan['entries'] as $item) {
                $entry = $item['entry'];
                $policy = Client::current()->findOrFail($entry->client_id)->policies()->create($item['data']);
                $old = $entry->only(['status', 'issues']);
                $entry->update(['insurance_policy_id' => $policy->id, 'status' => 'imported', 'issues' => [], 'source_status' => $item['source_status']]);
                app(AuditLogService::class)->log(self::ACTION, $entry, $old, [
                    'insurance_policy_id' => $policy->id, 'status' => 'imported', 'resolution_rule' => self::RULE,
                    'source_key' => $entry->source_key, 'payload_hash' => $entry->payload_hash, 'source_rows' => $entry->source_rows,
                    'compared_fields' => self::FIELDS, 'source_statuses' => $item['statuses'],
                ], 'Accorpamento approvato: '.count($entry->source_rows).' righe con dati contrattuali e note identici nella polizza '.$policy->number.'. Stato Da verificare; righe originali conservate integralmente.', $actor->id);
                $report['created']++;
                $report['policy_ids'][] = $policy->id;
            }
            return $report;
        });
    }
}
