from pathlib import Path
root = Path(__file__).resolve().parent.parent
p = root / 'app/Http/Controllers/AttachmentController.php'
s = p.read_text(encoding='utf-8')
start = s.index('        return DB::transaction', s.index('public function store'))
end = s.index('\n    public function download', start)
s = s[:start] + '''        $data = $request->validated();
        $attachable = $this->resolveAttachable($data['attachable_type'], (int) $data['attachable_id']);
        abort_unless($attachable instanceof Client, 404);
        $this->authorize('update', $attachable);
        app(\\App\\Services\\PrivateAttachmentBatch::class)->upload($attachable, $request->file('files') ?? [$request->file('file')], [
            'type' => $data['type'], 'description' => $data['description'] ?? null,
        ]);
        return back()->with('success', 'Allegati caricati correttamente.');
    }
''' + s[end:]
start=s.index('        $headers ='); end=s.index('\n\n        return $disk->download', start)
s=s[:start]+'''        $headers = ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'private, no-store'];'''+s[end:]
p.write_text(s, encoding='utf-8')
p=root/'app/Http/Controllers/ClientMaterialController.php'
s=p.read_text(encoding='utf-8'); start=s.index('        $file =',s.index('public function store')); end=s.index('\n        return redirect()',start)
s=s[:start]+'''        app(\\App\\Services\\PrivateAttachmentBatch::class)->upload($client, $request->file('files') ?? [$request->file('file')], [
            'type' => 'document', 'client_material_category' => $data['category'], 'description' => $data['description'] ?? null,
        ]);
''' + s[end:]
p.write_text(s,encoding='utf-8')
for name in ['resources/views/components/attachments-panel.blade.php','resources/views/clients/materials.blade.php']:
    p=root/name; s=p.read_text(encoding='utf-8'); s=s.replace('type="file" name="file"','type="file" name="files[]" multiple').replace("@error('file')","@error('files')").replace('name="file" required','name="files" required')
    s=s.replace('id="field-file"','id="field-files"').replace('Massimo 10 MB per file.','Fino a 10 file, massimo 10 MB per file.')
    s=s.replace('</form>', '''@foreach($errors->get('files.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach</form>''', 1)
    p.write_text(s,encoding='utf-8')
p=root/'resources/views/livewire/shared/attachment-manager.blade.php'; s=p.read_text(encoding='utf-8').replace('wire:model="file"','wire:model="files" multiple').replace('wire:target="file"','wire:target="files"').replace("@error('file')","@error('files.*')").replace('Carica allegato','Carica allegati')
s=s.replace("@error('files.*')", "@error('files')<div class=\"invalid-feedback\">{{ $message }}</div>@enderror\n                @error('files.*')")
p.write_text(s,encoding='utf-8')
p=root/'resources/views/clients/show.blade.php'; s=p.read_text(encoding='utf-8'); s='\n'.join(line for line in s.split('\n') if "@if($client->activity_description)" not in line); p.write_text(s,encoding='utf-8')
p=root/'app/Livewire/Shared/AttachmentManager.php'
p.write_text('''<?php

namespace App\\Livewire\\Shared;

use App\\Http\\Requests\\StoreAttachmentRequest;
use App\\Models\\Client;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Foundation\\Auth\\Access\\AuthorizesRequests;
use Livewire\\{Component, WithFileUploads};

class AttachmentManager extends Component
{
    use WithFileUploads, AuthorizesRequests;

    #[\\Livewire\\Attributes\\Locked]
    public Model $model;
    public array $files = [];

    public function mount(Model $model): void
    {
        abort_if(auth()->user()->isCommercial() || !$model instanceof Client, 403);
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
        app(\\App\\Services\\PrivateAttachmentBatch::class)->upload($this->model, $this->files);
        $this->reset('files');
        $this->reloadAttachments();
    }

    public function deleteAttachment($id): void
    {
        $this->authorize('view', $this->model);
        \\Illuminate\\Support\\Facades\\DB::transaction(function () use ($id) {
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
        abort_if(auth()->user()->isCommercial() || !$this->model instanceof Client, 403);
        $this->authorize('view', $this->model);
        return view('livewire.shared.attachment-manager');
    }
}
''', encoding='utf-8')
