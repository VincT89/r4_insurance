<x-app-layout title="Modelli dei documenti"><x-page-header><x-slot:title>Modelli dei documenti</x-slot:title><x-slot:actions><a class="btn btn-p" href="{{ route('form-templates.create') }}">Nuovo modello</a></x-slot:actions></x-page-header>@include('profile.partials.settings-navigation')
<form class="client-search-form filter-grid" method="GET">
    <div class="form-row model-filter-fields">
        <x-form-group label="Cerca modello" name="search"><input class="form-in" type="search" name="search" value="{{ $filters['search']??'' }}" placeholder="Nome del modello" maxlength="255"></x-form-group>
        <x-form-group label="Stato" name="status"><select class="form-sel" name="status"><option value="">Tutti</option>@foreach(\App\Models\InsuranceFormTemplate::STATUSES as $key=>$label)<option value="{{ $key }}" @selected(($filters['status']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
        <x-form-group label="Ambito" name="scope"><select class="form-sel" name="scope"><option value="">Tutti</option>@foreach(\App\Models\InsuranceFormTemplate::SCOPES as $key=>$label)<option value="{{ $key }}" @selected(($filters['scope']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
        <x-form-group label="Destinatari" name="client_type"><select class="form-sel" name="client_type"><option value="">Tutti i modelli</option>@foreach(\App\Models\InsuranceFormTemplate::CLIENT_TYPES as $key=>$label)<option value="{{ $key }}" @selected(($filters['client_type']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
    </div>
    <div class="search-form-actions"><label class="insurance-checkbox"><input type="checkbox" name="history" value="1" @checked($filters['history']??false)> Includi versioni precedenti</label><a class="btn btn-g" href="{{ route('form-templates.index') }}">Azzera</a><button class="btn btn-p">Applica filtri</button></div>
</form>
<x-panel title="Modelli e versioni"><div class="client-table-scroll policy-list-scroll"><table class="t-table policy-documents-table form-package-history"><thead><tr><th>Modello</th><th>Utilizzo</th><th>Versione e stato</th><th>Azioni</th></tr></thead><tbody>
    @forelse($templates as $template)<tr>
        <td data-label="Modello"><strong>{{ $template->title }}</strong><div class="u-text-meta">{{ $template->signature_required ? 'Richiede firma' : 'Senza firma' }}</div></td>
        <td data-label="Utilizzo">{{ \App\Models\InsuranceFormTemplate::SCOPES[$template->scope] }} · {{ \App\Models\InsuranceFormTemplate::CLIENT_TYPES[$template->client_type ?? 'all'] }}
            @if($template->scope==='policy')<div class="u-text-meta">{{ $template->branch?->name ?? 'Tutti i rami' }}{{ $template->subbranch ? ' / '.$template->subbranch->name : '' }}</div>@endif</td>
        <td data-label="Versione e stato">{{ \App\Models\InsuranceFormTemplate::STATUSES[$template->status] }}<div class="u-text-meta">Versione {{ $template->version }}</div></td>
        <td data-label="Azioni"><a class="btn btn-g btn-sm" href="{{ route('form-templates.edit',[$template,'revise'=>!$template->next && $template->status!=='draft' ? 1 : null]) }}">{{ $template->next ? 'Leggi versione' : 'Modifica modello' }}</a></td>
    </tr>@empty<tr><td colspan="4">Nessun modello corrispondente ai filtri.</td></tr>@endforelse
</tbody></table></div></x-panel>
</x-app-layout>
