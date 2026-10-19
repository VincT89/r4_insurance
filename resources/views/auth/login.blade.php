<x-guest-layout title="Accedi — R4 Insurance">
  <div class="login-shell">

    {{-- SINISTRA: brand --}}
    <div class="login-left">
      <div class="login-left-top">
        <div class="login-logo">
          <img src="{{ asset('images/agency-signature.svg') }}" alt="" width="115" height="50"><span class="login-logo-name">{{ config('branding.wordmark') }}</span>
        </div>
        <div class="login-eyebrow">Gestionale interno</div>
        <div class="login-title">
          Clienti, polizze<br>e documenti.
        </div>
        <p class="login-description">Accedi al gestionale per consultare le anagrafiche e seguire le scadenze assicurative.</p>
      </div>

      <div class="login-left-bottom">
        <div class="login-copy">© {{ date('Y') }} R4 Insurance</div>
      </div>
    </div>

    {{-- DESTRA: form --}}
    <div class="login-right">
      <div class="login-mobile-brand"><img src="{{ asset('images/agency-signature.svg') }}" alt="" width="115" height="50"><span>{{ config('branding.wordmark') }}</span></div>

      <div class="login-form-top">
        <div class="login-form-eyebrow">Bentornato</div>
        <div class="login-form-title">Accedi al tuo<br>account</div>
      </div>

      {{-- Session error (credenziali errate) --}}
      @if(session('status'))
        <div class="flash flash-error login-flash">
          {{ session('status') }}
        </div>
      @endif

      <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Email --}}
        <div class="form-g login-form-g">
          <label class="form-lbl" for="login-email">Email aziendale</label>
          <input
            id="login-email"
            type="email"
            name="email"
            class="form-in @error('email') is-invalid @enderror"
            value="{{ old('email') }}"
            placeholder="La tua email"
            required
            autofocus
            autocomplete="email"
          >
          @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        {{-- Password --}}
        <div class="form-g login-form-g last">
          <label class="form-lbl" for="login-password">Password</label>
          <x-password-input
            id="login-password"
            name="password"
            :class="$errors->has('password') ? 'is-invalid' : ''"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          />
          @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        {{-- Remember me --}}
        <div class="login-remember">
          <input type="checkbox" name="remember" id="remember" class="login-checkbox">
          <label for="remember" class="login-checkbox-lbl">
            Ricordami
          </label>
        </div>

        <div class="login-form-footer">
          <div class="login-form-hint">
            Problemi di accesso?<br>Contatta l'amministratore.
          </div>
          <button type="submit" class="btn btn-p">Entra</button>
        </div>
      </form>

    </div>
  </div>
</x-guest-layout>
