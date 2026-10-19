<x-panel title="Veicoli e targhe">
    <x-slot:headerActions>@can('updateRegistry', $client)<a href="{{ route('clients.vehicles.create', $client) }}" class="btn btn-p btn-sm">Aggiungi veicolo</a>@endcan</x-slot:headerActions>
    <div class="client-table-scroll" role="region" aria-label="Veicoli del cliente" tabindex="0">
        <table class="t-table">
            <thead><tr><th>Targa</th><th>Marca e modello</th><th>Telaio</th><th>Polizze collegate</th><th>Azioni</th></tr></thead>
            <tbody>
                @forelse($vehicles as $vehicle)
                    <tr><td>{{ $vehicle->plate }}</td><td>{{ trim($vehicle->make.' '.$vehicle->model) ?: 'Non indicati' }}</td><td>{{ $vehicle->vin ?: 'Non indicato' }}</td><td>{{ $vehicle->policies_count }}</td>
                        <td>@can('updateRegistry', $client)<a href="{{ route('clients.vehicles.edit', [$client, $vehicle]) }}" class="btn btn-g btn-sm" aria-label="Modifica veicolo {{ $vehicle->plate }}">Modifica</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="u-empty-state">Nessun veicolo inserito. Ogni targa può essere collegata a più polizze.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-panel>
