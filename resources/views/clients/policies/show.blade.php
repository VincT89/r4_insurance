<x-app-layout :title="'Polizza '.$policy->number">
    <div class="page-back-row"><a href="{{ route('clients.show', [$client, 'tab'=>'policies']) }}" class="btn btn-g">Cliente: {{ $client->name }}</a></div>
    <x-page-header><x-slot:title>Polizza {{ $policy->number }}</x-slot:title>
        <x-slot:actions>
            <x-policy-contract-link :policy="$policy" :button="true" />
            @can('updateRegistry',$client)
                @if($policy->contracts->count()===1 && $policy->contracts->first()->mime_type==='application/pdf' && !$policy->contracts->first()->latestTabletSignature)
                    <a class="btn btn-g" href="{{ route('attachments.sign',$policy->contracts->first()) }}">Firma contratto sul tablet</a>
                @endif
            @endcan
            @can('updateRegistry', $client)
                @if($policy->contracts->isEmpty())<a class="btn btn-g" href="{{ route('clients.documents.create', [$client, 'policy'=>$policy->id, 'category'=>'policy']) }}">Carica contratto</a>@endif
                @can('ledger.manage')
                    @if(!$hasTitles && in_array($policy->status,['active','renewed'],true))<a class="btn btn-p" href="{{ route('titles.schedule',$policy) }}">Prepara titoli</a>@endif
                    @if($payableTitles->isNotEmpty())<a class="btn btn-p" data-policy-section-link href="{{ route('clients.policies.show', [$client, $policy, 'tab'=>'payments']).'#payment-entry' }}">Registra pagamento</a>@endif
                @endcan
            @endcan
        </x-slot:actions>
    </x-page-header>
    <p class="policy-context">{{ $policy->company }} / {{ $policy->category }}@if($policy->vehicle) / {{ $policy->vehicle->plate }}@endif / Scadenza {{ $policy->expires_on->format('d/m/Y') }} / <x-policy-status :policy="$policy" /></p>
    @include('clients.policies.contract-lineage')
    @php
        $canTransition = !$policy->companyChangeTo && !$policy->replacementTo && !$policy->hasPendingReplacement();
        $policySections = ['overview'=>'Dati polizza', 'documents'=>'Contratto e documenti', 'payments'=>'Titoli e pagamenti', 'forms'=>'Moduli e firme', 'history'=>'Storico e lavorazione'];
        if (auth()->user()->can('system.admin')) $policySections['commissions'] = 'Provvigioni';
    @endphp
    @if($formReminder)
        <div class="policy-forms-reminder">
            <span><strong>{{ $formReminder->data['title'] }}</strong><small>La scelta dei moduli è ancora da completare.</small></span>
            <a class="btn btn-g" href="{{ \App\Support\NotificationDestination::local($formReminder->data['url']) }}">Scegli i moduli</a>
        </div>
    @endif
    <div data-policy-workspace>
    <nav class="policy-section-nav" aria-label="Sezioni della polizza">
        @foreach($policySections as $key=>$label)<a data-policy-section-link href="{{ route('clients.policies.show', [$client, $policy, 'tab'=>$key]) }}" @if($policyTab===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach
    </nav>
    @include('insurance-forms.policy-signatures')
    <section data-policy-section="overview" aria-label="Dati polizza" @if($policyTab!=='overview') hidden @endif>
        <div class="policy-section-actions">
            @can('updateRegistry',$client)
                @if($policy->editableBy(auth()->user()))<a class="btn btn-g" href="{{ route('clients.policies.edit', [$client, $policy]) }}">Modifica polizza</a>@endif
                @can('ledger.manage')@if($canTransition && in_array($policy->status,['active','renewed','expired'],true))<a class="btn btn-g" href="{{ route('titles.renewal',$policy) }}">Rinnova o cambia compagnia</a>@endif @if($canTransition && in_array($policy->status,['active','renewed','suspended'],true))<a class="btn btn-g" href="{{ route('policy-transitions.create',[$policy,'replacement']) }}">Sostituisci</a>@endif@endcan
            @endcan
            <a class="btn btn-g" data-policy-section-link href="{{ route('clients.policies.show', [$client, $policy, 'tab'=>'history']) }}">Lavorazione e sostituzioni</a>
        </div>
    <x-panel title="Dati della polizza" padded id="policy-overview">
        <dl class="client-registry-details">
            <div><dt>Ramo</dt><dd>{{ $policy->category }}</dd></div><div><dt>Sottoramo</dt><dd>{{ $policy->subcategory ?: 'Da specificare' }}</dd></div>
            <div><dt>Nome del prodotto</dt><dd>{{ $policy->product ?: 'Non indicato' }}</dd></div>
            <div><dt>Compagnia</dt><dd>@if($policy->supplierCompany && auth()->user()->can('view', $policy->supplierCompany))<a href="{{ route('supplier-companies.show', $policy->supplierCompany) }}">{{ $policy->company }}</a>@else {{ $policy->company }} @endif</dd></div>
            <div><dt>Agenzia</dt><dd>@if($policy->supplierAgency && auth()->user()->can('view', $policy->supplierAgency))<a href="{{ route('suppliers.show', $policy->supplierAgency) }}">{{ $policy->supplierAgency->name }}</a>@else {{ $policy->supplierAgency?->name ?: 'Non collegata' }} @endif</dd></div>
            <div><dt>Bene o soggetto assicurato</dt><dd>{{ $policy->insured_subject ?: 'Non indicato' }}</dd></div>
            <div><dt>Veicolo / targa</dt><dd>{{ $policy->vehicle?->plate ?? 'Non collegato' }}</dd></div>
            <div><dt>Immobile o altro bene collegato</dt><dd>@if($policy->asset)<a href="{{ route('clients.show', [$client, 'tab' => 'assets']).'#assets-'.$policy->asset->id }}">{{ $policy->asset->name }}</a>@else Non collegato @endif</dd></div>
            <div><dt>Classe universale CU</dt><dd>{{ $policy->merit_class_cu ?? 'Non indicata / non applicabile' }}</dd></div>
            <div><dt>Classe della compagnia</dt><dd>{{ $policy->merit_class_internal ?: 'Non indicata / non applicabile' }}</dd></div>
            <div><dt>Decorrenza</dt><dd>{{ $policy->starts_on->format('d/m/Y') }}</dd></div>
            <div><dt>Scadenza</dt><dd><x-status-text :alert="$policy->is_expired">{{ $policy->expires_on->format('d/m/Y') }}</x-status-text></dd></div>
            @if($policy->last_renewed_at)<div><dt>Ultimo rinnovo registrato</dt><dd>{{ $policy->last_renewed_at->format('d/m/Y H:i') }}</dd></div>@endif
            <div><dt>Premio di polizza</dt><dd>{{ $policy->premium !== null ? number_format((float) $policy->premium, 2, ',', '.').' €' : 'Non indicato' }}</dd></div>
            <div><dt>Tacito rinnovo</dt><dd>{{ $policy->tacit_renewal === null ? 'Non indicato' : ($policy->tacit_renewal ? 'Sì' : 'No') }}</dd></div><div><dt>Stato</dt><dd><x-policy-status :policy="$policy" /></dd></div>
        </dl>
        @if($policy->notes)<div class="client-detail-section"><h2 class="sec-lbl">Note</h2><p class="u-whitespace-pre-wrap">{{ $policy->notes }}</p></div>@endif
    </x-panel>
    @if($entry = $policy->importEntry)
        @can('system.admin')<div class="source-reference"><a href="{{ route('insurance-import.policy-source', $entry) }}">Origine dati</a></div>@endcan
    @endif
    <x-panel title="Sinistri" padded><p>Pratiche e documenti collegati a questa polizza.</p><div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('claims.index',['policy'=>$policy->id]) }}">Consulta sinistri</a>@can('updateRegistry',$client)<a class="btn btn-p" href="{{ route('claims.create',['policy'=>$policy->id]) }}">Nuovo sinistro</a>@endcan</div></x-panel>
    </section>
    <section data-policy-section="documents" aria-label="Contratto e documenti" @if($policyTab!=='documents') hidden @endif>
        <div class="policy-section-actions">
            @can('updateRegistry',$client)
                @php($openForms = $signatures['toSign']->concat($signatures['toReview'])->concat($signatures['previous']))
                @if($openForms->count() === 1)
                    <a class="btn btn-p" href="{{ $openForms->first()->signatureActionUrl() }}">{{ $openForms->first()->signatureActionLabel() }}</a>
                @elseif($openForms->isNotEmpty())
                    <a class="btn btn-p" data-policy-section-link href="{{ route('clients.policies.show',[$client,$policy,'tab'=>'forms']).'#policy-signatures' }}">Moduli da firmare o controllare</a>
                @endif
                <a class="btn {{ $openForms->isEmpty() ? 'btn-p' : 'btn-g' }}" href="{{ route('clients.documents.create', [$client, 'policy'=>$policy->id]) }}">{{ $openForms->isEmpty() ? 'Carica documento' : 'Carica altro documento' }}</a>
            @endcan
            <a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab'=>'documents']) }}">Tutti i documenti del cliente</a>
        </div>
        @if($policy->contracts->isNotEmpty())
            <x-panel title="Contratti e firme" padded id="policy-contracts">@include('document-signing.contract-list')</x-panel>
        @endif
        <x-panel title="Documenti della polizza" id="policy-documents">@include('clients.documents.table', ['documents'=>$policy->documents, 'documentContextPolicy'=>$policy])</x-panel>
    </section>
    <section data-policy-section="payments" aria-label="Titoli e pagamenti" @if($policyTab!=='payments') hidden @endif>
        @include('ledger.recorded-payment')
        @include('clients.policies.accounting-review')
    <x-panel title="Titoli e pagamenti" padded id="payments">
        @foreach($settledAllowanceTitles->filter(fn($row) => !$row->coverage_confirmation_valid) as $coverageTitle)
            @include('ledger.allowance-coverage',['coverageTitle'=>$coverageTitle,'allowanceOrigin'=>'policy'])
        @endforeach
        @if($payableTitles->isNotEmpty())
            <x-form-section title="Registra pagamento" id="payment-entry">
                @include('ledger.collection-form',['collectionPolicy'=>$policy,'collectionTitles'=>$payableTitles,'chooseTitle'=>true,'collectionAction'=>route('policies.collect',$policy),'selectedPaymentTitle'=>$selectedPaymentTitle])
            </x-form-section>
            @can('ledger.manage')@can('updateRegistry',$client)
                @include('ledger.allowance-form',['allowanceTitles'=>$payableTitles,'chooseAllowanceTitle'=>true,'allowanceAction'=>route('policies.allowances',$policy),'selectedAllowanceTitle'=>$selectedPaymentTitle])
            @endcan @endcan
        @elseif($hasTitles && !$policyTitles->contains(fn($title) => !$title->cancelled_at && $title->balance_cents > 0))
            <p class="u-text-meta">Nessun titolo con un residuo da incassare. I pagamenti restano consultabili nella contabilità della polizza.</p>
        @endif
        @if($settledAllowanceTitles->contains(fn($row) => $row->coverage_confirmation_valid))
            <details class="policy-payment-coverage"><summary>Coperture confermate con abbuono</summary>
                @foreach($settledAllowanceTitles->filter(fn($row) => $row->coverage_confirmation_valid) as $coverageTitle)
                    @include('ledger.allowance-coverage',['coverageTitle'=>$coverageTitle,'allowanceOrigin'=>'policy'])
                @endforeach
            </details>
        @endif
        <details class="policy-payment-coverage"><summary>Copertura risultante dai titoli</summary><dl class="client-registry-details"><div><dt>Copertura risultante fino al</dt><dd>{{ $policy->coverage_until?->format('d/m/Y') ?: 'Non determinata dai titoli' }}</dd></div></dl>
        <p class="u-text-meta">La data deriva da periodi consecutivi interamente incassati oppure saldati con abbuono e copertura confermata separatamente. Per l’effettiva operatività valgono lo stato e le condizioni del contratto.</p></details>
        <div class="insurance-form-actions">
            <a class="btn btn-g" href="{{ route('titles.index',['policy'=>$policy->id]) }}">Elenco completo titoli</a>
            <a class="btn btn-g" href="{{ route('collections.index',['policy'=>$policy->id,'month'=>'']) }}">Contabilità della polizza</a>
            <a class="btn btn-g" href="{{ route('allowances.index',['policy'=>$policy->id,'month'=>'']) }}">Abbuoni della polizza</a>
            @can('ledger.manage')
                @if(!$hasTitles && in_array($policy->status,['active','renewed'],true))<a class="btn btn-g" href="{{ route('titles.schedule',$policy) }}">Prepara titoli</a>@endif
                @if(!in_array($policy->status,\App\Models\InsurancePolicy::CLOSED_STATUSES,true))<a class="btn btn-g" href="{{ route('titles.create',$policy) }}">Titolo manuale</a>@endif
                @if($canTransition && in_array($policy->status,['active','renewed','expired'],true))<a class="btn btn-g" href="{{ route('titles.renewal',$policy) }}">Rinnova o cambia compagnia</a>@endif
            @endcan
        </div>
        @include('clients.policies.titles')
        @if($policy->insurance_quote_id)<p><a href="{{ route('quotes.show',[$policy->quote->insurance_quote_case_id,'proposal'=>$policy->insurance_quote_id]) }}#proposal-{{ $policy->insurance_quote_id }}">Preventivo e approvazione del cliente</a></p>@endif
    </x-panel>
    <x-panel title="Frazionamento e riferimenti di pagamento" padded><dl class="client-registry-details"><div><dt>Stato pagamento</dt><dd><x-badge :status="$policy->payment_status ?? 'unspecified'" :label="$policy->payment_status_label" /></dd></div><div><dt>Metodo di pagamento abituale</dt><dd>{{ $policy->payment_method ?: 'Non indicato' }}</dd></div><div><dt>Frazionamento</dt><dd>{{ $policy->payment_frequency ?: 'Non indicato' }}</dd></div><div><dt>Premio rata</dt><dd>{{ $policy->installment_premium !== null ? number_format((float)$policy->installment_premium,2,',','.').' €' : 'Non indicato' }}</dd></div></dl></x-panel>
    </section>
    <section data-policy-section="forms" id="policy-forms" aria-label="Moduli e firme" @if($policyTab!=='forms') hidden @endif>@include('insurance-forms.policy-summary')</section>
    <section data-policy-section="history" id="policy-history" aria-label="Storico e lavorazione" @if($policyTab!=='history') hidden @endif>
        @include('clients.policies.workflow')
        @include('policy-changes.history')
        @include('clients.policies.corrections')
    @can('system.admin')
        <x-delete-modal action="{{ route('clients.policies.destroy', [$client, $policy]) }}" title="Elimina polizza" message="La polizza può essere eliminata solo se priva di lavorazioni chiuse, segnalazioni, titoli, preventivi, documenti e altri collegamenti storici. Per conservarla, impostala come cessata."><button type="button" class="btn btn-g btn-danger-outline">Elimina polizza</button></x-delete-modal>
    @endcan
    </section>
    @can('system.admin')<section data-policy-section="commissions" aria-label="Provvigioni" @if($policyTab!=='commissions') hidden @endif>@include('commissions.summary', ['commissionPolicy'=>$policy])</section>@endcan
    </div>
</x-app-layout>
