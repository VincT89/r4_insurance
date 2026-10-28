<?php

namespace App\Services;

use App\Models\{Attachment, Client, InsuranceQuoteCase};
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};

class QuoteRequestDocuments
{
    public const MAX_BYTES = 20 * 1024 * 1024;
    private const GROUPS = ['identity_files'=>'identity', 'vehicle_files'=>'vehicle', 'files'=>'quote_request'];

    public static function rules(): array
    {
        $rules = [];
        foreach (self::GROUPS as $field=>$category) {
            $rules[$field] = ['nullable', 'array', 'list', 'max:10'];
            $formats = $category === 'quote_request' ? 'pdf' : 'pdf,jpg,jpeg,png,webp';
            $rules[$field.'.*'] = ['file', 'max:10240', 'mimes:'.$formats, 'extensions:'.$formats];
        }
        return $rules + [
            'identity_file_details'=>['nullable','array','list','max:10'],
            'identity_file_details.*'=>['array:expires_on,identity_type'],
            'identity_file_details.*.expires_on'=>['nullable','date_format:Y-m-d'],
            'identity_file_details.*.identity_type'=>['nullable',Rule::in(array_keys(Attachment::IDENTITY_TYPES))],
        ];
    }

    public static function uploads(Request $request): array
    {
        $uploads = [];
        foreach (self::GROUPS as $field=>$category) {
            foreach ($request->file($field, []) as $index=>$file) {
                $uploads[] = ['file'=>$file, 'category'=>$category, 'details'=>$category==='identity' ? ($request->input('identity_file_details', [])[$index] ?? []) : []];
            }
        }
        return $uploads;
    }

    public function normalize(array $uploads): array
    {
        return array_map(fn ($entry) => $entry instanceof UploadedFile ? ['file'=>$entry,'category'=>'quote_request','details'=>[]] : $entry, $uploads);
    }

    public function selected(InsuranceQuoteCase $case, array $ids)
    {
        $documents = $case->documents()->whereKey($ids)->orderBy('id')->get();
        if ($documents->count() !== count($ids) || $documents->contains(fn ($document) => $document->attachable_type !== $case->client->getMorphClass() || (int)$document->attachable_id !== (int)$case->client_id)) {
            throw ValidationException::withMessages(['document_ids'=>'Seleziona soltanto documenti di questa pratica e di questo cliente.']);
        }
        return $documents;
    }

    public function check(array $uploads, iterable $documents = []): void
    {
        $count = count($uploads); $size = 0;
        foreach ($uploads as $entry) {
            $file = $entry['file'];
            if (mb_strlen($file->getClientOriginalName()) > 255) throw ValidationException::withMessages(['files'=>'Il nome di ogni file può contenere al massimo 255 caratteri.']);
            $size += $file->getSize();
        }
        foreach ($documents as $document) {
            $disk = Storage::disk($document->disk);
            if (!in_array($document->mime_type, ['application/pdf','image/jpeg','image/png','image/webp'], true) || !$disk->exists($document->path)) throw ValidationException::withMessages(['document_ids'=>'Un documento selezionato non è disponibile o non ha un formato consentito.']);
            $size += $disk->size($document->path); $count++;
        }
        if ($count > 10 || $size > self::MAX_BYTES) throw ValidationException::withMessages(['email'=>'Seleziona al massimo 10 documenti e 20 MB complessivi, contando tutti i gruppi e i documenti già caricati.']);
    }

    public function store(Client $client, array $uploads, array $links, array &$stored): void
    {
        foreach ($uploads as $entry) {
            $metadata = $entry['details'] ?? [];
            $documentLinks = $links;
            if ($entry['category'] !== 'vehicle') unset($documentLinks['vehicle_id']);
            $stored[] = app(ClientDocumentRegistry::class)->upload($client, $entry['file'], $documentLinks + [
                'document_category'=>$entry['category'],
                'identity_type'=>$entry['category']==='identity' ? ($metadata['identity_type']??null) : null,
                'expires_on'=>$entry['category']==='identity' ? ($metadata['expires_on']??null) : null,
            ]);
        }
    }

    public function copyForEmail(Attachment $document, string $emailId): Attachment
    {
        /** Every reviewed email owns an immutable copy, so later requests cannot replace its evidence. */
        $disk = Storage::disk($document->disk);
        $extension = match($document->mime_type) { 'application/pdf'=>'pdf', 'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', default=>throw ValidationException::withMessages(['document_ids'=>'Formato non consentito.']) };
        $path = $document->directory.'/'.Str::uuid().'.'.$extension;
        try {
            if (!$disk->copy($document->path, $path)) throw ValidationException::withMessages(['document_ids'=>'Impossibile preparare una copia dell’allegato. Riprova.']);
            $copy = $document->replicate(['insurance_quote_case_id','insurance_quote_id','insurance_policy_id','insurance_quote_email_id','document_request_id','previous_version_id','validated_by','validated_at','validation_note']);
            $copy->forceFill(['insurance_quote_email_id'=>$emailId,'uploaded_by'=>auth()->id(),'path'=>$path,'stored_name'=>basename($path),'review_status'=>'received','version_number'=>1]);
            $copy->save();
            return $copy;
        } catch (\Throwable $exception) { $disk->delete($path); throw $exception; }
    }
}
