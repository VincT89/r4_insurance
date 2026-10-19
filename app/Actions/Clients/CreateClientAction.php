<?php

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Str;

class CreateClientAction
{
    public function execute(array $data): Client
    {
        if (auth()->user()?->isOperator()) {
            $data['commercial_user_id'] = auth()->id();
        }

        // Genera uno slug univoco basato sul nome
        $baseSlug = Str::slug($data['name']);
        $slug = $baseSlug;
        $counter = 1;

        while (Client::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $data['slug'] = $slug;

        // Imposta lo status di default se non fornito
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }

        $logo = data_get($data, 'logo');
        unset($data['logo']);

        if ($logo) {
            $data['logo_path'] = $logo->store('clients/logos', 'public');
        }

        return Client::create($data);
    }
}
