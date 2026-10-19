<nav class="client-section-nav" aria-label="Agenzie e compagnie">
    <a href="{{ route('suppliers.index') }}" @if(request()->routeIs('suppliers.*')) aria-current="page" @endif>Agenzie e subagenzie</a>
    <a href="{{ route('supplier-companies.index') }}" @if(request()->routeIs('supplier-companies.*')) aria-current="page" @endif>Compagnie</a>
    <a href="{{ route('insurance-catalog.index') }}" @if(request()->routeIs('insurance-catalog.*')) aria-current="page" @endif>Rami e sottorami</a>
</nav>
