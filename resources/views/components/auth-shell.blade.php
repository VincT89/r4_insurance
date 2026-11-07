@props(['title','description'=>null])
<main class="auth-shell">
    <header class="auth-brand"><img src="{{ asset('images/agency-signature.svg') }}" alt="" width="85" height="45"><span>{{ config('branding.wordmark') }}</span></header>
    <h1>{{ $title }}</h1>
    @if($description)<p class="auth-description">{{ $description }}</p>@endif
    @if(session('status'))<div class="flash flash-error" role="alert">{{ session('status') }}</div>@endif
    {{ $slot }}
</main>
