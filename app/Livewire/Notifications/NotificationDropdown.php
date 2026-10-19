<?php

namespace App\Livewire\Notifications;

use Livewire\Component;

class NotificationDropdown extends Component
{
    public function markAllAsRead()
    {
        auth()->user()->visibleNotifications()->whereNull('read_at')->get()->markAsRead();
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
        return view('livewire.notifications.notification-dropdown', [
            'unreadNotificationsCount' => $user->visibleNotifications()->whereNull('read_at')->count(),
            'latestNotifications' => $user->visibleNotifications()->latest()->take(10)->get(),
        ]);
    }
}
