<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyCommissionRate extends Model
{
    public const BASES = ['net_premium'=>'Premio imponibile','gross_premium'=>'Premio totale','other'=>'Altra base contrattuale'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['issue_rate_units'=>'integer','renewal_rate_units'=>'integer','quietance_rate_units'=>'integer']; }
    public function schedule() { return $this->belongsTo(AgencyCommissionSchedule::class,'agency_commission_schedule_id'); }
    public function branch() { return $this->belongsTo(InsuranceBranch::class,'insurance_branch_id'); }
    public function subbranch() { return $this->belongsTo(InsuranceSubbranch::class,'insurance_subbranch_id'); }
    public static function percent(?int $units, string $separator='.'): string
    {
        if ($units === null) return '';
        $fraction = rtrim(str_pad((string)($units % 10000),4,'0',STR_PAD_LEFT),'0');
        return intdiv($units,10000).($fraction !== '' ? $separator.$fraction : '');
    }
}
