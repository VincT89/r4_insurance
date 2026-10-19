<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientConsent extends Model
{
    public const KINDS = ['electronic_delivery' => 'Invio elettronico dei documenti contrattuali', 'other' => 'Altro consenso documentato'];

    public const SCOPES = ['all' => 'Tutti i documenti presenti e futuri del cliente', 'policy' => 'Solo la polizza indicata'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('I consensi si aggiornano aggiungendo una nuova registrazione.'));
        static::deleting(fn () => throw new \LogicException('Lo storico dei consensi deve essere conservato.'));
    }

    public function insurancePolicy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicy::class);
    }

    public const STATUSES = ['given' => 'Prestato', 'refused' => 'Negato', 'revoked' => 'Revocato'];

    protected $fillable = ['kind', 'purpose', 'scope', 'insurance_policy_id', 'signer_name', 'signer_role', 'channel', 'status', 'notice_reference', 'event_at', 'attachment_id', 'evidence_reference', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['event_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }
}
