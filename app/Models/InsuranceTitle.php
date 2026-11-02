<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InsuranceTitle extends Model
{
    public const KINDS=['emission'=>'Emissione','quietance'=>'Quietanza','renewal'=>'Rinnovo','adjustment'=>'Conguaglio'];
    public const STATES=['open'=>'Da incassare (tutti)','overdue'=>'Sospese di cassa','due_soon'=>'Titoli in giacenza','due_today'=>'In scadenza oggi','future'=>'Titoli futuri','paid'=>'Incassati','cancelled'=>'Annullati','partial'=>'Incassi parziali storici'];
    protected $guarded=['id'];
    protected function casts(): array { return ['due_on'=>'date','coverage_from'=>'date','coverage_to'=>'date','cancelled_at'=>'datetime','amount_cents'=>'integer','collected_cents'=>'integer','cancelled_residual_cents'=>'integer','affects_coverage'=>'boolean','contract_snapshot'=>'array']; }
    protected static function booted(): void
    {
        static::creating(function (InsuranceTitle $title) {
            $policy = $title->policy;
            $title->contract_snapshot ??= \Illuminate\Support\Arr::except($policy->getAttributes(), ['notes']);
            $title->supplier_company_id ??= $policy->supplier_company_id;
            $title->supplier_agency_id ??= $policy->supplier_agency_id;
            $title->affects_coverage ??= !$title->insurance_policy_change_id && $title->kind !== 'adjustment';
            $title->direction ??= 'receipt';
        });
    }
    public function getContractAttribute(): InsurancePolicy
    {
        /** Reports use the contract recorded with the title, even after a renewal changes the current policy. */
        $contract = clone $this->policy;
        $contract->setRawAttributes($this->contract_snapshot ?: $this->policy->getAttributes(), true);
        $contract->setRelation('supplierAgency', $this->supplierAgency)->unsetRelation('supplierCompany');
        return $contract;
    }
    public function supplierAgency(){return $this->belongsTo(SupplierAgency::class);}
    public function policy(){return $this->belongsTo(InsurancePolicy::class,'insurance_policy_id');}
    public function policyChange(){return $this->belongsTo(InsurancePolicyChange::class,'insurance_policy_change_id');}
    public function collections(){return $this->hasMany(InsuranceCollection::class);}
    public function commission(){return $this->hasOne(InsuranceCommission::class);}
    public function documents(){return $this->hasMany(Attachment::class);}
    public function scopeVisibleTo($query,User $user){return $query->whereHas('policy',fn($q)=>$q->whereIn('client_id',Client::current()->visibleTo($user)->select('id')));}
    public function getBalanceCentsAttribute(): int {return max(0,$this->amount_cents-$this->collected_cents-($this->cancelled_residual_cents ?? 0));}
    /** The operational state follows the due date; historical collections never hide an unpaid deadline. */
    public function getStateAttribute(): string {return $this->cancelled_at ? 'cancelled' : ($this->balance_cents===0 ? 'paid' : ($this->due_on->lt(today()) ? 'overdue' : ($this->due_on->isToday() ? 'due_today' : ($this->due_on->lte(today()->addDays(15)) ? 'due_soon' : 'future'))));}
    public function getStateLabelAttribute(): string {return $this->direction === 'refund' && !$this->cancelled_at ? ($this->balance_cents === 0 ? 'Rimborsato' : 'Da rimborsare') : ['future'=>'Futuro','due_soon'=>'In giacenza','due_today'=>'In scadenza oggi','overdue'=>'Sospeso di cassa','paid'=>'Incassato','cancelled'=>'Annullato'][$this->state];}
    public function getSummaryStateAttribute(): ?string
    {
        if ($this->cancelled_at || $this->balance_cents === 0 || $this->direction === 'refund') return null;
        if ($this->due_on->lt(today())) return 'overdue';
        if ($this->kind !== 'adjustment' && ($this->due_on->gte($this->policy->expires_on) || ($this->policy->closed_at && $this->due_on->gte($this->policy->closed_at->copy()->startOfDay())))) return null;
        return $this->due_on->lte(today()->addDays(15)) ? 'due_soon' : 'future';
    }
    public function getFollowUpOnAttribute(): \Illuminate\Support\Carbon {return $this->due_on->copy()->addDays(15);}
    public function scopeWithinContract($query)
    {
        /** Contract closure is distinct from closing an operator's work; unpaid history remains available. */
        return $query->where(fn($title)=>$title->where('insurance_titles.kind','adjustment')->orWhereHas('policy',fn($policy)=>$policy->whereColumn('insurance_titles.due_on','<','insurance_policies.expires_on')
            ->where(fn($end)=>$end->whereNull('insurance_policies.closed_at')->orWhereColumn('insurance_titles.due_on','<',\Illuminate\Support\Facades\DB::raw('DATE(insurance_policies.closed_at)')))));
    }
    public function scopeInPaymentState($query, ?string $state)
    {
        if (!$state || !array_key_exists($state, self::STATES)) return $query;
        if ($state === 'cancelled') return $query->whereNotNull('cancelled_at');
        $query->whereNull('cancelled_at');
        if ($state === 'paid') return $query->whereColumn('collected_cents', '>=', 'amount_cents');
        $query->whereColumn('collected_cents', '<', 'amount_cents');
        return match ($state) {
            'future' => $query->where('due_on', '>=', today()->addDays(16)->toDateString())->withinContract(),
            'due_soon' => $query->where('due_on', '>=', today()->toDateString())->where('due_on', '<', today()->addDays(16)->toDateString())->withinContract(),
            'due_today' => $query->where('due_on', '>=', today()->toDateString())->where('due_on', '<', today()->addDay()->toDateString()),
            'partial' => $query->where('collected_cents', '>', 0),
            'overdue' => $query->where('due_on', '<', today()->toDateString()),
            default => $query,
        };
    }
}
