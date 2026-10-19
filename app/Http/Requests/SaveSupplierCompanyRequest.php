<?php

namespace App\Http\Requests;

use App\Models\SupplierCompany;
use Illuminate\Foundation\Http\FormRequest;

class SaveSupplierCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');
        return $company ? $this->user()->can('update', $company) : $this->user()->can('create', SupplierCompany::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'reference_person' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'quote_priority'=>['sometimes','boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) $this->merge(['name' => preg_replace('/\s+/u', ' ', trim($this->input('name')))]);
        if (is_string($this->input('email'))) $this->merge(['email' => strtolower(trim($this->input('email')))]);
        if (! $this->route('company')) $this->merge(['status' => $this->input('status', 'active')]);
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) return;
            if (SupplierCompany::whereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper($this->input('name'))])
                ->when($this->route('company'), fn ($q, $company) => $q->where('id', '!=', $company->id))->exists()) {
                $validator->errors()->add('name', 'Questa compagnia è già presente. Usa la scheda esistente per collegare altre agenzie.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Inserisci il nome della compagnia.',
            '*.string' => 'Il campo :attribute deve contenere testo.',
            '*.max' => 'Il campo :attribute può contenere al massimo :max caratteri.',
            'email.email' => 'Inserisci un indirizzo email valido.',
            'status.required' => 'Seleziona lo stato della compagnia.',
            'status.in' => 'Lo stato deve essere attiva o inattiva.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome compagnia', 'phone' => 'telefono', 'reference_person' => 'referente', 'notes' => 'note'];
    }
}
