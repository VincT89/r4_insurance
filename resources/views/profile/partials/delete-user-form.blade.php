<div x-data="{ confirmingUserDeletion: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">
    <div class="u-mb-md u-text-muted u-text-sm">
        Puoi eliminare un account soltanto se non ha attività o dati collegati. Per gli account già utilizzati, un amministratore può disattivare l’accesso conservando lo storico.
    </div>
    @if($lastActiveAdministrator)
        <div class="u-alert-info" role="status">
            Non puoi eliminare questo account perché è l’unico amministratore attivo. Crea o attiva prima un altro amministratore.
        </div>
    @elseif($retainedHistory)
        <div class="u-alert-info" role="status">
            Questo account ha attività o dati collegati. Chiedi a un amministratore di disattivarlo: i documenti e l’attribuzione delle attività verranno conservati.
        </div>
    @else
        <button type="button" class="btn btn-g btn-danger-outline" @click="confirmingUserDeletion = true"
                :aria-expanded="confirmingUserDeletion.toString()" aria-controls="delete-account-confirmation">
            Elimina account
        </button>

    <div id="delete-account-confirmation" x-show="confirmingUserDeletion" class="u-mt-md u-pt-md u-border-t" x-cloak>
    <form method="post" action="{{ route('profile.destroy') }}">
        @csrf
        @method('delete')

        <div class="u-mb-sm u-text-strong">Sei sicuro di voler eliminare l'account?</div>
        
        <div class="form-row full">
            <x-form-group label="Password" name="password" required>
                <x-password-input id="delete-account-password" name="password" :class="$errors->getBag('userDeletion')->has('password') ? 'is-invalid' : ''" placeholder="La tua password per confermare" required autocomplete="current-password" />
                @error('password', 'userDeletion')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </x-form-group>
        </div>

        <div class="u-flex u-gap-sm u-mt-md">
            <button type="button" class="btn btn-g" @click="confirmingUserDeletion = false">Annulla</button>
            <button type="submit" class="btn btn-p btn-danger">Conferma ed Elimina</button>
        </div>
    </form>
    </div>
    @endif
</div>
