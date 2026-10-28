<x-guest-layout title="Accesso non consentito — R4 Insurance">
    <style>
        .access-denied-page { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .access-denied-panel { width: min(100%, 600px); padding: clamp(24px, 5vw, 40px); border: 1px solid var(--accent); border-radius: var(--r2); background: var(--bg1); box-shadow: var(--shadow-md); }
        .access-denied-title { margin: 0 0 20px; color: var(--accent); font-size: clamp(26px, 5vw, 34px); line-height: 1.2; }
        .access-denied-copy { margin: 0 0 24px; color: var(--text); line-height: 1.6; }
    </style>
    <main class="access-denied-page">
        <section class="access-denied-panel" aria-labelledby="access-denied-title">
            <h1 id="access-denied-title" class="access-denied-title">Accesso non consentito</h1>
            <p class="access-denied-copy">Il tuo account non è autorizzato a questa operazione. Se ti serve accedere, contatta l’amministratore dell’agenzia.</p>
            <a href="{{ url('/') }}" class="btn btn-p">Torna alla pagina iniziale</a>
        </section>
    </main>
</x-guest-layout>
