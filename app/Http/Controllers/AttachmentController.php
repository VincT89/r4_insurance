<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $attachable = $this->resolveAttachable($data['attachable_type'], (int) $data['attachable_id']);
        abort_unless($attachable instanceof Client, 404);
        $this->authorize('update', $attachable);
        app(\App\Services\PrivateAttachmentBatch::class)->upload($attachable, $request->file('files') ?? [$request->file('file')], [
            'type' => $data['type'], 'description' => $data['description'] ?? null,
        ]);
        return back()->with('success', 'Allegati caricati correttamente.');
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        $headers = ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'private, no-store'];

        return $disk->download($attachment->path, $attachment->original_name, $headers);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $attachment);
        DB::transaction(function () use ($attachment) {
            $client = Client::whereKey($attachment->attachable_id)->lockForUpdate()->firstOrFail();
            abort_if($client->merged_into_id, 409, 'Anagrafica già unificata. Riapri la scheda corrente.');
            $locked = Attachment::whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->attachable_id === (int) $client->id, 409);
            $this->authorize('delete', $locked);
            $locked->delete();
        });

        return back()->with('success', 'Allegato eliminato correttamente.');
    }

    protected function resolveAttachable(string $type, int $id): ?Model
    {
        $map = StoreAttachmentRequest::ATTACHABLE_MAP;
        $modelClass = $map[$type] ?? null;

        if (! $modelClass) {
            return null;
        }

        return $modelClass::query()->find($id);
    }

    protected function buildDirectory(string $type, int $id): string
    {
        return $type.'/'.$id;
    }
}
