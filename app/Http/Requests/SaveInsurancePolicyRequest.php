<?php

namespace App\Http\Requests;

use App\Models\InsurancePolicy;
use Illuminate\Validation\Rule;

class SaveInsurancePolicyRequest extends ClientInsuranceRequest
{
    private array $contractClientValues = [];

    public function isContractCreation(): bool
    {
        return $this->isMethod('POST') && in_array($this->input('entry_mode'), ['new', 'contract'], true);
    }

    public function newClientValues(): array
    {
        return $this->contractClientValues;
    }

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
            'last_renewed_at' => ['prohibited'],
            'correction_reason' => $this->isMethod('POST') ? ['prohibited'] : ['nullable', Rule::requiredIf(fn () => (bool) $this->route('policy')?->work_closed_at && $this->user()->canManageSystem()), 'string', 'min:10', 'max:3000'],
            'entry_mode'=>$this->isMethod('POST') ? ['required','in:new,contract,existing'] : ['prohibited'],
            'insurance_quote_id'=>$this->isMethod('POST') ? ['nullable','required_if:entry_mode,new','integer'] : ['prohibited'],
            'existing_reason'=>$this->isMethod('POST') ? ['nullable','required_if:entry_mode,existing','string','min:5','max:1000'] : ['prohibited'],
            'client_id' => $this->route('client') ? ['prohibited'] : ['nullable', 'required_without:new_client', 'integer', Rule::exists('clients', 'id')->whereNull('merged_into_id')],
            'new_client' => $this->isContractCreation() && !$this->route('client') && !$this->filled('client_id') && $this->input('entry_mode') === 'contract' ? ['nullable', 'array:name,client_type,company_name,tax_code,vat_number,email,phone,address,city,postal_code,province'] : ['prohibited'],
            'contract' => $this->isContractCreation() ? ['required', 'file', 'max:10240', 'mimes:pdf', 'extensions:pdf'] : ['prohibited'],
            'contract_reviewed' => $this->isContractCreation() ? ['accepted'] : ['prohibited'],
            'quote_changes_confirmed' => $this->isContractCreation() ? ['sometimes', 'boolean'] : ['prohibited'],
            'titles_mode' => $this->isContractCreation() ? ['required', 'in:automatic,manual'] : ['prohibited'],
            'schedule_fingerprint' => $this->isContractCreation() ? ['nullable', 'required_if:titles_mode,automatic', 'string', 'regex:/\A[a-f0-9]{64}\z/'] : ['prohibited'],
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
            'status' => ['required', Rule::in($this->isContractCreation() ? ['active','renewed'] : array_keys(InsurancePolicy::STATUSES))],
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
            'contract.required' => 'Carica il contratto PDF: contiene anche il numero di polizza.',
            'contract_reviewed.accepted' => 'Controlla i dati letti dal contratto e confermali prima di creare la polizza.',
            'schedule_fingerprint.required_if' => 'Controlla l’anteprima dei titoli prima di creare la polizza.',
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
            if ($this->isContractCreation() && $this->input('titles_mode') === 'automatic' && !$this->user()->can('ledger.manage')) $validator->errors()->add('titles_mode', 'Non sei autorizzato a generare i titoli.');
            if ($this->input('entry_mode') === 'contract' && $this->filled('insurance_quote_id')) $validator->errors()->add('insurance_quote_id', 'Usa il percorso del preventivo selezionato per conservarne il collegamento.');
            if (!$validator->errors()->has('new_client') && is_array($this->input('new_client')) && $this->input('new_client')) {
                /** Reuse registry normalization, both fiscal checks and authorization before the atomic creation. */
                $clientValues = $this->input('new_client');
                if (($clientValues['client_type'] ?? null) === 'company' && empty($clientValues['company_name'])) $clientValues['company_name'] = $clientValues['name'] ?? null;
                $registry = QuickStoreClientRequest::create('/', 'POST', $clientValues);
                $registry->setContainer(app())->setRedirector(app('redirect'))->setUserResolver($this->getUserResolver());
                try {
                    $registry->validateResolved();
                    $this->contractClientValues = $registry->validated();
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) foreach ($messages as $message) $validator->errors()->add('new_client.'.$field, $message);
                }
            }
            if($this->isMethod('POST') && $this->input('entry_mode')==='existing' && !$this->user()->canManageSystem()) $validator->errors()->add('entry_mode','Solo l’admin può registrare polizze già stipulate.');
            $policy = $this->route('policy');
            $vehicle = $this->exists('vehicle_id') ? $this->input('vehicle_id') : $policy?->vehicle_id;
            $asset = $this->exists('client_asset_id') ? $this->input('client_asset_id') : $policy?->client_asset_id;
            if ($vehicle && $asset) $validator->errors()->add('client_asset_id', 'Seleziona il veicolo oppure il bene assicurabile.');
        }];
    }
}
