<?php

namespace App\Domain\Core\Queries;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Collection;

class ClientDuplicateQuery
{
    public function forClient(Client $client, User $user): Collection
    {
        $phones = array_values(array_filter([$client->normalized_phone, $client->normalized_mobile]));
        $keys = array_filter($client->only(['registry_name_key', 'tax_code', 'vat_number', 'email']));
        if (! $keys && ! $phones) {
            return collect();
        }

        return Client::current()->visibleTo($user)->whereKeyNot($client->id)->where(function ($query) use ($keys, $phones) {
            foreach ($keys as $field => $value) {
                $query->orWhere($field, $value);
            }
            if ($phones) {
                $query->orWhereIn('normalized_phone', $phones)->orWhereIn('normalized_mobile', $phones);
            }
        })->orderBy('name')->get()->map(function ($candidate) use ($keys, $phones) {
            $reasons = [];
            foreach (['registry_name_key' => 'stesso nome', 'tax_code' => 'stesso codice fiscale', 'vat_number' => 'stessa partita IVA', 'email' => 'stessa email'] as $field => $label) {
                if (isset($keys[$field]) && mb_strtolower($candidate->$field ?? '') === mb_strtolower($keys[$field])) {
                    $reasons[] = $label;
                }
            }
            if (array_intersect($phones, array_filter([$candidate->normalized_phone, $candidate->normalized_mobile]))) {
                $reasons[] = 'recapito telefonico in comune';
            }

            return ['client' => $candidate, 'reasons' => $reasons];
        });
    }
}
