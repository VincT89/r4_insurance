<x-app-layout title="Riepilogo">
    <x-page-header>
        <x-slot:title>Riepilogo</x-slot:title>
        <x-slot:actions>@can('create', \App\Models\Client::class)<a href="{{ route('clients.create') }}" class="btn btn-g">Nuovo cliente</a><a href="{{ route('policies.create') }}" class="btn btn-p">Nuova polizza</a>@endcan</x-slot:actions>
    </x-page-header>
    @if($overview)
        <section class="overview-totals" aria-label="Situazione del portafoglio">
            @foreach([
                ['Clienti', $overview['clients'], route('clients.index'), false],
                ['Polizze', $overview['policies'], route('policies.index'), false],
                ['In vigore', $overview['active'], route('policies.index', ['status'=>'active']), false],
                ['Polizze in scadenza entro '.$period.' giorni', $overview['due'], route('deadlines.index', ['kind'=>'policy','period'=>$period]), false],
            ] as [$label, $value, $url, $alert])
                <a href="{{ $url }}" @class(['overview-total', 'is-alert' => $alert])><span>{{ $label }}</span><strong @class(['status-alert' => $alert])>{{ number_format($value, 0, ',', '.') }}</strong></a>
            @endforeach
        </section>
        <x-payment-summary :summary="$paymentSummary" />
        <div class="workspace-columns">
            <section class="workspace-primary" aria-label="Operatività e scadenze">
                <x-panel title="Titoli da incassare" id="due-titles">
                    <x-slot:headerActions><a class="btn btn-g btn-sm" href="{{ route('titles.index',['state'=>'open']) }}">Tutti i titoli</a></x-slot:headerActions>
                    <form method="GET" action="{{ route('dashboard') }}#due-titles" class="client-search-form" aria-label="Scadenza dei titoli">
                        <input type="hidden" name="period" value="{{ $period }}">
                        <x-form-group label="Scadenza dal" name="titles_from"><input class="form-in" type="date" name="titles_from" value="{{ $titlesDates['titles_from']??'' }}"></x-form-group>
                        <x-form-group label="Al" name="titles_to"><input class="form-in" type="date" name="titles_to" value="{{ $titlesDates['titles_to']??'' }}"></x-form-group>
                        <div class="search-form-actions"><button class="btn btn-p">Filtra titoli</button><a class="btn btn-g" href="{{ route('dashboard',['period'=>$period]) }}#due-titles">Azzera date</a></div>
                    </form>
                    <p class="u-text-meta">{{ array_filter($titlesDates) ? 'Titoli con residuo nel periodo scelto.' : 'Titoli scaduti e in scadenza entro '.$period.' giorni.' }}</p>
                    <div class="client-table-scroll" role="region" aria-label="Titoli da incassare" tabindex="0"><table class="t-table"><thead><tr><th>Scadenza</th><th>Cliente e polizza</th><th>Residuo</th><th>Stato</th></tr></thead><tbody>
                        @forelse($dueTitles as $title)<tr><td class="u-nowrap"><x-status-text :alert="$title->state === 'overdue'">{{ $title->due_on->format('d/m/Y') }}</x-status-text></td><td>{{ $title->policy->client->name }}<div><a href="{{ route('titles.show',$title) }}">{{ $title->policy->number }} / #{{ $title->id }}</a></div><div class="u-text-meta">{{ $title->payment_frequency }}</div></td><td class="u-nowrap"><x-status-text :alert="in_array($title->state, ['due_today', 'partial', 'overdue'], true)">{{ \App\Support\Money::format($title->balance_cents) }}</x-status-text></td><td><x-title-status :title="$title" /></td></tr>
                        @empty<tr><td colspan="4">Nessun titolo da incassare nel periodo.</td></tr>@endforelse
                    </tbody></table></div>{{ $dueTitles->links() }}
                </x-panel>
                <section id="today-policies" aria-label="Polizze inserite oggi">
                <x-dashboard-section title="Polizze inserite oggi" :empty="$todayPolicies->total() === 0" message="Nessuna polizza aperta oggi e ancora attiva.">
                <x-slot:actions>@can('communications.manage')<a href="{{ route('agency-communications.index') }}" class="btn btn-g btn-sm">Riepilogo per le agenzie</a>@endcan</x-slot:actions>
                <div class="client-table-scroll" role="region" aria-label="Polizze inserite oggi" tabindex="0"><table class="t-table" data-list-details="dashboard-policies" data-details-label="Altri dati"><thead><tr><th data-list-detail>Ora</th><th>Contraente</th><th>Polizza</th><th data-list-detail>Agenzia</th><th>Premio di polizza</th><th>Stato</th><th>Comunicazione</th></tr></thead><tbody>
                    @forelse($todayPolicies as $policy)<tr><td>{{ $policy->created_at->format('H:i') }}</td><td><a href="{{ route('clients.show',$policy->client_id) }}">{{ $policy->client->name }}</a></td><td><a class="u-nowrap" href="{{ route('clients.policies.show',[$policy->client_id,$policy]) }}">{{ $policy->number }}</a><div class="u-text-meta">{{ $policy->company }}</div></td><td>{{ $policy->supplierAgency?->name ?: 'Da assegnare' }}</td><td class="u-nowrap">{{ $policy->premium !== null ? number_format((float) $policy->premium, 2, ',', '.').' €' : 'Non indicato' }}</td><td><x-policy-status :policy="$policy" /></td><td>{{ $policy->ad_horas ? 'Ad horas' : 'Riepilogo serale' }}</td></tr>@empty<tr><td colspan="7">Nessuna polizza in questa pagina. Torna alla prima pagina dell’elenco.</td></tr>@endforelse
                </tbody></table></div>{{ $todayPolicies->links() }}</x-dashboard-section>
                </section>
                <x-dashboard-section title="Prossime scadenze" :empty="$duePolicies->isEmpty()" message="Nessuna polizza in scadenza nel periodo.">
                <x-slot:actions>
                    <nav class="period-options" aria-label="Periodo scadenze">@foreach(['7','15','30','60'] as $days)<a href="{{ route('dashboard', ['period'=>$days]+$titlesDates) }}" class="btn btn-g btn-sm" @if($period===$days) aria-current="page" @endif>{{ $days }} giorni</a>@endforeach</nav>
                </x-slot:actions>
                    <div class="client-table-scroll" role="region" aria-label="Prossime scadenze" tabindex="0">
                        <table class="t-table"><thead><tr><th>Scadenza</th><th>Cliente</th><th>Polizza</th><th>Ramo</th><th>Stato</th></tr></thead><tbody>
                        @forelse($duePolicies as $policy)
                            <tr><td><x-status-text :alert="$policy->is_expired">{{ $policy->expires_on->format('d/m/Y') }}</x-status-text></td><td><a href="{{ route('clients.show', $policy->client) }}">{{ $policy->client->name }}</a></td><td><a class="u-nowrap" href="{{ route('clients.policies.show', [$policy->client_id,$policy]) }}">{{ $policy->number }}</a><div class="u-text-meta">{{ $policy->company }}</div></td><td>{{ $policy->category }}</td><td><x-policy-status :policy="$policy" /></td></tr>
                        @empty<tr><td colspan="5" class="u-empty-state">Nessuna polizza in scadenza nel periodo.</td></tr>@endforelse
                        </tbody></table>
                    </div>
                    {{ $duePolicies->links() }}
                    <div class="panel-footer-link"><a href="{{ route('deadlines.index', ['period'=>$period]) }}">Apri lo scadenzario</a></div>
                </x-dashboard-section>
                @include('claims.dashboard')
            </section>
            <aside class="workspace-secondary">
                <x-panel :title="'Incassi '.today()->format('j/n/y')" class="collection-summary">
                    <x-slot:headerActions><a class="btn btn-g btn-sm" href="{{ route('collections.index') }}">Apri contabilità</a></x-slot:headerActions>
                    <dl class="collection-summary-values">
                        <div><dt>Incassi</dt><dd>{{ \App\Support\Money::format((int)$todayCollections->receipts) }}</dd></div>
                        <div><dt>Rimborsi</dt><dd>{{ \App\Support\Money::format((int)$todayCollections->refunds) }}</dd></div>
                        <div><dt>Saldo incassi meno rimborsi</dt><dd>{{ \App\Support\Money::format((int)$todayCollections->total) }}</dd></div>
                    </dl>
                    <details class="collection-cash-details"><summary>Ripartizione per chi ha gestito il pagamento</summary>
                        <dl><div><dt>Saldo della nostra agenzia</dt><dd>{{ \App\Support\Money::format((int)$todayCollections->ours) }}</dd></div><div><dt>Saldo compagnie e altre agenzie</dt><dd>{{ \App\Support\Money::format((int)$todayCollections->total-(int)$todayCollections->ours) }}</dd></div></dl>
                    </details>
                    <p class="collection-summary-note">Data effettiva del pagamento. Gli storni sono esclusi.</p>
                </x-panel>
                <x-panel title="Da seguire" padded>
                    <a class="work-queue-link" href="{{ route('titles.index', ['state'=>'overdue']) }}"><span>Sospese di cassa</span><strong><x-status-text :alert="$paymentSummary['overdue']['count'] > 0">{{ number_format($paymentSummary['overdue']['count'],0,',','.') }}</x-status-text></strong></a>
                    <a class="work-queue-link" href="{{ route('policies.index', ['status'=>'review']) }}"><span>Stato da chiarire</span><strong>{{ number_format($overview['review'],0,',','.') }}</strong></a>
                    <a class="work-queue-link" href="{{ route('deadlines.index', ['kind'=>'request','period'=>'overdue']) }}"><span>Documenti da ricevere</span><span>Apri</span></a>
                </x-panel>
                <x-panel title="Richieste documenti" padded>
                    @forelse($missingDocuments as $item)
                        <a class="document-queue-link" href="{{ route('clients.show', [$item->client_id,'tab'=>'documents']).'#document-request-'.$item->id }}"><strong>{{ $item->client->name }}</strong><span>{{ $item->label }}</span>@if($item->due_on)<span class="u-text-meta"><x-status-text :alert="$item->due_on->lt(today())">Entro {{ $item->due_on->format('d/m/Y') }}</x-status-text></span>@endif</a>
                    @empty<p class="empty-copy">Nessuna richiesta aperta.</p>@endforelse
                </x-panel>
                @can('system.admin')@livewire('dashboard.recent-activity')<a class="btn btn-g workspace-report-link" href="{{ route('reports.index') }}">Apri Report</a>@endcan
            </aside>
        </div>
    @else
        <x-panel title="Area di lavoro" padded><p class="u-mb-md">Benvenuto, {{ auth()->user()->name }}.</p><a href="{{ route('daily-notes.index') }}" class="btn btn-g">Blocco note</a></x-panel>
    @endif
</x-app-layout>
