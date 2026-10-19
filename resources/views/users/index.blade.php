<x-app-layout title="Gestione Utenti">
    <x-page-header
        
        :meta="$users->count() . ' totali'"
    >
    <x-slot:title><strong>Utenti</strong></x-slot:title>
        <x-slot:actions>
            <a href="{{ route('users.create') }}" class="btn btn-p">Nuovo utente</a>
        </x-slot:actions>
    </x-page-header>

    @if(session('temp_password'))
        <div class="u-alert-danger u-mb-xl">
            <div class="u-text-accent u-text-strong u-mb-sm">
                Password Rigenerata con Successo
            </div>
            <div class="u-mb-sm u-text-strong">
                Ecco la nuova password per <strong>{{ session('temp_password')['user'] }}</strong>.
            </div>
            <span class="u-text-sm u-text-muted">Mostra questa password all'utente. Non verrà mostrata di nuovo.</span>
            <div
                class="secret-reveal"
                data-secret-reveal
                data-secret="{{ session('temp_password')['password'] }}"
                data-visible="false"
            >
                <span class="secret-reveal-value" data-secret-value aria-label="Password temporanea nascosta">••••••••</span>
                <button type="button" class="btn btn-g btn-sm" data-secret-toggle aria-pressed="false" aria-label="Mostra password temporanea">Mostra</button>
                <button type="button" class="btn btn-g btn-sm" data-secret-copy aria-label="Copia password temporanea">Copia</button>
                <span class="sr-only" data-secret-status role="status" aria-live="polite">Password temporanea nascosta.</span>
            </div>
        </div>
    @endif

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Ruolo</th>
                    <th>Stato</th>
                    <th class="t-actions">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td class="name-col">{{ $user->name }}</td>
                    <td class="mono-col">{{ $user->email }}</td>
                    <td>
                        <div>
                            {{ $user->role->label() }}
                        </div>
                    </td>
                    <td><x-badge :status="$user->status" :label="$user->status_label" /></td>
                    <td class="t-actions">
                        <div class="t-actions-list">
                        <a href="{{ route('users.edit', $user) }}" class="btn btn-g btn-sm" aria-label="Modifica utente {{ $user->name }}">Modifica</a>
                        
                        @if($user->id !== auth()->id())
                            <form action="{{ route('users.reset-password', $user) }}" method="POST" onsubmit="return confirm('Forzare il reset di password e generarne una nuova temporanea per questo utente?')">
                                @csrf
                                <button type="submit" class="btn btn-g btn-sm" aria-label="Reimposta password di {{ $user->name }}">Reimposta password</button>
                            </form>

                            <form action="{{ route('users.toggle-status', $user) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-g btn-sm" aria-label="{{ $user->status === 'active' ? 'Disattiva' : 'Riattiva' }} utente {{ $user->name }}">
                                    {{ $user->status === 'active' ? 'Disattiva' : 'Riattiva' }}
                                </button>
                            </form>
                        @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="u-text-center u-text-muted u-p-xl">Nessun utente trovato</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-panel>
</x-app-layout>
