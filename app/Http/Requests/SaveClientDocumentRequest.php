<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveClientDocumentRequest extends ClientInsuranceRequest
{
    public function rules(): array
    {
        $clientId = $this->route('client')->id;
        return [
            'file' => $this->isMethod('POST')
                ? ['required_without:files', 'prohibits:files', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,txt', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,txt']
                : ['prohibited'],
            'files' => $this->isMethod('POST') ? ['required_without:file', 'array', 'list', 'min:1', 'max:10'] : ['prohibited'],
            'files.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,txt', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,txt'],
            'file_details' => $this->isMethod('POST') ? ['nullable', 'array', 'list', 'max:10'] : ['prohibited'],
            'file_details.*' => ['array:expires_on,identity_type'],
            'file_details.*.expires_on' => ['nullable', 'date_format:Y-m-d'],
            'file_details.*.identity_type' => ['nullable', Rule::in(array_keys(Attachment::IDENTITY_TYPES))],
            'identity_type' => ['nullable', Rule::in(array_keys(Attachment::IDENTITY_TYPES))],
            'document_category' => ['required', Rule::in(array_keys(Attachment::DOCUMENT_CATEGORIES))],
            'insurance_policy_id' => ['nullable', 'integer', Rule::exists('insurance_policies', 'id')->where('client_id', $clientId)],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('client_vehicles', 'id')->where('client_id', $clientId)],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:500'],
            'previous_version_id' => $this->isMethod('POST') ? ['nullable', 'integer', Rule::exists('attachments', 'id')->where('attachable_id', $clientId)->where('attachable_type', $this->route('client')->getMorphClass())->whereNotNull('document_category')] : ['prohibited'],
            'document_request_id' => $this->isMethod('POST') ? ['nullable', 'integer', Rule::exists('client_document_requests', 'id')->where('client_id', $clientId)] : ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $document = $this->route('document');
            $files = $this->file('files', []);
            if (count($files) > 1 && $this->filled('previous_version_id')) {
                $validator->errors()->add('files', 'Per sostituire la versione di un singolo file scegli il suo aggiornamento. Gli altri allegati si aggiungono alla richiesta con selezione multipla.');
            }
            if (count((array)$this->input('file_details', [])) > count($files)) $validator->errors()->add('file_details', 'Le scadenze devono corrispondere ai file selezionati.');
            foreach (array_merge($files, $this->hasFile('file') ? [$this->file('file')] : []) as $file) {
                if (mb_strlen($file->getClientOriginalName()) > 255) $validator->errors()->add('files', 'Il nome dei file non può superare 255 caratteri.');
            }
            $policyId = $this->input('insurance_policy_id', $document?->insurance_policy_id);
            $vehicleId = $this->input('vehicle_id', $document?->vehicle_id);
            if (! $policyId || ! $vehicleId) {
                return;
            }
            $policy = $this->route('client')->policies()->find($policyId);
            if (! $policy || (int) $policy->vehicle_id !== (int) $vehicleId) {
                $validator->errors()->add('vehicle_id', 'Il veicolo deve corrispondere a quello della polizza selezionata. Puoi lasciare il campo vuoto per usare il collegamento della polizza.');
            }
        }];
    }
}
