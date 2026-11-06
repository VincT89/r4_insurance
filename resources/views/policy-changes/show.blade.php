<x-app-layout :title="$change->kind_label">
    <x-page-header><x-slot:title>{{ $change->kind_label }} / {{ $change->reference }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.show',[$client,'tab'=>'policies']) }}">{{ $client->name }}</a><a class="btn btn-g" href="{{ route('clients.policies.show',[$client,$change->resultPolicy]) }}">Scheda polizza</a></x-slot:actions></x-page-header>
    @if($scheduled=$change->replacementSchedule)
        @if($scheduled->state==='pending')
            <x-panel title="Sostituzione programmata" padded>
                <p>Il cambio avverrà il {{ $change->effective_on->format('d/m/Y') }}. Fino ad allora la polizza {{ $change->sourcePolicy->number }} resta operativa; quella nuova ha decorrenza futura.</p>
                @can('ledger.manage')@can('updateRegistry',$client)
                <details class="u-mt-md"><summary>Annulla programmazione</summary>
                    <form method="POST" action="{{ route('clients.policy-changes.cancel-schedule',[$client,$change]) }}" class="u-mt-md">@csrf
                        <x-form-group label="Motivo dell’annullamento" name="reason" required><textarea class="form-ta" name="reason" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea></x-form-group>
                        <p>La nuova polizza e i suoi titoli saranno annullati. Documenti, moduli e storico restano conservati. La vecchia polizza continua normalmente.</p>
                        <div class="insurance-form-actions"><button class="btn btn-g">Annulla programmazione</button></div>
                    </form>
                </details>@endcan @endcan
            </x-panel>
        @elseif($scheduled->state==='cancelled')<x-panel title="Programmazione annullata" padded><p>{{ $scheduled->cancelled_at->format('d/m/Y H:i') }} / {{ $scheduled->cancellation_reason }}</p><p>La polizza precedente e lo storico sono conservati.</p></x-panel>
        @else<x-panel title="Sostituzione in vigore" padded><p>Il cambio è entrato in vigore dal {{ $change->effective_on->format('d/m/Y') }}.</p></x-panel>@endif
    @endif
    <x-panel title="Variazione registrata" padded>
        <dl class="client-registry-details">
            <div><dt>{{ ($change->kind==='replacement' || $change->isCompanyChange()) ? 'Polizza precedente' : 'Numero polizza' }}</dt><dd><a href="{{ route('clients.policies.show',[$client,$change->sourcePolicy]) }}">{{ $change->snapshot['before']['policy']['number'] }}</a></dd></div>
            @if($change->kind==='replacement' || $change->isCompanyChange())<div><dt>{{ $change->isCompanyChange() ? 'Nuova polizza' : 'Polizza sostitutiva' }}</dt><dd><a href="{{ route('clients.policies.show',[$client,$change->resultPolicy]) }}">{{ $change->snapshot['after']['policy']['number'] }}</a></dd></div>@endif
            <div><dt>Compagnia</dt><dd>{{ $change->snapshot['after']['policy']['company'] }}</dd></div><div><dt>Periodo della variazione</dt><dd>{{ $change->effective_on->format('d/m/Y') }} – {{ $change->expires_on->format('d/m/Y') }}</dd></div>
            <div><dt>{{ $change->kind==='addition' ? 'Premio aggiuntivo del periodo' : 'Premio del nuovo contratto' }}</dt><dd>{{ \App\Support\Money::format($change->premium_cents) }}</dd></div><div><dt>Registrata da</dt><dd>{{ $change->author?->name ?: 'Utente non disponibile' }} / {{ $change->created_at->format('d/m/Y H:i') }}</dd></div>
        </dl><p class="u-whitespace-pre-wrap u-mt-md">{{ $change->description }}</p>
        <div class="insurance-form-actions">@if($change->quote)<a class="btn btn-g" href="{{ route('quotes.show',$change->quote->quoteCase) }}">Preventivo e approvazione</a>@endif @foreach($change->documents->where('document_category','policy') as $contract)<a class="btn btn-p" href="{{ route('attachments.preview',$contract) }}">Apri contratto</a>@endforeach<a class="btn btn-g" href="{{ route('clients.policies.show',[$client,$change->resultPolicy,'tab'=>'payments']) }}">Titoli e pagamenti</a></div>
    </x-panel>
    @if($change->kind !== 'addition')
    <x-panel title="Titoli e conguagli registrati" padded>
        @if(($change->snapshot['titles_mode'] ?? 'automatic') === 'manual')<p>Completa i titoli ordinari dalla scheda della polizza: hai scelto l’inserimento manuale.</p>@endif
        @if($change->snapshot['cancelled_cents'] ?? 0)<p>{{ $scheduled?->state==='pending' ? 'Residui da annullare alla decorrenza, al netto degli eventuali pagamenti ricevuti nel frattempo' : ($scheduled?->state==='cancelled' ? 'Annullamento dei residui non eseguito; importo previsto' : ($scheduled?->state==='active' ? 'Residui selezionati alla registrazione' : 'Residui annullati')) }}: {{ \App\Support\Money::format($change->snapshot['cancelled_cents']) }}. Gli incassi già ricevuti sono conservati.</p><p>Motivo: {{ $change->snapshot['cancellation_reason'] }}</p>@endif
        @foreach($change->titles as $title)<p class="u-mb-sm"><a href="{{ route('titles.show',$title) }}">{{ $title->direction === 'refund' ? 'Rimborso' : \App\Models\InsuranceTitle::KINDS[$title->kind] }} del {{ $title->due_on->format('d/m/Y') }}</a>: {{ \App\Support\Money::format($title->amount_cents) }} / {{ $title->state_label }}.</p>@endforeach
    </x-panel>
    @endif
    <x-panel title="Moduli e firme della variazione" padded>
        @forelse($change->formPackages as $package)<p><a href="{{ route('clients.forms.show',[$client,$package]) }}">Fascicolo #{{ $package->id }}</a> / {{ $package->created_at->format('d/m/Y') }} / {{ $package->state_label }}</p>@empty<p>I moduli per questa variazione sono ancora da preparare e far firmare.</p>@endforelse
        @if($scheduled?->state!=='cancelled' && $change->resultPolicy->editableBy(auth()->user()) && $change->id===(int)$change->resultPolicy->incomingChanges()->max('id'))
            @php($policy=$change->resultPolicy)
            @php($templates=app(\App\Services\InsuranceForms::class)->available($policy,$client))
            @php($contextHash=app(\App\Services\InsuranceForms::class)->contextHash($client,$policy))
            @php($safeOld=fn($key,$default='')=>is_scalar(old($key,$default))?old($key,$default):'')
            @if($templates->isNotEmpty())
                <details @if($change->formPackages->isEmpty()) open @endif><summary>Scegli i moduli da generare</summary>@include('insurance-forms.package-form')</details>
            @else<p class="u-text-meta">Nessun modello attivo disponibile per questa polizza. L’admin può abilitarli nelle impostazioni dei modelli.</p>@endif
        @else<p class="u-text-meta">I moduli di questa variazione restano consultabili nello storico.</p>@endif
    </x-panel>
    <x-panel title="Allegati della variazione">@include('clients.documents.table',['documents'=>$change->documents])</x-panel>
    @can('updateRegistry',$client)<x-panel title="Aggiungi documenti allo storico" padded><form method="POST" enctype="multipart/form-data" action="{{ route('clients.policy-changes.documents',[$client,$change]) }}">@csrf<x-form-group label="Contratto, appendice e altri allegati" name="documents" required><input class="form-in" type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" multiple required><p class="u-text-meta">Fino a 10 file da 10 MB ciascuno, 50 MB complessivi. I documenti precedenti restano conservati.</p></x-form-group><div class="insurance-form-actions"><button class="btn btn-p">Aggiungi allegati</button></div></form></x-panel>@endcan
    <x-panel title="Controllo contabile" padded>
<p>I movimenti precedenti restano nello storico con la loro compagnia e agenzia. I titoli aperti si possono saldare dalla scheda della polizza, indicando data effettiva e modalità.</p>
        <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('titles.index',['policy'=>$change->source_policy_id]) }}">Rate della polizza {{ $change->sourcePolicy->number }}</a>@if($change->kind==='replacement' || $change->isCompanyChange())<a class="btn btn-g" href="{{ route('titles.index',['policy'=>$change->result_policy_id]) }}">Titoli della nuova polizza</a>@endif</div>
        @if($change->kind==='addition')
            @foreach($change->titles as $title)<p><a href="{{ route('titles.show',$title) }}">Titolo {{ $title->reference ?: '#'.$title->id }}</a> / {{ \App\Support\Money::format($title->amount_cents) }} / <x-status-text :alert="in_array($title->state,['due_today','partial','overdue'],true)">{{ $title->state_label }}</x-status-text></p>@endforeach
            @can('ledger.manage')@if(!in_array($change->resultPolicy->status,\App\Models\InsurancePolicy::CLOSED_STATUSES,true) && $change->premium_cents>$change->titles->whereNull('cancelled_at')->sum('amount_cents'))<div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('titles.create',[$change->resultPolicy,'change'=>$change->id]) }}">Registra titolo del premio aggiuntivo</a></div>@endif @endcan
        @endif
    </x-panel>
</x-app-layout>
