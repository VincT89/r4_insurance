<x-app-layout title="Nucleo familiare">
    <x-page-header><x-slot:title>Nucleo familiare</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab' => 'network']) }}">{{ $client->name }}</a></x-slot:actions></x-page-header>
    <x-panel padded>
        <form method="POST" action="{{ $household->exists ? route('clients.households.update', [$client, $household]) : route('clients.households.store', $client) }}">
            @csrf @if($household->exists) @method('PATCH') @endif
            <x-form-section title="Nucleo familiare">
            <x-form-group label="Nome del nucleo" name="name" required><input id="field-name" name="name" class="form-in" value="{{ old('name', $household->name) }}" maxlength="255" required></x-form-group>
            </x-form-section>
            <x-form-section title="Componenti e ruoli"><p class="u-text-meta u-mb-md">Seleziona le persone fisiche già censite e indica, se utile, il ruolo nel nucleo.</p>
                @php $selected = array_map('strval', old('members', $household->exists ? $household->members->pluck('id')->all() : [$client->id])); @endphp
                @foreach($candidates as $candidate)
                    <div class="form-row">
                        <label class="insurance-checkbox"><input type="checkbox" name="members[]" value="{{ $candidate->id }}" @checked(in_array((string)$candidate->id, $selected, true))> {{ $candidate->name }}</label>
                        <x-form-group :label="'Ruolo di '.$candidate->name" :name="'roles.'.$candidate->id"><input id="field-{{ Str::slug('roles.'.$candidate->id) }}" name="roles[{{ $candidate->id }}]" class="form-in" maxlength="150" value="{{ old('roles.'.$candidate->id, $household->exists ? $household->members->firstWhere('id', $candidate->id)?->pivot->role : '') }}"></x-form-group>
                    </div>
                @endforeach
                @error('members')<p class="form-error">{{ $message }}</p>@enderror
            </x-form-section>
            <x-form-group label="Note sul nucleo" name="notes"><textarea id="field-notes" name="notes" class="form-ta" rows="3" maxlength="10000">{{ old('notes', $household->notes) }}</textarea></x-form-group>
            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab' => 'network']) }}">Annulla</a><button class="btn btn-p" type="submit">Salva nucleo</button></div>
        </form>
    </x-panel>
</x-app-layout>
