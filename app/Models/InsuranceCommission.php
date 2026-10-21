<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceCommission extends Model
{
    public const STATES = ['incomplete'=>'Da completare', 'expected'=>'Prevista', 'partial'=>'Maturata in parte', 'accrued'=>'Maturata', 'settled'=>'Liquidata', 'reconcile'=>'Da riconciliare', 'cancelled'=>'Annullata'];
    protected $guarded = ['id'];
    protected $attributes = ['accrued_cents'=>0, 'settled_cents'=>0, 'version'=>0];

    protected function casts(): array
    {
        return ['base_cents'=>'integer', 'expected_cents'=>'integer', 'accrued_cents'=>'integer', 'settled_cents'=>'integer', 'version'=>'integer', 'rule_snapshot'=>'array', 'locked_at'=>'datetime'];
    }

    public function title() { return $this->belongsTo(InsuranceTitle::class, 'insurance_title_id'); }
    public function rate() { return $this->belongsTo(AgencyCommissionRate::class, 'agency_commission_rate_id'); }
    public function movements() { return $this->hasMany(InsuranceCommissionMovement::class); }
    public function getStateAttribute(): string
    {
        if ($this->settled_cents > $this->accrued_cents) return 'reconcile';
        if ($this->title->cancelled_at) return 'cancelled';
        if (!$this->locked_at) return 'incomplete';
        if ($this->expected_cents === 0) return 'accrued';
        if ($this->settled_cents === $this->expected_cents) return 'settled';
        if ($this->accrued_cents === $this->expected_cents) return 'accrued';
        return $this->accrued_cents > 0 ? 'partial' : 'expected';
    }
}
