<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AgencyCommunication extends Model
{
    use HasUuids;

    public const STATUSES = ['review'=>'Da confermare', 'sending'=>'Invio in corso', 'sent'=>'Inviata al server di posta', 'uncertain'=>'Esito da verificare', 'failed'=>'Non inviata'];
    protected $guarded = ['id'];
    protected $hidden = ['payload'];
    protected function casts(): array { return ['payload'=>'encrypted:array', 'review_expires_at'=>'datetime', 'reviewed_at'=>'datetime', 'sending_at'=>'datetime', 'sent_at'=>'datetime']; }
    public function agency() { return $this->belongsTo(SupplierAgency::class, 'supplier_agency_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function documents() { return $this->hasMany(AgencyCommunicationAttachment::class); }
    public function deliveryPayload(): array
    {
        $payload = $this->payload;
        if (!empty($payload['attachments'])) {
            $payload['body'] = str_replace('; gli allegati riservati del cliente non sono inclusi.', '.', $payload['body']);
            $payload['body'] .= "\n\nAllegati selezionati:\n".implode("\n",array_map(fn ($file) => $file['name'], $payload['attachments']));
        }
        return $payload;
    }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? $this->status; }
    public function canResolve(): bool { return $this->status === 'uncertain' || ($this->status === 'sending' && $this->sending_at?->lt(now()->subMinutes(5))); }
}
