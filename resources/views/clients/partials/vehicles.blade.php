<x-panel title="Veicoli e targhe">
    <x-slot:headerActions>@can('updateRegistry', $client)<a href="{{ route('clients.vehicles.create', $client) }}" class="btn btn-p btn-sm">Aggiungi veicolo</a>@endcan</x-slot:headerActions>
    <div class="client-table-scroll" role="region" aria-label="Veicoli del cliente" tabindex="0">
        <table class="t-table">
            <thead><tr><th>Targa</th><th>Marca e modello</th><th>Telaio</th><th>Classe dichiarata</th><th>Polizze e compagnie</th><th>Azioni</th></tr></thead>
            <tbody>
                @forelse($vehicles as $vehicle)
                    <tr><td>{{ $vehicle->plate }}</td><td>{{ trim($vehicle->make.' '.$vehicle->model) ?: 'Non indicati' }}</td><td>{{ $vehicle->vin ?: 'Non indicato' }}</td><td>{{ $vehicle->merit_class_cu ? 'CU '.$vehicle->merit_class_cu : 'Non indicata' }}@if($vehicle->merit_class_internal)<div class="u-text-meta">Interna {{ $vehicle->merit_class_internal }} · {{ $vehicle->meritClassCompany?->name }}</div>@endif</td>
                        <td>@forelse($vehicle->policies as $vehiclePolicy)<div class="vehicle-policy-reference"><a href="{{ route('clients.policies.show',[$client,$vehiclePolicy]) }}">{{ $vehiclePolicy->number }} · {{ $vehiclePolicy->company }}</a><div class="u-text-meta">{{ $vehiclePolicy->merit_class_cu ? 'CU '.$vehiclePolicy->merit_class_cu : 'CU non indicata' }}{{ $vehiclePolicy->merit_class_internal ? ' · Interna '.$vehiclePolicy->merit_class_internal : '' }} · {{ $vehiclePolicy->status_label }} · {{ $vehiclePolicy->expires_on?->format('d/m/Y') }}</div></div>@empty Nessuna polizza @endforelse</td>
                        <td>@can('updateRegistry', $client)<div class="vehicle-actions"><a href="{{ route('clients.vehicles.edit', [$client, $vehicle]) }}" class="btn btn-g btn-sm" aria-label="Modifica veicolo {{ $vehicle->plate }}">Modifica</a><a href="{{ route('quotes.create',['client_id'=>$client->id,'vehicle_id'=>$vehicle->id]) }}" class="btn btn-p btn-sm">Richiedi preventivo</a></div>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="u-empty-state">Nessun veicolo inserito. Ogni targa può essere collegata a più polizze.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-panel>
