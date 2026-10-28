@props(['title'])
<x-badge :status="$title->state" :label="$title->state_label" />
@if($title->state === 'overdue' && $title->collected_cents > 0)<small class="u-text-meta u-block">Già incassati {{ \App\Support\Money::format($title->collected_cents) }}</small>@endif
