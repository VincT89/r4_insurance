<form method="GET" action="{{ route('clients.show', $client) }}" class="client-search-form">
    <input type="hidden" name="tab" value="deadlines">
    <x-form-group label="Periodo" name="period">
        <select id="field-period" name="period" class="form-sel">
            @foreach(['all' => 'Tutte le scadenze', 'overdue' => 'Già scadute', '7' => 'Prossimi 7 giorni', '15' => 'Prossimi 15 giorni', '30' => 'Prossimi 30 giorni', '45' => 'Prossimi 45 giorni', '60' => 'Prossimi 60 giorni', 'custom' => 'Intervallo personalizzato'] as $value => $label)
                <option value="{{ $value }}" @selected((string) request('period', 'all') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-form-group>
    <x-form-group label="Dal (intervallo personalizzato)" name="from"><input id="field-from" type="date" name="from" value="{{ request('from') }}" class="form-in"></x-form-group>
    <x-form-group label="Al (intervallo personalizzato)" name="to"><input id="field-to" type="date" name="to" value="{{ request('to') }}" class="form-in"></x-form-group>
    <button type="submit" class="btn btn-p">Filtra</button>
</form>
<x-panel title="Scadenze del cliente">
    <div class="client-table-scroll" role="region" aria-label="Scadenze del cliente" tabindex="0">
        <table class="t-table">
            <thead><tr><th>Data</th><th>Tipo</th><th>Elemento</th><th>Frazionamento</th><th>Situazione</th></tr></thead>
            <tbody>
                @forelse($deadlines as $deadline)
                    <tr>
                        <td><x-status-text :alert="!($deadline['policy_review'] ?? false) && ($deadline['date']->lt(today()) || ($deadline['expired'] ?? false))">{{ $deadline['date']->format('d/m/Y') }}</x-status-text></td><td>{{ $deadline['kind'] }}</td>
                        <td><a href="{{ $deadline['url'] }}">{{ $deadline['title'] }}</a>@if($deadline['detail'])<div class="u-text-meta">{{ $deadline['detail'] }}</div>@endif</td>
                        <td>{{ $deadline['frequency'] ?? 'Non indicato' }}</td><td class="{{ !($deadline['policy_review'] ?? false) && ($deadline['date']->lt(today()) || ($deadline['expired'] ?? false)) ? 'client-overdue' : '' }}">{{ ($deadline['policy_review'] ?? false) ? 'Stato polizza da verificare' : (($deadline['date']->lt(today()) || ($deadline['expired'] ?? false)) ? 'Scaduta' : ($deadline['date']->isToday() ? 'Scade oggi' : 'In scadenza')) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="u-empty-state">Nessuna scadenza nel periodo selezionato.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-panel>
