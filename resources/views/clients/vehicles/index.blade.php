<x-app-layout title="Parco veicoli">
    <x-page-header>
        <x-slot:title>Parco veicoli</x-slot:title>
        <x-slot:actions><a href="{{ route('clients.index') }}" class="btn btn-g">Anagrafica clienti</a></x-slot:actions>
    </x-page-header>
    <x-panel padded>
        <form method="GET" action="{{ route('vehicles.index') }}">
            <div class="form-row">
                <x-form-group label="Cliente" name="client"><input id="field-client" class="form-in" type="search" name="client" value="{{ request('client') }}" maxlength="255" placeholder="Nome o ragione sociale"></x-form-group>
                <x-form-group label="Targa" name="plate"><input id="field-plate" class="form-in" type="search" name="plate" value="{{ request('plate') }}" maxlength="20" placeholder="Anche parte della targa"></x-form-group>
                <x-form-group label="Classe di merito CU" name="merit_class_cu"><select id="field-merit-class-cu" class="form-sel" name="merit_class_cu"><option value="">Tutte le classi</option>@for($cu = 1; $cu <= 18; $cu++)<option value="{{ $cu }}" @selected((string) request('merit_class_cu') === (string) $cu)>{{ $cu }}</option>@endfor</select></x-form-group>
                <x-form-group label="Compagnia della polizza" name="company_id"><select id="field-company-id" class="form-sel" name="company_id"><option value="">Tutte le compagnie</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>{{ $company->name }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Agenzia della polizza" name="agency_id"><select id="field-agency-id" class="form-sel" name="agency_id"><option value="">Tutte le agenzie</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((string) request('agency_id') === (string) $agency->id)>{{ $agency->name }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Scadenza polizza dal" name="expires_from"><input id="field-expires-from" class="form-in" type="date" name="expires_from" value="{{ request('expires_from') }}"></x-form-group>
                <x-form-group label="Scadenza polizza al" name="expires_to"><input id="field-expires-to" class="form-in" type="date" name="expires_to" value="{{ request('expires_to') }}"></x-form-group>
            </div>
            <div class="insurance-form-actions"><button type="submit" class="btn btn-p">Cerca veicoli</button><a href="{{ route('vehicles.index') }}" class="btn btn-g">Azzera filtri</a></div>
        </form>
    </x-panel>
    <x-panel>
        @if($hasPolicyFilters)<p class="u-text-meta u-mb-md">Sono mostrate solo le polizze che corrispondono ai filtri selezionati.</p>@endif
        <div class="client-table-scroll" role="region" aria-label="Parco veicoli dei clienti" tabindex="0">
            <table class="t-table">
                <thead><tr><th scope="col">Targa e veicolo</th><th scope="col">Cliente</th><th scope="col">Classe di merito</th><th scope="col">Polizze collegate</th><th scope="col">Azioni</th></tr></thead>
                <tbody>
                    @forelse($vehicles as $vehicle)
                        <tr>
                            <td><strong>{{ $vehicle->plate }}</strong><div class="u-text-meta">{{ trim($vehicle->make.' '.$vehicle->model) ?: 'Marca e modello non indicati' }}</div></td>
                            <td><a href="{{ route('clients.show', [$vehicle->client, 'tab' => 'vehicles']) }}">{{ $vehicle->client->name }}</a></td>
                            <td>{{ $vehicle->merit_class_cu ? 'CU '.$vehicle->merit_class_cu : 'Non indicata' }}@if($vehicle->merit_class_internal)<div class="u-text-meta">Classe della compagnia: {{ $vehicle->merit_class_internal }} / {{ $vehicle->meritClassCompany?->name }}</div>@endif</td>
                            <td>@forelse($vehicle->policies as $policy)
                                <div class="vehicle-policy-reference"><a href="{{ route('clients.policies.show', [$vehicle->client, $policy]) }}">{{ $policy->number }}</a> / {{ $policy->company }}<div class="u-text-meta">{{ $policy->supplierAgency?->name ?: 'Agenzia non indicata' }}</div><div class="u-text-meta">{{ $policy->status_label }} / Scadenza {{ $policy->expires_on?->format('d/m/Y') ?: 'non indicata' }}</div></div>
                            @empty Nessuna polizza collegata @endforelse</td>
                            <td>@can('updateRegistry', $vehicle->client)<div class="vehicle-actions"><a class="btn btn-g btn-sm" href="{{ route('clients.vehicles.edit', [$vehicle->client, $vehicle]) }}" aria-label="Modifica veicolo {{ $vehicle->plate }}">Modifica</a><a class="btn btn-p btn-sm" href="{{ route('quotes.create', ['client_id' => $vehicle->client_id, 'vehicle_id' => $vehicle->id]) }}" aria-label="Richiedi preventivo per {{ $vehicle->plate }}">Richiedi preventivo</a></div>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="u-empty-state">Nessun veicolo trovato. Puoi modificare i filtri oppure aggiungere un veicolo dalla scheda del cliente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $vehicles->links() }}
    </x-panel>
</x-app-layout>
