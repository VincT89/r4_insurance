@props([
    'model'
])

@php
    $type = array_search(get_class($model), \App\Http\Requests\StoreAttachmentRequest::ATTACHABLE_MAP);
@endphp

@if($type)
<div class="att-panel-container">
    <x-panel title="Allegati" dot="var(--accent)" padded>
        @if(count($model->attachments ?? []))
            <table class="t-table att-table">
                <thead>
                    <tr>
                        <th>Nome File</th>
                        <th>Categoria</th>
                        <th>Formato</th>
                        <th>Dimensione</th>
                        <th>Utente</th>
                        <th>Data</th>
                        <th class="t-actions">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($model->attachments as $att)
                    <tr>
                        <td class="name-col">{{ $att->original_name }}</td>
                        <td class="mono-col">{{ strtoupper($att->type ?? 'DOCUMENT') }}</td>
                        <td class="mono-col">{{ $att->mime_type }}</td>
                        <td class="mono-col">{{ number_format($att->size / 1024, 0) }} KB</td>
                        <td>{{ $att->uploader?->name ?? 'Sistema' }}</td>
                        <td class="mono-col">{{ $att->created_at->format('d/m/Y H:i') }}</td>
                        <td class="t-actions">
                            <div class="att-actions t-actions-list">
                                @can('download', $att)
                                <a href="{{ route('attachments.download', $att) }}" target="_blank" rel="noopener noreferrer"
                                   class="btn-icon" title="Scarica" aria-label="Scarica allegato">↓</a>
                                @endcan

                                @can('delete', $att)
                                    <x-delete-modal 
                                        action="{{ route('attachments.destroy', $att) }}" 
                                        title="Elimina Allegato" 
                                        message="Sei sicuro di voler eliminare il file '{{ $att->original_name }}'?">
                                        <button type="button" class="btn-icon att-btn-delete" title="Elimina">×</button>
                                    </x-delete-modal>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="att-empty">Nessun allegato presente.</div>
        @endif

        @can('update', $model)
        <div class="att-form-wrap">
            <form action="{{ route('attachments.store') }}" method="POST" enctype="multipart/form-data" class="att-form">
                @csrf
                <input type="hidden" name="attachable_type" value="{{ $type }}">
                <input type="hidden" name="attachable_id" value="{{ $model->id }}">
                <div class="att-form-col">
                    <div class="form-lbl">Tipo File</div>
                    <select name="type" class="form-in att-input-sm" required>
                        <option value="document">Documento</option>
                        <option value="image">Immagine</option>
                        <option value="media">Media (Audio/Video)</option>
                        <option value="other">Altro</option>
                    </select>
                </div>
                <div class="att-form-col">
                    <div class="form-lbl">Nuovo Allegato</div>
                    <input type="file" name="files[]" multiple class="form-in att-input-sm" required>
                </div>
                <button type="submit" class="btn btn-p att-btn-submit">Carica</button>
            @foreach($errors->get('files.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach</form>
            @error('files')
                <div class="att-error">{{ $message }}</div>
            @enderror
            @error('attachable_type')
                <div class="att-error">{{ $message }}</div>
            @enderror
        </div>
        @endcan
    </x-panel>
</div>
@endif
