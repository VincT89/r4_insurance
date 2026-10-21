<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceFormPackage extends Model
{
    public const REASONS = ['initial'=>'Nuova emissione','change'=>'Variazione della polizza','replacement'=>'Sostituzione','addition'=>'Aggiunta di coperture','renewal'=>'Rinnovo','client'=>'Documenti del cliente'];
    public function policyChange() { return $this->belongsTo(InsurancePolicyChange::class, 'insurance_policy_change_id'); }
    protected $guarded = ['id'];
    protected function casts(): array { return ['snapshot'=>'encrypted:array']; }
    public function client() { return $this->belongsTo(Client::class); }
    public function policy() { return $this->belongsTo(InsurancePolicy::class,'insurance_policy_id'); }
    public function author() { return $this->belongsTo(User::class,'created_by'); }
    public function documents() { return $this->hasMany(InsuranceFormDocument::class); }
    public function currentData(): bool { return hash_equals($this->context_hash, app(\App\Services\InsuranceForms::class)->contextHash($this->client, $this->policy)); }
    public function getStateLabelAttribute(): string
    {
        if (!$this->currentData()) return 'Dati aggiornati: predisporre nuovi moduli';
        foreach ($this->documents as $document) if ($document->signature_required && $document->state_label !== 'Firma controllata') return 'Firme da completare';
        return 'Documentazione completata';
    }
}
