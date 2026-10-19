<?php

namespace App\Http\Requests\Concerns;

use App\Models\Client;
use Illuminate\Validation\Rule;

trait ValidatesClientRegistry
{
    protected function clientRegistryRules(?Client $client = null): array
    {
        // Imported households may legitimately share an address. Keeping their existing
        // contact must not prevent editing; assigning a different address still checks duplicates.
        $emailUnchanged = $client && mb_strtolower(trim((string) $client->email)) === mb_strtolower(trim((string) $this->input('email')));
        return [
            'preferred_contact_channel' => ['nullable', Rule::in(array_keys(Client::CONTACT_CHANNELS))],
            'preferred_contact_time' => ['nullable', 'string', 'max:255'],
            'contact_preferences' => ['nullable', 'string', 'max:10000'],
            'client_type' => ['sometimes', 'required', Rule::in(['person', 'company'])],
            'title' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'prohibited_if:client_type,company'],
            'birth_place' => ['nullable', 'string', 'max:255', 'prohibited_if:client_type,company'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'target' => ['nullable', 'string', 'max:255'],
            'reference_email' => ['nullable', 'email', 'max:255'],
            'reference_role' => ['nullable', 'string', 'max:255'],
            'reference_phone' => ['nullable', 'string', 'max:50'],
            'office' => ['nullable', 'string', 'max:255'],
            'consultant' => ['nullable', 'string', 'max:255'],
            'collaborator' => ['nullable', 'string', 'max:255'],
            'operator' => ['nullable', 'string', 'max:255'],
            'salient_notes' => ['nullable', 'string', 'max:10000'],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', ...($emailUnchanged ? [] : [Rule::unique('clients', 'email')->whereNull('merged_into_id')->ignore($client)])],
            'phone' => ['nullable', 'string', 'max:50'],
            'reference_person' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:20', Rule::unique('clients', 'vat_number')->whereNull('merged_into_id')->ignore($client)],
            'tax_code' => ['nullable', 'string', 'max:20', Rule::unique('clients', 'tax_code')->whereNull('merged_into_id')->ignore($client)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'province' => ['nullable', 'string', 'max:5'],
            'country' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'pec' => ['nullable', 'email', 'max:255'],
            'sdi_code' => ['nullable', 'string', 'min:6', 'max:7', 'regex:/^[A-Z0-9]+$/'],
            'commercial_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareClientRegistryData(): void
    {
        $normalized = [];
        foreach (['email', 'reference_email', 'billing_email', 'pec', 'vat_number', 'tax_code', 'country_code', 'province', 'sdi_code'] as $field) {
            $value = $this->input($field);
            if (! $this->exists($field) || ! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $normalized[$field] = $value === '' ? null : (
                in_array($field, ['email', 'reference_email', 'billing_email', 'pec'], true) ? strtolower($value) : strtoupper($value)
            );
        }
        $this->merge($normalized);
    }

    public function messages(): array
    {
        $duplicate = 'Già presente in anagrafica. Cerca il cliente oppure chiedi all’amministratore di associartelo.';

        return [
            'email.unique' => 'Email già utilizzata. '.$duplicate,
            'vat_number.unique' => 'Partita IVA già utilizzata. '.$duplicate,
            'tax_code.unique' => 'Codice fiscale già utilizzato. '.$duplicate,
            'birth_date.prohibited_if' => 'La data di nascita riguarda una persona fisica.',
            'birth_place.prohibited_if' => 'Il luogo di nascita riguarda una persona fisica.',
            'commercial_user_id.missing' => 'Il commerciale viene associato automaticamente. Le riassegnazioni sono gestite dall’amministratore.',
        ];
    }
}
