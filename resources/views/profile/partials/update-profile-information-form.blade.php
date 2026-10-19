<form method="post" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <div class="form-row full">
        <x-form-group label="Nome completo" name="name" required>
            <input id="name" name="name" type="text" class="form-in @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" />
        </x-form-group>
    </div>

    <div class="form-row full">
        <x-form-group label="Email" name="email" required>
            <input id="email" name="email" type="email" class="form-in @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required autocomplete="username" />
        </x-form-group>
    </div>

    <div class="form-row">
        <x-form-group label="Telefono" name="phone">
            <input id="phone" name="phone" type="text" class="form-in @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}" placeholder="+39 ..." />
        </x-form-group>
        <x-form-group label="Ambito di competenza" name="primary_specialization">
            <input id="primary_specialization" name="primary_specialization" type="text" class="form-in @error('primary_specialization') is-invalid @enderror" value="{{ old('primary_specialization', $user->primary_specialization) }}" placeholder="Es. Gestione assicurativa" />
        </x-form-group>
    </div>

    <div class="modal-ft u-mt-md">
        <button type="submit" class="btn btn-p">Salva</button>
        @if (session('status') === 'profile-updated')
            <span class="u-text-sm u-text-green u-ml-sm">Salvato.</span>
        @endif
    </div>
</form>