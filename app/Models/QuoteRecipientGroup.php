<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteRecipientGroup extends Model
{
    protected $fillable = ['name', 'insurance_branch_id', 'insurance_subbranch_id', 'cu_from', 'cu_to', 'status'];

    protected function casts(): array
    {
        return ['cu_from' => 'integer', 'cu_to' => 'integer'];
    }

    public function companies()
    {
        return $this->belongsToMany(SupplierCompany::class, 'quote_recipient_group_company');
    }

    public function branch() { return $this->belongsTo(InsuranceBranch::class, 'insurance_branch_id'); }
    public function subbranch() { return $this->belongsTo(InsuranceSubbranch::class, 'insurance_subbranch_id'); }

    public function scopeForRequest($query, int $branchId, ?int $subbranchId, ?int $cu)
    {
        return $query->where('status','active')->where('insurance_branch_id',$branchId)
            ->where(fn($q) => $q->whereNull('insurance_subbranch_id')->when($subbranchId,fn($q) => $q->orWhere('insurance_subbranch_id',$subbranchId)))
            ->where(fn($q) => $q->whereNull('cu_from')->when($cu,fn($q) => $q->orWhere(fn($q) => $q->where('cu_from','<=',$cu)->where('cu_to','>=',$cu))));
    }

    public function scopeForClass($query, int $cu)
    {
        return $query->where('status', 'active')->where('cu_from', '<=', $cu)->where('cu_to', '>=', $cu);
    }

    public function getClassLabelAttribute(): string
    {
        if ($this->cu_from === null) return 'Senza vincolo di classe';
        return $this->cu_from === $this->cu_to ? 'CU '.$this->cu_from : 'CU '.$this->cu_from.'–'.$this->cu_to;
    }
}
