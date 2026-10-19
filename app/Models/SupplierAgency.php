<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphMany};

class SupplierAgency extends Model
{
    protected $fillable = [
        'name', 'company_name', 'agency_code', 'vat_number', 'tax_code',
        'email', 'pec', 'phone', 'mobile', 'reference_person', 'reference_role',
        'reference_email', 'reference_phone', 'address', 'city', 'province',
        'postal_code', 'country', 'status', 'notes', 'supplier_company_id', 'parent_agency_id',
    ];

    public function getStatusLabelAttribute(): string
    {
        return $this->status === 'active' ? 'Attiva' : 'Inattiva';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(SupplierCompany::class, 'supplier_company_id');
    }

    public function parentAgency(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_agency_id');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(SupplierCompany::class, 'supplier_agency_company')->orderBy('supplier_companies.name');
    }
    public function policies(): HasMany { return $this->hasMany(InsurancePolicy::class); }
    public function commissionSchedules(): HasMany { return $this->hasMany(AgencyCommissionSchedule::class); }

    protected static function booted(): void
    {
        // Preserve compatibility for callers that still submit one company.
        static::saved(function (self $agency) {
            if ($agency->wasRecentlyCreated || $agency->wasChanged('supplier_company_id')) {
                $old = $agency->getOriginal('supplier_company_id');
                if ($old && (int) $old !== (int) $agency->supplier_company_id) $agency->companies()->detach($old);
                if ($agency->supplier_company_id) $agency->companies()->syncWithoutDetaching([$agency->supplier_company_id]);
                $agency->unsetRelation('companies');
            }
        });
    }

    public function subagencies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_agency_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->parent_agency_id ? 'Subagenzia' : 'Agenzia';
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
    public function importEntry(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RegistryImportEntry::class, 'entity_id')->where('kind', 'agency')->where('status', 'imported');
    }
}
