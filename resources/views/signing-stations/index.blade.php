<x-app-layout title="Postazioni firma">
    <x-page-header><x-slot:title>Postazioni firma</x-slot:title></x-page-header>
    @include('profile.partials.settings-navigation')
    <p class="u-mb-md">Sul tablet apri <strong>{{ config('document_signing.station_public_url') }}</strong> e premi «Collega questa postazione». Riporta qui il codice visualizzato. Ogni tablet ha il proprio collegamento.</p>
    <x-panel title="Collega un tablet" padded>
        <form method="post" action="{{ route('signing-stations.pair') }}">
            @csrf
            <div class="form-row">
                <x-form-group label="Codice mostrato sul tablet" name="pairing_code" required><input class="form-in" name="pairing_code" inputmode="numeric" pattern="[0-9]{8}" minlength="8" maxlength="8" autocomplete="off" required></x-form-group>
                <x-form-group label="Nome della postazione" name="name" required><input class="form-in" name="name" value="{{ old('name') }}" placeholder="Ad esempio Reception" minlength="2" maxlength="80" required></x-form-group>
            </div>
            <div class="insurance-form-actions"><button class="btn btn-p">Collega tablet</button></div>
        </form>
    </x-panel>
    <x-panel title="Postazioni collegate">
        <div class="client-table-scroll"><table class="t-table"><thead><tr><th>Postazione</th><th>Collegamento</th><th>Ultimo contatto</th><th>Azioni</th></tr></thead><tbody>
        @forelse($stations as $station)
            <tr><td>{{ $station->name }}<div class="u-text-meta">Collegata da {{ $station->pairedBy?->name }}</div></td>
                <td>{{ $station->usable() ? ($station->pending_requests_count ? 'Occupata' : 'Disponibile') : ($station->status === 'revoked' ? 'Revocato' : 'Scaduto') }}</td>
                <td>{{ $station->last_seen_at?->format('d/m/Y H:i') ?? 'In attesa del tablet' }}</td>
                <td>@if($station->usable() && (auth()->user()->isAdmin() || $station->paired_by === auth()->id()))
                    <details><summary>Revoca collegamento</summary><form method="post" action="{{ route('signing-stations.revoke',$station) }}">@csrf
                        <label class="signing-confirm"><input type="checkbox" name="confirmed" value="1" required><span>Interrompi anche le richieste ancora in attesa su questo tablet.</span></label>
                        <button class="btn btn-g">Revoca questo tablet</button>
                    </form></details>
                @else<span class="u-text-meta">{{ $station->usable() ? 'Revoca riservata a chi l’ha collegata o all’admin' : 'Puoi collegarla di nuovo dal tablet' }}</span>@endif</td>
            </tr>
        @empty<tr><td colspan="4">Nessuna postazione collegata. Apri la pagina di firma sul tablet per iniziare.</td></tr>@endforelse
        </tbody></table></div>
    </x-panel>
    <p class="u-text-meta">Il codice dura {{ config('document_signing.station_pairing_minutes') }} minuti. Il collegamento del tablet dura fino a {{ config('document_signing.station_credential_days') }} giorni e può essere revocato prima.</p>
</x-app-layout>
