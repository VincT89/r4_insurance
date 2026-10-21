@include('insurance-forms.documentation')
@if($policy->vehicle?->plate)<x-panel title="Copertura RCA del veicolo" padded><p>Targa {{ $policy->vehicle->plate }}. Il controllo della copertura RCA è distinto dalla registrazione del pagamento.</p><a class="btn btn-g" href="{{ route('clients.policies.coverage',[$client,$policy]) }}">Verifica RCA</a></x-panel>@endif
