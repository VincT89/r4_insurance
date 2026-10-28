@props(['status' => '', 'type' => null, 'label' => null])

@php
// Mappa status → classe badge
$map = [
    // generici
    'active'          => 'bg',
    'inactive'        => 'bd',
    // offerte commerciali
    'presented'       => 'bb',
    'accepted'        => 'bg',
    'rejected'        => 'br',
    // ticket / task status
    'open'            => 'ba',
    'in_progress'     => 'bb',
    'waiting'         => 'ba',
    'resolved'        => 'bg',
    'closed'          => 'bd',
    'todo'            => 'bd',
    'done'            => 'bg',
    // ticket priority
    'low'             => 'bd',
    'medium'          => 'bd',
    'high'            => 'br',
    'urgent'          => 'br',
    // invoice status
    'draft'           => 'bd',
    'issued'          => 'bb',
    'partially_paid'  => 'ba',
    'paid'            => 'bg',
    'overdue'         => 'br',
    'cancelled'       => 'bd',
    'pending'         => 'ba',
    'processing'      => 'ba',
    // ticket type
    'bug'             => 'br',
    'support'         => 'bd',
    'request'         => 'bb',
    'change'          => 'ba',
    'admin'           => 'bk',
    // calendar event type
    'internal_meeting'=> 'bb',
    'client_meeting'  => 'bk',
    'deadline'        => 'br',
    'review'          => 'ba',
    'delivery'        => 'bg',
    'personal'        => 'bd',
    'other'           => 'bd',
    // payment method
    'bank_transfer'   => 'bb',
    'cash'            => 'bg',
    'card'            => 'bb',
    // marketing statuses
    'draft'           => 'bd',
    'queued_to_n8n'   => 'ba', // giallo
    'n8n_failed'      => 'br', // rosso
    'submitted_to_n8n'=> 'bb', // blu
    'posts_received'  => 'bb',
    'internal_review' => 'ba',
    'sent_to_client'  => 'ba',
    'client_changes_requested' => 'br',
    'client_approved' => 'bg',
    'ready_to_publish'=> 'bg', // verde
    'not_ready'       => 'bd', // grigio
    'publishing'      => 'ba', // giallo/arancione
    'failed'          => 'br', // rosso
    // project status
    'completed'       => 'bg',
    'on_hold'         => 'ba',
    'cancelled'       => 'br',
    // team roles
    'member'          => 'bg',
    'lead'            => 'ba',
    'support'         => 'bb',
    // hosting status
    'expired'         => 'br',
    'expiring'        => 'ba',
    /** Gli stati assicurativi prevalgono sulle corrispondenze dei moduli precedenti. */
    'unpaid'          => 'br',
    'partially_paid'  => 'br',
    'partial'         => 'br',
    'renewed'         => 'bg',
    'future'          => 'bb',
    'due_today'       => 'br',
    'suspended'       => 'ba',
    'replaced'        => 'bd',
    'ceased'          => 'bd',
    'cancelled'       => 'bd',
];
$class = $map[$status] ?? 'bd';
@endphp

<span class="badge {{ $class }}">{{ $label ?? ($slot->isEmpty() ? $status : $slot) }}</span>
