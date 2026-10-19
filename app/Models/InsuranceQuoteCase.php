<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InsuranceQuoteCase extends Model
{
    protected $guarded=['id'];
    protected function casts(): array { return ['current_policy_snapshot'=>'encrypted:array']; }
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
        if ($this->requester_name) $lines[] = 'Richiedente: '.$this->requester_name;
        if ($snapshot = $this->current_policy_snapshot) {
            $lines[] = 'Polizza attuale: '.$snapshot['number'].' / '.$snapshot['company'];
            if ($snapshot['plate'] ?? null) $lines[] = 'Targa: '.$snapshot['plate'];
        } elseif ($this->current_policy_reference) $lines[] = 'Polizza attuale: '.$this->current_policy_reference;
        if ($this->current_coverages) $lines[] = "Coperture attuali dichiarate:\n".$this->current_coverages;
        if ($this->additional_coverages) $lines[] = "Coperture aggiuntive da quotare:\n".$this->additional_coverages;
        return implode("\n\n", $lines);
    }
}
