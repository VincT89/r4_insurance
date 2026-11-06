<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceAllowanceRecovery extends Model
{
    public const METHODS = ['transfer'=>'Bonifico', 'offset'=>'Compensazione', 'cheque'=>'Assegno', 'cash'=>'Contanti', 'other'=>'Altro'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_cents'=>'integer', 'received_on'=>'date', 'voided_at'=>'datetime'];
    }

    public function allowance(): BelongsTo { return $this->belongsTo(InsuranceAllowance::class, 'insurance_allowance_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function voidedBy(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
}
