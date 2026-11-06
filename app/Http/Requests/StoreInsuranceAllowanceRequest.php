<?php

namespace App\Http\Requests;

use App\Models\InsuranceAllowance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInsuranceAllowanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ledger.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'allowance_title_id'=>[$this->route('policy') ? 'required' : 'prohibited', 'integer', 'min:1'],
            'allowance_amount'=>['required', 'regex:/\A\d{1,10}([.,]\d{1,2})?\z/'],
            'allowance_on'=>['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'allowance_borne_by'=>['required', Rule::in(array_keys(InsuranceAllowance::BEARERS))],
            'allowance_agency_id'=>[
                'nullable', 'required_if:allowance_borne_by,agency', 'prohibited_unless:allowance_borne_by,agency',
                'integer', Rule::exists('supplier_agencies', 'id')->where('status', 'active'),
            ],
            'allowance_reason'=>['required', 'string', 'min:5', 'max:1000'],
            'allowance_reference'=>['nullable', 'string', 'max:150'],
            'allowance_balance'=>['required', 'integer', 'min:1'],
            'allowance_key'=>['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'allowance_title_id'=>'titolo dell’abbuono', 'allowance_amount'=>'importo dell’abbuono',
            'allowance_on'=>'data dell’abbuono', 'allowance_borne_by'=>'chi sostiene l’abbuono',
            'allowance_agency_id'=>'agenzia che sostiene l’abbuono', 'allowance_reason'=>'motivo dell’abbuono',
            'allowance_reference'=>'riferimento dell’abbuono', 'allowance_balance'=>'residuo del titolo',
            'allowance_key'=>'riferimento della richiesta',
        ];
    }
}
