<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRelationship extends Model
{
    public const TYPES = ['family' => 'Familiare', 'business' => 'Aziendale', 'reference' => 'Referente', 'other' => 'Altra relazione'];

    protected $fillable = ['client_id', 'related_client_id', 'relationship_type', 'client_role', 'related_role', 'notes'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function relatedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'related_client_id');
    }

    public function other(Client $client): Client
    {
        return $client->id === $this->client_id ? $this->relatedClient : $this->client;
    }

    public function roleFor(Client $client): ?string
    {
        return $client->id === $this->client_id ? $this->client_role : $this->related_role;
    }
}
