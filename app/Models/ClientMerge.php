<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMerge extends Model
{
    protected $fillable = ['source_id', 'target_id', 'created_by', 'snapshot', 'choices'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'choices' => 'array'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'source_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'target_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
