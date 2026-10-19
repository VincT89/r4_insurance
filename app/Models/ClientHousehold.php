<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClientHousehold extends Model
{
    protected $fillable = ['name', 'notes'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_household_members')->withPivot('role')->withTimestamps();
    }
}
