<?php

namespace App\Http\Requests;

use App\Models\InsuranceAllowanceRecovery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInsuranceAllowanceRecoveryRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('ledger.manage') ?? false; }

    public function rules(): array
    {
        return [
            'recovery_amount'=>['required', 'regex:/\A\d{1,10}([.,]\d{1,2})?\z/'],
            'recovery_on'=>['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'recovery_method'=>['required', Rule::in(array_keys(InsuranceAllowanceRecovery::METHODS))],
            'recovery_reference'=>['nullable', 'string', 'max:150'],
            'recovery_notes'=>['nullable', 'string', 'max:1000'],
            'recovery_key'=>['required', 'uuid'],
            'recovery_fingerprint'=>['required', 'string', 'size:64'],
        ];
    }

    public function attributes(): array
    {
        return ['recovery_amount'=>'importo del rientro', 'recovery_on'=>'data del rientro', 'recovery_method'=>'modalità del rientro',
            'recovery_reference'=>'riferimento del rientro', 'recovery_notes'=>'note del rientro', 'recovery_key'=>'riferimento della richiesta',
            'recovery_fingerprint'=>'versione del recupero'];
    }
}
