<x-guest-layout title="Imposta Password — R4 Insurance">
  <x-auth-shell title="Imposta la tua password" description="Scegli una password personale per completare il primo accesso.">
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
  </x-auth-shell>
</x-guest-layout>
