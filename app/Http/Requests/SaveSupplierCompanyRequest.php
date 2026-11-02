<?php

namespace App\Http\Requests;

use App\Models\SupplierCompany;
use Illuminate\Foundation\Http\FormRequest;

class SaveSupplierCompanyRequest extends FormRequest
{
    use Concerns\ValidatesBankDetails;
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
            'company_name'=>['nullable','string','max:255'],
            'vat_number'=>['nullable','string','max:20'],
            'tax_code'=>['nullable','string','max:20'],
            'pec'=>['nullable','email','max:255'],
            'mobile'=>['nullable','string','max:50'],
            'reference_role'=>['nullable','string','max:255'],
            'reference_email'=>['nullable','email','max:255'],
            'reference_phone'=>['nullable','string','max:50'],
            'address'=>['nullable','string','max:255'],
            'city'=>['nullable','string','max:100'],
            'province'=>['nullable','string','max:5'],
            'postal_code'=>['nullable','string','max:10'],
            'country'=>['nullable','string','max:100'],
        ] + $this->bankRules();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeBankDetails();
        foreach (['pec','reference_email','vat_number','tax_code','province'] as $field) {
            if (is_string($this->input($field))) $this->merge([$field=>in_array($field,['pec','reference_email'],true) ? strtolower(trim($this->input($field))) : strtoupper(trim($this->input($field)))]);
        }
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
            '*.email' => 'Inserisci un indirizzo email valido per :attribute.',
            'status.required' => 'Seleziona lo stato della compagnia.',
            'status.in' => 'Lo stato deve essere attiva o inattiva.',
        ] + $this->bankMessages();
    }

    public function attributes(): array
    {
        return ['name'=>'nome compagnia','company_name'=>'ragione sociale','vat_number'=>'partita IVA','tax_code'=>'codice fiscale','pec'=>'PEC','phone'=>'telefono','mobile'=>'cellulare','reference_person'=>'referente','reference_role'=>'ruolo referente','reference_email'=>'email referente','reference_phone'=>'telefono referente','address'=>'indirizzo','city'=>'città','province'=>'provincia','postal_code'=>'CAP','country'=>'paese','notes'=>'note'] + $this->bankAttributes();
    }
}
