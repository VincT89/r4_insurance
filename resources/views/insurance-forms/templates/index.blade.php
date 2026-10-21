<x-app-layout title="Modelli documentali"><x-page-header><x-slot:title>Modelli documentali</x-slot:title><x-slot:actions><a class="btn btn-p" href="{{ route('form-templates.create') }}">Nuovo modello</a></x-slot:actions></x-page-header>@include('profile.partials.settings-navigation')
<form class="client-search-form filter-grid" method="GET">
    <div class="form-row">
        <x-form-group label="Stato" name="status"><select class="form-sel" name="status"><option value="">Tutti</option>@foreach(\App\Models\InsuranceFormTemplate::STATUSES as $key=>$label)<option value="{{ $key }}" @selected(($filters['status']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
        <x-form-group label="Ambito" name="scope"><select class="form-sel" name="scope"><option value="">Tutti</option>@foreach(\App\Models\InsuranceFormTemplate::SCOPES as $key=>$label)<option value="{{ $key }}" @selected(($filters['scope']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
        <x-form-group label="Destinatari" name="client_type"><select class="form-sel" name="client_type"><option value="">Tutti i modelli</option>@foreach(\App\Models\InsuranceFormTemplate::CLIENT_TYPES as $key=>$label)<option value="{{ $key }}" @selected(($filters['client_type']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
    </div>
    <div class="search-form-actions"><a class="btn btn-g" href="{{ route('form-templates.index') }}">Azzera</a><button class="btn btn-p">Applica filtri</button></div>
</form>
<x-panel title="Modelli e versioni"><div class="client-table-scroll"><table class="t-table"><thead><tr><th>Modello</th><th>Versione</th><th>Ambito</th><th>Destinatari</th><th>Ramo / sottoramo</th><th>Firma</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>
    @forelse($templates as $template)<tr><td>{{ $template->title }}</td><td>{{ $template->version }}</td><td>{{ \App\Models\InsuranceFormTemplate::SCOPES[$template->scope] }}</td><td>{{ \App\Models\InsuranceFormTemplate::CLIENT_TYPES[$template->client_type ?? 'all'] }}</td><td>{{ $template->branch?->name??'Tutti' }} / {{ $template->subbranch?->name??'Tutti' }}</td><td>{{ $template->signature_required?'Richiesta':'Non richiesta' }}</td><td>{{ \App\Models\InsuranceFormTemplate::STATUSES[$template->status] }}</td><td><a class="btn btn-g btn-sm" href="{{ route('form-templates.edit',$template) }}">Apri modello</a></td></tr>
    @empty<tr><td colspan="8">Nessun modello corrispondente ai filtri.</td></tr>@endforelse
</tbody></table></div></x-panel>
</x-app-layout>
