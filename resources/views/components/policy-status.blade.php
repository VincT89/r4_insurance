@props(['policy'])
<x-badge :status="$policy->is_expired ? 'expired' : ($policy->status_label === 'Decorrenza futura' ? 'future' : $policy->status)" :label="$policy->status_label" />
