<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ClientVehicle extends Model
{
    protected $fillable = ['plate', 'make', 'model', 'vin', 'registered_on', 'notes', 'merit_class_cu', 'merit_class_internal', 'merit_class_company_id'];

    protected function casts(): array
    {
        return ['registered_on' => 'date', 'merit_class_cu' => 'integer'];
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

    public function quoteCases(): HasMany
    {
        return $this->hasMany(InsuranceQuoteCase::class, 'vehicle_id');
    }

    public function meritClassCompany(): BelongsTo
    {
        return $this->belongsTo(SupplierCompany::class, 'merit_class_company_id');
    }
}
