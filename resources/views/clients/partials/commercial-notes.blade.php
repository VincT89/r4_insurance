@if(filled($client->commercial_notes))
    <div class="mt-panel">
        <x-panel title="Note commerciali" padded>
            <div class="client-commercial-notes">{{ $client->commercial_notes }}</div>
        </x-panel>
    </div>
@endif
