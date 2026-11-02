@props(['title'])
<x-badge :status="$title->state" :label="$title->state_label" />
@if($title->balance_cents > 0 && $title->collected_cents > 0)<small class="u-text-meta u-block">{{ $title->direction === 'refund' ? 'Rimborso già registrato:' : 'Incasso già registrato:' }} {{ \App\Support\Money::format($title->collected_cents) }}</small>@endif
