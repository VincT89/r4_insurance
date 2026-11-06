<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceAllowance extends Model
{
    public const BEARERS = ['company'=>'Compagnia del titolo', 'our_agency'=>'Nostra agenzia', 'agency'=>'Altra agenzia'];
    public const RECOVERY_STATES = ['open'=>'Da recuperare', 'partial'=>'Recupero parziale', 'recovered'=>'Recuperato'];

    protected $guarded = ['id'];
    protected $attributes = ['recovered_cents'=>0, 'recovery_revision'=>0];

    protected function casts(): array
    {
        return ['amount_cents'=>'integer', 'recovered_cents'=>'integer', 'recovery_revision'=>'integer', 'effective_on'=>'date', 'voided_at'=>'datetime'];
    }

    public function title(): BelongsTo { return $this->belongsTo(InsuranceTitle::class, 'insurance_title_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function voidedBy(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
    public function supplierAgency(): BelongsTo { return $this->belongsTo(SupplierAgency::class); }
    public function recoveries(): HasMany { return $this->hasMany(InsuranceAllowanceRecovery::class); }

    public function getRecoverableCentsAttribute(): int
    {
        return $this->borne_by === 'agency' && !$this->voided_at ? max(0, $this->amount_cents - $this->recovered_cents) : 0;
    }

    public function getRecoveryStateAttribute(): string
    {
        if ($this->voided_at) return 'voided';
        if ($this->borne_by !== 'agency') return 'not_applicable';
        return $this->recoverable_cents === 0 ? 'recovered' : ($this->recovered_cents > 0 ? 'partial' : 'open');
    }

    public function getRecoveryStateLabelAttribute(): string
    {
        return self::RECOVERY_STATES[$this->recovery_state] ?? ($this->voided_at ? 'Abbuono stornato' : 'Non previsto');
    }

    public function recoveryFingerprint(): string
    {
        return hash('sha256', json_encode([$this->id, $this->amount_cents, $this->borne_by, $this->supplier_agency_id,
            $this->recovered_cents, $this->recovery_revision, $this->voided_at?->toIso8601String()], JSON_THROW_ON_ERROR));
    }

    public function getBearerLabelAttribute(): string
    {
        return $this->borne_by === 'our_agency' ? self::BEARERS['our_agency'] : self::BEARERS[$this->borne_by].' / '.$this->bearer_name;
    }
}
