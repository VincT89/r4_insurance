<x-app-layout title="Moduli e firme">
<x-page-header><x-slot:title>Moduli e firme · {{ $client->name }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ $policy ? route('clients.policies.show',[$client,$policy]) : route('clients.show',$client) }}">{{ $policy ? 'Polizza '.$policy->number : 'Scheda cliente' }}</a>@can('updateRegistry',$client)@if(!$policy || $policy->editableBy(auth()->user()))<a class="btn btn-p" href="{{ route('clients.forms.create',[$client,'policy'=>$policy?->id]) }}">Prepara moduli</a>@endif @endcan</x-slot:actions></x-page-header>
@include('insurance-forms.open-signatures')
<form class="client-search-form filter-grid" method="GET"><input type="hidden" name="policy" value="{{ $policy?->id }}"><div class="form-row"><x-form-group label="Motivo" name="reason"><select name="reason" class="form-sel"><option value="">Tutti</option>@foreach(\App\Models\InsuranceFormPackage::REASONS as $key=>$label)<option value="{{ $key }}" @selected(($filters['reason']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group><x-form-group label="Preparati dal" name="from"><input class="form-in" name="from" type="date" value="{{ $filters['from']??'' }}"></x-form-group><x-form-group label="Preparati fino al" name="to"><input class="form-in" name="to" type="date" value="{{ $filters['to']??'' }}"></x-form-group></div><div class="search-form-actions"><a class="btn btn-g" href="{{ route('clients.forms.index',[$client,'policy'=>$policy?->id]) }}">Azzera</a><button class="btn btn-p">Applica filtri</button></div></form>
<x-panel title="Fascicoli e storico">
    <div class="client-table-scroll policy-list-scroll">
        <table class="t-table policy-documents-table form-package-history">
            <thead><tr><th>Fascicolo</th><th>Polizza e periodo</th><th>Preparazione</th><th>Stato</th><th>Azioni</th></tr></thead>
            <tbody>
            @forelse($packages as $package)
                <tr>
                    <td data-label="Fascicolo"><strong>{{ \App\Models\InsuranceFormPackage::REASONS[$package->reason] }}</strong><div class="u-text-meta">#{{ $package->id }}</div></td>
                    <td data-label="Polizza e periodo">{{ $package->policy_number ?? 'Documenti del cliente' }}@if($period = $package->policy_period)<div class="u-text-meta">{{ $period }}</div>@endif</td>
                    <td data-label="Preparazione">{{ $package->created_at->format('d/m/Y H:i') }}<div class="u-text-meta">{{ $package->author?->name ?? 'Utente non disponibile' }}</div></td>
                    <td data-label="Stato">{{ $package->state_label }}</td>
                    <td data-label="Azioni"><a class="btn btn-g btn-sm" href="{{ route('clients.forms.show',[$client,$package]) }}">Apri fascicolo</a></td>
                </tr>
            @empty
                <tr><td colspan="5">Nessun fascicolo preparato.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $packages->links() }}
</x-panel>
</x-app-layout>
