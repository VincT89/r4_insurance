<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInteraction extends Model
{
    public const CHANNELS = ['phone' => 'Telefono', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'in_person' => 'Incontro', 'note' => 'Nota', 'other' => 'Altro'];

    protected $fillable = ['event_at', 'channel', 'subject', 'notes', 'outcome', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['event_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
