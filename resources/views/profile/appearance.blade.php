<x-app-layout title="Aspetto">
    <x-page-header :meta="auth()->user()->email">
        <x-slot:title><strong>Aspetto</strong></x-slot:title>
    </x-page-header>
    @include('profile.partials.settings-navigation')
    <p class="appearance-intro">Personalizza i colori del tuo account. Le scelte ti seguono su ogni dispositivo e non cambiano l’aspetto degli altri utenti.</p>
    <p class="appearance-intro">Anche il login usa questi colori: in quel browser conserva l’ultimo tema utilizzato. Su un nuovo browser il tuo tema viene caricato dopo l’accesso.</p>
    <p class="appearance-intro">L’anteprima aggiorna questa pagina. Il testo si adatta automaticamente quando il colore preferito non è abbastanza leggibile sullo sfondo scelto. I colori degli avvisi mantengono il loro significato.</p>
    <form method="POST" action="{{ route('appearance.update') }}" data-appearance-editor data-defaults="{{ json_encode($defaults) }}" data-saved="{{ json_encode($colors) }}">
        @csrf
        @method('PATCH')
        <div class="appearance-grid">
            @foreach($fields as $group => $items)
                <x-form-section :title="$group" class="appearance-group">
                    @foreach($items as $key => $field)
                        <div class="appearance-field">
                            <label for="color-{{ $key }}">{{ $field['label'] }}</label>
                            <div class="appearance-inputs">
                                <input type="color" value="{{ $colors[$key] }}" data-color-picker="{{ $key }}" aria-label="Scegli il colore: {{ $field['label'] }}">
                                <input id="color-{{ $key }}" class="form-in" name="colors[{{ $key }}]" data-color-value="{{ $key }}" value="{{ old('colors.'.$key, $colors[$key]) }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" required spellcheck="false" autocomplete="off" aria-describedby="color-format">
                            </div>
                        </div>
                    @endforeach
                </x-form-section>
            @endforeach
        </div>
        <p id="color-format" class="appearance-help">Scegli dal selettore oppure inserisci un codice colore di sei caratteri, preceduto da #.</p>
        <x-panel title="Anteprima tabella" class="appearance-preview">
            <p>Esempio dimostrativo, senza dati reali.</p>
            <div class="appearance-preview-scroll">
                <table class="t-table">
                    <thead><tr><th scope="col">Cliente</th><th scope="col">Polizza</th><th scope="col">Scadenza</th></tr></thead>
                    <tbody><tr><td class="name-col">Cliente dimostrativo</td><td>Polizza dimostrativa</td><td>Da definire</td></tr></tbody>
                </table>
            </div>
        </x-panel>
        <div class="appearance-actions">
            <button type="submit" class="btn btn-p">Salva aspetto</button>
            <button type="button" class="btn btn-g" data-appearance-cancel>Annulla modifiche</button>
            <button type="button" class="btn btn-g" data-appearance-defaults>Prova colori iniziali</button>
            <p role="status" aria-live="polite" data-appearance-status>Colori salvati.</p>
        </div>
    </form>
    <form method="POST" action="{{ route('appearance.destroy') }}" class="appearance-reset">
        @csrf
        @method('DELETE')
        <button class="btn btn-g" type="submit">Ripristina e salva i colori iniziali</button>
    </form>
</x-app-layout>
