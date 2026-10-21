<x-app-layout :title="\App\Models\InsurancePolicyChange::KINDS[$change->kind]">
    <x-page-header><x-slot:title>{{ \App\Models\InsurancePolicyChange::KINDS[$change->kind] }} / {{ $change->reference }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.show',[$client,'tab'=>'policies']) }}">{{ $client->name }}</a><a class="btn btn-g" href="{{ route('clients.policies.show',[$client,$change->resultPolicy]) }}">Scheda polizza</a></x-slot:actions></x-page-header>
    <x-panel title="Variazione registrata" padded>
        <dl class="client-registry-details">
            <div><dt>{{ $change->kind==='replacement' ? 'Polizza precedente' : 'Numero polizza' }}</dt><dd><a href="{{ route('clients.policies.show',[$client,$change->sourcePolicy]) }}">{{ $change->snapshot['before']['policy']['number'] }}</a></dd></div>
            @if($change->kind==='replacement')<div><dt>Polizza sostitutiva</dt><dd><a href="{{ route('clients.policies.show',[$client,$change->resultPolicy]) }}">{{ $change->snapshot['after']['policy']['number'] }}</a></dd></div>@endif
            <div><dt>Compagnia</dt><dd>{{ $change->snapshot['after']['policy']['company'] }}</dd></div><div><dt>Periodo della variazione</dt><dd>{{ $change->effective_on->format('d/m/Y') }} – {{ $change->expires_on->format('d/m/Y') }}</dd></div>
            <div><dt>{{ $change->kind==='addition' ? 'Premio aggiuntivo del periodo' : 'Premio del nuovo contratto' }}</dt><dd>{{ \App\Support\Money::format($change->premium_cents) }}</dd></div><div><dt>Registrata da</dt><dd>{{ $change->author?->name ?: 'Utente non disponibile' }} / {{ $change->created_at->format('d/m/Y H:i') }}</dd></div>
        </dl><p class="u-whitespace-pre-wrap u-mt-md">{{ $change->description }}</p>
        <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('quotes.show',$change->quote->quoteCase) }}">Preventivo e approvazione</a></div>
    </x-panel>
    <x-panel title="Moduli e firme della variazione" padded>
        @forelse($change->formPackages as $package)<p><a href="{{ route('clients.forms.show',[$client,$package]) }}">Fascicolo #{{ $package->id }}</a> / {{ $package->created_at->format('d/m/Y') }} / {{ $package->state_label }}</p>@empty<p>I moduli per questa variazione sono ancora da preparare e far firmare.</p>@endforelse
        @if($change->resultPolicy->editableBy(auth()->user()) && $change->id===(int)$change->resultPolicy->incomingChanges()->max('id'))<div class="insurance-form-actions"><a class="btn btn-p" href="{{ route('clients.forms.create',[$client,'policy'=>$change->result_policy_id,'change'=>$change->id]) }}">Prepara moduli della variazione</a></div>@else<p class="u-text-meta">I fascicoli di questa variazione restano consultabili. Le nuove preparazioni seguono i permessi e l’ultima versione della polizza.</p>@endif
    </x-panel>
    <x-panel title="Allegati della variazione">@include('clients.documents.table',['documents'=>$change->documents])</x-panel>
    @can('updateRegistry',$client)<x-panel title="Aggiungi documenti allo storico" padded><form method="POST" enctype="multipart/form-data" action="{{ route('clients.policy-changes.documents',[$client,$change]) }}">@csrf<x-form-group label="Contratto, appendice e altri allegati" name="documents" required><input class="form-in" type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" multiple required><p class="u-text-meta">Fino a 10 file da 10 MB ciascuno, 50 MB complessivi. I documenti precedenti restano conservati.</p></x-form-group><div class="insurance-form-actions"><button class="btn btn-p">Aggiungi allegati</button></div></form></x-panel>@endcan
    <x-panel title="Controllo contabile" padded>
        <p>Le rate e gli incassi precedenti restano collegati alla loro polizza. Verifica manualmente importi residui, eventuali rimborsi e {{ $change->kind==='addition' ? 'il titolo del premio aggiuntivo' : 'le rate del nuovo contratto' }} prima di registrarli.</p>
        <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('titles.index',['policy'=>$change->source_policy_id]) }}">Rate della polizza {{ $change->sourcePolicy->number }}</a>@if($change->kind==='replacement')<a class="btn btn-g" href="{{ route('titles.index',['policy'=>$change->result_policy_id]) }}">Rate della sostitutiva</a>@endif</div>
        @if($change->kind==='addition')
            @foreach($change->titles as $title)<p><a href="{{ route('titles.show',$title) }}">Titolo {{ $title->reference ?: '#'.$title->id }}</a> / {{ \App\Support\Money::format($title->amount_cents) }} / <x-status-text :alert="in_array($title->state,['open','partial','overdue'],true)">{{ $title->state_label }}</x-status-text></p>@endforeach
            @can('ledger.manage')@if(!in_array($change->resultPolicy->status,\App\Models\InsurancePolicy::CLOSED_STATUSES,true) && $change->premium_cents>$change->titles->whereNull('cancelled_at')->sum('amount_cents'))<div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('titles.create',[$change->resultPolicy,'change'=>$change->id]) }}">Registra titolo del premio aggiuntivo</a></div>@endif @endcan
        @endif
    </x-panel>
</x-app-layout>
