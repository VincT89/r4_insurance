<?php

namespace App\Services;

use App\Models\InboundEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class InboundEmailReceiver
{
    public function receive(array $message, array $files): array
    {
        /** Normalizzazione deterministica: un retry non dipende dall'ordine delle chiavi JSON. */
        $payload = [];
        foreach (['external_id','message_id','subject','text','html','sent_at','in_reply_to'] as $field) $payload[$field] = ($message[$field] ?? '') === '' ? null : $message[$field];
        $payload['from'] = ['address'=>$message['from']['address'], 'name'=>($message['from']['name'] ?? '') === '' ? null : $message['from']['name']];
        foreach (['to','cc','references'] as $field) $payload[$field] = array_values($message[$field] ?? []);
        $descriptors = array_map(fn ($file) => ['name'=>$file->getClientOriginalName(), 'sha256'=>hash_file('sha256', $file->getRealPath()), 'size'=>$file->getSize(), 'mime'=>$file->getMimeType()], $files);
        $canonicalFiles = $descriptors;
        usort($canonicalFiles, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
        $source = (string) config('inbound-email.source');
        $key = hash('sha256', $source."\0".$payload['external_id']);
        $fingerprint = hash('sha256', json_encode([$payload, $canonicalFiles], JSON_THROW_ON_ERROR));
        if ($existing = InboundEmail::where('delivery_key', $key)->first()) return $this->duplicate($existing, $fingerprint);
        $paths = [];
        try {
            $email = DB::transaction(function () use ($source, $key, $fingerprint, $payload, $files, $descriptors, &$paths) {
                /** Il vincolo univoco viene acquisito prima di scrivere file, anche con consegne simultanee. */
                $email = InboundEmail::create(['source'=>$source, 'delivery_key'=>$key, 'content_hash'=>$fingerprint, 'sender_hash'=>hash('sha256', mb_strtolower($payload['from']['address'])), 'payload'=>$payload, 'received_at'=>now()]);
                foreach ($files as $index => $file) {
                    $path = 'inbound-email/'.$email->public_id.'/'.Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
                    $paths[] = $path;
                    if (!Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) throw new \RuntimeException('Salvataggio allegato non riuscito.');
                    $details = $descriptors[$index];
                    $email->files()->create(['original_name'=>$details['name'], 'path'=>$path, 'mime_type'=>$details['mime'], 'size'=>$details['size'], 'sha256'=>$details['sha256']]);
                }
                app(AuditLogService::class)->log('inbound_email_received', $email, description:'Ricevuta email '.$email->public_id.' con '.count($files).' allegati.');
                return $email;
            });
            return [$email, false];
        } catch (\Throwable $exception) {
            foreach ($paths as $path) Storage::disk('local')->delete($path);
            if ($exception instanceof UniqueConstraintViolationException && ($existing = InboundEmail::where('delivery_key', $key)->first())) return $this->duplicate($existing, $fingerprint);
            throw $exception;
        }
    }

    private function duplicate(InboundEmail $email, string $fingerprint): array
    {
        abort_unless(hash_equals($email->content_hash, $fingerprint), 409, 'Identificativo già ricevuto con contenuto diverso. Conserva il messaggio e verifica la consegna.');
        return [$email, true];
    }
}
