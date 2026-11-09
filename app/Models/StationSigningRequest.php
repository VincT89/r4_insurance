<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Concerns\HasUuids, Model};

class StationSigningRequest extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['signer_name'=>'encrypted','signer_role'=>'encrypted','note'=>'encrypted','failure_reason'=>'encrypted','expires_at'=>'datetime','identity_confirmed_at'=>'datetime','completed_at'=>'datetime','cancelled_at'=>'datetime'];
    }
    public function station() { return $this->belongsTo(SigningStation::class,'signing_station_id'); }
    public function client() { return $this->belongsTo(Client::class); }
    public function policy() { return $this->belongsTo(InsurancePolicy::class,'insurance_policy_id'); }
    public function author() { return $this->belongsTo(User::class,'created_by'); }
    public function items() { return $this->hasMany(StationSigningItem::class)->orderBy('position'); }
    public function pending(): bool { return $this->status === 'pending' && $this->expires_at->isFuture(); }
    public function getStateLabelAttribute(): string
    {
        return match ($this->status === 'pending' && $this->expires_at->isPast() ? 'expired' : $this->status) {
            'pending'=>'In attesa delle firme', 'completed'=>'Firme raccolte', 'cancelled'=>'Annullata', 'blocked'=>'Da verificare', default=>'Scaduta',
        };
    }
}
