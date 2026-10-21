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
    public function linkedDocuments() { return $this->belongsToMany(Attachment::class, 'client_document_request_files')->withPivot(['linked_by', 'created_at']); }
    public function currentDocuments(): \Illuminate\Support\Collection
    {
        $files = $this->documents->merge($this->linkedDocuments)->unique('id');
        $replaced = collect();
        foreach ($files as $file) {
            $seen = [];
            while ($file->previous_version_id && !isset($seen[$file->previous_version_id])) {
                $seen[$file->previous_version_id] = true;
                $replaced->push($file->previous_version_id);
                $file = $file->previousVersion;
                if (!$file) break;
            }
        }
        /** Keep a superseded linked file visible until its replacement is explicitly linked to this request. */
        return $files->reject(fn ($file) => $replaced->contains($file->id))->values();
    }
    public function getStateLabelAttribute(): string
    {
        $files = $this->currentDocuments();
        if ($files->isEmpty()) return 'Da ricevere';
        if ($files->contains(fn ($file) => $file->newerVersion !== null)) return 'Documento sostituito: aggiorna collegamento';
        if ($files->contains(fn ($file) => $file->expires_on?->lt(today()))) return 'Documento scaduto';
        if ($files->contains(fn ($file) => $file->review_status !== 'validated' || ($file->document_category === 'identity' && !$file->expires_on))) return 'Ricevuto, da validare';
        return 'Validato';
    }
}
