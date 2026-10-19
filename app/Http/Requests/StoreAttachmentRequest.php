<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAttachmentRequest extends FormRequest
{
    public const ALLOWED_MIMES = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp',
        'doc', 'docx', 'xls', 'xlsx', 'csv',
        'zip', 'txt',
    ];

    public const ATTACHABLE_MAP = ['client' => Client::class];

    public function authorize(): bool
    {
        $type = $this->input('attachable_type');
        $id = $this->input('attachable_id');

        $modelClass = is_string($type) ? (self::ATTACHABLE_MAP[$type] ?? null) : null;

        if (! $modelClass || !is_scalar($id) || !ctype_digit((string) $id)) {
            return false;
        }

        $attachable = $modelClass::query()->find($id);

        if (! $attachable) {
            return false;
        }

        /** Upload authorization follows the parent client, never a client-supplied model name. */
        return $this->user()->can('update', $attachable);
    }

    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'string'],
            'attachable_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:document,image,media,other'],
            'file' => [
                'required_without:files', 'prohibits:files',
                'file',
                'max:10240',
                'mimes:' . implode(',', self::ALLOWED_MIMES),
                'extensions:'.implode(',', self::ALLOWED_MIMES),
            ],
            'files' => ['required_without:file', 'array', 'list', 'min:1', 'max:10'],
            'files.*' => ['file', 'max:10240', 'mimes:'.implode(',', self::ALLOWED_MIMES), 'extensions:'.implode(',', self::ALLOWED_MIMES)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) return;
                foreach ($this->file('files') ?? [$this->file('file')] as $file) {
                    if (mb_strlen($file->getClientOriginalName()) > 255) $validator->errors()->add('files', 'Il nome dei file non può superare 255 caratteri.');
                }
                $type = $this->input('attachable_type');
                $id = $this->input('attachable_id');

                $modelClass = self::ATTACHABLE_MAP[$type] ?? null;

                if (! $modelClass) {
                    $validator->errors()->add(
                        'attachable_type',
                        'Il tipo di allegato selezionato non è valido.'
                    );

                    return;
                }

                $exists = $modelClass::query()->whereKey($id)->exists();

                if (! $exists) {
                    $validator->errors()->add(
                        'attachable_id',
                        'L\'entità selezionata non esiste.'
                    );
                }
            },
        ];
    }
}
