<div class="dropdown" wire:poll.30s.visible x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="if(open){ open = false; $nextTick(() => $refs.notificationsTrigger.focus()) }">
  <button
    id="notifications-trigger"
    type="button"
    class="tb-btn"
    x-ref="notificationsTrigger"
    aria-controls="notifications-panel"
    :aria-expanded="open.toString()"
    aria-label="Notifiche<?php echo e(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0 ? ', '.$unreadNotificationsCount.' non lette' : ''); ?>"
    @click="open = !open; if (open) $wire.$refresh()"
  >
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0): ?>
      <span class="badge-notif" aria-hidden="true"><?php echo e($unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount); ?></span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" />
    </svg>
  </button>

  <div id="notifications-panel" class="dropdown-menu dropdown-menu-wide" role="region" aria-labelledby="notifications-trigger" x-show="open" x-transition x-cloak :aria-hidden="(!open).toString()">
    <div class="dropdown-header">
      <div class="dropdown-header-title">Notifiche</div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0): ?>
        <div class="u-flex-center u-gap-md">
          <button type="button" wire:click="markAllAsRead" wire:loading.attr="disabled" class="notif-mark-all" @click.stop>Segna tutte lette</button>
          <span class="notif-count-badge"><?php echo e($unreadNotificationsCount); ?> nuove</span>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="dropdown-body" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'notifications-page-'.e($notificationPage).''; ?>wire:key="notifications-page-<?php echo e($notificationPage); ?>">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $latestNotifications ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php
            $deadline = $notification->type === \App\Services\DeadlineReminders::TYPE;
            $pastDue = $deadline && is_string($notification->data['due_on'] ?? null) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $notification->data['due_on']) && $notification->data['due_on'] < today()->toDateString();
            $paymentDue = $deadline && ($notification->data['kind'] ?? null) === 'title';
            $reversal = $notification->type === \App\Services\CollectionCommunicationReminders::REVERSAL_TYPE;
            $detail = $notificationDetails[$notification->id] ?? [];
        ?>
        <div class="notif-item <?php echo e($notification->read_at ? '' : 'unread'); ?> <?php echo e($pastDue || $paymentDue || $reversal ? 'is-alert' : ''); ?>">
          <button type="button" wire:click="markAsReadAndRedirect('<?php echo e($notification->id); ?>')" class="notif-item-btn" @click.stop>
            <?php
              $iconName = 'bell';
              if ($paymentDue || $reversal) $iconName = 'credit-card';
              elseif ($notification->type === \App\Services\CollectionCommunicationReminders::TYPE) $iconName = 'mail';
            ?>
            <div class="notif-item-title">
              <i data-lucide="<?php echo e($iconName); ?>" class="u-icon-sm notif-item-icon"></i>
              <span><?php echo e($detail['title'] ?? ($pastDue ? 'Scadenza superata' : ($notification->data['title'] ?? 'Nuova notifica'))); ?></span>
            </div>
            <div class="notif-item-body"><?php echo e($detail['message'] ?? $notification->data['message'] ?? ''); ?></div>
            <div class="notif-item-time"><?php echo e($notification->updated_at->locale('it')->diffForHumans()); ?></div>
          </button>
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('system.admin')): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($notification->type !== \App\Services\PolicyFormReminder::TYPE): ?>
          <button type="button" wire:click="deleteNotification('<?php echo e($notification->id); ?>')" class="notif-item-delete" title="Elimina notifica" aria-label="Elimina notifica" @click.stop>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" />
            </svg>
          </button>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php endif; ?>
        </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <div class="notif-empty">
          <i data-lucide="inbox" class="u-icon-lg notif-empty-icon"></i>
          <div class="u-text-strong">Nessuna notifica</div>
          <div>Gli avvisi sulle scadenze e le segnalazioni compariranno qui.</div>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($notificationsTotal > 10): ?>
      <nav class="notification-paging" aria-label="Paginazione degli avvisi">
        <span aria-live="polite">Avvisi <?php echo e(($notificationPage - 1) * 10 + 1); ?>–<?php echo e(min($notificationPage * 10, $notificationsTotal)); ?> di <?php echo e($notificationsTotal); ?></span>
        <div>
          <button type="button" class="btn btn-g btn-sm" wire:click="previousNotificationsPage" wire:loading.attr="disabled" <?php if($notificationPage <= 1): echo 'disabled'; endif; ?> @click.stop>Avvisi precedenti</button>
          <button type="button" class="btn btn-g btn-sm" wire:click="nextNotificationsPage" wire:loading.attr="disabled" <?php if($notificationPage >= $notificationPages): echo 'disabled'; endif; ?> @click.stop>Altri avvisi</button>
        </div>
      </nav>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="notification-links">
      <a href="<?php echo e(route('deadlines.index',['period'=>'15'])); ?>">Scadenze entro 15 giorni</a>
      <a href="<?php echo e(route('deadlines.index',['period'=>'overdue'])); ?>">Già scadute</a>
      <a href="<?php echo e(route('claims.index',['reminder'=>'all'])); ?>">Attività sinistri</a>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('communications.manage')): ?><a href="<?php echo e(route('agency-communications.collections',['scope'=>'pending'])); ?>">Incassi da comunicare</a><?php endif; ?>
    </div>
  </div>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/livewire/notifications/notification-dropdown.blade.php ENDPATH**/ ?>