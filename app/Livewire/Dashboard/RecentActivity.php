<?php

namespace App\Livewire\Dashboard;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class RecentActivity extends Component
{
    public function render()
    {
        Gate::authorize('system.admin');
        return view('livewire.dashboard.recent-activity', [
            'logs' => AuditLog::with('user:id,name')->latest('created_at')->latest('id')->limit(12)->get(),
        ]);
    }
}
