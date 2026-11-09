<x-app-layout :title="'Polizza '.$policy->number">
    <div class="policy-desk" data-policy-record data-list-user="{{ auth()->id() }}" data-policy-list-url="{{ route('policies.index') }}">
    @php
        $canTransition = !$policy->companyChangeTo && !$policy->replacementTo && !$policy->hasPendingReplacement();
        $receiptDue = $policyTitles->where('direction','receipt')->reject(fn ($title) => $title->cancelled_at)->sum('balance_cents');
        $refundDue = $policyTitles->where('direction','refund')->reject(fn ($title) => $title->cancelled_at)->sum('balance_cents');
        $policySections = ['overview'=>'Dati polizza', 'payments'=>'Titoli e pagamenti', 'documents'=>'Contratto e documenti', 'forms'=>'Moduli e firme', 'history'=>'Storico e lavorazione'];
        if (auth()->user()->can('system.admin')) $policySections['commissions'] = 'Provvigioni';
    @endphp
    <div class="policy-breadcrumbs"><a href="{{ route('policies.index') }}" data-policy-list-return>Elenco polizze</a><span>/</span><a href="{{ route('clients.show',[$client,'tab'=>'policies']) }}">Cliente: {{ $client->name }}</a></div>
    <header class="policy-file-heading">
        <div><p class="policy-file-client">{{ $client->name }}</p><h1>Polizza {{ $policy->number }}</h1></div>
        <div class="policy-file-actions">
            @can('updateRegistry',$client)@can('ledger.manage')
                @if($payableTitles->isNotEmpty())<a class="btn btn-p" data-policy-primary-payment data-policy-section-link href="{{ route('clients.policies.show',[$client,$policy,'tab'=>'payments']).'#payment-entry' }}" @if($policyTab==='payments') hidden @endif>{{ $receiptDue>0 ? 'Incassa' : 'Registra rimborso' }}</a>
                @elseif(!$hasTitles && in_array($policy->status,['active','renewed'],true))<a class="btn btn-p" href="{{ route('titles.schedule',$policy) }}">Prepara titoli</a>@endif
                @if($canTransition && in_array($policy->status,['active','renewed','expired'],true))<a class="btn btn-g" href="{{ route('titles.renewal',$policy) }}">Rinnova o cambia compagnia</a>@endif
            @endcan @endcan
            <details class="policy-other-actions"><summary>Altre operazioni</summary><div>
                @can('updateRegistry',$client)
                    @if($policy->editableBy(auth()->user()))<a href="{{ route('clients.policies.edit',[$client,$policy]) }}">Modifica polizza</a>@endif
                    @can('ledger.manage')@if($canTransition && in_array($policy->status,['active','renewed','suspended'],true))<a href="{{ route('policy-transitions.create',[$policy,'replacement']) }}">Sostituisci</a>@endif @endcan
                    <a href="{{ route('claims.create',['policy'=>$policy->id]) }}">Nuovo sinistro</a>
                @endcan
                <a data-policy-section-link href="{{ route('clients.policies.show',[$client,$policy,'tab'=>'history']) }}">Lavorazione e sostituzioni</a>
            </div></details>
        </div>
    </header>
    <div class="policy-file-context">
        <span>{{ $policy->company }} · {{ $policy->category }}@if($policy->vehicle) · {{ $policy->vehicle->plate }}@endif</span>
        <span @class(['policy-file-state','is-alert'=>$policy->is_expired])>{{ $policy->status_label }}</span>
        <x-policy-contract-link :policy="$policy" />
        @can('updateRegistry',$client)
            @if($policy->contracts->count()===1 && $policy->contracts->first()->mime_type==='application/pdf' && !$policy->contracts->first()->latestTabletSignature)<a href="{{ route('station-signing.create',$policy->contracts->first()) }}">Firma contratto sul tablet</a>@endif
            @if($policy->contracts->isEmpty())<a href="{{ route('clients.documents.create',[$client,'policy'=>$policy->id,'category'=>'policy']) }}">Carica contratto</a>@endif
        @endcan
    </div>
    @include('clients.policies.contract-lineage')
    <div class="policy-desk-workspace" data-policy-workspace data-current-section="{{ $policyTab }}" data-guide-payments="{{ $payableTitles->isNotEmpty() ? 'open' : ($hasTitles ? 'settled' : 'unprepared') }}">
    <nav class="policy-section-nav" aria-label="Sezioni della polizza">
        @foreach($policySections as $key=>$label)<a data-policy-section-link href="{{ route('clients.policies.show',[$client,$policy,'tab'=>$key]) }}" @if($policyTab===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach
    </nav>
    <div class="policy-desk-body">
    <section data-policy-section="overview" aria-label="Dati polizza" @if($policyTab!=='overview') hidden @endif>
        <div class="policy-facts" id="policy-overview">
            <h2>Il contratto</h2>
            <dl class="policy-key-facts">
                <div><dt>Decorrenza</dt><dd>{{ $policy->starts_on->format('d/m/Y') }}</dd></div>
                <div><dt>Scadenza</dt><dd><x-status-text :alert="$policy->is_expired">{{ $policy->expires_on->format('d/m/Y') }}</x-status-text></dd></div>
                <div><dt>Premio di polizza</dt><dd>{{ $policy->premium !== null ? number_format((float)$policy->premium,2,',','.').' €' : 'Non indicato' }}</dd></div>
                <div><dt>Frazionamento</dt><dd>{{ $policy->payment_frequency ?: 'Non indicato' }}</dd></div>
                <div><dt>Compagnia</dt><dd>@if($policy->supplierCompany && auth()->user()->can('view',$policy->supplierCompany))<a href="{{ route('supplier-companies.show',$policy->supplierCompany) }}">{{ $policy->company }}</a>@else {{ $policy->company }} @endif</dd></div>
                <div><dt>Agenzia</dt><dd>@if($policy->supplierAgency && auth()->user()->can('view',$policy->supplierAgency))<a href="{{ route('suppliers.show',$policy->supplierAgency) }}">{{ $policy->supplierAgency->name }}</a>@else {{ $policy->supplierAgency?->name ?: 'Non collegata' }} @endif</dd></div>
                <div><dt>Ramo</dt><dd>{{ $policy->category }}</dd></div>
                <div><dt>Sottoramo</dt><dd>{{ $policy->subcategory ?: 'Da specificare' }}</dd></div>
            </dl>
        </div>
        @if($policy->vehicle || $policy->asset || $policy->insured_subject)
        <div class="policy-facts">
            <h2>Cosa è assicurato</h2>
            <dl class="policy-key-facts">
                @if($policy->vehicle)<div><dt>Veicolo / targa</dt><dd>{{ $policy->vehicle->plate }}@if($policy->vehicle->make || $policy->vehicle->model)<small>{{ $policy->vehicle->make }} {{ $policy->vehicle->model }}</small>@endif</dd></div>@endif
                @if($policy->asset)<div><dt>Immobile o altro bene collegato</dt><dd><a href="{{ route('clients.show',[$client,'tab'=>'assets']).'#assets-'.$policy->asset->id }}">{{ $policy->asset->name }}</a></dd></div>@endif
                @if($policy->insured_subject)<div><dt>Bene o soggetto assicurato</dt><dd>{{ $policy->insured_subject }}</dd></div>@endif
                @if($policy->merit_class_cu!==null)<div><dt>Classe universale CU</dt><dd>{{ $policy->merit_class_cu }}</dd></div>@endif
            </dl>
        </div>
        @endif
        <details class="policy-extra-facts">
            <summary>Altri dati del contratto</summary>
            <dl class="policy-key-facts">
                <div><dt>Nome del prodotto</dt><dd>{{ $policy->product ?: 'Non indicato' }}</dd></div>
                <div><dt>Classe universale CU</dt><dd>{{ $policy->merit_class_cu ?? 'Non indicata / non applicabile' }}</dd></div>
                <div><dt>Classe della compagnia</dt><dd>{{ $policy->merit_class_internal ?: 'Non indicata / non applicabile' }}</dd></div>
                <div><dt>Tacito rinnovo</dt><dd>{{ $policy->tacit_renewal === null ? 'Non indicato' : ($policy->tacit_renewal ? 'Sì' : 'No') }}</dd></div>
                <div><dt>Metodo di pagamento abituale</dt><dd>{{ $policy->payment_method ?: 'Non indicato' }}</dd></div>
                <div><dt>Premio rata</dt><dd>{{ $policy->installment_premium !== null ? number_format((float)$policy->installment_premium,2,',','.').' €' : 'Non indicato' }}</dd></div>
                @if($policy->last_renewed_at)<div><dt>Ultimo rinnovo registrato</dt><dd>{{ $policy->last_renewed_at->format('d/m/Y H:i') }}</dd></div>@endif
                <div><dt>Stato pagamento</dt><dd>{{ $policy->payment_status_label }}</dd></div>
            </dl>
        </details>
        @if($policy->notes)<div class="policy-facts"><h2>Note</h2><p class="u-whitespace-pre-wrap">{{ $policy->notes }}</p></div>@endif
        <div class="policy-related-links">
            <a href="{{ route('claims.index',['policy'=>$policy->id]) }}">Consulta sinistri</a>
            @can('updateRegistry',$client)<a href="{{ route('claims.create',['policy'=>$policy->id]) }}">Nuovo sinistro</a>@endcan
            @if($policy->insurance_quote_id)<a href="{{ route('quotes.show',[$policy->quote->insurance_quote_case_id,'proposal'=>$policy->insurance_quote_id]) }}#proposal-{{ $policy->insurance_quote_id }}">Preventivo e approvazione del cliente</a>@endif
            @if($entry=$policy->importEntry)@can('system.admin')<a href="{{ route('insurance-import.policy-source',$entry) }}">Origine dati</a>@endcan @endif
        </div>
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
        @foreach($settledAllowanceTitles->filter(fn($row) => !$row->coverage_confirmation_valid) as $coverageTitle)
            @include('ledger.allowance-coverage',['coverageTitle'=>$coverageTitle,'allowanceOrigin'=>'policy'])
        @endforeach
        <div @class(['policy-payment-layout','is-settled'=>$payableTitles->isEmpty()]) id="payments">
            <div class="policy-entry-column">
                @if($payableTitles->isNotEmpty())
                    <section id="payment-entry" class="policy-money-entry" aria-label="Registra pagamento">
                        <h2>Registra pagamento</h2>
                        <p class="policy-money-context">{{ $client->name }} · {{ $policy->number }}</p>
                        @include('ledger.collection-form',['collectionPolicy'=>$policy,'collectionTitles'=>$payableTitles,'chooseTitle'=>true,'collectionAction'=>route('policies.collect',$policy),'selectedPaymentTitle'=>$selectedPaymentTitle,'compactCollection'=>true])
                    </section>
                    @can('ledger.manage')@can('updateRegistry',$client)
                        @include('ledger.allowance-form',['allowanceTitles'=>$payableTitles,'chooseAllowanceTitle'=>true,'allowanceAction'=>route('policies.allowances',$policy),'selectedAllowanceTitle'=>$selectedPaymentTitle])
                    @endcan @endcan
                @else
                    <div class="policy-payment-empty"><h2>Pagamenti</h2><p>Non ci sono titoli con un residuo da registrare.</p></div>
                @endif
            </div>
            <div class="policy-payment-ledger">
                @include('clients.policies.titles')
                <div class="policy-related-links">
                    <a href="{{ route('titles.index',['policy'=>$policy->id]) }}">Elenco completo titoli</a>
                    <a href="{{ route('collections.index',['policy'=>$policy->id,'month'=>'']) }}">Contabilità della polizza</a>
                    <a href="{{ route('allowances.index',['policy'=>$policy->id,'month'=>'']) }}">Abbuoni della polizza</a>
                    @can('ledger.manage')
                        @if(!$hasTitles && in_array($policy->status,['active','renewed'],true))<a href="{{ route('titles.schedule',$policy) }}">Prepara titoli</a>@endif
                        @if(!in_array($policy->status,\App\Models\InsurancePolicy::CLOSED_STATUSES,true))<a href="{{ route('titles.create',$policy) }}">Titolo manuale</a>@endif
                    @endcan
                </div>
                <details class="policy-payment-coverage"><summary>Copertura risultante dai titoli</summary>
                    <dl class="client-registry-details"><div><dt>Copertura risultante fino al</dt><dd>{{ $policy->coverage_until?->format('d/m/Y') ?: 'Non determinata dai titoli' }}</dd></div></dl>
                    <p class="u-text-meta">La data deriva da periodi consecutivi interamente incassati oppure saldati con abbuono e copertura confermata separatamente. Per l’effettiva operatività valgono lo stato e le condizioni del contratto.</p>
                </details>
                @if($settledAllowanceTitles->contains(fn($row) => $row->coverage_confirmation_valid))
                    <details class="policy-payment-coverage"><summary>Coperture confermate con abbuono</summary>
                        @foreach($settledAllowanceTitles->filter(fn($row) => $row->coverage_confirmation_valid) as $coverageTitle)
                            @include('ledger.allowance-coverage',['coverageTitle'=>$coverageTitle,'allowanceOrigin'=>'policy'])
                        @endforeach
                    </details>
                @endif
                <p class="policy-payment-reference">Frazionamento: {{ $policy->payment_frequency ?: 'Non indicato' }} · Metodo abituale: {{ $policy->payment_method ?: 'Non indicato' }}</p>
            </div>
        </div>
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
    <aside class="policy-work-rail" data-policy-work-rail @if(!in_array($policyTab,['overview','forms'],true)) hidden @endif aria-label="Attività della polizza">
        <div data-policy-overview-only @if($policyTab!=='overview') hidden @endif>
            <div class="policy-balance">
                <h2>Pagamenti</h2>
                <dl><div><dt>Da incassare</dt><dd>{{ $hasTitles ? \App\Support\Money::format($receiptDue) : 'Non determinato' }}</dd></div>@if($refundDue>0)<div><dt>Da rimborsare</dt><dd>{{ \App\Support\Money::format($refundDue) }}</dd></div>@endif</dl>
                <p>{{ $hasTitles ? 'Residui dei titoli aperti, comprese le scadenze future.' : 'Non sono presenti titoli per questa polizza.' }}</p>
                <a class="policy-rail-link" data-policy-section-link href="{{ route('clients.policies.show',[$client,$policy,'tab'=>'payments']) }}">Vedi titoli e movimenti</a>
            </div>
            @if($settledAllowanceTitles->contains(fn($title) => !$title->coverage_confirmation_valid))<p class="policy-coverage-notice">Ci sono titoli saldati con abbuono: <a data-policy-section-link href="{{ route('clients.policies.show',[$client,$policy,'tab'=>'payments']) }}">controlla la copertura da confermare</a>.</p>@endif
            @if($formReminder)<div class="policy-module-choice"><h2>Scelta dei moduli</h2><p>La scelta dei moduli è ancora da completare.</p><a class="btn btn-g" href="{{ \App\Support\NotificationDestination::local($formReminder->data['url']) }}">Scegli i moduli</a></div>@endif
        </div>
        @include('insurance-forms.policy-signatures')
    </aside>
    </div>
    </div>
    </div>
</x-app-layout>
