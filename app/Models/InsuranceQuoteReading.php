<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceQuoteReading extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['extraction'=>'encrypted:array']; }
    public function document() { return $this->belongsTo(Attachment::class, 'attachment_id'); }
    public function usage() { return $this->belongsTo(AiUsage::class, 'ai_usage_id'); }
}
