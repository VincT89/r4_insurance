<?php
namespace App\Policies;

use App\Models\{Attachment, Client, User};

class AttachmentPolicy
{
    public function before(User $user, string $ability, ?Attachment $attachment = null): ?bool
    {
        if (! $attachment || $attachment->attachable_type !== (new Client)->getMorphClass()) {
            return false;
        }
        $client = Client::find($attachment->attachable_id);
        if (! $client) {
            return false;
        }
        if ($attachment->insurance_claim_id && !$user->can('viewAny',Client::class)) return false;
        if ($attachment->client_material_category !== null) {
            return match ($ability) {
                'download' => $user->can('viewMaterials', $client),
                'delete' => $user->can('system.admin') && $user->can('manageMaterials', $client),
                default => false,
            };
        }
        if ($attachment->document_category !== null) {
            return match ($ability) {
                'download' => $user->can('view', $client),
                'delete' => $user->can('system.admin') && $user->can('view', $client),
                default => false,
            };
        }
        return $user->canManageSystem() ? true : null;
    }

    public function download(User $user, Attachment $attachment): bool
    {
        return $user->can('view', $attachment->attachable);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->can('system.admin') && $this->download($user, $attachment);
    }
}
