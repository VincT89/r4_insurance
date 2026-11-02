<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, MorphMany};

class SupplierCompany extends Model
{
    protected $fillable = ['name','company_name','vat_number','tax_code','email','pec','phone','mobile','reference_person','reference_role','reference_email','reference_phone','address','city','province','postal_code','country','status','notes','quote_priority','iban','account_holder','bank_name','bic','billing_email','sdi_code'];
    protected $hidden = ['iban'];
    protected function casts(): array {return ['quote_priority'=>'boolean','iban'=>'encrypted'];}

    public function agencies(): BelongsToMany
    {
        return $this->belongsToMany(SupplierAgency::class, 'supplier_agency_company');
    }
    public function policies(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(InsurancePolicy::class); }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status === 'active' ? 'Attiva' : 'Inattiva';
    }
    public function importEntry(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RegistryImportEntry::class, 'entity_id')->where('kind', 'company')->where('status', 'imported');
    }
}
