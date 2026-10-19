<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\AuditLogService;

class ClientObserver
{
    public function saving(Client $client): void
    {
        foreach (['phone' => 'normalized_phone', 'mobile' => 'normalized_mobile'] as $field => $normalized) {
            if ($client->isDirty($field)) {
                $client->$normalized = app(\App\Support\PhoneNormalizer::class)->normalize($client->$field);
            }
        }
        if ($client->isDirty('name')) $client->registry_name_key = \App\Support\ClientIdentity::nameKey($client->name);
    }

    public function __construct(protected AuditLogService $auditLog) {}

    public function created(Client $client): void
    {
        $this->auditLog->log('created', $client, null, $client->getAttributes());
    }

    public function updated(Client $client): void
    {
        $old = array_intersect_key($client->getOriginal(), $client->getDirty());
        $new = $client->getDirty();

        $action = isset($new['status']) ? 'status_changed' : 'updated';

        $this->auditLog->log($action, $client, $old, $new);
    }

    public function deleted(Client $client): void
    {
        $this->auditLog->log('deleted', $client, $client->getOriginal(), null);
    }
}
