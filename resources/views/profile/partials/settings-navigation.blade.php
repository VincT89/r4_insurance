<nav class="client-section-nav" aria-label="Impostazioni personali">
    <a href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif>Profilo e sicurezza</a>
    <a href="{{ route('appearance.edit') }}" @if(request()->routeIs('appearance.*')) aria-current="page" @endif>Aspetto</a>
    @can('system.admin')<a href="{{ route('quote-groups.index') }}" @if(request()->routeIs('quote-groups.*')) aria-current="page" @endif>Gruppi preventivi</a>@endcan
    @can('viewAny', \App\Models\SupplierCompany::class)<a href="{{ route('insurance-catalog.index') }}" @if(request()->routeIs('insurance-catalog.*')) aria-current="page" @endif>Rami e sottorami</a>@endcan
    @can('system.admin')<a href="{{ route('registry-imports.index') }}" @if(request()->routeIs('registry-imports.*', 'insurance-import.*')) aria-current="page" @endif>Importazioni CSV/Excel</a>@endcan
    @can('system.admin')<a href="{{ route('email-settings.edit') }}" @if(request()->routeIs('email-settings.*')) aria-current="page" @endif>Email</a>@endcan
    @can('system.admin')<a href="{{ route('form-templates.index') }}" @if(request()->routeIs('form-templates.*')) aria-current="page" @endif>Modelli documentali</a>@endcan
</nav>
