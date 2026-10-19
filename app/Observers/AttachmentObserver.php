<?php

namespace App\Observers;

use App\Models\Attachment;
use App\Services\AuditLogService;

class AttachmentObserver
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function created(Attachment $attachment): void
    {
        if ($attachment->attachable_type === \App\Models\Client::class && $attachment->attachable) {
            $userName = auth()->user()?->name ?? 'Sistema';
            $desc = "{$userName} ha caricato l'allegato {$attachment->original_name}";
            $this->auditLog->log('uploaded_attachment', $attachment->attachable, null, null, $desc, auth()->id() ?: $attachment->uploaded_by);
        }
    }

    public function deleting(Attachment $attachment): void
    {
        if ($attachment->attachable_type === \App\Models\Client::class && $attachment->attachable) {
            $userName = auth()->user()?->name ?? 'Sistema';
            $desc = "{$userName} ha eliminato l'allegato {$attachment->original_name}";
            $this->auditLog->log('deleted_attachment', $attachment->attachable, null, null, $desc, auth()->id());
        }
    }

    public function updated(Attachment $attachment): void
    {
        if ($attachment->document_category !== null && $attachment->attachable_type === \App\Models\Client::class) {
            $fields = ['document_category', 'identity_type', 'insurance_policy_id', 'vehicle_id', 'expires_on', 'description'];
            $new = array_intersect_key($attachment->getChanges(), array_flip($fields));
            if ($new) {
                \App\Models\AuditLog::create([
                    'user_id' => auth()->id(), 'action' => 'updated',
                    'auditable_type' => $attachment->attachable_type, 'auditable_id' => $attachment->attachable_id,
                    'old_values' => array_intersect_key($attachment->getRawOriginal(), $new), 'new_values' => $new,
                    'description' => 'Dati del documento '.$attachment->original_name.' aggiornati.', 'created_at' => now(),
                ]);
            }
        }
    }
}
