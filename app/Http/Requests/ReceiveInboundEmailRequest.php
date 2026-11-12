<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class ReceiveInboundEmailRequest extends FormRequest
{
    public const EXTENSIONS = 'pdf,jpg,jpeg,png,webp,docx,xlsx,csv,txt';
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            if (strlen($this->input('message')) > 2 * 1024 * 1024) throw ValidationException::withMessages(['message'=>'Metadati troppo grandi.']);
            try { $message = json_decode($this->input('message'), true, 16, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { throw ValidationException::withMessages(['message'=>'Il campo message deve contenere JSON valido.']); }
            $this->merge(['message'=>$message]);
        }
    }

    public function rules(): array
    {
        return [
            'message'=>['required','array:external_id,message_id,from,to,cc,subject,text,html,sent_at,in_reply_to,references'],
            'message.external_id'=>['required','string','max:998','regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'message.message_id'=>['nullable','string','max:998','regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'message.from'=>['required','array:address,name'],
            'message.from.address'=>['required','email:rfc','max:254'],
            'message.from.name'=>['nullable','string','max:200'],
            'message.to'=>['required','array','list','min:1','max:30'],
            'message.to.*'=>['required','email:rfc','max:254'],
            'message.cc'=>['sometimes','array','list','max:30'],
            'message.cc.*'=>['required','email:rfc','max:254'],
            'message.subject'=>['nullable','string','max:998'],
            'message.text'=>['nullable','string','max:500000'],
            'message.html'=>['nullable','string','max:500000'],
            'message.sent_at'=>['nullable','date_format:Y-m-d\TH:i:sP'],
            'message.in_reply_to'=>['nullable','string','max:998','regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'message.references'=>['sometimes','array','list','max:50'],
            'message.references.*'=>['required','string','max:998','regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'attachments'=>['sometimes','array','list','max:10'],
            'attachments.*'=>['required','file','max:10240','mimes:'.self::EXTENSIONS,'extensions:'.self::EXTENSIONS],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) return;
            if (array_diff(array_keys($this->all()), ['message','attachments'])) $validator->errors()->add('message','Sono ammessi soltanto message e attachments.');
            $bytes = 0;
            foreach ($this->file('attachments', []) as $file) {
                $name = $file->getClientOriginalName();
                if (!mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > 255 || preg_match('/[\x00-\x1F\x7F]/u', $name)) $validator->errors()->add('attachments','Nome di allegato non valido.');
                $bytes += $file->getSize();
            }
            if ($bytes > 25 * 1024 * 1024) $validator->errors()->add('attachments','Gli allegati non possono superare complessivamente 25 MB.');
            if (strlen(json_encode($this->input('message'), JSON_THROW_ON_ERROR)) > 2 * 1024 * 1024) $validator->errors()->add('message','Metadati troppo grandi.');
        }];
    }
}
