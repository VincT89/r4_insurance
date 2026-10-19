<?php

namespace App\Services;

use App\Models\{Attachment, Client, ClientDocumentRequest};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class ClientDocumentRegistry
{
    public function uploadMany(Client $client, array $files, array $values, array $details = []): array
    {
        $documents = [];
        try {
            return DB::transaction(function () use ($client, $files, $values, $details, &$documents) {
                foreach ($files as $index => $file) {
                    /** Each file keeps its own confirmed date; a batch must never copy another document's expiry. */
                    $metadata = $details[$index] ?? [];
                    $perFile = array_replace($values, ['expires_on' => $metadata['expires_on'] ?? null, 'identity_type' => $metadata['identity_type'] ?? null]);
                    if (($values['document_category'] ?? null) !== 'identity') $perFile['identity_type'] = null;
                    $documents[] = $this->upload($client, $file, $perFile);
                }
                return $documents;
            });
        } catch (\Throwable $exception) {
            foreach ($documents as $document) Storage::disk($document->disk)->delete($document->path);
            throw $exception;
        }
    }

    public function upload(Client $client, UploadedFile $file, array $values): Attachment
    {
        $path = null;
        try {
            return DB::transaction(function () use ($client, $file, $values, &$path) {
                $client = Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
                $previous = !empty($values['previous_version_id']) ? $client->attachments()->whereNotNull('document_category')->whereKey($values['previous_version_id'])->lockForUpdate()->first() : null;
                if (!empty($values['previous_version_id']) && (!$previous || $previous->newerVersion()->exists())) throw ValidationException::withMessages(['previous_version_id' => 'La versione non è disponibile o è già stata sostituita. Riapri il documento corrente.']);
                if ($previous) {
                    if ($previous->insurance_quote_case_id) throw ValidationException::withMessages(['previous_version_id'=>'Aggiungi il documento aggiornato dalla pratica preventivi, conservando l’originale.']);
                    if ($previous->insurance_claim_id || $previous->insurance_form_document_id || $previous->insurance_coverage_check_id) throw ValidationException::withMessages(['previous_version_id'=>'Aggiungi le nuove copie dalla scheda del sinistro, dei moduli o del controllo RCA.']);
                    if ($previous->insurance_quote_email_id) throw ValidationException::withMessages(['previous_version_id'=>'Conserva gli allegati originali delle richieste di preventivo.']);
                    if ($previous->insurance_quote_id || $previous->insurance_title_id || $previous->insurance_collection_id) throw ValidationException::withMessages(['previous_version_id'=>'Gli originali di preventivi e movimenti devono essere conservati. Aggiungi altri documenti dalla relativa scheda.']);
                    if (isset($values['document_request_id']) && $values['document_request_id'] != $previous->document_request_id) throw ValidationException::withMessages(['document_request_id' => 'Conserva la richiesta collegata alla versione precedente.']);
                    foreach (['document_category', 'insurance_policy_id', 'vehicle_id'] as $field) {
                        if (($values[$field] ?? null) != $previous->$field) throw ValidationException::withMessages([$field => 'Una nuova versione deve conservare categoria e collegamenti del documento precedente.']);
                    }
                    $values['document_request_id'] = $previous->document_request_id;
                    $values['version_number'] = $previous->version_number + 1;
                }
                $request = !empty($values['document_request_id']) ? $client->documentRequests()->whereKey($values['document_request_id'])->lockForUpdate()->first() : null;
                if (!empty($values['document_request_id']) && !$request) throw ValidationException::withMessages(['document_request_id' => 'Richiesta non disponibile per questo cliente.']);
                if ($request) {
                    if ($request->document_category !== $values['document_category'] || $request->insurance_policy_id != ($values['insurance_policy_id'] ?? null)) throw ValidationException::withMessages(['document_request_id' => 'Categoria e polizza devono corrispondere alla richiesta.']);
                    if (!$previous && $request->documents()->exists()) throw ValidationException::withMessages(['document_request_id' => 'Questa richiesta ha già un file. Usa Nuova versione sul documento corrente.']);
                }
                $directory = 'client/'.$client->id.'/insurance';
                $path = $file->store($directory, 'attachments');
                if (!$path) throw ValidationException::withMessages(['file' => 'Caricamento non riuscito. Riprova.']);
                return $client->attachments()->create(array_merge($values, [
                    'type' => 'document', 'uploaded_by' => auth()->id(), 'disk' => 'attachments', 'directory' => $directory, 'path' => $path,
                    'original_name' => $file->getClientOriginalName(), 'stored_name' => basename($path), 'mime_type' => $file->getMimeType(),
                    'extension' => $file->extension(), 'size' => $file->getSize(), 'review_status' => 'received', 'validated_by' => null, 'validated_at' => null,
                ]));
            });
        } catch (\Throwable $exception) {
            if ($path) Storage::disk('attachments')->delete($path);
            throw $exception;
        }
    }

    public function update(Attachment $document, array $values): void
    {
        DB::transaction(function () use ($document, $values) {
            $document = Attachment::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($document->insurance_quote_case_id) throw ValidationException::withMessages(['document'=>'Conserva il documento originale della pratica preventivi. Aggiungi una nuova copia dalla pratica.']);
            if ($document->insurance_claim_id || $document->insurance_form_document_id || $document->insurance_coverage_check_id) throw ValidationException::withMessages(['document'=>'Conserva il documento originale nello storico del sinistro, dei moduli o del controllo RCA.']);
            if ($document->insurance_quote_email_id) throw ValidationException::withMessages(['document'=>'Conserva l’allegato originale della richiesta di preventivo.']);
            if ($document->insurance_quote_id || $document->insurance_title_id || $document->insurance_collection_id) throw ValidationException::withMessages(['document'=>'Conserva il documento originale del preventivo o del movimento contabile. Aggiungi gli eventuali ulteriori allegati dalla relativa scheda.']);
            if ($document->newerVersion()->exists()) throw ValidationException::withMessages(['document' => 'Questa è una versione storica. Modifica il documento corrente.']);
            if ($document->document_request_id || $document->previous_version_id) {
                foreach (['document_category', 'insurance_policy_id', 'vehicle_id'] as $field) if (array_key_exists($field, $values) && $values[$field] != $document->$field) throw ValidationException::withMessages([$field => 'Conserva i collegamenti della richiesta e dello storico documentale.']);
            }
            $document->fill($values);
            if ($document->document_category !== 'identity') $document->identity_type = null;
            if ($document->isDirty() && $document->review_status === 'validated') $document->fill(['review_status' => 'received', 'validated_at' => null, 'validated_by' => null, 'validation_note' => null]);
            $document->save();
        });
    }

    public function validateDocument(Attachment $document, ?string $note): void
    {
        DB::transaction(function () use ($document, $note) {
            $document = Attachment::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($document->insurance_claim_id || $document->insurance_form_document_id || $document->insurance_coverage_check_id) throw ValidationException::withMessages(['document'=>'Registra il controllo nella scheda dedicata al sinistro, ai moduli o alla verifica RCA.']);
            if ($document->newerVersion()->exists()) throw ValidationException::withMessages(['document' => 'Valida la versione corrente del documento.']);
            if ($document->document_category === 'identity' && !$document->expires_on) throw ValidationException::withMessages(['document' => 'Verifica la scadenza del documento di identità prima di validarlo.']);
            if ($document->expires_on?->lt(today())) throw ValidationException::withMessages(['document' => 'Il documento è scaduto. Carica una versione aggiornata prima di validarlo.']);
            if (!Storage::disk($document->disk)->exists($document->path)) throw ValidationException::withMessages(['document' => 'Il file non è disponibile e non può essere validato.']);
            $document->update(['review_status' => 'validated', 'validated_by' => auth()->id(), 'validated_at' => now(), 'validation_note' => $note]);
            app(AuditLogService::class)->log('document_validated', $document, newValues: $document->only(['review_status', 'validated_by', 'validated_at', 'validation_note']), description: 'Validato il documento '.$document->original_name.', versione '.$document->version_number.'.');
        });
    }
}
