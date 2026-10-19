<?php

namespace App\Livewire\Shared;

use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\{Component, WithFileUploads};

class AttachmentManager extends Component
{
    use WithFileUploads, AuthorizesRequests;

    #[\Livewire\Attributes\Locked]
    public Model $model;
    public array $files = [];

    public function mount(Model $model): void
    {
        abort_if(!$model instanceof Client, 403);
        $this->authorize('view', $model);
        $this->model = $model;
    }

    public function updatedFiles(): void { $this->upload(); }

    public function upload(): void
    {
        abort_unless($this->model instanceof Client, 403);
        $this->authorize('update', $this->model);
        $types = implode(',', StoreAttachmentRequest::ALLOWED_MIMES);
        $this->validate(['files' => ['required', 'array', 'list', 'min:1', 'max:10'], 'files.*' => ['file', 'max:10240', 'mimes:'.$types, 'extensions:'.$types, function ($attribute, $file, $fail) {
            if (mb_strlen($file->getClientOriginalName()) > 255) $fail('Il nome del file non può superare 255 caratteri.');
        }]]);
        app(\App\Services\PrivateAttachmentBatch::class)->upload($this->model, $this->files);
        $this->reset('files');
        $this->reloadAttachments();
    }

    public function deleteAttachment($id): void
    {
        $this->authorize('view', $this->model);
        \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            $client = Client::whereKey($this->model->id)->lockForUpdate()->firstOrFail();
            abort_if($client->merged_into_id, 409, 'Anagrafica già unificata. Riapri la scheda corrente.');
            $attachment = $client->attachments()->whereNull('client_material_category')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorize('delete', $attachment);
            $attachment->delete();
        });
        $this->reloadAttachments();
    }

    private function reloadAttachments(): void
    {
        $this->model->load(['attachments' => fn ($query) => $query->whereNull('client_material_category')->with('uploader')]);
    }

    public function render()
    {
        abort_if(!$this->model instanceof Client, 403);
        $this->authorize('view', $this->model);
        return view('livewire.shared.attachment-manager');
    }
}
