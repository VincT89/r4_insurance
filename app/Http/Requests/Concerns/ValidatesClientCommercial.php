<?php

namespace App\Http\Requests\Concerns;

use App\Enums\UserRole;
use App\Models\Client;
use Illuminate\Validation\Rule;

trait ValidatesClientCommercial
{
    protected function commercialUserRules(): array
    {
        if (! $this->user()->canManageSystem()) {
            return ['missing'];
        }

        $client = $this->route('client');

        return ['nullable', 'integer', Rule::exists('users', 'id')->where(function ($query) use ($client) {
            $query->where(function ($eligible) use ($client) {
                $eligible->where(fn ($active) => $active->where('role', UserRole::Operator->value)->where('status', 'active'));

                // Mantiene selezionabile il referente attuale anche se disattivato.
                if ($client instanceof Client && $client->commercial_user_id) {
                    $eligible->orWhere('id', $client->commercial_user_id);
                }
            });
        })];
    }
}
