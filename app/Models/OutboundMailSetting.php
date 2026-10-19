<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboundMailSetting extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['credentials'];
    protected function casts(): array { return ['credentials' => 'encrypted:array']; }
}
