<?php

namespace App\Livewire\Notifications;

use Livewire\Component;

class NotificationDropdown extends Component
{
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
        /** Count the same visible snapshot once, keeping paid or revoked reminders out of both the list and the counter. */
        $notifications = $user->visibleNotifications()->select('notifications.*')
            ->selectRaw('SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) OVER () AS inbox_unread_count')
            ->reorder()->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN type = ? THEN 0 WHEN type = ? THEN 1 ELSE 2 END', [\App\Services\CollectionCommunicationReminders::REVERSAL_TYPE, \App\Services\CollectionCommunicationReminders::TYPE])
            ->orderBy('data->due_on')->orderByDesc('updated_at')->orderByDesc('id')->take(10)->get();
        return view('livewire.notifications.notification-dropdown', [
            'unreadNotificationsCount' => (int) ($notifications->first()?->inbox_unread_count ?? 0),
            'latestNotifications' => $notifications,
            'notificationDetails' => app(\App\Services\DeadlineReminders::class)->details($notifications, $user),
        ]);
    }
}
