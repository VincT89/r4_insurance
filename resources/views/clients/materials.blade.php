<x-app-layout :title="'Materiali di '.$client->name">
    <div class="client-materials">
        <div class="page-back-row">
            @can('view', $client)<a href="{{ route('clients.show', $client) }}" class="btn btn-g">Torna al cliente</a>
            @else<a href="{{ route('dashboard') }}" class="btn btn-g">Torna alla dashboard</a>@endcan
        </div>
        <x-page-header><x-slot:title>Materiali del cliente</x-slot:title></x-page-header>
        <p class="u-mb-lg">File aggiuntivi di {{ $client->name }}. Per documenti d’identità, polizze e scadenze usa la sezione Documenti della scheda cliente.</p>
        @can('manageMaterials', $client)
            <x-panel title="Aggiungi materiale" padded>
                <form action="{{ route('clients.materials.store', $client) }}" method="POST" enctype="multipart/form-data" class="form-stack">
                    @csrf
                    <div class="form-row">
                        <x-form-group label="Categoria" name="category" required>
                            <select id="field-category" name="category" class="form-sel" required>
                                @foreach(\App\Models\Attachment::CLIENT_MATERIAL_CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach
                            </select>
                        </x-form-group>
                        <x-form-group label="File" name="files" required>
                            <input id="field-files" type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.svg,.ai,.eps,.psd,.zip,.doc,.docx,.ppt,.pptx,.txt" class="form-in" required>
                        </x-form-group>
                    </div>
                    <p class="u-text-meta">Immagini, PDF, SVG, AI, EPS, PSD, ZIP, documenti Word, PowerPoint e TXT. Fino a 10 file, massimo 10 MB per file. I file sorgenti e vettoriali si scaricano per aprirli nel programma appropriato.</p>
                    <x-form-group label="Descrizione o indicazioni d’uso" name="description">
                        <textarea id="field-description" name="description" class="form-ta" rows="2" maxlength="500">{{ old('description') }}</textarea>
                    </x-form-group>
                    <div><button type="submit" class="btn btn-p">Carica materiale</button></div>
                @foreach($errors->get('files.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach</form>
            </x-panel>
        @endcan
        <div class="u-mt-lg"><x-panel title="Archivio materiali" padded>
            <form action="{{ route('clients.materials.index', $client) }}" method="GET" class="client-search-form">
                <x-form-group label="Cerca materiale" name="search"><input id="field-search" type="search" name="search" class="form-in" value="{{ $filters['search'] ?? '' }}" maxlength="255" placeholder="Nome file o descrizione"></x-form-group>
                <x-form-group label="Categoria" name="filter-category" for="material-filter-category">
                    <select id="material-filter-category" name="category" class="form-sel"><option value="">Tutte</option>@foreach(\App\Models\Attachment::CLIENT_MATERIAL_CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
                </x-form-group>
                <div class="search-form-actions"><button type="submit" class="btn btn-p">Applica filtri</button><a href="{{ route('clients.materials.index', $client) }}" class="btn btn-g">Azzera filtri</a></div>
            </form>
            @forelse($materials as $material)
                <article class="client-material-row">
                    <div class="client-material-info">
                        <h2 class="u-text-strong">{{ $material->original_name }}</h2>
                        <p class="u-text-meta">{{ \App\Models\Attachment::CLIENT_MATERIAL_CATEGORIES[$material->client_material_category] ?? 'Altro' }} · {{ strtoupper($material->extension) }} · {{ number_format($material->size / 1024, 0, ',', '.') }} KB</p>
                        @if($material->description)<p class="ticket-description">{{ $material->description }}</p>@endif
                        <p class="u-text-meta">Caricato da {{ $material->uploader?->name ?? 'Utente non disponibile' }} il {{ $material->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="commercial-actions">
                        <a href="{{ route('attachments.download', $material) }}" class="btn btn-g" aria-label="Scarica {{ $material->original_name }}">Scarica</a>
                        @can('delete', $material)
                            <x-delete-modal :action="route('attachments.destroy', $material)" title="Elimina materiale"
                                :message="'Eliminare il materiale '.$material->original_name.'?'">
                                <button type="button" class="btn btn-g btn-danger-outline" aria-label="Elimina {{ $material->original_name }}">Elimina</button>
                            </x-delete-modal>
                        @endcan
                    </div>
                </article>
            @empty<p class="u-mt-lg">Nessun materiale trovato. I documenti già caricati nella scheda cliente restano nella sezione Allegati.</p>@endforelse
            {{ $materials->links() }}
        </x-panel></div>
    </div>
</x-app-layout>
