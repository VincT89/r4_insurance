<dl class="client-registry-details">
<div><dt>Contraente</dt><dd><a href="{{ route('clients.show',$policy->client) }}">{{ $policy->client->name }}</a></dd></div>
<div><dt>Polizza</dt><dd><a href="{{ route('clients.policies.show',[$policy->client_id,$policy]) }}">{{ $policy->number }}</a></dd></div>
<div><dt>Agenzia</dt><dd>{{ $policy->supplierAgency?->name?:'Rapporto diretto' }}</dd></div>
<div><dt>Compagnia</dt><dd>{{ $policy->company }}</dd></div>
<div><dt>Ramo / sottoramo</dt><dd>{{ $policy->category }}{{ $policy->subcategory?' / '.$policy->subcategory:'' }}</dd></div>
<div><dt>Targa / bene assicurato</dt><dd>{{ $policy->vehicle?->plate?:$policy->insured_subject?:'Non indicato' }}</dd></div>
<div><dt>Periodo della polizza</dt><dd>{{ $policy->starts_on->format('d/m/Y') }} – {{ $policy->expires_on->format('d/m/Y') }}</dd></div>
<div><dt>Stato della polizza</dt><dd>{{ $policy->status_label }}</dd></div>
</dl>
