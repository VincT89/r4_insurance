<x-app-layout title="Importazione Excel">
    <x-page-header><x-slot:title>Importazione Excel</x-slot:title>
        <x-slot:actions>@if($summary)<a class="btn btn-g" href="{{ route('insurance-import.download') }}">Scarica prospetto di verifica</a>@endif</x-slot:actions>
    </x-page-header>
    @include('profile.partials.settings-navigation')
    @if(!$summary)
        <x-panel padded><p>{{ $error ?? 'Nessun lotto preparato. Caricare i file sorgente nella procedura di preparazione per generare l’anteprima.' }}</p></x-panel>
    @else
        @if($registryCounts ?? [])
            <p class="u-text-meta u-mb-md">Anagrafiche importate: {{ number_format($registryCounts['client']['imported'] ?? 0, 0, ',', '.') }} clienti, {{ $registryCounts['agency']['imported'] ?? 0 }} agenzie e {{ $registryCounts['company']['imported'] ?? 0 }} compagnie collegate. @if($policyCounts ?? []) Polizze importate: {{ number_format($policyCounts['imported'] ?? 0, 0, ',', '.') }}. Gruppi in sospeso: {{ number_format($policyCounts['held'] ?? 0, 0, ',', '.') }}. @else Le polizze non sono state importate. @endif</p>
        @else
            <p class="u-text-meta u-mb-md">Anteprima di preparazione. I dati Excel non sono ancora stati caricati nelle anagrafiche. Le proposte di accorpamento richiedono verifica.</p>
        @endif
        <nav class="client-section-nav" aria-label="Anteprima importazione">
            @foreach($sections as $value => $label)<a href="{{ route('insurance-import.index', ['section' => $value]) }}" @if($section === $value) aria-current="page" @endif>{{ $label }}</a>@endforeach
        </nav>
        <x-panel padded>
        @if($section === 'registry')
            <h2 class="workspace-section-title">Esito importazione anagrafiche</h2>
            <p class="u-text-meta">Clienti in sospeso: {{ $registryCounts['client']['held'] ?? 0 }}. Voci collegate a schede esistenti: {{ $registryCounts['client']['linked'] ?? 0 }}. Apri «Confronta dati» per vedere i valori e le righe originali. Le schede incomplete restano segnalate tra i clienti da verificare.</p>
            <form method="GET" action="{{ route('insurance-import.index') }}" class="client-search-form u-mt-md">
                <input type="hidden" name="section" value="registry">
                <x-form-group label="Anagrafica" name="kind"><select class="form-sel" name="kind" id="field-kind"><option value="">Tutte</option>@foreach(['client'=>'Clienti','agency'=>'Agenzie','company'=>'Compagnie'] as $key=>$label)<option value="{{ $key }}" @selected(request('kind')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Esito" name="result"><select class="form-sel" name="result" id="field-result"><option value="">Tutti</option><option value="held" @selected(request('result')==='held')>In sospeso</option><option value="imported" @selected(request('result')==='imported')>Importati</option><option value="linked" @selected(request('result')==='linked')>Collegati a schede esistenti</option></select></x-form-group>
                <button class="btn btn-p btn-sm" type="submit">Mostra</button>
                <a class="btn btn-g btn-sm" href="{{ route('insurance-import.index', ['section'=>'registry','result'=>'held']) }}">Solo in sospeso</a>
            </form>
            <div class="client-table-scroll" role="region" aria-label="Esito importazione anagrafiche" tabindex="0">
                <table class="t-table"><thead><tr><th>Anagrafica</th><th>Tipo</th><th>Esito</th><th>Righe Excel</th><th>Verifiche</th><th>Azioni</th></tr></thead><tbody>
                @forelse($registryEntries as $entry)
                    <tr><td>{{ $entry->source_name }}</td><td>{{ $entry->kind_label }}</td><td>{{ $entry->status_label }}</td><td>{{ implode(', ', $entry->source_rows) ?: 'Catalogo fornitori' }}</td><td>{{ implode(' ', $entry->issue_labels) ?: 'Nessun rilievo' }}</td><td>@if($entry->entity_id)<a class="btn btn-g btn-sm" href="{{ route(['client'=>'clients.show','agency'=>'suppliers.show','company'=>'supplier-companies.show'][$entry->kind], [$entry->entity_id, ...($entry->kind==='client' ? ['tab'=>'registry'] : [])]) }}">Apri scheda</a>@endif @if($entry->kind==='client')<a class="btn btn-g btn-sm" href="{{ route('insurance-import.compare', $entry) }}">Confronta dati</a>@endif</td></tr>
                @empty<tr><td colspan="6">Nessuna anagrafica per questi filtri.</td></tr>@endforelse
                </tbody></table>
            </div>
            {{ $registryEntries->links() }}
        @elseif($section === 'policies')
            <h2 class="workspace-section-title">Esito importazione polizze</h2>
            <p class="u-text-meta">Le polizze importate partono con stato Da verificare. I gruppi con più righe, classificazioni o collegamenti dubbi restano in sospeso; rate e rinnovi non diventano nuove polizze. Apri Dati originali per confrontare le righe dell’Excel.</p>
            <form method="GET" action="{{ route('insurance-import.index') }}" class="client-search-form u-mt-md">
                <input type="hidden" name="section" value="policies">
                <x-form-group label="Cerca numero, cliente o fornitore" name="search"><input class="form-in" type="search" id="field-search" name="search" maxlength="255" value="{{ request('search') }}"></x-form-group>
                <x-form-group label="Esito" name="result"><select class="form-sel" id="field-result" name="result"><option value="">Tutti</option><option value="imported" @selected(request('result')==='imported')>Importate</option><option value="held" @selected(request('result')==='held')>In sospeso</option></select></x-form-group>
                <button class="btn btn-p" type="submit">Mostra polizze</button><a class="btn btn-g" href="{{ route('insurance-import.index', ['section'=>'policies','result'=>'held']) }}">Solo in sospeso</a><a class="btn btn-g" href="{{ route('insurance-import.index', ['section'=>'policies']) }}">Azzera filtri</a>
            </form>
            <div class="client-table-scroll" role="region" aria-label="Esito importazione polizze" tabindex="0"><table class="t-table"><thead><tr><th>Numero polizza</th><th>Contraente Excel</th><th>Compagnia</th><th>Agenzia</th><th>Esito</th><th>Stato attuale polizza</th><th>Righe Excel</th><th>Verifiche</th><th>Azioni</th></tr></thead><tbody>
                @forelse($policyEntries ?? [] as $entry)<tr><td>{{ $entry->number }}</td><td>{{ $entry->client_name }}</td><td>{{ $entry->company }}</td><td>{{ $entry->agency ?: 'Non indicata' }}</td><td>{{ $entry->status_label }}</td><td>{{ $entry->policy?->status_label ?? 'Non importata' }}</td><td>{{ implode(', ', $entry->source_rows) }}</td><td>{{ implode(' ', $entry->issue_labels) ?: 'Nessun conflitto; stato iniziale da verificare.' }}</td><td>@if($entry->policy)<a class="btn btn-g btn-sm" href="{{ route('clients.policies.show', [$entry->policy->client_id, $entry->policy]) }}">Apri polizza</a>@endif <a class="btn btn-g btn-sm" href="{{ route('insurance-import.policy-source', $entry) }}">Dati originali</a></td></tr>@empty<tr><td colspan="9">Nessuna polizza per questi filtri, oppure importazione non ancora eseguita.</td></tr>@endforelse
            </tbody></table></div>
            @if($policyEntries){{ $policyEntries->links() }}@endif
        @elseif($section === 'overview')
            <h2 class="workspace-section-title">Contenuto del lotto</h2>
            <dl class="client-registry-details u-mt-md">
                @foreach(['registry_rows' => 'Righe anagrafica originali', 'client_candidates' => 'Anagrafiche candidate', 'clients_to_review' => 'Anagrafiche da verificare', 'policy_rows' => 'Righe assicurative', 'policy_groups' => 'Gruppi di polizze', 'policy_repeated_groups' => 'Gruppi con numero ripetuto', 'financial_rows_excluded' => 'Righe amministrative separate', 'policies_needing_status' => 'Gruppi con stato da verificare'] as $key => $label)
                <div><dt>{{ $label }}</dt><dd>{{ number_format($summary['counts'][$key], 0, ',', '.') }}</dd></div>
                @endforeach
            </dl>
            <p class="u-text-meta u-mt-md">I gruppi non sono ancora polizze definitive: possono contenere rate, rinnovi o righe storiche. «Archivio» e «OLD» non vengono tradotti automaticamente in uno stato.</p>
            <h2 class="workspace-section-title u-mt-md">Decisioni e verifiche</h2>
            @foreach($summary['decisions'] as $decision)
                <section class="client-detail-section"><h3 class="sec-lbl">{{ $decision['topic'] }} — {{ $decision['state'] === 'definita' ? 'Definita' : 'Da verificare' }}</h3><p>{{ $decision['text'] }}</p></section>
            @endforeach
            <h2 class="workspace-section-title u-mt-md">File sorgente</h2>
            @foreach($summary['sources'] as $source)<p class="import-source-name">{{ $source['file'] }} · {{ $source['sheet'] }} · intestazioni alla riga {{ $source['header_row'] }}</p>@endforeach
        @else
            @php
                $tables = [
                    'branches' => ['title' => 'Rami, sottorami e prodotti', 'rows' => $summary['taxonomy'], 'cols' => ['source_branch' => 'Ramo Excel', 'source_subbranch' => 'Sottoramo Excel', 'branch' => 'Ramo nel catalogo', 'subbranch' => 'Sottoramo nel catalogo', 'product' => 'Nome del prodotto', 'rows' => 'Righe', 'result' => 'Esito', 'reason' => 'Criterio']],
                    'providers' => ['title' => 'Compagnie e agenzie', 'rows' => $summary['company_agencies'], 'cols' => ['source_company' => 'Compagnia Excel', 'source_agency' => 'Agenzia Excel', 'company' => 'Compagnia proposta', 'agency' => 'Agenzia proposta', 'rows' => 'Righe', 'result' => 'Esito', 'reason' => 'Criterio']],
                    'issues' => ['title' => 'Controlli sul lotto', 'rows' => $summary['issue_counts'], 'cols' => ['code' => 'Controllo', 'records' => 'Gruppi interessati']],
                    'mapping' => ['title' => 'Collegamento dei campi', 'rows' => $summary['mapping'], 'cols' => ['source' => 'Campo Excel', 'target' => 'Destinazione', 'rule' => 'Regola']],
                ];
                $table = $tables[$section];
            @endphp
            <h2 class="workspace-section-title">{{ $table['title'] }}</h2>
            @if($section === 'branches')<p class="u-text-meta u-mb-md">Le destinazioni riflettono il catalogo e gli accorpamenti approvati. Il nome commerciale del prodotto è un dato separato: le etichette ambigue restano da verificare. I valori mancanti non sono stati dedotti. @if(!($policyCounts ?? [])) Le polizze non sono state importate. @else L’esito del caricamento è nella scheda Esito polizze. @endif Il prospetto di verifica scaricabile conserva le proposte originali; usa Esporta Excel o Esporta PDF della tabella per la situazione aggiornata.</p>@endif
            @if($section === 'issues')<p class="u-text-meta u-mb-md">Lo stesso gruppo può presentare più problemi. I riferimenti alle righe originali sono nel prospetto scaricabile.</p>@endif
            <div class="client-table-scroll u-mt-md" role="region" aria-label="{{ $table['title'] }}" tabindex="0">
                <table class="t-table import-review-table"><thead><tr>@foreach($table['cols'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                <tbody>@foreach($table['rows'] as $row)<tr>@foreach($table['cols'] as $key => $label)<td>{{ $key === 'result' || $key === 'code' ? ucfirst(str_replace('_', ' ', $row[$key])) : ($row[$key] ?? 'Non indicato') }}</td>@endforeach</tr>@endforeach</tbody></table>
            </div>
        @endif
        </x-panel>
    @endif
</x-app-layout>
