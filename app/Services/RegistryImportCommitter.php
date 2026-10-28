<?php

namespace App\Services;

use App\Models\{AuditLog, InsuranceBranch, InsuranceSubbranch, SupplierAgency, SupplierCompany, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistryImportCommitter
{
    public function confirm(array $batch, int $userId, string $hash): array
    {
        return DB::transaction(function () use ($batch, $userId, $hash) {
            /** Serialize confirmations across administrators and keep a durable receipt in the same transaction as the new records. */
            User::orderBy('id')->lockForUpdate()->firstOrFail();
            $description = 'Importazione anagrafiche '.$batch['id'];
            $receipt = AuditLog::where('action', 'registry_import_completed')->where('user_id', $userId)->where('description', $description)->first();
            if ($receipt) return $receipt->new_values;
            if (!isset($batch['preview']) || !hash_equals($batch['preview']['hash'], $hash)) $this->stale();
            SupplierCompany::orderBy('id')->lockForUpdate()->get();
            InsuranceBranch::orderBy('id')->lockForUpdate()->get();
            SupplierAgency::orderBy('id')->lockForUpdate()->get();
            InsuranceSubbranch::orderBy('id')->lockForUpdate()->get();
            $plan = app(RegistryImportPlanner::class)->plan($batch, $batch['preview']['sheet'], $batch['preview']['mapping']);
            if (!hash_equals($plan['hash'], $hash)) $this->stale();
            $created = [];
            foreach ($plan['rows'] as $row) {
                if ($row['state'] !== 'new') continue;
                $record = match ($batch['kind']) {
                    'company' => SupplierCompany::create($row['data']),
                    'agency' => app(SupplierAgencyRegistry::class)->save(new SupplierAgency, $row['data']),
                    'branch' => app(InsuranceCatalog::class)->save(new InsuranceBranch, $row['data']),
                    'subbranch' => app(InsuranceCatalog::class)->save(new InsuranceSubbranch, $row['data']),
                };
                $created[] = $record->id;
            }
            if (!$created) throw ValidationException::withMessages(['confirm' => 'Non ci sono nuove voci da importare.']);
            $result = ['kind' => $batch['kind'], 'created' => count($created), 'record_ids' => $created, 'excluded' => count($plan['rows']) - count($created), 'completed_at' => now()->toIso8601String()];
            AuditLog::create(['user_id' => $userId, 'action' => 'registry_import_completed', 'auditable_type' => User::class, 'auditable_id' => $userId, 'new_values' => $result, 'description' => $description, 'created_at' => now()]);
            return $result;
        }, 3);
    }

    private function stale(): never
    {
        throw ValidationException::withMessages(['confirm' => 'I dati o l’anteprima sono cambiati. Ricalcola l’anteprima e controllala prima di confermare. Nessuna voce è stata importata.']);
    }
}
