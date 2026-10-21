<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsurancePolicyChange extends Model
{
    public const KINDS = ['replacement'=>'Sostituzione', 'addition'=>'Aggiunta di coperture'];
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['effective_on'=>'date', 'expires_on'=>'date', 'premium_cents'=>'integer', 'snapshot'=>'encrypted:array'];
    }
    protected static function booted(): void
    {
        /** Contract events are append-only: later corrections must not erase the signed historical context. */
        static::updating(fn () => throw \Illuminate\Validation\ValidationException::withMessages(['change'=>'La variazione è conservata nello storico e non può essere riscritta.']));
        static::deleting(fn () => throw \Illuminate\Validation\ValidationException::withMessages(['change'=>'La variazione e i suoi documenti devono essere conservati.']));
    }
    public function sourcePolicy() { return $this->belongsTo(InsurancePolicy::class, 'source_policy_id'); }
    public function resultPolicy() { return $this->belongsTo(InsurancePolicy::class, 'result_policy_id'); }
    public function quote() { return $this->belongsTo(InsuranceQuote::class, 'insurance_quote_id'); }
    public function author() { return $this->belongsTo(User::class, 'created_by'); }
    public function documents() { return $this->hasMany(Attachment::class); }
    public function formPackages() { return $this->hasMany(InsuranceFormPackage::class); }
    public function titles() { return $this->hasMany(InsuranceTitle::class); }
}
