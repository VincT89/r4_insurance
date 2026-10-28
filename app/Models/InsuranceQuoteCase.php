<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InsuranceQuoteCase extends Model
{
    public const PURPOSES = ['new'=>'Nuova polizza', 'replacement'=>'Sostituzione di una polizza', 'addition'=>'Aggiunta di coperture'];
    public function availableCompanies()
    {
        return SupplierCompany::where('status','active')
            ->when($this->purpose==='addition', fn($q)=>$q->whereKey($this->currentPolicy?->supplier_company_id ?? 0))
            ->when($this->recipient_group_snapshot !== null, fn($q)=>$q->whereIn('id', array_column($this->recipient_group_snapshot['companies'] ?? [], 'id')))
            ->orderByDesc('quote_priority')->orderBy('name');
    }
    protected $guarded=['id'];
    protected function casts(): array { return ['current_policy_snapshot'=>'encrypted:array', 'vehicle_snapshot'=>'encrypted:array', 'recipient_group_snapshot'=>'array', 'merit_class_cu'=>'integer']; }
    public function vehicle(){return $this->belongsTo(ClientVehicle::class);}
    public function recipientGroup(){return $this->belongsTo(QuoteRecipientGroup::class, 'quote_recipient_group_id');}
    public function client(){return $this->belongsTo(Client::class);}
    public function currentPolicy(){return $this->belongsTo(InsurancePolicy::class,'current_policy_id');}
    public function documents(){return $this->hasMany(Attachment::class);}
    public function branch(){return $this->belongsTo(InsuranceBranch::class,'insurance_branch_id');}
    public function subbranch(){return $this->belongsTo(InsuranceSubbranch::class,'insurance_subbranch_id');}
    public function quotes(){return $this->hasMany(InsuranceQuote::class);}
    public function emails(){return $this->hasMany(InsuranceQuoteEmail::class);}
    public function scopeVisibleTo($query,User $user){return $query->whereIn('client_id',Client::current()->visibleTo($user)->select('id'));}

    public function requestContext(): string
    {
        $lines = [];
        if (in_array($this->purpose, ['replacement','addition'], true)) $lines[] = 'Richiesta: '.self::PURPOSES[$this->purpose];
        if ($this->purpose === 'addition') $lines[] = 'Quotare il solo premio aggiuntivo per il periodo indicato, mantenendo il numero della polizza.';
        if ($this->requester_name) $lines[] = 'Richiedente: '.$this->requester_name;
        if ($vehicle = $this->vehicle_snapshot) {
            $lines[] = 'Veicolo: '.trim(($vehicle['make'] ?? '').' '.($vehicle['model'] ?? '')).' / Targa: '.$vehicle['plate'];
            if ($vehicle['vin'] ?? null) $lines[] = 'Telaio: '.$vehicle['vin'];
            if ($vehicle['registered_on'] ?? null) $lines[] = 'Prima immatricolazione: '.\Illuminate\Support\Carbon::parse($vehicle['registered_on'])->format('d/m/Y');
        }
        if ($this->merit_class_cu) $lines[] = 'Classe CU dichiarata: '.$this->merit_class_cu;
        if ($this->merit_class_internal) $lines[] = 'Classe interna dichiarata: '.$this->merit_class_internal.(!empty($vehicle['class_company']) ? ' ('.$vehicle['class_company'].')' : '');
        if ($snapshot = $this->current_policy_snapshot) {
            $lines[] = 'Polizza attuale: '.$snapshot['number'].' / '.$snapshot['company'];
            if ($snapshot['plate'] ?? null) $lines[] = 'Targa: '.$snapshot['plate'];
        } elseif ($this->current_policy_reference) $lines[] = 'Polizza attuale: '.$this->current_policy_reference;
        if ($this->current_coverages) $lines[] = "Coperture attuali dichiarate:\n".$this->current_coverages;
        if ($this->additional_coverages) $lines[] = "Coperture aggiuntive da quotare:\n".$this->additional_coverages;
        return implode("\n\n", $lines);
    }
}
