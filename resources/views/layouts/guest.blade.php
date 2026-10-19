<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? config('branding.name') }}</title>
  <x-brand-icons />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="guest-body app-body" style="{{ \App\Support\Appearance::style(\App\Support\Appearance::forRequest(request())) }}">
  {{ $slot }}
</body>
</html>
