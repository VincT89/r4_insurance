<!doctype html>
<html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>Firma · {{ $source->original_name }}</title><x-brand-icons />
@vite(['resources/css/app.css','resources/js/app.js'])
</head><body class="app-body signing-body" style="{{ \App\Support\Appearance::style(auth()->user()?->appearance) }}">
<main class="tablet-signing" data-tablet-signing data-return="{{ $returnUrl }}">
    <header class="tablet-signing-header">
        <div><h1>Firma il documento</h1><p>{{ $client->name }}@if($policy) · Polizza {{ $policy->number }}@endif</p><p class="signing-filename">{{ $source->original_name }}</p></div>
        <a class="btn btn-g" href="{{ $returnUrl }}">Torna alla {{ $policy ? 'polizza' : 'pratica' }}</a>
    </header>
    <div class="tablet-signing-layout">
        <section class="signing-document" aria-label="Documento da leggere e firmare">
            <div class="signing-placement-tools">
                <button class="btn btn-g" type="button" data-place-toggle aria-pressed="false">Scegli punto firma</button>
                <button class="btn btn-g" type="button" data-place-bottom>Firma in fondo all’ultima pagina</button>
                <span role="status" data-placement-status>Nessun punto firma indicato.</span>
            </div>
            <p class="signing-instruction" data-placement-help>Leggi il documento. Scegli dove inserire la firma oppure usa lo spazio in fondo all’ultima pagina e controlla che sia libero.</p>
            <x-pdf-reader :source="$source->downloadUrl()" />
            <div class="signing-placement-list" data-placement-list aria-label="Punti firma selezionati"></div>
        </section>
        <form class="signing-controls" method="post" action="{{ route('attachments.sign.store',$source) }}" data-signing-form>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <h2>Firmatario</h2>
            <label for="signer-name">Nome e cognome</label>
            <input class="form-in" id="signer-name" name="signer_name" value="{{ $signer }}" maxlength="150" autocomplete="off" required>
            @if($client->client_type === 'company' || filled($role))
                <label for="signer-role">Qualifica{{ $client->client_type === 'company' ? '' : ' (facoltativa)' }}</label>
                <input class="form-in" id="signer-role" name="signer_role" value="{{ $role }}" maxlength="150" @required($client->client_type === 'company')>
            @endif
            <h2>Disegna la firma</h2>
            <p>Usa il dito o il pennino nel riquadro. La stessa firma verrà inserita nei punti indicati di questo documento.</p>
            <canvas class="signing-pad" data-signing-pad width="900" height="450" tabindex="0" aria-label="Riquadro firma" aria-describedby="signing-keyboard-help"></canvas>
            <div class="signing-ink-actions"><button class="btn btn-g" type="button" data-undo-ink>Annulla ultimo tratto</button><button class="btn btn-g" type="button" data-clear-ink>Cancella firma</button></div>
            <details class="signing-keyboard"><summary>Uso con tastiera</summary><p id="signing-keyboard-help">Frecce: sposta la penna. Spazio: inizia o termina il tratto. Esc: termina il tratto.</p></details>
            @if($needsNote)
                <label for="signing-note">Motivo della nuova copia</label><textarea class="form-ta" id="signing-note" name="note" minlength="10" maxlength="2000" required rows="2"></textarea>
            @endif
            <label class="signing-confirm"><input type="checkbox" name="identity_confirmed" value="1" required><span>L’operatore conferma di aver verificato l’identità del firmatario presente.</span></label>
            <label class="signing-confirm"><input type="checkbox" name="consent" value="1" required><span>{{ $consentText }}</span></label>
            <p class="signing-error" role="alert" data-signing-error hidden></p>
            <button class="btn btn-p signing-save" type="submit" data-save-signature>Conferma e salva firma</button>
            <p class="signing-disclosure">Firma grafica raccolta nel gestionale. Questa modalità non dichiara una firma elettronica avanzata o qualificata. L’originale e la copia con firma restano nello storico.</p>
            <noscript><p>Per firmare qui attiva JavaScript. Puoi tornare alla polizza e caricare una copia firmata.</p></noscript>
        </form>
    </div>
</main>
</body></html>
