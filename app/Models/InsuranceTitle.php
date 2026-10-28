<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InsuranceTitle extends Model
{
    public const KINDS=['emission'=>'Emissione','quietance'=>'Quietanza','renewal'=>'Rinnovo'];
    public const STATES=['open'=>'Da incassare (tutte)','future'=>'Future','partial'=>'Parzialmente incassate (non scadute)','due_today'=>'In scadenza oggi','overdue'=>'Scadute / in giacenza','paid'=>'Incassate','cancelled'=>'Annullate'];
    protected $guarded=['id'];
    protected function casts(): array { return ['due_on'=>'date','coverage_from'=>'date','coverage_to'=>'date','cancelled_at'=>'datetime','amount_cents'=>'integer','collected_cents'=>'integer']; }
    public function policy(){return $this->belongsTo(InsurancePolicy::class,'insurance_policy_id');}
    public function policyChange(){return $this->belongsTo(InsurancePolicyChange::class,'insurance_policy_change_id');}
    public function collections(){return $this->hasMany(InsuranceCollection::class);}
    public function commission(){return $this->hasOne(InsuranceCommission::class);}
    public function documents(){return $this->hasMany(Attachment::class);}
    public function scopeVisibleTo($query,User $user){return $query->whereHas('policy',fn($q)=>$q->whereIn('client_id',Client::current()->visibleTo($user)->select('id')));}
    public function getBalanceCentsAttribute(): int {return max(0,$this->amount_cents-$this->collected_cents);}
    /** A past due date takes priority over a partial collection; each outstanding instalment belongs to one group. */
    public function getStateAttribute(): string {return $this->cancelled_at ? 'cancelled' : ($this->balance_cents===0 ? 'paid' : ($this->due_on->lt(today()) ? 'overdue' : ($this->collected_cents>0 ? 'partial' : ($this->due_on->gt(today()) ? 'future' : 'due_today'))));}
    public function getStateLabelAttribute(): string {return ['future'=>'Futura','partial'=>'Parzialmente incassata','due_today'=>'In scadenza oggi','overdue'=>'Scaduta / in giacenza','paid'=>'Incassata','cancelled'=>'Annullata'][$this->state];}
    public function scopeInPaymentState($query, ?string $state)
    {
        if (!$state || !array_key_exists($state, self::STATES)) return $query;
        if ($state === 'cancelled') return $query->whereNotNull('cancelled_at');
        $query->whereNull('cancelled_at');
        if ($state === 'paid') return $query->whereColumn('collected_cents', '>=', 'amount_cents');
        $query->whereColumn('collected_cents', '<', 'amount_cents');
        return match ($state) {
            'future' => $query->where('collected_cents', 0)->where('due_on', '>=', today()->addDay()->toDateString()),
            'due_today' => $query->where('collected_cents', 0)->where('due_on', '>=', today()->toDateString())->where('due_on', '<', today()->addDay()->toDateString()),
            'partial' => $query->where('collected_cents', '>', 0)->where('due_on', '>=', today()->toDateString()),
            'overdue' => $query->where('due_on', '<', today()->toDateString()),
            default => $query,
        };
    }
}
