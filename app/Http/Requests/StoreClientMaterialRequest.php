<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientMaterialRequest extends FormRequest
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'svg', 'ai', 'eps', 'psd', 'zip', 'doc', 'docx', 'ppt', 'pptx', 'txt'];

    public function authorize(): bool
    {
        return $this->user()->can('manageMaterials', $this->route('client'));
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(Attachment::CLIENT_MATERIAL_CATEGORIES))],
            'description' => ['nullable', 'string', 'max:500'],
            'file' => array_merge(['required_without:files', 'prohibits:files'], $this->fileRules()),
            'files' => ['required_without:file', 'array', 'list', 'min:1', 'max:10'],
            'files.*' => $this->fileRules(),
        ];
    }

    private function fileRules(): array
    {
        /** Source files are private downloads and are never rendered or executed by the server. */
        return ['file', 'max:10240', 'extensions:'.implode(',', self::EXTENSIONS), function ($attribute, $file, $fail) {
                if (mb_strlen($file->getClientOriginalName()) > 255) {
                    $fail('Il nome del file non può superare 255 caratteri.');
                }
                if (! in_array(strtolower($file->getClientOriginalExtension()), ['svg', 'ai', 'eps', 'psd'], true)) {
                    $validator = validator(['file' => $file], ['file' => 'mimes:jpg,jpeg,png,gif,webp,pdf,zip,doc,docx,ppt,pptx,txt']);
                    if ($validator->fails()) {
                        $fail('Il contenuto del file non corrisponde a un formato consentito.');
                    }
                }
            }];
    }
}
