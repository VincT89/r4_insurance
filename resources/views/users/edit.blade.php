<x-app-layout title="Modifica Utente">
    <x-page-header
        
    >
    <x-slot:title><strong>Modifica</strong> utente</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="btn btn-g">Utenti</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('users.update', $user) }}" method="POST">
            @csrf
            @include('users.partials.permissions')
            @method('PATCH')
            
            <x-form-section title="Dati dell’utente">
            <div class="form-row">
                <x-form-group label="Nome completo" name="name" required>
                    <input name="name" class="form-in @error('name') is-invalid @enderror"
                           value="{{ old('name', $user->name) }}" required autocomplete="name">
                </x-form-group>
                <x-form-group label="Email" name="email" required>
                    <input type="email" name="email" class="form-in @error('email') is-invalid @enderror"
                           value="{{ old('email', $user->email) }}" required autocomplete="email">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Ruolo" name="role" required>
                    @if($lastActiveAdministrator)
                        <input type="hidden" name="role" value="{{ $user->role->value }}">
                    @endif
                    <select name="role" class="form-sel @error('role') is-invalid @enderror" required @disabled($lastActiveAdministrator)>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" {{ old('role', $user->role->value) == $role->value ? 'selected' : '' }}>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Telefono (opzionale)" name="phone">
                    <input name="phone" class="form-in @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $user->phone) }}">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Ambito di competenza (opzionale)" name="primary_specialization">
                    <input name="primary_specialization" class="form-in @error('primary_specialization') is-invalid @enderror"
                           value="{{ old('primary_specialization', $user->primary_specialization) }}">
                </x-form-group>
                <x-form-group label="Stato Account" name="status" required>
                    @if($lastActiveAdministrator)
                        <input type="hidden" name="status" value="active">
                    @endif
                    <select name="status" class="form-sel @error('status') is-invalid @enderror" required @disabled($lastActiveAdministrator)>
                        <option value="active" {{ old('status', $user->status) === 'active' ? 'selected' : '' }}>Attivo</option>
                        <option value="inactive" {{ old('status', $user->status) === 'inactive' ? 'selected' : '' }}>Inattivo (sospeso)</option>
                    </select>
                </x-form-group>
            </div>

            @if($lastActiveAdministrator)
                <div class="u-alert-info u-mt-md" role="status">
                    Questo account è l’unico amministratore attivo. Ruolo e stato restano bloccati finché non viene creato o attivato un altro amministratore.
                </div>
            @endif

            </x-form-section>
            <div class="insurance-form-actions">
                <a href="{{ route('users.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Aggiorna utente</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
