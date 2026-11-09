<?php
namespace App\Providers;

use App\Models\{Attachment, Client, User};
use App\Policies\{AttachmentPolicy, ClientPolicy};
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Blade, Gate, RateLimiter};
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments) {
            if ($user->status !== 'active' || !\App\Enums\UserRole::tryFrom((string) $user->getRawOriginal('role'))) return false;
            /** Deny destructive abilities before policy overrides, including Livewire requests. */
            if (! $user->isAdmin() && in_array($ability, ['delete', 'deleteAny', 'forceDelete', 'forceDeleteAny'], true)) return false;
            return null;
        });

        \Carbon\Carbon::setLocale('it');
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.custom');
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');
        RateLimiter::for('client-media', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('signing-terminal', fn (Request $request) => Limit::perMinute(90)->by(hash('sha256',$request->cookie(\App\Services\SigningStations::COOKIE) ?: $request->ip())));
        RateLimiter::for('signing-terminal-write', fn (Request $request) => Limit::perMinute(20)->by(hash('sha256',$request->cookie(\App\Services\SigningStations::COOKIE) ?: $request->ip())));

        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(\App\Models\SupplierAgency::class, \App\Policies\SupplierAgencyPolicy::class);
        Gate::policy(\App\Models\SupplierCompany::class, \App\Policies\SupplierCompanyPolicy::class);
        Gate::define('system.admin', fn (User $user) => $user->canManageSystem());
        Gate::define('communications.manage', fn (User $user) => $user->canManageAgencyCommunications());
        Gate::define('ledger.manage', fn (User $user) => $user->canViewAllClients());
        Gate::define('forms.manage', fn (User $user) => $user->canViewAllClients());
    }
}
