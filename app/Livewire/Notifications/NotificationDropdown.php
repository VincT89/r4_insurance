<?php

namespace App\Livewire\Notifications;

use Livewire\Component;

class NotificationDropdown extends Component
{
    public function placeholder()
    {
        /** The current inbox loads in its own authenticated request without holding up the page's tables. */
        return view('livewire.notifications.notification-placeholder');
    }

    public function markAllAsRead()
    {
        auth()->user()->visibleNotifications()->whereNull('read_at')->update(['read_at'=>now()]);
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()->visibleNotifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }
    }

    public function markAsReadAndRedirect($id)
    {
        $notification = auth()->user()->visibleNotifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
            $url = $notification->data['intended_url']
                ?? $notification->data['url']
                ?? null;
            if ($url) {
                return $this->redirect(\App\Support\NotificationDestination::local($url), navigate: true);
            }
        }
    }

    public function deleteNotification($id)
    {
        \Illuminate\Support\Facades\Gate::authorize('system.admin');
        $notification = auth()->user()->visibleNotifications()->where('id', $id)->first();
        if ($notification) {
            $notification->delete();
        }
    }

    public function render()
    {
        $user = auth()->user();
        app(\App\Services\DeadlineReminders::class)->refresh($user);
        app(\App\Services\ClaimReminders::class)->refresh($user);
        app(\App\Services\CollectionCommunicationReminders::class)->forUser($user);
        /** Sort only identifiers and ordering fields; load the JSON bodies for the ten displayed notifications afterwards. */
        $visible = $user->visibleNotifications()->reorder()->select(['id', 'read_at', 'type', 'updated_at', 'data->due_on as due_date'])->toBase();
        $inbox = \Illuminate\Support\Facades\DB::query()->fromSub($visible, 'visible')->select('visible.*')
            ->selectRaw('SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) OVER () AS inbox_unread_count')
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN type = ? THEN 0 WHEN type = ? THEN 1 ELSE 2 END', [\App\Services\CollectionCommunicationReminders::REVERSAL_TYPE, \App\Services\CollectionCommunicationReminders::TYPE])
            ->orderBy('due_date')->orderByDesc('updated_at')->orderByDesc('id')->limit(10);
        /** One statement retains the same visibility snapshot for the unread count and the displayed records. */
        $notifications = $user->notifications()->joinSub($inbox, 'inbox', fn ($join) => $join->on('inbox.id', '=', 'notifications.id'))
            ->select('notifications.*', 'inbox.inbox_unread_count')->reorder()->orderByRaw('CASE WHEN inbox.read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN inbox.type = ? THEN 0 WHEN inbox.type = ? THEN 1 ELSE 2 END', [\App\Services\CollectionCommunicationReminders::REVERSAL_TYPE, \App\Services\CollectionCommunicationReminders::TYPE])
            ->orderBy('inbox.due_date')->orderByDesc('inbox.updated_at')->orderByDesc('inbox.id')->get();
        return view('livewire.notifications.notification-dropdown', [
            'unreadNotificationsCount' => (int) ($notifications->first()?->inbox_unread_count ?? 0),
            'latestNotifications' => $notifications,
            'notificationDetails' => app(\App\Services\DeadlineReminders::class)->details($notifications, $user),
        ]);
    }
}
