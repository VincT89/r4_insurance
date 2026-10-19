<div class="u-mt-lg u-mb-lg">
    <x-panel title="Allegati" dot="var(--accent)">
        @if(count($model->attachments ?? []))
            <div class="attachments-table-region" x-data="{
                scrollable: false,
                observer: null,
                init() {
                    this.$nextTick(() => {
                        this.observer = new ResizeObserver(() => {
                            this.scrollable = this.$refs.scrollArea.scrollWidth > this.$refs.scrollArea.clientWidth + 1;
                        });
                        this.observer.observe(this.$refs.scrollArea);
                        this.observer.observe(this.$refs.table);
                    });
                },
                destroy() { this.observer?.disconnect(); }
            }">
                <p id="attachment-scroll-help-{{ $this->getId() }}" class="attachments-scroll-hint" x-show="scrollable" x-cloak>Scorri orizzontalmente per vedere tutte le colonne.</p>
                <div class="attachments-table-wrap" x-ref="scrollArea" role="region" aria-label="Tabella allegati"
                     tabindex="0" :tabindex="scrollable ? 0 : -1"
                     :aria-describedby="scrollable ? 'attachment-scroll-help-{{ $this->getId() }}' : null">
                <table class="t-table attachments-table" x-ref="table">
                <thead>
                    <tr>
                        <th>Nome File</th>
                        <th>Tipo</th>
                        <th>Dimens.</th>
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
                        <td class="mono-col">{{ number_format($att->size / 1024, 0) }} KB</td>
                        <td>{{ $att->uploader?->name ?? 'Sistema' }}</td>
                        <td class="mono-col">{{ $att->created_at->format('d/m/Y H:i') }}</td>
                        <td class="t-actions">
                            <div class="t-actions-list">
                                @can('download', $att)
                                <a href="{{ route('attachments.download', $att) }}" target="_blank" rel="noopener noreferrer"
                                   class="btn-ghost-primary btn-xs" title="Scarica" aria-label="Scarica allegato">
                                    <i data-lucide="download" class="u-icon-sm"></i>
                                </a>
                                @endcan

                                @can('delete', $att)
                                    <x-confirm-modal
                                        title="Elimina Allegato"
                                        message="Sei sicuro di voler eliminare il file '{{ $att->original_name }}'?"
                                        confirmText="Elimina"
                                        confirmMethod="deleteAttachment({{ $att->id }})"
                                        confirmClass="btn btn-p btn-danger"
                                        icon="trash-2"
                                        variant="danger"
                                    >
                                        <button type="button" class="btn-ghost-danger btn-xs" title="Elimina">
                                            <i data-lucide="trash-2" class="u-icon-sm"></i>
                                        </button>
                                    </x-confirm-modal>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
                </div>
            </div>
        @else
            <div class="u-text-center u-text-muted u-p-md u-mb-md">Nessun allegato presente.</div>
        @endif

        @can('update', $model)
        <div class="attachments-upload-footer">
            <div class="u-flex-between">
                <div>
                    <input
                        id="attachment-upload-{{ $model->id }}"
                        type="file"
                        wire:model="files" multiple
                        class="attachments-file-input"
                    />
                    
                    <label for="attachment-upload-{{ $model->id }}" class="btn btn-p attachments-upload-label">
                        <span wire:loading.remove wire:target="files">Carica allegati</span>
                        <span wire:loading wire:target="files">Caricamento...</span>
                    </label>
                </div>
                
                @error('files')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('files.*')
                    <div class="u-text-red u-text-meta">{{ $message }}</div>
                @enderror
            </div>
        </div>
        @endcan
    </x-panel>
</div>
