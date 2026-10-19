<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class QuickStoreClientRequest extends FormRequest
{
    use Concerns\ValidatesClientRegistry;

    public function authorize(): bool
    {
        return $this->user()->can('quickCreate', Client::class);
    }

    public function rules(): array
    {
        return array_merge($this->clientRegistryRules(), [
            'commercial_user_id' => ['missing'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareClientRegistryData();
    }
}
