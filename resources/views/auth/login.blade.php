<x-guest-layout title="Accedi — R4 Insurance">
  <x-auth-shell title="Accedi al gestionale">
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
  </x-auth-shell>
</x-guest-layout>
