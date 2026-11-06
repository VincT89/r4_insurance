<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolicyReplacementSchedule extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['effective_on'=>'date', 'activated_at'=>'datetime', 'cancelled_at'=>'datetime', 'cancellation_reason'=>'encrypted'];
    }
    public function change() { return $this->belongsTo(InsurancePolicyChange::class, 'insurance_policy_change_id'); }
}
