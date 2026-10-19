<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaimActivity extends Model
{
    public const STATUSES = ['pending'=>'Da svolgere','completed'=>'Completata','cancelled'=>'Annullata'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['due_on'=>'date','completed_at'=>'datetime','label'=>'encrypted','revision'=>'integer']; }
    public function claim(): BelongsTo { return $this->belongsTo(InsuranceClaim::class,'insurance_claim_id'); }
    public function scopePending(Builder $query): Builder { return $query->where('status','pending')->whereHas('claim',fn($q)=>$q->open()); }
    public function scopeVisibleTo(Builder $query, User $user): Builder { return $query->whereHas('claim',fn($q)=>$q->visibleTo($user)); }
    public function getStateLabelAttribute(): string
    {
        if ($this->status!=='pending') return self::STATUSES[$this->status];
        return $this->due_on->lt(today()) ? 'In arretrato' : ($this->due_on->isToday() ? 'Da svolgere oggi' : 'Da svolgere');
    }
}
