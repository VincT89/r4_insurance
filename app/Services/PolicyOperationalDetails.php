<?php

namespace App\Services;

use App\Models\{AuditLog, InsurancePolicy, InsurancePolicyImport, User};
use Illuminate\Support\Facades\DB;

class PolicyOperationalDetails
{
    public const DECISION = 'Stati confermati dal cliente: Archivio indica polizza attiva; OLD indica polizza scaduta e non pagata.';

    public function values(array $rows): array
    {
        $data = [];
        $same = function (string $field) use ($rows): ?string {
            $values = collect($rows)->map(fn ($row) => trim((string)($row['values'][$field] ?? '')))->unique()->values();
            return $values->count() === 1 && $values[0] !== '' ? $values[0] : null;
        };
        $status = $same('Polizze');
        if ($status === 'Archivio') $data['status'] = 'active';
        if ($status === 'OLD') $data += ['status' => 'expired', 'payment_status' => 'unpaid'];
        // Un metodo indicato non dimostra che il pagamento sia stato effettuato.
        if (($method = $same('Tipo Pagamento')) && !in_array($method, ['Sospeso', 'Abbuoni'], true)) $data['payment_method'] = $method;
        if (($frequency = $same('Frazionamento')) && in_array($frequency, InsurancePolicy::PAYMENT_FREQUENCIES, true)) $data['payment_frequency'] = $frequency;
        if (($renewal = $same('Tacito Rinnovo')) !== null) {
            if (in_array(mb_strtolower($renewal), ['si','sì','s','true','1'], true)) $data['tacit_renewal'] = true;
            elseif (in_array(mb_strtolower($renewal), ['no','n','false','0'], true)) $data['tacit_renewal'] = false;
        }
        if ($amount = $same('Premio Rata')) {
            $normalized = str_contains($amount, ',') ? str_replace(',', '.', str_replace('.', '', $amount)) : $amount;
            if (preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized) && (float)$normalized <= 9999999999.99) $data['installment_premium'] = number_format((float)$normalized, 2, '.', '');
        }
        return $data;
    }

    public function apply(User $actor): array
    {
        if (!$actor->isAdmin() || $actor->status !== 'active') throw new \RuntimeException('Richiesto amministratore attivo.');
        return DB::transaction(function () use ($actor) {
            $report = ['updated' => 0, 'already_applied' => 0, 'mixed_status' => 0, 'operator_changes_preserved' => 0];
            $entries = InsurancePolicyImport::where('status', 'imported')->orderBy('id')->lockForUpdate()->get();
            $policies = InsurancePolicy::whereIn('id', $entries->pluck('insurance_policy_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $already = AuditLog::where('action', 'policy_operational_details')->where('auditable_type', (new InsurancePolicy)->getMorphClass())->pluck('auditable_id')->flip();
            foreach ($entries as $entry) {
                if (!hash_equals($entry->payload_hash, hash('sha256', json_encode($entry->source_payload, JSON_THROW_ON_ERROR)))) throw new \RuntimeException('La fonte conservata è stata modificata.');
                $policy = $policies[$entry->insurance_policy_id] ?? null;
                if (!$policy || (int)$policy->client_id !== (int)$entry->client_id) throw new \RuntimeException('Collegamento polizza non coerente.');
                if ($already->has($policy->id)) { $report['already_applied']++; continue; }
                if ($policy->status !== 'review') { $report['operator_changes_preserved']++; continue; }
                $data = $this->values($entry->source_payload['rows'] ?? []);
                if (!isset($data['status'])) $report['mixed_status']++;
                // I nuovi campi compilati da un operatore non vengono sovrascritti.
                foreach ($data as $field => $value) if ($field !== 'status' && $policy->$field !== null) unset($data[$field]);
                $old = array_intersect_key($policy->getAttributes(), $data);
                if ($data) InsurancePolicy::withoutEvents(fn () => $policy->update($data));
                app(AuditLogService::class)->log('policy_operational_details', $policy, $old, $data, self::DECISION, $actor->id);
                $report['updated']++;
            }
            return $report;
        });
    }
}
