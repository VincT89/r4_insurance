<?php

namespace App\Http\Requests;

use App\Models\InsurancePolicy;
use Illuminate\Validation\Rule;

class SaveInsurancePolicyRequest extends ClientInsuranceRequest
{
    public function policyClient(): ?\App\Models\Client
    {
        return $this->route('client') ?? \App\Models\Client::current()->find($this->integer('client_id'));
    }

    public function authorize(): bool
    {
        if ($policy=$this->route('policy')) if(!$policy->editableBy($this->user())) return false;
        if ($this->route('client')) return parent::authorize();
        $client = $this->policyClient();
        return $client ? $this->user()->can('updateRegistry', $client) : $this->user()->can('create', \App\Models\Client::class);
    }

    public function rules(): array
    {
        $clientId = $this->policyClient()?->id;
        return [
            'policy_revision' => $this->isMethod('POST') ? ['prohibited'] : ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'work_closed_at' => ['prohibited'], 'work_closed_by' => ['prohibited'],
            'correction_reason' => $this->isMethod('POST') ? ['prohibited'] : ['nullable', Rule::requiredIf(fn () => (bool) $this->route('policy')?->work_closed_at && $this->user()->canManageSystem()), 'string', 'min:10', 'max:3000'],
            'entry_mode'=>$this->isMethod('POST') ? ['required','in:new,existing'] : ['prohibited'],
            'insurance_quote_id'=>$this->isMethod('POST') ? ['nullable','required_if:entry_mode,new','integer'] : ['prohibited'],
            'existing_reason'=>$this->isMethod('POST') ? ['nullable','required_if:entry_mode,existing','string','min:5','max:1000'] : ['prohibited'],
            'client_id' => $this->route('client') ? ['prohibited'] : ['required', 'integer', Rule::exists('clients', 'id')->whereNull('merged_into_id')],
            'category' => ['required', 'string', 'max:100'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'company' => ['required', 'string', 'max:150'],
            'supplier_agency_id' => ['nullable', 'integer', Rule::exists('supplier_agencies', 'id')],
            'number' => ['required', 'string', 'max:100', Rule::unique('insurance_policies')->where('client_id', $clientId)->where('company', $this->input('company'))->ignore($this->route('policy'))],
            'product' => ['nullable', 'string', 'max:255'],
            'insured_subject' => ['nullable', 'string', 'max:255'],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('client_vehicles', 'id')->where('client_id', $clientId)],
            'client_asset_id' => ['nullable', 'integer', Rule::exists('client_assets', 'id')->where('client_id', $clientId)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'expires_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'premium' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'status' => ['required', Rule::in(array_keys(InsurancePolicy::STATUSES))],
            'payment_status' => ['prohibited'],
            'merit_class_cu' => ['nullable', 'integer', 'between:1,18'],
            'merit_class_internal' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['nullable', 'string', 'max:150'],
            'payment_frequency' => ['nullable', Rule::in(InsurancePolicy::PAYMENT_FREQUENCIES)],
            'installment_premium' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'tacit_renewal' => ['sometimes', 'nullable', 'boolean'],
            'ad_horas' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'documents' => $this->isMethod('POST') ? ['nullable', 'array', 'max:10'] : ['prohibited'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf', 'extensions:pdf'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['category', 'subcategory', 'product', 'company', 'number', 'payment_method'] as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = preg_replace('/\s+/u', ' ', trim($this->input($field)));
            }
        }
        foreach (InsurancePolicy::CATEGORY_SUGGESTIONS as $category) {
            if (mb_strtolower($values['category'] ?? '') === mb_strtolower($category)) {
                $values['category'] = $category;
            }
        }
        foreach (['premium', 'installment_premium'] as $field) if (is_string($this->input($field))) $values[$field] = str_replace(',', '.', $this->input($field));
        $this->merge($values);
    }

    public function messages(): array
    {
        return [
            'policy_revision.required' => 'Riapri il modulo della polizza: manca il riferimento ai dati da modificare.',
            'policy_revision.regex' => 'Riapri il modulo della polizza: il riferimento ai dati non è valido.',
            'number.unique' => 'Questa polizza è già presente per il cliente e la compagnia indicati.',
            'vehicle_id.exists' => 'Seleziona un veicolo di questo cliente.',
            'client_asset_id.exists' => 'Seleziona un bene di questo cliente.',
            'expires_on.after_or_equal' => 'La scadenza non può precedere la decorrenza.',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if($this->isMethod('POST') && $this->input('entry_mode')==='existing' && !$this->user()->canManageSystem()) $validator->errors()->add('entry_mode','Solo l’admin può registrare polizze già stipulate.');
            $policy = $this->route('policy');
            $vehicle = $this->exists('vehicle_id') ? $this->input('vehicle_id') : $policy?->vehicle_id;
            $asset = $this->exists('client_asset_id') ? $this->input('client_asset_id') : $policy?->client_asset_id;
            if ($vehicle && $asset) $validator->errors()->add('client_asset_id', 'Seleziona il veicolo oppure il bene assicurabile.');
        }];
    }
}
