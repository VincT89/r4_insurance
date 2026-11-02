<x-app-layout title="Prepara moduli">
<x-page-header><x-slot:title>Prepara moduli · {{ $client->name }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.forms.index',[$client,'policy'=>$policy?->id]) }}">Moduli e firme</a></x-slot:actions></x-page-header>
@php($safeOld=fn($key,$default='')=>is_scalar(old($key,$default))?old($key,$default):'')
@if($templates->isEmpty())<x-panel padded><p>Non ci sono modelli approvati per {{ $policy ? 'questa polizza' : 'l’anagrafica cliente' }}. L’admin può revisionare e abilitare i modelli nelle impostazioni.</p>@can('system.admin')<a class="btn btn-p" href="{{ route('form-templates.index') }}">Apri modelli documentali</a>@endcan</x-panel>@else
<x-panel padded>@include('insurance-forms.package-form')</x-panel>@endif
</x-app-layout>
