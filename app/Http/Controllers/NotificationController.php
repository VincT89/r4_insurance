<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markAllAsRead(): RedirectResponse
    {
        auth()->user()->visibleNotifications()->whereNull('read_at')->get()->markAsRead();
        return back()->with('success', 'Tutte le notifiche sono state segnate come lette.');
    }

    public function markAsReadAndRedirect(string $id): RedirectResponse
    {
        $notification = auth()->user()->visibleNotifications()->findOrFail($id);

        $notification->markAsRead();

        return redirect()->to(\App\Support\NotificationDestination::local(
            $notification->data['intended_url']
                ?? $notification->data['url']
                ?? url('/dashboard')
        ));
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorize('system.admin');
        $notification = auth()->user()->visibleNotifications()->findOrFail($id);
        $notification->delete();

        return back()->with('success', 'Notifica eliminata.');
    }
}
