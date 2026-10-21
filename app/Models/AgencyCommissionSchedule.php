<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyCommissionSchedule extends Model
{
    public const ACCRUAL_EVENTS = ['collection'=>'All’incasso del premio','title'=>'All’emissione del titolo','confirmation'=>'Alla conferma della compagnia o agenzia'];
    public const COLLECTION_MODES = ['proportional'=>'In proporzione al premio incassato', 'full'=>'Solo a saldo completo della rata'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['valid_from'=>'date','valid_to'=>'date','superseded_at'=>'datetime','voided_at'=>'datetime']; }
    public function agency() { return $this->belongsTo(SupplierAgency::class,'supplier_agency_id'); }
    public function company() { return $this->belongsTo(SupplierCompany::class,'supplier_company_id'); }
    public function author() { return $this->belongsTo(User::class,'created_by'); }
    public function rates() { return $this->hasMany(AgencyCommissionRate::class); }
    public function previous() { return $this->belongsTo(self::class,'replaces_id'); }
    public function replacement() { return $this->hasOne(self::class,'replaces_id'); }
    public function scopeCurrent($query) { return $query->whereNull('superseded_at')->whereNull('voided_at'); }
    public function editable(): bool { return !$this->superseded_at && !$this->voided_at; }
    public function getStateLabelAttribute(): string
    {
        if ($this->voided_at) return 'Annullata';
        if ($this->superseded_at) return 'Versione precedente';
        if ($this->valid_from->gt(today())) return 'Futura';
        if ($this->valid_to?->lt(today())) return 'Terminata';
        return 'In vigore';
    }
}
