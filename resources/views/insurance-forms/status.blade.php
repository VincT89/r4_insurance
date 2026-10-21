@php($formStatus = app(\App\Services\InsuranceDocumentation::class)->forms($client, $formPolicy ?? null))
<div class="document-form-status">
    <h3>{{ isset($formPolicy) ? 'Moduli della polizza' : 'Moduli del cliente' }}</h3>
    <p>{{ $formStatus['package']?->state_label ?? 'Nessun fascicolo preparato.' }}</p>
    @if($formStatus['templates']->isEmpty())<p class="u-text-meta">Nessun modello approvato per questo {{ $client->client_type === 'company' ? 'cliente azienda' : 'cliente privato' }}.</p>@endif
    <div class="insurance-form-actions">
        @if($formStatus['package'])<a class="btn btn-g btn-sm" href="{{ route('clients.forms.show', [$client, $formStatus['package']]) }}">Apri ultimo fascicolo</a>@endif
        <a class="btn btn-g btn-sm" href="{{ route('clients.forms.index', [$client, 'policy'=>$formPolicy?->id ?? null, 'reason'=>isset($formPolicy) ? null : 'client']) }}">Storico moduli</a>
        @can('updateRegistry', $client)@if(!isset($formPolicy) || $formPolicy->editableBy(auth()->user()))
            @if($formStatus['templates']->isNotEmpty())<a class="btn btn-p btn-sm" href="{{ route('clients.forms.create', [$client, 'policy'=>$formPolicy?->id ?? null]) }}">Prepara moduli {{ isset($formPolicy) ? 'polizza' : 'cliente' }}</a>@endif
        @endif@endcan
        @can('system.admin')@if($formStatus['templates']->isEmpty())<a class="btn btn-g btn-sm" href="{{ route('form-templates.index', ['scope'=>isset($formPolicy) ? 'policy' : 'client']) }}">Configura modelli</a>@endif@endcan
    </div>
</div>
