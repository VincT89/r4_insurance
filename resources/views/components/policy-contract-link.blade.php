@props(['policy', 'button' => false])
@if($policy->contracts->count() === 1)
    @php($contract = $policy->contracts->first())
    <a data-table-export-ignore href="{{ $contract->openUrl() }}" @if($contract->canPreview()) target="_blank" rel="noopener" @endif @class(['btn btn-g' => $button, 'policy-contract-link' => !$button])>{{ $contract->canPreview() ? 'Apri contratto' : 'Scarica contratto' }}</a>
@elseif($policy->contracts->isNotEmpty())
    <a data-policy-section-link data-table-export-ignore href="{{ route('clients.policies.show', [$policy->client_id, $policy, 'tab'=>'documents']).'#policy-contracts' }}" @class(['btn btn-g' => $button, 'policy-contract-link' => !$button])>Apri contratti ({{ $policy->contracts->count() }})</a>
@endif
