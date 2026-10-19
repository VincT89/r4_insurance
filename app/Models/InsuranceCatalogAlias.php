<?php

namespace App\Models;

use App\Services\InsuranceCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceCatalogAlias extends Model
{
    protected $guarded = ['id'];

    public static function sourceKey(string $branch, ?string $subbranch = null): string
    {
        return hash('sha256', json_encode([InsuranceCatalog::normalize($branch), InsuranceCatalog::normalize($subbranch ?? '')], JSON_THROW_ON_ERROR));
    }

    public function branch(): BelongsTo { return $this->belongsTo(InsuranceBranch::class, 'insurance_branch_id'); }
    public function subbranch(): BelongsTo { return $this->belongsTo(InsuranceSubbranch::class, 'insurance_subbranch_id'); }
}
