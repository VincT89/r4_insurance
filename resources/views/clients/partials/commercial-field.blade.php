@if(auth()->user()->canManageSystem())
    <div class="form-row full">
        <x-form-group label="Operatore di riferimento" name="commercial_user_id">
            <select id="field-commercial-user-id" name="commercial_user_id" class="form-sel @error('commercial_user_id') is-invalid @enderror">
                <option value="">Nessun operatore associato</option>
                @foreach($commercialUsers as $commercialUser)
                    <option value="{{ $commercialUser->id }}" @selected(old('commercial_user_id', $client->commercial_user_id ?? null) == $commercialUser->id)>
                        {{ $commercialUser->name }}{{ $commercialUser->status !== 'active' || !$commercialUser->isOperator() ? ' (non disponibile per nuove assegnazioni)' : '' }}
                    </option>
                @endforeach
            </select>
            <p class="u-text-meta">Il referente segue il cliente; tutti gli operatori possono accedere alla scheda.</p>
        </x-form-group>
    </div>
@endif
