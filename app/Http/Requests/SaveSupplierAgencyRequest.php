<?php

namespace App\Http\Requests;

use App\Models\SupplierAgency;
use Illuminate\Foundation\Http\FormRequest;

class SaveSupplierAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');
        return $supplier
            ? $this->user()->can('update', $supplier)
            : $this->user()->can('create', SupplierAgency::class);
    }

    public function rules(): array
    {
        return [
            'supplier_company_id' => ['bail', 'required_without:supplier_company_ids', 'nullable', 'integer', 'exists:supplier_companies,id'],
            'supplier_company_ids' => ['sometimes', 'required', 'array', 'min:1', 'max:100'],
            'supplier_company_ids.*' => ['bail', 'required', 'integer', 'distinct', 'exists:supplier_companies,id'],
            'parent_agency_id' => ['bail', 'nullable', 'integer', 'exists:supplier_agencies,id'],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'agency_code' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:20'],
            'tax_code' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'pec' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'reference_person' => ['nullable', 'string', 'max:255'],
            'reference_role' => ['nullable', 'string', 'max:255'],
            'reference_email' => ['nullable', 'email', 'max:255'],
            'reference_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:5'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['email', 'pec', 'reference_email', 'vat_number', 'tax_code', 'province'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $values[$field] = in_array($field, ['email', 'pec', 'reference_email'], true) ? strtolower($value) : strtoupper($value);
            }
        }
        if (! $this->route('supplier')) {
            $values['status'] = $this->input('status', 'active');
        }
        $this->merge($values);
    }

    public function messages(): array
    {
        return [
            'supplier_company_id.required' => 'Seleziona la compagnia di appartenenza.',
            'supplier_company_id.exists' => 'La compagnia selezionata non è disponibile.',
            'supplier_company_id.integer' => 'Seleziona una compagnia valida.',
            'parent_agency_id.exists' => 'L’agenzia principale selezionata non è disponibile.',
            'parent_agency_id.integer' => 'Seleziona un’agenzia principale valida.',
            'name.required' => 'Inserisci il nome dell’agenzia.',
            '*.string' => 'Il campo :attribute deve contenere testo.',
            '*.email' => 'Inserisci un indirizzo email valido per :attribute.',
            '*.max' => 'Il campo :attribute può contenere al massimo :max caratteri.',
            'status.required' => 'Seleziona lo stato dell’agenzia.',
            'status.in' => 'Lo stato deve essere attiva o inattiva.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome agenzia', 'company_name' => 'ragione sociale', 'agency_code' => 'codice agenzia',
            'vat_number' => 'partita IVA', 'tax_code' => 'codice fiscale', 'email' => 'email', 'pec' => 'PEC',
            'phone' => 'telefono', 'mobile' => 'cellulare', 'reference_person' => 'referente',
            'reference_role' => 'ruolo referente', 'reference_email' => 'email referente',
            'reference_phone' => 'telefono referente', 'address' => 'indirizzo', 'city' => 'città',
            'province' => 'provincia', 'postal_code' => 'CAP', 'country' => 'paese', 'notes' => 'note',
        ];
    }
}
