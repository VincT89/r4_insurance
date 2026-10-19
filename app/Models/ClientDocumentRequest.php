<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ClientDocumentRequest extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['due_on' => 'date']; }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function insurancePolicy(): BelongsTo { return $this->belongsTo(InsurancePolicy::class); }
    public function branch(): BelongsTo { return $this->belongsTo(InsuranceBranch::class, 'insurance_branch_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function documents(): HasMany { return $this->hasMany(Attachment::class, 'document_request_id'); }
    public function getStateLabelAttribute(): string
    {
        $document = $this->documents->sortByDesc('version_number')->first();
        if (!$document) return 'Da ricevere';
        if ($document->expires_on?->lt(today())) return 'Documento scaduto';
        return $document->review_status === 'validated' ? 'Validato' : 'Ricevuto, da validare';
    }
}
