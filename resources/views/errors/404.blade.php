<x-guest-layout title="Pagina non trovata — R4 Insurance">
    <style>
        .error-page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: var(--bg);
            color: var(--text);
        }

        .error-card {
            width: min(100%, 620px);
            padding: clamp(28px, 7vw, 56px);
            border: 1px solid var(--line);
            border-radius: var(--r2);
            background: var(--bg1);
            box-shadow: var(--shadow-md);
        }

        .error-code {
            margin: 0 0 12px;
            color: var(--accent);
            font: 700 12px/1 var(--mono);
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .error-title {
            margin: 0;
            font-size: clamp(34px, 8vw, 54px);
            line-height: 1.05;
            letter-spacing: -.04em;
        }

        .error-copy {
            max-width: 520px;
            margin: 18px 0 28px;
            color: var(--text2);
            font-size: 16px;
            line-height: 1.65;
        }

        .error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
    </style>

    <main class="error-page">
        <section class="error-card" aria-labelledby="error-title">
            <p class="error-code">Errore 404</p>
            <h1 id="error-title" class="error-title">Pagina non trovata</h1>
            <p class="error-copy">
                L’indirizzo potrebbe essere errato oppure la pagina potrebbe essere stata spostata.
                Torna a un’area disponibile e riprova.
            </p>
            <div class="error-actions">
                <a href="{{ url('/') }}" class="btn btn-p">Torna alla pagina iniziale</a>
                <a href="{{ route('legal.privacy-policy') }}" class="btn btn-g">Privacy Policy</a>
            </div>
        </section>
    </main>
</x-guest-layout>
