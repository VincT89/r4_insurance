<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

abstract class ClientInsuranceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');
        if (! $client instanceof Client || ! $this->user()->can('updateRegistry', $client)) {
            return false;
        }
        foreach (['policy', 'vehicle'] as $parameter) {
            if ($record = $this->route($parameter)) {
                abort_unless((int) $record->client_id === (int) $client->id, 404);
            }
        }
        if ($document = $this->route('document')) {
            abort_unless($document->attachable_type === $client->getMorphClass()
                && (int) $document->attachable_id === (int) $client->id
                && $document->document_category !== null, 404);
        }
        return true;
    }
}
