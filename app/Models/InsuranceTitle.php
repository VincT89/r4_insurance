<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InsuranceTitle extends Model
{
    public const KINDS=['emission'=>'Emissione','quietance'=>'Quietanza','renewal'=>'Rinnovo'];
    public const STATES=['open'=>'Da incassare','partial'=>'Parzialmente incassato','overdue'=>'In giacenza','paid'=>'Incassato','cancelled'=>'Annullato'];
    protected $guarded=['id'];
    protected function casts(): array { return ['due_on'=>'date','coverage_from'=>'date','coverage_to'=>'date','cancelled_at'=>'datetime','amount_cents'=>'integer','collected_cents'=>'integer']; }
    public function policy(){return $this->belongsTo(InsurancePolicy::class,'insurance_policy_id');}
    public function policyChange(){return $this->belongsTo(InsurancePolicyChange::class,'insurance_policy_change_id');}
    public function collections(){return $this->hasMany(InsuranceCollection::class);}
    public function commission(){return $this->hasOne(InsuranceCommission::class);}
    public function documents(){return $this->hasMany(Attachment::class);}
    public function scopeVisibleTo($query,User $user){return $query->whereHas('policy',fn($q)=>$q->whereIn('client_id',Client::current()->visibleTo($user)->select('id')));}
    public function getBalanceCentsAttribute(): int {return max(0,$this->amount_cents-$this->collected_cents);}
    public function getStateAttribute(): string {return $this->cancelled_at ? 'cancelled' : ($this->balance_cents===0 ? 'paid' : ($this->due_on->lt(today()) ? 'overdue' : ($this->collected_cents>0 ? 'partial' : 'open')));}
    public function getStateLabelAttribute(): string {return self::STATES[$this->state];}
}
