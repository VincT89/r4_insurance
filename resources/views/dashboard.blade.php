<x-app-layout title="Dashboard">
    <x-page-header>
        <x-slot:title>Dashboard</x-slot:title>
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
                <x-dashboard-section title="Polizze inserite oggi" :empty="$todayPolicies->isEmpty()" message="Nessuna polizza aperta oggi e ancora attiva.">
                <x-slot:actions>@can('system.admin')<a href="{{ route('agency-communications.index') }}" class="btn btn-g btn-sm">Riepilogo per le agenzie</a>@endcan</x-slot:actions>
                <div class="client-table-scroll" role="region" aria-label="Polizze inserite oggi" tabindex="0"><table class="t-table"><thead><tr><th>Ora</th><th>Contraente</th><th>Polizza</th><th>Agenzia</th><th>Premio di polizza</th><th>Stato</th><th>Comunicazione</th></tr></thead><tbody>
                    @forelse($todayPolicies as $policy)<tr><td>{{ $policy->created_at->format('H:i') }}</td><td><a href="{{ route('clients.show',$policy->client_id) }}">{{ $policy->client->name }}</a></td><td><a class="u-nowrap" href="{{ route('clients.policies.show',[$policy->client_id,$policy]) }}">{{ $policy->number }}</a><div class="u-text-meta">{{ $policy->company }}</div></td><td>{{ $policy->supplierAgency?->name ?: 'Da assegnare' }}</td><td class="u-nowrap">{{ $policy->premium !== null ? number_format((float) $policy->premium, 2, ',', '.').' €' : 'Non indicato' }}</td><td><x-policy-status :policy="$policy" /></td><td>{{ $policy->ad_horas ? 'Ad horas' : 'Riepilogo serale' }}</td></tr>@empty<tr><td colspan="7">Nessuna polizza aperta oggi e ancora attiva.</td></tr>@endforelse
                </tbody></table></div></x-dashboard-section>
                <x-dashboard-section title="Rate scadute e in scadenza" :empty="$dueTitles->isEmpty()" :message="'Nessuna rata scaduta o in scadenza entro '.$period.' giorni.'">
                <x-slot:actions><a class="btn btn-g btn-sm" href="{{ route('titles.index',['state'=>'open']) }}">Tutte le rate da incassare</a></x-slot:actions>
                <div class="client-table-scroll" role="region" aria-label="Rate da incassare" tabindex="0"><table class="t-table"><thead><tr><th>Scadenza</th><th>Cliente</th><th>Polizza / rata</th><th>Frazionamento</th><th>Residuo</th><th>Stato</th></tr></thead><tbody>@forelse($dueTitles as $title)<tr><td><x-status-text :alert="$title->state === 'overdue'">{{ $title->due_on->format('d/m/Y') }}</x-status-text></td><td>{{ $title->policy->client->name }}</td><td><a href="{{ route('titles.show',$title) }}">{{ $title->policy->number }} / #{{ $title->id }}</a></td><td>{{ $title->payment_frequency }}</td><td><x-status-text :alert="in_array($title->state, ['due_today', 'partial', 'overdue'], true)">{{ \App\Support\Money::format($title->balance_cents) }}</x-status-text></td><td><x-title-status :title="$title" /></td></tr>@empty<tr><td colspan="6">Nessuna rata scaduta o in scadenza entro {{ $period }} giorni.</td></tr>@endforelse</tbody></table></div>{{ $dueTitles->links() }}</x-dashboard-section>
                <x-dashboard-section title="Prossime scadenze" :empty="$duePolicies->isEmpty()" message="Nessuna polizza in scadenza nel periodo.">
                <x-slot:actions>
                    <nav class="period-options" aria-label="Periodo scadenze">@foreach(['7','15','30','60'] as $days)<a href="{{ route('dashboard', ['period'=>$days]) }}" class="btn btn-g btn-sm" @if($period===$days) aria-current="page" @endif>{{ $days }} giorni</a>@endforeach</nav>
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
                @can('system.admin')@livewire('dashboard.recent-activity')@endcan
                <x-panel title="Da seguire" padded>
                    <a class="work-queue-link" href="{{ route('titles.index', ['state'=>'overdue']) }}"><span>Rate scadute</span><strong><x-status-text :alert="$paymentSummary['overdue']['count'] > 0">{{ number_format($paymentSummary['overdue']['count'],0,',','.') }}</x-status-text></strong></a>
                    <a class="work-queue-link" href="{{ route('policies.index', ['status'=>'review']) }}"><span>Stato da chiarire</span><strong>{{ number_format($overview['review'],0,',','.') }}</strong></a>
                    <a class="work-queue-link" href="{{ route('deadlines.index', ['kind'=>'request','period'=>'overdue']) }}"><span>Documenti da ricevere</span><span>Apri</span></a>
                </x-panel>
                <x-panel title="Richieste documenti" padded>
                    @forelse($missingDocuments as $item)
                        <a class="document-queue-link" href="{{ route('clients.show', [$item->client_id,'tab'=>'documents']).'#document-request-'.$item->id }}"><strong>{{ $item->client->name }}</strong><span>{{ $item->label }}</span>@if($item->due_on)<span class="u-text-meta"><x-status-text :alert="$item->due_on->lt(today())">Entro {{ $item->due_on->format('d/m/Y') }}</x-status-text></span>@endif</a>
                    @empty<p class="empty-copy">Nessuna richiesta aperta.</p>@endforelse
                </x-panel>
                @can('system.admin')<a class="btn btn-g workspace-report-link" href="{{ route('reports.index') }}">Apri Report</a>@endcan
            </aside>
        </div>
    @else
        <x-panel title="Area di lavoro" padded><p class="u-mb-md">Benvenuto, {{ auth()->user()->name }}.</p><a href="{{ route('daily-notes.index') }}" class="btn btn-g">Blocco note</a></x-panel>
    @endif
</x-app-layout>
