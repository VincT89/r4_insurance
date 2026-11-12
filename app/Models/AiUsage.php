<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsage extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['result','request_key','document_hash'];
    protected function casts(): array { return ['result'=>'encrypted:array','reconciliation_note'=>'encrypted','started_at'=>'datetime','completed_at'=>'datetime','reconciled_at'=>'datetime','input_tokens'=>'integer','cached_input_tokens'=>'integer','output_tokens'=>'integer','reasoning_tokens'=>'integer','total_tokens'=>'integer','cost_microusd'=>'integer','reserved_microusd'=>'integer']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function getStateLabelAttribute(): string { return ['counting'=>'Preparazione','reserved'=>'In preparazione','running'=>'In corso','complete'=>'Completata','failed'=>'Non riuscita','uncertain'=>'Costo da verificare','rejected'=>'Limite raggiunto'][$this->state] ?? 'Da verificare'; }
}
