<!doctype html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>Postazione firma R4</title><x-brand-icons />@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="app-body r4-workbench station-terminal-body" style="{{ \App\Support\Appearance::style(null) }}">
<main class="station-terminal" data-signing-terminal data-current-url="{{ route('signing-terminal.current') }}" data-home="{{ route('signing-terminal.index') }}" data-terminal-state="{{ $station?->usable() ? 'ready' : ($station?->status==='pending' && $station->pairing_expires_at->isFuture() ? 'pairing' : 'unpaired') }}">
    <h1>Postazione firma</h1>
    @if(auth()->check())<p class="station-browser-notice">Questo browser ha anche un accesso operatore. Per il cliente usa il browser del tablet senza accessi operatore al gestionale.</p>@endif
    @if($station?->usable())
        <h2>{{ $station->name }}</h2><p>In attesa dei documenti.</p><p>L’operatore invierà qui la pratica da leggere e firmare.</p>
    @elseif($station?->status==='pending' && $station->pairing_expires_at->isFuture())
        <p>Comunica questo codice all’operatore.</p><p class="station-pairing-code" aria-label="Codice di abbinamento">{{ $station->pairing_code }}</p>
        <p>Dal PC, apri Impostazioni e poi Postazioni firma. Inserisci il codice e il nome di questo tablet.</p>
        <p>Il codice scade alle {{ $station->pairing_expires_at->format('H:i') }}.</p>
    @else
        <p>{{ $station ? 'Il collegamento è scaduto o è stato revocato. Richiedi un nuovo codice.' : 'Collega questo tablet al gestionale per ricevere i documenti da firmare.' }}</p>
        <form method="post" action="{{ route('signing-terminal.pair') }}">@csrf<button class="btn btn-p">Collega questa postazione</button></form>
    @endif
    <p data-terminal-error class="signing-error" role="status" hidden></p>
    <noscript><p>Per usare la postazione attiva JavaScript nel browser.</p></noscript>
</main></body></html>
