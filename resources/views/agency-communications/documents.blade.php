@if($includedDocuments->isNotEmpty())
    <div class="communication-document-source">
        <h3>{{ $editable ? 'File inclusi: togli la selezione per escluderli dall’email' : 'File inclusi nel riepilogo' }}</h3>
        @foreach($includedDocuments as $file)
            @php($details = $file->details)
            <div class="communication-document-row">
                <div class="communication-document-label">
                    @if($editable)<input type="checkbox" id="keep-document-{{ $file->id }}" name="keep_ids[]" value="{{ $file->id }}" checked>@endif
                    <{{ $editable ? 'label' : 'div' }} @if($editable) for="keep-document-{{ $file->id }}" @endif>
                        <strong>{{ $details['name'] }}</strong><small>{{ $details['context'] }} · {{ number_format(ceil($file->size/1024),0,',','.') }} KB</small>
                        @if($details['identity'])
                            @php($expired = !empty($details['expires_on']) && $details['expires_on'] < today()->toDateString())
                            <small class="{{ $expired ? 'client-overdue' : '' }}">{{ empty($details['expires_on']) ? 'Da verificare, data di scadenza non leggibile' : ($expired ? 'Documento non valido' : 'Documento valido') }}@if(!empty($details['expires_on'])) · Scadenza {{ \Illuminate\Support\Carbon::parse($details['expires_on'])->format('d/m/Y') }}@endif</small>
                        @endif
                    </{{ $editable ? 'label' : 'div' }}>
                </div>
                <a class="btn btn-g" href="{{ route('agency-communications.documents.download',[$communication,$file]) }}" aria-label="Scarica {{ $details['name'] }}">Scarica copia</a>
            </div>
        @endforeach
    </div>
@endif
