<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class InsuranceClaim extends Model
{
    public const STATUSES = ['open'=>'Aperto','waiting_documents'=>'In attesa documenti','in_management'=>'In gestione presso compagnia/agenzia','waiting_settlement'=>'In attesa liquidazione','closed_paid'=>'Chiuso con liquidazione','closed_unpaid'=>'Chiuso senza liquidazione','cancelled'=>'Annullato per errore'];
    public const CLOSED = ['closed_paid','closed_unpaid','cancelled'];
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['event_on'=>'date','reported_on'=>'date','closed_at'=>'datetime','description'=>'encrypted','policy_snapshot'=>'encrypted:array','version'=>'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw \Illuminate\Validation\ValidationException::withMessages(['claim'=>'Conserva il sinistro e il suo storico. L’admin può annullare una pratica inserita per errore.']));
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function policy(): BelongsTo { return $this->belongsTo(InsurancePolicy::class,'insurance_policy_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class,'assigned_to'); }
    public function activities(): HasMany { return $this->hasMany(InsuranceClaimActivity::class); }
    public function events(): HasMany { return $this->hasMany(InsuranceClaimEvent::class); }
    public function files(): HasMany { return $this->hasMany(Attachment::class); }
    public function nextActivity(): HasOne { return $this->hasOne(InsuranceClaimActivity::class)->ofMany(['due_on'=>'min','id'=>'min'],fn($q)=>$q->where('status','pending')); }
    public function scopeVisibleTo(Builder $query, User $user): Builder { return $query->whereIn('client_id',Client::current()->visibleTo($user)->select('id')); }
    public function scopeOpen(Builder $query): Builder { return $query->whereNotIn('status',self::CLOSED); }
    public function isClosed(): bool { return in_array($this->status,self::CLOSED,true); }
    public function editableBy(User $user): bool { return $user->can('viewAny',Client::class) && $user->can('updateRegistry',$this->client) && (!$this->isClosed() || $user->isAdmin()); }
    public function getReferenceAttribute(): string { return $this->number ?: 'Pratica #'.$this->id; }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? $this->status; }
}
