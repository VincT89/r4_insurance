<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InsuranceQuote extends Model
{
    public function policyChange() { return $this->hasOne(InsurancePolicyChange::class); }
    public const STATUSES=['requested'=>'Richiesto','received'=>'Ricevuto','pending'=>'In approvazione cliente','approved'=>'Approvato dal cliente','rejected'=>'Rifiutato dal cliente','converted'=>'Polizza stipulata'];
    protected $guarded=['id'];
    protected function casts(): array {return ['priority'=>'boolean','requested_at'=>'datetime','received_at'=>'datetime','submitted_at'=>'datetime','approved_on'=>'date','starts_on'=>'date','expires_on'=>'date','valid_until'=>'date','premium_cents'=>'integer'];}
    public function quoteCase(){return $this->belongsTo(InsuranceQuoteCase::class,'insurance_quote_case_id');}
    public function company(){return $this->belongsTo(SupplierCompany::class,'supplier_company_id');}
    public function agency(){return $this->belongsTo(SupplierAgency::class,'supplier_agency_id');}
    public function documents(){return $this->hasMany(Attachment::class);}
    public function policy(){return $this->hasOne(InsurancePolicy::class);}
}
