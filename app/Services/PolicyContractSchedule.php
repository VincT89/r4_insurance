<?php

namespace App\Services;

use App\Models\InsurancePolicy;
use Illuminate\Validation\Rule;

class PolicyContractSchedule
{
    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'expires_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
            'premium' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'installment_premium' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'payment_frequency' => ['required', Rule::in(InsurancePolicy::PAYMENT_FREQUENCIES)],
            'payment_method' => ['required', Rule::in(InsurancePolicy::PAYMENT_METHODS)],
            'status' => ['required', Rule::in(['active', 'renewed'])],
        ];
    }

    public function fingerprint(array $rows): string
    {
        /** Bind the displayed schedule to this operator; final creation recomputes it from validated policy data. */
        return hash_hmac('sha256', json_encode([auth()->id(), $rows], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
