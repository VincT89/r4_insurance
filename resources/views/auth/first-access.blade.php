<x-guest-layout title="Imposta Password — R4 Insurance">
  <div class="login-shell">

    {{-- SINISTRA: brand --}}
    <div class="login-left">
      <div class="login-left-top">
        <div class="login-logo">
          <img src="{{ asset('images/agency-signature.svg') }}" alt="" width="115" height="50"><span class="login-logo-name">{{ config('branding.wordmark') }}</span>
        </div>
        <div class="login-eyebrow">Onboarding Obbligatorio</div>
        <div class="login-title">
          Sicurezza,<br>Protezione,<br>
          <strong>Account.</strong>
        </div>
      </div>

      <div class="login-left-bottom">
        <div class="login-copy">© {{ date('Y') }} R4 Insurance</div>
      </div>
    </div>

    {{-- DESTRA: form --}}
    <div class="login-right">
      <div class="login-mobile-brand"><img src="{{ asset('images/agency-signature.svg') }}" alt="" width="115" height="50"><span>{{ config('branding.wordmark') }}</span></div>

      <div class="login-form-top">
        <div class="login-form-eyebrow">Primo Accesso</div>
        <div class="login-form-title">Imposta la tua<br>Password</div>
        <p class="login-form-desc">
          Per motivi di sicurezza è obbligatorio impostare una nuova password personalizzata prima di poter accedere al gestionale.
        </p>
      </div>

      {{-- Session error --}}
      @if(session('status'))
        <div class="flash flash-error login-flash">
          {{ session('status') }}
        </div>
      @endif

      <form method="POST" action="{{ route('password.setup.update') }}">
        @csrf

        {{-- Password --}}
        <div class="form-g login-form-g">
          <label class="form-lbl" for="first-access-password">Nuova Password</label>
          <x-password-input
            id="first-access-password"
            name="password"
            :class="$errors->has('password') ? 'is-invalid' : ''"
            placeholder="Minimo 8 caratteri"
            required
            autofocus
            autocomplete="new-password"
          />
          @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        {{-- Conferma Password --}}
        <div class="form-g login-form-g lg">
          <label class="form-lbl" for="first-access-password-confirmation">Conferma Password</label>
          <x-password-input
            id="first-access-password-confirmation"
            name="password_confirmation"
            placeholder="Ripeti password"
            required
            autocomplete="new-password"
          />
        </div>

        <div class="login-form-footer end">
          <button type="submit" class="btn btn-p">Salva e accedi</button>
        </div>
      </form>

      <div class="login-divider"></div>
      
      <form method="POST" action="{{ route('logout') }}" class="login-logout-form">
          @csrf
          <button type="submit" class="login-logout-btn">Annulla ed Esci</button>
      </form>

    </div>
  </div>
</x-guest-layout>
