<!DOCTYPE html>
<html lang="it">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? config('branding.name') }}</title>
  <x-brand-icons />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @livewireStyles
</head>

<body class="app-body" style="{{ \App\Support\Appearance::style(auth()->user()?->appearance) }}">

  <a class="skip-link" href="#content-area">Vai al contenuto principale</a>

  {{-- Background Canvas --}}

  {{-- TOAST (flash messages) --}}
  <div id="toast" role="status" aria-live="polite"></div>

  <div class="shell" id="shell" 
       x-data="{ sidebarOpen: window.innerWidth >= 1024 }" 
       @resize.window="sidebarOpen = window.innerWidth >= 1024"
       @keydown.escape.window="if (window.innerWidth < 1024) sidebarOpen = false"
       :class="sidebarOpen ? 'expanded' : ''">

    {{-- TOPBAR --}}
    <header class="topbar">
      <div class="topbar-left">
        <button
          type="button"
          class="mobile-menu-toggle"
          @click="sidebarOpen = !sidebarOpen; setTimeout(() => window.refreshAppLayout(), 350)"
          :aria-expanded="sidebarOpen.toString()"
          aria-controls="app-sidebar"
          :aria-label="sidebarOpen ? 'Chiudi menu di navigazione' : 'Apri menu di navigazione'"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
        <a class="app-brand" href="{{ route('dashboard') }}" aria-label="{{ config('branding.name') }}: dashboard">
          <img class="brand-symbol" src="{{ asset('images/agency-signature.svg') }}" alt="" width="115" height="50">
          <span class="brand-name">{{ config('branding.wordmark') }}</span>
        </a>
      </div>
      <div class="topbar-center">
        <nav class="breadcrumb" aria-label="Percorso della pagina">
          @isset($breadcrumb)
            @foreach($breadcrumb as $label => $url)
              @if(!$loop->last)
                <a href="{{ $url }}">{{ $label }}</a>
                <span class="sep">/</span>
              @else
                <span class="cur">{{ $label }}</span>
              @endif
            @endforeach
          @else
            <span class="cur">{{ $title ?? 'Dashboard' }}</span>
          @endisset
        </nav>
      </div>
      <div class="topbar-right">
        <span class="tb-date">{{ strtoupper(now()->locale('it')->isoFormat('ddd D MMM YYYY')) }}</span>
        @unless(\App\Support\TableListing::exporting())
          @livewire('notifications.notification-dropdown')
        @endunless

        <div class="dropdown" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="if(open){ open = false; $nextTick(() => $refs.profileTrigger.focus()) }">
          <button
            type="button"
            class="avatar-btn"
            x-ref="profileTrigger"
            title="{{ auth()->user()->name }}"
            aria-label="Apri menu profilo di {{ auth()->user()->name }}"
            aria-controls="profile-menu"
            :aria-expanded="open.toString()"
            @click="open = !open"
          >
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(strstr(auth()->user()->name, ' '), 1, 1)) }}
          </button>
          <div id="profile-menu" class="dropdown-menu" role="menu" x-show="open" x-transition x-cloak :aria-hidden="(!open).toString()">
            <div class="dropdown-header stacked">
              <div class="dropdown-header-title">{{ auth()->user()->name }}</div>
              <div class="u-text-meta">{{ auth()->user()->role->label() }}</div>
            </div>
            <a href="{{ route('profile.edit') }}" class="dropdown-item" role="menuitem">Profilo</a>
            <div class="dropdown-divider"></div>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="dropdown-item danger" role="menuitem">Esci</button>
            </form>
          </div>
        </div>
      </div>
    </header>

    <button
      type="button"
      class="sidebar-backdrop"
      x-show="sidebarOpen"
      x-transition.opacity
      x-cloak
      @click="sidebarOpen = false"
      aria-label="Chiudi menu di navigazione"
    ></button>

    {{-- SIDEBAR --}}
    <nav
      class="sidebar"
      id="app-sidebar"
      aria-label="Navigazione principale"
      :aria-hidden="(!sidebarOpen && window.innerWidth < 1024).toString()"
      :inert="!sidebarOpen && window.innerWidth < 1024"
      @click="if ($event.target.closest('a') && window.innerWidth < 1024) sidebarOpen = false"
    >
      <button
        type="button"
        class="sidebar-toggle"
        @click="sidebarOpen = !sidebarOpen; setTimeout(() => window.refreshAppLayout(), 350)"
        :aria-expanded="sidebarOpen.toString()"
        aria-controls="app-sidebar"
        :aria-label="sidebarOpen ? 'Chiudi menu di navigazione' : 'Apri menu di navigazione'"
      >
        <svg x-show="sidebarOpen" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" x-cloak>
          <path d="m15 18-6-6 6-6" />
        </svg>
        <svg x-show="!sidebarOpen" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" x-cloak>
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <line x1="3" y1="12" x2="21" y2="12"></line>
          <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
      </button>

      <div class="nav-group">
        <div class="nav-group-label">Operatività</div>
        <x-nav-item href="{{ route('dashboard') }}" icon="layout-dashboard" label="Dashboard" :active="request()->routeIs('dashboard')" />
        <x-nav-item href="{{ route('daily-notes.index') }}" wire:navigate icon="book-open" label="Blocco Note" :active="request()->routeIs('daily-notes.*')" />
        @can('create', \App\Models\Client::class)
          <a href="{{ route('policies.create') }}" class="btn btn-p sidebar-policy-button" title="Nuova polizza" @if(request()->routeIs('policies.create','clients.policies.create')) aria-current="page" @endif><i data-lucide="plus" width="16" height="16" aria-hidden="true"></i><span>Nuova polizza</span></a>
        @endcan
      </div>
      @can('viewAny', \App\Models\Client::class)
        <div class="nav-divider"></div>
        <div class="nav-group">
          <div class="nav-group-label">Anagrafica</div>
          <x-nav-item href="{{ route('clients.index') }}" icon="users" label="Clienti" :active="request()->routeIs('clients.*') && !request()->routeIs('clients.policies.*')" />
          @can('viewAny', \App\Models\SupplierAgency::class)
            <x-nav-item href="{{ route('suppliers.index') }}" icon="building-2" label="Agenzie" :active="request()->routeIs('suppliers.*', 'supplier-companies.*', 'insurance-catalog.*')" />
          @endcan
        </div>
      @endcan
      @can('viewAny', \App\Models\Client::class)
        <div class="nav-divider"></div>
        <div class="nav-group">
          <div class="nav-group-label">Gestione</div>
          <x-nav-item href="{{ route('claims.index') }}" icon="folder-open" label="Sinistri" :active="request()->routeIs('claims.*')" />
          <x-nav-item href="{{ route('quotes.index') }}" icon="file-text" label="Preventivi" :active="request()->routeIs('quotes.*')" />
          <x-nav-item href="{{ route('titles.index') }}" icon="files" label="Rate e quietanze" :active="request()->routeIs('titles.*')" />
          <x-nav-item href="{{ route('collections.index') }}" icon="wallet" label="Incassi" :active="request()->routeIs('collections.*')" />
          <x-nav-item href="{{ route('policies.index') }}" icon="files" label="Polizze" :active="request()->routeIs('policies.index', 'clients.policies.show', 'clients.policies.edit')" />
          <x-nav-item href="{{ route('deadlines.index') }}" icon="clock" label="Scadenze" :active="request()->routeIs('deadlines.*')" />
          @can('system.admin')<x-nav-item href="{{ route('agency-communications.index') }}" icon="mail" label="Comunicazioni" :active="request()->routeIs('agency-communications.*')" /><x-nav-item href="{{ route('reports.index') }}" icon="chart-no-axes-combined" label="Report" :active="request()->routeIs('reports.*','commissions.*')" />@endcan
        </div>
      @endcan
      @can('system.admin')
        <div class="nav-divider"></div>
        <div class="nav-group">
          <div class="nav-group-label">Amministrazione</div>
          <x-nav-item href="{{ route('users.index') }}" icon="user-cog" label="Utenti" :active="request()->routeIs('users.*')" />
          <x-nav-item href="{{ route('audit-logs.index') }}" icon="history" label="Registro attività" :active="request()->routeIs('audit-logs.*')" />
        </div>
      @endcan
      <div class="nav-spacer"></div>
      <div class="nav-divider"></div>
      <div class="nav-group">
        <x-nav-item href="{{ route('profile.edit') }}" icon="settings" label="Impostazioni" :active="request()->routeIs('profile.*', 'appearance.*', 'insurance-import.*')" />
      </div>

      <form method="POST" action="{{ route('logout') }}" class="sidebar-logout-form">
        @csrf
        <button type="submit" class="nav-item logout sidebar-logout-btn" x-bind:title="!sidebarOpen ? 'Logout' : ''">
          <div class="nav-icon"><i data-lucide="log-out" width="16" height="16" stroke-width="1.8"></i></div>
          <span class="nav-label">Logout</span>
          <div class="nav-tooltip">Logout</div>
        </button>
      </form>
    </nav>

    {{-- MAIN --}}
    <main class="main">
      <div class="content page-transition-root" id="content-area" tabindex="-1">
        {{-- Flash messages --}}
        @if(session('success'))
          <div class="flash flash-success" data-flash-toast="success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="flash flash-error" data-flash-toast="error">{{ session('error') }}</div>
        @endif
        @if(session('warning'))<div class="document-expiry-status" data-state="unknown" role="alert">{{ session('warning') }}</div>@endif
        @if($errors->any())
          <div class="flash flash-error" role="alert">
            <strong>Controlla i dati inseriti.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
          </div>
        @endif

        {{ $slot }}
      </div>
    </main>

  </div>

  <script>
    window.refreshAppLayout = function () {
      window.dispatchEvent(new CustomEvent('app:layout-resized'));
    };
  </script>

  @stack('scripts')
  @livewireScripts
</body>

</html>
