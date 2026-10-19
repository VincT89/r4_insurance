<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SaveClientVehicleRequest extends ClientInsuranceRequest
{
    public function rules(): array
    {
        return [
            'plate' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('client_vehicles')->where('client_id', $this->route('client')->id)->ignore($this->route('vehicle'))],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'vin' => ['nullable', 'string', 'max:30'],
            'registered_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('plate'))) {
            $this->merge(['plate' => strtoupper(preg_replace('/\s+/', '', $this->input('plate')))]);
        }
        if (is_string($this->input('vin'))) {
            $this->merge(['vin' => strtoupper(trim($this->input('vin')))]);
        }
    }

    public function messages(): array
    {
        return ['plate.unique' => 'La targa è già presente nella scheda di questo cliente.'];
    }
}
