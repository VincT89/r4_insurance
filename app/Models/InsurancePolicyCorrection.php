<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsurancePolicyCorrection extends Model
{
    public const NOTIFICATION_TYPE = 'policy_correction';
    public const STATUSES = ['open' => 'Da esaminare', 'corrected' => 'Correzione effettuata', 'no_change' => 'Nessuna modifica necessaria'];

    protected $fillable = ['insurance_policy_id', 'requested_by', 'request_key', 'message', 'status', 'resolution', 'resolved_by', 'resolved_at'];
    protected function casts(): array { return ['resolved_at' => 'datetime']; }
    public function policy(): BelongsTo { return $this->belongsTo(InsurancePolicy::class, 'insurance_policy_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
}
