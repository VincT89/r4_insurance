<?php

namespace App\Observers;

use App\Models\{AuditLog, ClientVehicle, InsurancePolicy};

class ClientInsuranceObserver
{
    public function created(ClientVehicle|InsurancePolicy $record): void
    {
        $this->record($record, 'creato', null, $record->only($record->getFillable()));
    }

    public function updated(ClientVehicle|InsurancePolicy $record): void
    {
        $changes = array_intersect_key($record->getChanges(), array_flip($record->getFillable()));
        if ($changes) {
            $this->record($record, 'aggiornato', array_intersect_key($record->getRawOriginal(), $changes), $changes);
        }
    }

    public function deleted(ClientVehicle|InsurancePolicy $record): void
    {
        $this->record($record, 'eliminato', $record->only($record->getFillable()), null);
    }

    private function record(ClientVehicle|InsurancePolicy $record, string $action, ?array $old, ?array $new): void
    {
        $label = $record instanceof InsurancePolicy ? 'Polizza '.$record->number : 'Veicolo '.$record->plate;
        AuditLog::create([
            'user_id' => auth()->id(), 'action' => match ($action) { 'creato' => 'created', 'eliminato' => 'deleted', default => 'updated' },
            'auditable_type' => $record->client->getMorphClass(), 'auditable_id' => $record->client_id,
            'old_values' => $old === null ? null : [$label => $old],
            'new_values' => $new === null ? null : [$label => $new],
            'description' => $label.': record '.$action.'.', 'created_at' => now(),
        ]);
    }
}
