<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class PrivateAttachmentBatch
{
    public function upload(Client $client, array $files, array $values = []): array
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($client, $files, $values, &$paths) {
                $client = Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
                abort_if($client->merged_into_id, 409, 'Anagrafica già unificata. Riapri la scheda corrente.');
                $directory = 'client/'.$client->id.(!empty($values['client_material_category']) ? '/materials' : '');
                $attachments = [];
                foreach ($files as $file) {
                    $path = $file->store($directory, 'attachments');
                    if (!$path) throw ValidationException::withMessages(['files' => 'Caricamento non riuscito. Riprova.']);
                    $paths[] = $path;
                    $mime = $file->getMimeType() ?: 'application/octet-stream';
                    $attachments[] = $client->attachments()->create(array_merge($values, [
                        'type' => $values['type'] ?? (str_starts_with($mime, 'image/') ? 'image' : 'document'),
                        'uploaded_by' => auth()->id(), 'disk' => 'attachments', 'directory' => $directory, 'path' => $path,
                        'stored_name' => basename($path), 'original_name' => $file->getClientOriginalName(),
                        'extension' => strtolower($file->getClientOriginalExtension()), 'mime_type' => $mime, 'size' => $file->getSize(),
                    ]));
                }
                return $attachments;
            });
        } catch (\Throwable $exception) {
            foreach ($paths as $path) Storage::disk('attachments')->delete($path);
            throw $exception;
        }
    }
}
