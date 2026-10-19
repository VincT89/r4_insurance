<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="form-row full">
        <x-form-group label="Password attuale" name="current_password" required>
            <x-password-input id="update_password_current_password" name="current_password" :class="$errors->getBag('updatePassword')->has('current_password') ? 'is-invalid' : ''" required autocomplete="current-password" />
            @error('current_password', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </x-form-group>
    </div>

    <div class="form-row full">
        <x-form-group label="Nuova password" name="password" required>
            <x-password-input id="update_password_password" name="password" :class="$errors->getBag('updatePassword')->has('password') ? 'is-invalid' : ''" required autocomplete="new-password" />
            @error('password', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </x-form-group>
    </div>

    <div class="form-row full">
        <x-form-group label="Conferma password" name="password_confirmation" required>
            <x-password-input id="update_password_password_confirmation" name="password_confirmation" :class="$errors->getBag('updatePassword')->has('password_confirmation') ? 'is-invalid' : ''" required autocomplete="new-password" />
            @error('password_confirmation', 'updatePassword')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </x-form-group>
    </div>

    <div class="modal-ft u-mt-md">
        <button type="submit" class="btn btn-p">Aggiorna Password</button>
        @if (session('status') === 'password-updated')
            <span class="u-text-sm u-text-green u-ml-sm">Aggiornata.</span>
        @endif
    </div>
</form>
