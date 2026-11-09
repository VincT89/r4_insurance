<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Concerns\HasUuids, Model};

class SigningStation extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected $hidden = ['credential_hash','pairing_code','pairing_code_hash'];
    protected function casts(): array
    {
        return ['pairing_code'=>'encrypted','pairing_expires_at'=>'datetime','credential_expires_at'=>'datetime','paired_at'=>'datetime','last_seen_at'=>'datetime','revoked_at'=>'datetime'];
    }
    public function requests() { return $this->hasMany(StationSigningRequest::class); }
    public function pairedBy() { return $this->belongsTo(User::class,'paired_by'); }
    public function usable(): bool { return $this->status === 'active' && $this->credential_expires_at->isFuture(); }
}
