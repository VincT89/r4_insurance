<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\ClientAsset;
use App\Models\ClientConsent;
use App\Models\ClientContact;
use App\Models\ClientInteraction;
use Illuminate\Database\Eloquent\Model;

class ClientRegistryObserver
{
    public function created(Model $record): void
    {
        $this->log($record, null, $record->getAttributes(), 'aggiunto');
    }

    public function updated(Model $record): void
    {
        $this->log($record, $record->getOriginal(), $record->getChanges(), 'aggiornato');
    }

    public function deleted(Model $record): void
    {
        $this->log($record, $record->getOriginal(), null, 'rimosso');
    }

    private function log(Model $record, ?array $old, ?array $new, string $verb): void
    {
        $label = match (true) {
            $record instanceof ClientContact => 'Referente '.$record->name,
            $record instanceof ClientAsset => 'Bene '.$record->name,
            $record instanceof ClientInteraction => 'Contatto '.$record->subject,
            $record instanceof ClientConsent => 'Consenso '.$record->purpose,
        };
        AuditLog::create([
            'user_id' => auth()->id(), 'action' => 'updated',
            'auditable_type' => $record->client->getMorphClass(), 'auditable_id' => $record->client_id,
            'old_values' => $old === null ? null : [$label => $old],
            'new_values' => $new === null ? null : [$label => $new],
            'description' => $label.': '.$verb.'.', 'created_at' => now(),
        ]);
    }
}
