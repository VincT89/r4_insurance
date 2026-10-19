<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'name',
    'slug',
    'company_name',
    'commercial_user_id',
    'email',
    'phone',
    'normalized_phone',
    'reference_person',
    'vat_number',
    'tax_code',
    'address',
    'city',
    'postal_code',
    'province',
    'country',
    'country_code',
    'billing_email',
    'pec',
    'sdi_code',
    'status',
    'notes',
    'commercial_notes',
    'logo_path',
    'activity_description',
    'client_type', 'title', 'birth_date', 'birth_place', 'mobile', 'fax', 'target',
    'reference_email', 'reference_role', 'reference_phone', 'office', 'consultant',
    'collaborator', 'operator', 'salient_notes',
    'preferred_contact_channel', 'preferred_contact_time', 'contact_preferences',
])]
class Client extends Model
{
    use HasFactory;

    public const CONTACT_CHANNELS = ['phone' => 'Telefono', 'mobile' => 'Cellulare', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'pec' => 'PEC', 'post' => 'Posta'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'merged_at' => 'datetime'];
    }

    public function scopeCurrent(Builder $query): Builder { return $query->whereNull('merged_into_id'); }
    public function importEntry(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RegistryImportEntry::class, 'entity_id')->where('kind', 'client')->where('status', 'imported');
    }
    public function linkedImportEntries(): HasMany
    {
        return $this->hasMany(RegistryImportEntry::class, 'entity_id')->where('kind', 'client')->where('status', 'linked');
    }
    public function contacts(): HasMany { return $this->hasMany(ClientContact::class); }
    public function quoteCases(): HasMany { return $this->hasMany(InsuranceQuoteCase::class); }
    public function formPackages(): HasMany { return $this->hasMany(InsuranceFormPackage::class); }
    public function claims(): HasMany { return $this->hasMany(InsuranceClaim::class); }
    public function documentRequests(): HasMany { return $this->hasMany(ClientDocumentRequest::class); }
    public function assets(): HasMany { return $this->hasMany(ClientAsset::class); }
    public function interactions(): HasMany { return $this->hasMany(ClientInteraction::class); }
    public function consents(): HasMany { return $this->hasMany(ClientConsent::class); }
    public function mergedSources(): HasMany { return $this->hasMany(Client::class, 'merged_into_id'); }
    public function merges(): HasMany { return $this->hasMany(ClientMerge::class, 'target_id'); }
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(ClientHousehold::class, 'client_household_members', 'client_id', 'client_household_id')->withPivot('role')->withTimestamps();
    }
    public function relationships(): Builder
    {
        return ClientRelationship::where(fn ($q) => $q->where('client_id', $this->id)->orWhere('related_client_id', $this->id));
    }

    public function getClientTypeLabelAttribute(): string
    {
        return match ($this->client_type) {
            'person' => 'Persona fisica',
            'company' => 'Azienda',
            default => 'Da classificare',
        };
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(ClientVehicle::class);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(InsurancePolicy::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? URL::temporarySignedRoute(
                'media.public',
                now()->addMinutes(
                    30
                ),
                ['path' => $this->logo_path]
            )
            : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Attivo',
            'inactive' => 'Inattivo',
            default => ucfirst((string) $this->status),
        };
    }

    public function commercialUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_user_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }


    public function authorizedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_user')->withTimestamps();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewAllClients()) {
            return $query;
        }
        return $query->whereRaw('1=0');
    }
}
