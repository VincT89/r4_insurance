@php
    $safeOld = fn ($key,$default='') => is_scalar($value = old($key,$default)) ? $value : '';
    $pageTitle = $reminder ? ($change && !$change->isCompanyChange() ? 'Moduli del rinnovo' : 'Moduli della nuova polizza') : 'Prepara moduli';
@endphp
<x-app-layout :title="$pageTitle">
    <x-page-header>
        <x-slot:title>{{ $pageTitle }}</x-slot:title>
        <x-slot:meta>{{ $client->name }}@if($policy) · Polizza {{ $policy->number }} · {{ $policy->company }}@endif</x-slot:meta>
        <x-slot:actions><a class="btn btn-g" href="{{ route('clients.forms.index',[$client,'policy'=>$policy?->id]) }}">Storico moduli</a></x-slot:actions>
    </x-page-header>
    @if($policy)
        <div class="module-context">
            <span>{{ $policy->category }}{{ $policy->subcategory ? ' / '.$policy->subcategory : '' }} · {{ $policy->starts_on?->format('d/m/Y') }} – {{ $policy->expires_on?->format('d/m/Y') }}</span>
            <div><a href="{{ route('clients.policies.show',[$client,$policy,'tab'=>'payments']) }}">Titoli e pagamenti</a>
                @if($change)<a href="{{ route('clients.policy-changes.show',[$client,$change]) }}">{{ $change->isCompanyChange() ? 'Cambio compagnia e storico' : 'Dettagli del contratto' }}</a>@endif
                @php
                    $contract = $change ? $change->documents()->where('document_category','policy')->latest('id')->first() : $policy->contracts()->first();
                @endphp
                @if($contract)<a href="{{ $contract->openUrl() }}" target="_blank" rel="noopener">Apri contratto</a>@endif
            </div>
        </div>
    @endif
    @if($templates->isEmpty() && !$reminder)
        <x-panel padded><p>Non ci sono modelli approvati per {{ $policy ? 'questa polizza' : 'l’anagrafica cliente' }}.</p>
            @can('forms.manage')<a class="btn btn-g" href="{{ route('form-templates.index') }}">Configura modelli</a>@endcan
        </x-panel>
    @else
        <x-panel padded>
            @if($reminder)<p class="module-help">Spunta i moduli da preparare per questo contratto. Con «Più tardi» puoi riprendere la scelta dalla polizza.</p>@endif
            @include('insurance-forms.package-form')
        </x-panel>
    @endif
</x-app-layout>
