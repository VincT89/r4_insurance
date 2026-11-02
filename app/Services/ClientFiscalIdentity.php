<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Collection;

class ClientFiscalIdentity
{
    public function matches(array $values, ?Client $client = null): Collection
    {
        $identifiers = array_filter(array_intersect_key($values, array_flip(['vat_number', 'tax_code'])), fn ($value) => is_string($value) && $value !== '');
        if (!$identifiers) return collect();

        return Client::current()->when($client?->exists, fn ($query) => $query->whereKeyNot($client->id))
            ->where(function ($query) use ($identifiers) {
                foreach ($identifiers as $field => $value) $query->orWhere($field, $value);
            })->orderBy('id')->get(['id', 'name', 'vat_number', 'tax_code'])
            ->sortBy(fn ($candidate) => in_array('vat_number', $this->fields($candidate, $identifiers), true) ? 0 : 1)->values();
    }

    public function fields(Client $candidate, array $values): array
    {
        return array_values(array_filter(['vat_number', 'tax_code'], fn ($field) => filled($values[$field] ?? null) && $candidate->$field === $values[$field]));
    }

    public function conflicts(Collection $matches, array $values): bool
    {
        return filled($values['vat_number'] ?? null) && filled($values['tax_code'] ?? null)
            && $matches->contains(fn ($candidate) => $candidate->vat_number === $values['vat_number'])
            && $matches->contains(fn ($candidate) => $candidate->tax_code === $values['tax_code'])
            && $matches->count() > 1;
    }
}
