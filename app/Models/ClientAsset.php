<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientAsset extends Model
{
    public const TYPES = ['property' => 'Immobile', 'other' => 'Altro bene'];

    protected $fillable = ['asset_type', 'name', 'category', 'identifier', 'address', 'city', 'province', 'postal_code', 'country', 'notes'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(InsurancePolicy::class);
    }
}
