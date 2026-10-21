<?php

namespace App\Models;


use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'status',
    'primary_specialization',
    'role',
    'password_changed_at',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    // Trait per factory e notifiche
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($reason = $user->deletionBlockReason()) {
                throw ValidationException::withMessages(['user' => $reason]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'appearance' => 'array',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Attivo',
            'inactive' => 'Inattivo (sospeso)',
            default => ucfirst((string) $this->status),
        };
    }

    public function assignedClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_user')->withTimestamps();
    }

    public function visibleNotifications(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->notifications()->where(function ($query) {
            $query->whereNotIn('type', [\App\Services\ClaimReminders::TYPE, \App\Services\DeadlineReminders::TYPE, InsurancePolicyCorrection::NOTIFICATION_TYPE, \App\Services\CollectionCommunicationReminders::TYPE, \App\Services\CollectionCommunicationReminders::REVERSAL_TYPE])
                ->orWhere(function ($query) {
                    $query->where('type', InsurancePolicyCorrection::NOTIFICATION_TYPE)->whereIn('data->client_id', Client::current()->visibleTo($this)->select('id'));
                })->orWhere(function ($query) {
                    app(\App\Services\DeadlineReminders::class)->pending($query, $this);
                })->orWhere(function ($query) {
                    app(\App\Services\CollectionCommunicationReminders::class)->pending($query, $this);
                })->orWhere(function ($query) {
                    $query->where('type',\App\Services\ClaimReminders::TYPE)->whereIn('data->activity_id',InsuranceClaimActivity::pending()->visibleTo($this)->whereHas('claim',fn($q)=>$q->when(!$this->isAdmin(),fn($q)=>$q->where('assigned_to',$this->id)))->select('id'));
                    if (!$this->can('viewAny',Client::class) || $this->status!=='active') $query->whereRaw('1=0');
                });
        });
    }

    public function canViewAllClients(): bool
    {
        return $this->status === 'active' && ($this->isAdmin() || $this->isOperator());
    }

    public function dailyNotes(): HasMany
    {
        return $this->hasMany(UserDailyNote::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function hasClientDocuments(): bool
    {
        return Attachment::where('uploaded_by', $this->id)->exists();
    }

    public function hasRetainedHistory(): bool
    {
        /** Keep author and assignment references, including records that do not produce audit events. */
        $references = [
            'audit_logs' => ['user_id'],
            'attachments' => ['uploaded_by', 'validated_by'],
            'clients' => ['commercial_user_id'],
            'client_user' => ['user_id'],
            'client_interactions' => ['created_by', 'updated_by'],
            'client_consents' => ['created_by'],
            'client_merges' => ['created_by'],
            'client_document_requests' => ['requested_by'],
            'client_document_request_files' => ['linked_by'],
            'outbound_mail_settings' => ['updated_by'],
            'agency_communications' => ['user_id'],
            'insurance_quote_cases' => ['created_by'],
            'insurance_quotes' => ['approved_by'],
            'insurance_titles' => ['created_by'],
            'insurance_collections' => ['created_by', 'consultant_id', 'voided_by'],
            'insurance_policies' => ['work_closed_by'],
            'insurance_policy_corrections' => ['requested_by', 'resolved_by'],
            'insurance_quote_emails' => ['user_id'],
            'agency_commission_schedules' => ['created_by', 'voided_by'],
            'insurance_commission_movements' => ['created_by'],
            'insurance_form_templates' => ['created_by', 'approved_by'],
            'insurance_form_packages' => ['created_by'],
            'insurance_form_submissions' => ['created_by', 'reviewed_by'],
            'insurance_coverage_checks' => ['created_by'],
            'insurance_claims' => ['assigned_to', 'created_by'],
            'insurance_claim_activities' => ['completed_by'],
            'insurance_claim_events' => ['created_by'],
        ];

        foreach ($references as $table => $columns) {
            if (DB::table($table)->where(function ($query) use ($columns) {
                foreach ($columns as $column) {
                    $query->orWhere($column, $this->getKey());
                }
            })->exists()) {
                return true;
            }
        }

        return false;
    }

    public function deletionBlockReason(): ?string
    {
        if ($this->isLastActiveAdministrator()) {
            return 'Deve rimanere almeno un amministratore attivo.';
        }

        return $this->hasRetainedHistory()
            ? 'Questo account ha attività o dati collegati. Un amministratore può disattivarlo, conservando lo storico e i suoi autori.'
            : null;
    }

    public function deleteUnusedAccount(?\Closure $beforeDelete = null): void
    {
        DB::transaction(function () use ($beforeDelete) {
            /** Lock the referenced user while checking history; log out only after deletion is permitted. */
            $user = static::query()->lockForUpdate()->findOrFail($this->getKey());
            if ($reason = $user->deletionBlockReason()) {
                throw ValidationException::withMessages(['user' => $reason]);
            }
            $beforeDelete?->__invoke();
            $user->delete();
        });
    }

    public function isLastActiveAdministrator(): bool
    {
        if (! $this->isAdmin() || $this->status !== 'active') {
            return false;
        }

        return ! static::query()
            ->where('role', UserRole::Admin->value)
            ->where('status', 'active')
            ->whereKeyNot($this->getKey())
            ->exists();
    }

    public function canManageSystem(): bool
    {
        return $this->isAdmin();
    }

    public function isOperator(): bool
    {
        return $this->role === UserRole::Operator;
    }

    public function canManageClientMaterials(): bool
    {
        return $this->canViewAllClients();
    }

    public function canViewManagementDashboard(): bool
    {
        return $this->isAdmin();
    }

    public function canViewAuditLogs(): bool
    {
        return $this->isAdmin();
    }

    public function hasOperationalDashboard(): bool
    {
        return $this->isOperator();
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }
}
