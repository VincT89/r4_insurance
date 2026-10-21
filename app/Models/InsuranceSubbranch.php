<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class InsuranceSubbranch extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['needs_review' => 'boolean', 'source_values' => 'array', 'document_categories' => 'array', 'person_document_categories' => 'array', 'company_document_categories' => 'array']; }
    public function branch(): BelongsTo { return $this->belongsTo(InsuranceBranch::class, 'insurance_branch_id'); }
    public function policies(): HasMany { return $this->hasMany(InsurancePolicy::class); }
    public function getStatusLabelAttribute(): string { return $this->status === 'active' ? 'Attivo' : 'Inattivo'; }
}
