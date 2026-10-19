<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ClientVehicle extends Model
{
    protected $fillable = ['plate', 'make', 'model', 'vin', 'registered_on', 'notes'];

    protected function casts(): array
    {
        return ['registered_on' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(InsurancePolicy::class, 'vehicle_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Attachment::class, 'vehicle_id');
    }
}
