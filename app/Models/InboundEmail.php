<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InboundEmail extends Model
{
    public const KINDS = ['quote'=>'Preventivo', 'contract'=>'Contratto o appendice', 'receipt'=>'Quietanza o ricevuta', 'document'=>'Documento cliente', 'other'=>'Da classificare'];
    protected $guarded = ['id'];
    protected $hidden = ['payload', 'delivery_key', 'content_hash', 'sender_hash', 'association_note'];

    protected static function booted(): void
    {
        static::creating(function (self $email) { $email->public_id ??= (string) Str::uuid(); });
    }

    protected function casts(): array
    {
        return ['payload'=>'encrypted:array', 'association_note'=>'encrypted', 'received_at'=>'datetime', 'assigned_at'=>'datetime', 'version'=>'integer'];
    }

    public function getRouteKeyName(): string { return 'public_id'; }
    public function files() { return $this->hasMany(InboundEmailFile::class); }
    public function client() { return $this->belongsTo(Client::class); }
    public function quoteCase() { return $this->belongsTo(InsuranceQuoteCase::class, 'insurance_quote_case_id'); }
    public function policy() { return $this->belongsTo(InsurancePolicy::class, 'insurance_policy_id'); }
    public function operator() { return $this->belongsTo(User::class, 'assigned_by'); }
    public function auditLogs() { return $this->morphMany(AuditLog::class, 'auditable'); }
    public function getStatusLabelAttribute(): string { return $this->status === 'assigned' ? 'Associata' : 'Da associare'; }

    public function displayBody(): string
    {
        $payload = $this->payload;
        if (filled($payload['text'] ?? null)) return $payload['text'];
        /** Le email HTML diventano testo: nessuna immagine remota, script o stile viene eseguito. */
        $html = preg_replace('~<(script|style|head)\b[^>]*>.*?</\1\s*>~is', '', $payload['html'] ?? '');
        $html = preg_replace('~<(?:br\s*/?|/p|/div|/li|/tr)>~i', "\n", $html);
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
