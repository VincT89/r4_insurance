<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceQuoteReview extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['overrides'=>'encrypted:array', 'history'=>'encrypted:array', 'verification_note'=>'encrypted', 'revision'=>'integer', 'verified_at'=>'datetime']; }
    public function quote() { return $this->belongsTo(InsuranceQuote::class, 'insurance_quote_id'); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
}
