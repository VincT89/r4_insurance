<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceFormTemplate extends Model
{
    public const SCOPES = ['client'=>'Anagrafica cliente','policy'=>'Polizza'];
    public const STATUSES = ['draft'=>'Da revisionare','active'=>'Utilizzabile','retired'=>'Ritirato','legacy'=>'Modello precedente'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['signature_required'=>'boolean','required'=>'boolean','source_reviewed'=>'boolean','approved_at'=>'datetime']; }
    public function branch() { return $this->belongsTo(InsuranceBranch::class,'insurance_branch_id'); }
    public function subbranch() { return $this->belongsTo(InsuranceSubbranch::class,'insurance_subbranch_id'); }
    public function previous() { return $this->belongsTo(self::class,'previous_id'); }
    public function next() { return $this->hasOne(self::class,'previous_id'); }
    public function approver() { return $this->belongsTo(User::class,'approved_by'); }
    public function applies(?InsurancePolicy $policy): bool
    {
        return $this->scope === ($policy ? 'policy' : 'client') && (!$this->insurance_branch_id || $this->insurance_branch_id === $policy?->insurance_branch_id) && (!$this->insurance_subbranch_id || $this->insurance_subbranch_id === $policy?->insurance_subbranch_id);
    }
}
