<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
    public function getPolicyNumberAttribute(): ?string
    {
        return $this->snapshot['context']['policy']['number'] ?? $this->policy?->number;
    }
    public function getPolicyPeriodAttribute(): ?string
    {
        $snapshot = $this->snapshot;
        $period = $snapshot['context']['policy'] ?? [];
        if (empty($period['starts_on']) || empty($period['expires_on'])) return null;

        /** Le date stampate conservano il giorno del contratto anche se la mezzanotte locale è serializzata in UTC. */
        $tokens = $snapshot['tokens'] ?? [];
        $start = $tokens['{{decorrenza}}'] ?? Carbon::parse($period['starts_on'])->timezone(config('app.timezone'))->format('d/m/Y');
        $end = $tokens['{{scadenza}}'] ?? Carbon::parse($period['expires_on'])->timezone(config('app.timezone'))->format('d/m/Y');
        return $start.' – '.$end;
    }
    public function currentData(): bool { return hash_equals($this->context_hash, app(\App\Services\InsuranceForms::class)->contextHash($this->client, $this->policy)); }
    public function getStateLabelAttribute(): string
    {
        if ((int)$this->policy?->latestRenewal?->id > (int)$this->insurance_policy_change_id) return 'Periodo precedente';
        if (!$this->currentData()) return 'Dati aggiornati: predisporre nuovi moduli';
        foreach ($this->documents as $document) if ($document->signature_required && $document->state_label !== 'Firma controllata') return 'Firme da completare';
        return 'Documentazione completata';
    }
}
