<div class="dropdown" wire:poll.30s.visible x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="if(open){ open = false; $nextTick(() => $refs.notificationsTrigger.focus()) }">
  <button
    id="notifications-trigger"
    type="button"
    class="tb-btn"
    x-ref="notificationsTrigger"
    aria-controls="notifications-panel"
    :aria-expanded="open.toString()"
    aria-label="Notifiche{{ isset($unreadNotificationsCount) && $unreadNotificationsCount > 0 ? ', '.$unreadNotificationsCount.' non lette' : '' }}"
    @click="open = !open; if (open) $wire.$refresh()"
  >
    @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
      <span class="badge-notif" aria-hidden="true">{{ $unreadNotificationsCount }}</span>
    @endif
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" />
    </svg>
  </button>

  <div id="notifications-panel" class="dropdown-menu dropdown-menu-wide" role="region" aria-labelledby="notifications-trigger" x-show="open" x-transition x-cloak :aria-hidden="(!open).toString()">
    <div class="dropdown-header">
      <div class="dropdown-header-title">Notifiche</div>
      @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
        <div class="u-flex-center u-gap-md">
          <button type="button" wire:click="markAllAsRead" wire:loading.attr="disabled" class="notif-mark-all" @click.stop>Segna tutte lette</button>
          <span class="notif-count-badge">{{ $unreadNotificationsCount }} nuove</span>
        </div>
      @endif
    </div>
    <div class="dropdown-body">
      @forelse($latestNotifications ?? [] as $notification)
        @php
            $deadline = $notification->type === \App\Services\DeadlineReminders::TYPE;
            $pastDue = $deadline && is_string($notification->data['due_on'] ?? null) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $notification->data['due_on']) && $notification->data['due_on'] < today()->toDateString();
            $paymentDue = $deadline && ($notification->data['kind'] ?? null) === 'title';
        @endphp
        <div class="notif-item {{ $notification->read_at ? '' : 'unread' }} {{ $pastDue || $paymentDue ? 'is-alert' : '' }}">
          <button type="button" wire:click="markAsReadAndRedirect('{{ $notification->id }}')" class="notif-item-btn" @click.stop>
            @php
              $iconName = 'bell';
              $nType = $notification->data['type'] ?? '';
              $nCategory = $notification->data['category'] ?? '';
              if ($nCategory === 'shooting')
                $iconName = 'camera';
              elseif (in_array($nType, ['task_assigned', 'task_due_soon', 'task_created']))
                $iconName = 'check-square';
              elseif ($nType === 'ticket_assigned')
                $iconName = 'ticket';
              elseif (in_array($nType, ['invoice_overdue', 'payment_recorded', 'electronic_invoice_status']))
                $iconName = 'credit-card';
            @endphp
            <div class="notif-item-title">
              <i data-lucide="{{ $iconName }}" class="u-icon-sm notif-item-icon"></i>
              <span>{{ $pastDue ? 'Scadenza superata' : ($notification->data['title'] ?? 'Nuova Notifica') }}</span>
            </div>
            <div class="notif-item-body">{{ $notification->data['message'] ?? '' }}</div>
            <div class="notif-item-time">{{ $notification->created_at->diffForHumans() }}</div>
          </button>
          @can('system.admin')
          <button type="button" wire:click="deleteNotification('{{ $notification->id }}')" class="notif-item-delete" title="Elimina notifica" aria-label="Elimina notifica" @click.stop>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" />
            </svg>
          </button>
          @endcan
        </div>
      @empty
        <div class="notif-empty">
          <i data-lucide="inbox" class="u-icon-lg notif-empty-icon"></i>
          <div class="u-text-strong">Nessuna notifica</div>
          <div>Gli avvisi sulle scadenze e le segnalazioni compariranno qui.</div>
        </div>
      @endforelse
    </div>
  </div>
</div>
