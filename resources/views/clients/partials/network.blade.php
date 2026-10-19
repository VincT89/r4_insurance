@include('clients.partials.registry-records', ['section' => 'contacts', 'records' => $contacts])
<x-panel title="Relazioni tra clienti" padded>
    <x-slot:headerActions>@can('updateRegistry', $client)<a href="{{ route('clients.relationships.create', $client) }}" class="btn btn-p btn-sm">Collega cliente</a>@endcan</x-slot:headerActions>
    @forelse($relationships as $relationship)
        @php $other = $relationship->other($client); @endphp
        <section class="client-detail-section">
            <h2 class="sec-lbl"><a href="{{ route('clients.show', [$other, 'tab' => 'network']) }}">{{ $other->name }}</a></h2>
            <dl class="client-registry-details"><div><dt>Relazione</dt><dd>{{ \App\Models\ClientRelationship::TYPES[$relationship->relationship_type] }}</dd></div><div><dt>Ruolo di {{ $client->name }}</dt><dd>{{ $relationship->roleFor($client) ?: 'Non indicato' }}</dd></div><div><dt>Ruolo di {{ $other->name }}</dt><dd>{{ $relationship->roleFor($other) ?: 'Non indicato' }}</dd></div></dl>
            @if($relationship->notes)<p class="u-whitespace-pre-wrap">{{ $relationship->notes }}</p>@endif
            @if(auth()->user()->can('updateRegistry', $client) && auth()->user()->can('updateRegistry', $other))
                <div class="insurance-form-actions"><a href="{{ route('clients.relationships.edit', [$client, $relationship]) }}" class="btn btn-g btn-sm">Modifica relazione</a>@can('system.admin')<x-delete-modal :action="route('clients.relationships.destroy', [$client, $relationship])" title="Rimuovi relazione" message="Verrà rimosso solo il collegamento tra i clienti."><button type="button" class="btn btn-g btn-sm">Rimuovi relazione</button></x-delete-modal>@endcan</div>
            @endif
        </section>
    @empty <p>Nessuna relazione consultabile.</p> @endforelse
</x-panel>
<x-panel title="Nuclei familiari" padded>
    <x-slot:headerActions>@if($client->client_type === 'person')@can('updateRegistry', $client)<a href="{{ route('clients.households.create', $client) }}" class="btn btn-p btn-sm">Crea nucleo familiare</a>@endcan @endif</x-slot:headerActions>
    @forelse($households as $household)
        <section class="client-detail-section"><h2 class="sec-lbl">{{ $household->name }}</h2>
            <ul>@foreach($household->members as $member)<li><a href="{{ route('clients.show', [$member, 'tab' => 'network']) }}">{{ $member->name }}</a>@if($member->pivot->role) / {{ $member->pivot->role }}@endif</li>@endforeach</ul>
            @if($household->notes)<p class="u-whitespace-pre-wrap">{{ $household->notes }}</p>@endif
            @if($household->members->every(fn($member) => auth()->user()->can('updateRegistry', $member)))
                <div class="insurance-form-actions"><a href="{{ route('clients.households.edit', [$client, $household]) }}" class="btn btn-g btn-sm">Modifica nucleo</a>@can('system.admin')<x-delete-modal :action="route('clients.households.destroy', [$client, $household])" title="Rimuovi nucleo" message="Verrà rimosso il raggruppamento familiare. Le anagrafiche dei componenti restano disponibili."><button type="button" class="btn btn-g btn-sm">Rimuovi nucleo</button></x-delete-modal>@endcan</div>
            @endif
        </section>
    @empty <p>Nessun nucleo familiare consultabile.</p> @endforelse
</x-panel>
