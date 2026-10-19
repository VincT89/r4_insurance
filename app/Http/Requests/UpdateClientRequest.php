<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    use Concerns\ValidatesClientCommercial;
    use Concerns\ValidatesClientRegistry;

    public function authorize(): bool
    {
        return $this->user()->can('updateRegistry', $this->route('client'));
    }

    public function rules(): array
    {
        return array_merge($this->clientRegistryRules($this->route('client')), [
            'commercial_user_id' => $this->commercialUserRules(),
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
            'activity_description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareClientRegistryData();
    }
}
