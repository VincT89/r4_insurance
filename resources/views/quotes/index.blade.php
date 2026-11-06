<x-app-layout title="Preventivi">
<x-page-header><x-slot:title>Preventivi{{ $selectedClient ? ' / '.$selectedClient->name : '' }}</x-slot:title><x-slot:actions>@if($selectedClient)<a class="btn btn-g" href="{{ route('clients.show',$selectedClient) }}">Scheda cliente</a><a class="btn btn-g" href="{{ route('quotes.index') }}">Tutti i preventivi</a>@endif @can('create',\App\Models\Client::class)<a class="btn btn-p" href="{{ route('quotes.create', $selectedClient ? ['client_id'=>$selectedClient->id] : []) }}">Nuovo preventivo</a>@endcan</x-slot:actions></x-page-header>
<form id="quotes-filters" method="GET" class="client-search-form" aria-label="Filtra preventivi" data-list-filters>
@if($filters['client']??null)<input type="hidden" name="client" value="{{ $filters['client'] }}">@endif
<x-form-group label="Cliente o oggetto" name="search"><input class="form-in" name="search" value="{{ $filters['search']??'' }}"></x-form-group>
<x-form-group label="Stato pratica" name="status"><select class="form-sel" name="status"><option value="">Tutti</option>@foreach(['open'=>'Aperta','approved'=>'Approvata','closed'=>'Polizza stipulata'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['status']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
<x-form-group label="Compagnia" name="company"><select class="form-sel" name="company"><option value="">Tutte</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(($filters['company']??'')==$company->id)>{{ $company->name }}</option>@endforeach</select></x-form-group>
<div class="search-form-actions"><button class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route('quotes.index', $selectedClient ? ['client'=>$selectedClient->id] : []) }}">Azzera filtri</a></div>
<details class="search-more" @if(filled($filters['from']??null) || filled($filters['to']??null) || filled($filters['plate']??null) || filled($filters['cu']??null)) open @endif>
<summary class="btn btn-g">Altri filtri</summary><div class="search-more-fields">
<x-form-group label="Targa del veicolo" name="plate"><input class="form-in" name="plate" value="{{ $filters['plate']??'' }}" maxlength="20"></x-form-group>
<x-form-group label="Classe CU" name="cu"><select class="form-sel" name="cu"><option value="">Tutte</option>@for($cu=1;$cu<=18;$cu++)<option value="{{ $cu }}" @selected((string)($filters['cu']??'')===(string)$cu)>{{ $cu }}</option>@endfor</select></x-form-group>
@foreach(['from'=>'Apertura dal','to'=>'Al'] as $key=>$label)<x-form-group :label="$label" :name="$key"><input class="form-in" type="date" name="{{ $key }}" value="{{ $filters[$key]??'' }}"></x-form-group>@endforeach
</div><div data-column-filters></div></details>
</form>
<x-panel><p class="list-results" data-table-page-summary>{{ $cases->total() }} {{ $cases->total() === 1 ? 'preventivo' : 'preventivi' }} · Pagina {{ $cases->currentPage() }} di {{ $cases->lastPage() }}</p><div class="client-table-scroll" role="region" aria-label="Preventivi" tabindex="0"><table class="t-table" data-list-details="quotes" data-details-label="Altri dati" data-filter-form="quotes-filters"><thead><tr><th>Pratica</th><th>Cliente</th><th>Targa</th><th data-list-detail>Classe CU</th><th data-list-detail>Gruppo</th><th data-list-detail>Ramo</th><th data-list-detail>Sottoramo</th><th data-list-detail>Compagnie interpellate</th><th>Data</th><th>Stato</th></tr></thead><tbody>
@forelse($cases as $case)
    <tr data-list-label="{{ $case->subject }}">
        <td><a class="quote-entry-link" href="{{ route('quotes.show',$case) }}" aria-label="Apri preventivo {{ $case->subject }}"><strong>{{ $case->subject }}</strong><span data-table-export-ignore>Apri preventivo</span></a>
            @if($case->status === 'open' && $case->quotes->isNotEmpty())
                @php($replyCount = $case->quotes->where('status', '!=', 'requested')->count())
                <small class="quote-reply-count" data-table-export-ignore>{{ $replyCount }} {{ $replyCount === 1 ? 'risposta' : 'risposte' }} su {{ $case->quotes->count() }} {{ $case->quotes->count() === 1 ? 'richiesta' : 'richieste' }}</small>
            @endif
        </td>
        <td><a href="{{ route('clients.show',$case->client) }}">{{ $case->client->name }}</a></td>
        <td>{{ $case->vehicle_plate ?: 'Non collegata' }}</td><td>{{ $case->merit_class_cu ?: 'Non indicata' }}</td>
        <td>{{ $case->recipient_group_snapshot['name'] ?? 'Scelta manuale' }}</td><td>{{ $case->branch->name }}</td>
        <td>{{ $case->subbranch?->name ?: 'Non specificato' }}</td><td>{{ $case->quotes->map(fn($q)=>$q->company->name)->unique()->join(', ') ?: 'Nessuna richiesta registrata' }}</td>
        <td>{{ $case->created_at->format('d/m/Y') }}</td><td>{{ ['open'=>'Aperta','approved'=>'Approvata','closed'=>'Polizza stipulata'][$case->status] }}</td>
    </tr>
@empty
    <tr><td colspan="10">Nessun preventivo corrisponde ai filtri.</td></tr>
@endforelse
</tbody></table></div>{{ $cases->links() }}</x-panel>
</x-app-layout>
