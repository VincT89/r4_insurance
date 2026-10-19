<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class InsurancePolicy extends Model
{
    public const CATEGORY_SUGGESTIONS = ['RC auto', 'Infortuni', 'RC persone'];
    public const STATUSES = ['active' => 'Attiva', 'renewed' => 'Rinnovata', 'expired' => 'Scaduta', 'suspended' => 'Sospesa', 'replaced' => 'Sostituita', 'cancelled' => 'Annullata', 'ceased' => 'Cessata', 'review' => 'Da verificare'];
    public const CLOSED_STATUSES = ['replaced', 'cancelled', 'ceased'];
    public const PAYMENT_STATUSES = ['unpaid' => 'Non pagata', 'paid' => 'Pagata'];
    public const PAYMENT_METHODS = ['Bonifico a Compagnia', 'Bonifico a Intermediario', 'Assegno a Compagnia', 'Assegno a Intermediario', 'Contanti a Intermediario', 'Contanti a Compagnia', 'Carta di credito a Intermediario', 'Carta di credito a Compagnia', 'Finanziamento'];
    public const PAYMENT_FREQUENCIES = ['Unico', 'Annuale', 'Semestrale', 'Quadrimestrale', 'Trimestrale', 'Mensile'];

    protected $fillable = ['vehicle_id', 'client_asset_id', 'insurance_branch_id', 'insurance_subbranch_id', 'supplier_company_id', 'supplier_agency_id', 'category', 'subcategory', 'company', 'number', 'product', 'insured_subject', 'starts_on', 'expires_on', 'premium', 'status', 'notes', 'payment_status', 'payment_method', 'payment_frequency', 'installment_premium', 'tacit_renewal', 'ad_horas', 'merit_class_cu', 'merit_class_internal'];
    protected static function booted(): void
    {
        static::deleting(function (InsurancePolicy $policy) {
            if (InsuranceQuoteCase::where('current_policy_id',$policy->id)->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['policy'=>'La polizza è un riferimento di una pratica preventivi e deve essere conservata.']);
        });
    }
    public function supplierCompany(): BelongsTo { return $this->belongsTo(SupplierCompany::class); }
    public function titles(): HasMany { return $this->hasMany(InsuranceTitle::class); }
    public function formPackages(): HasMany { return $this->hasMany(InsuranceFormPackage::class); }
    public function coverageChecks(): HasMany { return $this->hasMany(InsuranceCoverageCheck::class); }
    public function claims(): HasMany { return $this->hasMany(InsuranceClaim::class); }
    public function quote(): BelongsTo { return $this->belongsTo(InsuranceQuote::class,'insurance_quote_id'); }
    public function corrections(): HasMany { return $this->hasMany(InsurancePolicyCorrection::class); }
    public function workClosedBy(): BelongsTo { return $this->belongsTo(User::class, 'work_closed_by'); }
    public function workLocked(): bool { return $this->work_closed_at && $this->work_closed_at->copy()->startOfDay()->lt(today()); }
    public function editableBy(User $user): bool { return $user->can('updateRegistry', $this->client) && ($user->canManageSystem() || !$this->workLocked()); }
    public function workFingerprint(): string { return hash('sha256', json_encode($this->getAttributes(), JSON_THROW_ON_ERROR)); }
    public function editFingerprint(): string
    {
        /** Compare persisted values as well as timestamps, including edits within the same second. */
        $values = $this->getRawOriginal();
        ksort($values);
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }
    public function supplierAgency(): BelongsTo { return $this->belongsTo(SupplierAgency::class); }
    public function importEntry(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(InsurancePolicyImport::class); }
    public function branch(): BelongsTo { return $this->belongsTo(InsuranceBranch::class, 'insurance_branch_id'); }
    public function subbranch(): BelongsTo { return $this->belongsTo(InsuranceSubbranch::class, 'insurance_subbranch_id'); }

    public function asset(): BelongsTo { return $this->belongsTo(ClientAsset::class, 'client_asset_id'); }
    public function consents(): HasMany { return $this->hasMany(ClientConsent::class); }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'expires_on' => 'date', 'premium' => 'decimal:2', 'installment_premium' => 'decimal:2', 'tacit_renewal' => 'boolean', 'ad_horas' => 'boolean', 'merit_class_cu' => 'integer','coverage_until'=>'date','coverage_origin_on'=>'date','closed_at'=>'datetime','work_closed_at'=>'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(ClientVehicle::class, 'vehicle_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->status === 'expired' || (in_array($this->status, ['active', 'renewed'], true) && $this->expires_on->lt(today()));
    }

    public function getStatusLabelAttribute(): string
    {
        if (!in_array($this->status, ['active', 'renewed'], true)) {
            return self::STATUSES[$this->status] ?? $this->status;
        }
        if ($this->is_expired) {
            return 'Scaduta';
        }
        $effectiveStart=$this->status==='renewed' ? ($this->coverage_origin_on ?? $this->starts_on) : $this->starts_on;
        return $effectiveStart->gt(today()) ? 'Decorrenza futura' : ($this->status === 'renewed' ? 'Rinnovata' : 'In vigore');
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? 'Non indicato';
    }
}
