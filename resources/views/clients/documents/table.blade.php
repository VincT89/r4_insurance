<div class="client-table-scroll" role="region" aria-label="Documenti del cliente" tabindex="0">
    <table class="t-table">
        <thead><tr><th>Documento</th><th>Categoria</th><th>Collegamento</th><th>Scadenza</th><th>Verifica e versione</th><th>Azioni</th></tr></thead>
        <tbody>
            @forelse($documents as $document)
                <tr>
                    <td><a href="{{ $document->downloadUrl() }}">{{ $document->original_name }}</a>@if($document->description)<div class="u-text-meta">{{ $document->description }}</div>@endif<div class="u-text-meta">Caricato il {{ $document->created_at->format('d/m/Y') }} da {{ $document->uploader?->name ?? 'Utente non disponibile' }}</div></td>
                    <td>{{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$document->document_category] ?? 'Allegato esistente' }}@if($document->identity_type)<div class="u-text-meta">{{ \App\Models\Attachment::IDENTITY_TYPES[$document->identity_type] ?? '' }}</div>@endif</td>
                    <td>
                        @if($document->insurancePolicy)<a href="{{ route('clients.policies.show', [$client, $document->insurancePolicy]) }}">Polizza {{ $document->insurancePolicy->number }}</a>
                        @elseif($document->vehicle)Veicolo {{ $document->vehicle->plate }}
                        @else Anagrafica cliente @endif
                        @if($document->insurance_title_id)<div><a href="{{ route('titles.show',$document->insurance_title_id) }}">Titolo #{{ $document->insurance_title_id }}</a></div>@endif
                        @if($document->insurance_form_document_id)<div><a href="{{ route('clients.forms.show',[$client,$document->formDocument->insurance_form_package_id]) }}">Fascicolo moduli e firme</a></div>@endif
                        @if($document->insurance_coverage_check_id)<div><a href="{{ route('clients.policies.coverage',[$client,$document->insurance_policy_id]) }}">Controllo RCA</a></div>@endif
                        @if($document->insurance_claim_id)<div><a href="{{ route('claims.show',$document->insurance_claim_id) }}">Sinistro #{{ $document->insurance_claim_id }}</a></div>@endif
                        @if($document->insurance_quote_email_id)<div><a href="{{ route('quote-emails.show',$document->insurance_quote_email_id) }}">Richiesta preventivo</a></div>@endif
                        @if($document->insurance_quote_case_id)<div><a href="{{ route('quotes.show',$document->insurance_quote_case_id) }}">Pratica preventivi</a></div>@endif
                        @if($document->insurance_quote_id)<div><a href="{{ route('quotes.show',\App\Models\InsuranceQuote::find($document->insurance_quote_id)->insurance_quote_case_id) }}">Preventivo e approvazione</a></div>@endif
                    </td>
                    <td><x-status-text :alert="$document->expires_on?->lt(today())">{{ $document->expires_on?->format('d/m/Y') ?? 'Non indicata' }}</x-status-text>@if($document->document_category === 'identity' || $document->expires_on)<div @class(['client-overdue' => $document->expires_on?->lt(today())])>{{ $document->validity_label }}</div>@endif</td>
                    <td>Versione {{ $document->version_number }}{{ $document->newerVersion ? ' / storica' : '' }}<div>{{ ['generated'=>'Modulo predisposto','validated'=>'Validato','rejected'=>'Da correggere'][$document->review_status] ?? ($document->insurance_claim_id?'Conservato nel fascicolo sinistro':($document->insurance_coverage_check_id?'Prova del controllo RCA':'Ricevuto, da validare')) }}</div>@if($document->validated_at)<div class="u-text-meta">{{ $document->validated_at->format('d/m/Y H:i') }} / {{ $document->validator?->name ?? 'Utente non disponibile' }}</div>@endif</td>
                    <td><div class="commercial-actions">
                        @if($document->document_category && !$document->newerVersion && !$document->insurance_quote_case_id && !$document->insurance_quote_id && !$document->insurance_title_id && !$document->insurance_collection_id && !$document->insurance_quote_email_id && !$document->insurance_form_document_id && !$document->insurance_coverage_check_id && !$document->insurance_claim_id)
                            @can('updateRegistry', $client)<a href="{{ route('clients.documents.edit', [$client, $document]) }}" class="btn btn-g btn-sm" aria-label="Modifica dati di {{ $document->original_name }}">Modifica dati</a><a class="btn btn-g btn-sm" href="{{ route('clients.documents.create', [$client, 'replace'=>$document->id]) }}">Nuova versione</a>@endcan
                        @endif
                        @if(!$document->previous_version_id && !$document->newerVersion && !$document->document_request_id && !$document->checklist_requests_exists && !$document->insurance_quote_case_id && !$document->insurance_quote_id && !$document->insurance_title_id && !$document->insurance_collection_id && !$document->insurance_quote_email_id && !$document->insurance_form_document_id && !$document->insurance_coverage_check_id && !$document->insurance_claim_id)
                        @can('delete', $document)
                            <x-delete-modal action="{{ route('attachments.destroy', $document) }}" title="Elimina documento" message="Il file verrà eliminato. Confermi?">
                                <button type="button" class="btn btn-g btn-sm" aria-label="Elimina {{ $document->original_name }}">Elimina</button>
                            </x-delete-modal>
                        @endcan
                        @endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="u-empty-state">Nessun documento caricato.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
