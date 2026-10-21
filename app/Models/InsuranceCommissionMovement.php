<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceCommissionMovement extends Model
{
    public const KINDS = ['forecast'=>'Previsione', 'accrual'=>'Maturazione', 'settlement'=>'Liquidazione'];
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array { return ['amount_cents'=>'integer', 'effective_on'=>'date', 'created_at'=>'datetime']; }
    public function commission() { return $this->belongsTo(InsuranceCommission::class, 'insurance_commission_id'); }
    public function author() { return $this->belongsTo(User::class, 'created_by'); }
    public function reversal() { return $this->hasOne(self::class, 'reversal_of_id'); }
}
