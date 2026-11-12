<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['api_key'];
    protected function casts(): array { return ['api_key'=>'encrypted','enabled'=>'boolean','monthly_budget_microusd'=>'integer','document_budget_microusd'=>'integer']; }
}
