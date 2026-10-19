<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InsuranceQuoteEmail extends Model
{
    use HasUuids;

    public const STATUSES = ['review'=>'Da confermare', 'sending'=>'Invio in corso', 'sent'=>'Inviata al server di posta', 'uncertain'=>'Esito da verificare', 'failed'=>'Non inviata'];
    protected $guarded = ['id'];
    protected $hidden = ['payload'];
    protected function casts(): array { return ['payload'=>'encrypted:array', 'review_expires_at'=>'datetime', 'reviewed_at'=>'datetime', 'sending_at'=>'datetime', 'sent_at'=>'datetime']; }
    public function quoteCase() { return $this->belongsTo(InsuranceQuoteCase::class, 'insurance_quote_case_id'); }
    public function company() { return $this->belongsTo(SupplierCompany::class, 'supplier_company_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function documents() { return $this->hasMany(Attachment::class, 'insurance_quote_email_id'); }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? $this->status; }
    public function canResolve(): bool { return $this->status === 'uncertain' || ($this->status === 'sending' && $this->sending_at?->lt(now()->subMinutes(5))); }
}
