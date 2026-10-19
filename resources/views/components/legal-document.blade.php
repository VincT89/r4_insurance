@props([
    'title',
    'description',
    'updated',
])

<x-guest-layout :title="$title.' - '.config('branding.name')">
    <style>
        .legal-shell {
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
        }

        .legal-header,
        .legal-footer,
        .legal-main {
            width: min(100% - 40px, 980px);
            margin: 0 auto;
        }

        .legal-header {
            min-height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 1px solid var(--line);
        }

        .legal-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            font-weight: 700;
            text-decoration: none;
        }

        .legal-brand img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .legal-nav {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 18px;
        }

        .legal-nav a,
        .legal-footer a,
        .legal-content a {
            color: var(--accent);
            text-underline-offset: 3px;
        }

        .legal-nav a {
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .legal-nav a[aria-current="page"] {
            text-decoration: underline;
        }

        .legal-main {
            padding: 48px 0 64px;
        }

        .legal-card {
            padding: clamp(24px, 5vw, 56px);
            background: var(--bg1);
            border: 1px solid var(--line);
            border-radius: var(--r2);
            box-shadow: var(--shadow-md);
        }

        .legal-kicker {
            margin: 0 0 10px;
            color: var(--accent);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .legal-card h1 {
            margin: 0;
            font-size: clamp(34px, 6vw, 54px);
            line-height: 1.05;
            letter-spacing: -.04em;
        }

        .legal-lead {
            max-width: 760px;
            margin: 20px 0 0;
            color: var(--text2);
            font-size: 17px;
            line-height: 1.65;
        }

        .legal-updated {
            margin: 18px 0 0;
            color: var(--text3);
            font-size: 12px;
        }

        .legal-content {
            margin-top: 42px;
            color: var(--text2);
            font-size: 15px;
            line-height: 1.75;
        }

        .legal-content section + section {
            margin-top: 34px;
            padding-top: 34px;
            border-top: 1px solid var(--line);
        }

        .legal-content h2 {
            margin: 0 0 14px;
            color: var(--text);
            font-size: 22px;
            line-height: 1.25;
        }

        .legal-content h3 {
            margin: 22px 0 8px;
            color: var(--text);
            font-size: 16px;
        }

        .legal-content p,
        .legal-content ul {
            margin: 0 0 14px;
        }

        .legal-content ul {
            padding-left: 22px;
        }

        .legal-content li + li {
            margin-top: 7px;
        }

        .legal-content strong {
            color: var(--text);
        }

        .legal-footer {
            padding: 28px 0 42px;
            border-top: 1px solid var(--line);
            color: var(--text3);
            font-size: 12px;
            line-height: 1.7;
        }

        @media (max-width: 680px) {
            .legal-header {
                align-items: flex-start;
                flex-direction: column;
                padding: 18px 0;
            }

            .legal-nav {
                justify-content: flex-start;
            }

            .legal-main {
                padding-top: 28px;
            }
        }
    </style>

    <div class="legal-shell">
        <header class="legal-header">
            <a class="legal-brand" href="{{ url('/') }}">
                <img class="legal-signature" src="{{ asset('images/agency-signature.svg') }}" alt="" width="115" height="50"><span>{{ config('branding.wordmark') }}</span>
            </a>

            <nav class="legal-nav" aria-label="Documenti legali">
                <a href="{{ route('legal.privacy-policy') }}"
                   @if(request()->routeIs('legal.privacy-policy')) aria-current="page" @endif>
                    Privacy Policy
                </a>
                <a href="{{ route('legal.terms-of-service') }}"
                   @if(request()->routeIs('legal.terms-of-service')) aria-current="page" @endif>
                    Termini di servizio
                </a>
            </nav>
        </header>

        <main class="legal-main">
            <article class="legal-card">
                <p class="legal-kicker">{{ config('branding.name') }}</p>
                <h1>{{ $title }}</h1>
                <p class="legal-lead">{{ $description }}</p>
                <p class="legal-updated">Ultimo aggiornamento: {{ $updated }}</p>

                <div class="legal-content">
                    {{ $slot }}
                </div>
            </article>
        </main>

        <footer class="legal-footer">
            <strong>Sodano Consulting S.r.l.</strong><br>
            Via Eduardo De Filippo, 4, 70010 Valenzano (BA), Italia<br>
            P. IVA IT08962440726<br>
            <a href="mailto:info@sodanoconsulting.it">info@sodanoconsulting.it</a>
        </footer>
    </div>
</x-guest-layout>
