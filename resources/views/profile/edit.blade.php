<x-app-layout title="Profilo">
  <x-page-header :meta="auth()->user()->email" >
    <x-slot:title><strong>Profilo</strong></x-slot:title>
</x-page-header>
  @include('profile.partials.settings-navigation')

  <div class="g-2col u-gap-md">

    {{-- Aggiorna informazioni --}}
    <x-panel title="Informazioni personali" :padded="true">
      @include('profile.partials.update-profile-information-form')
    </x-panel>

    {{-- Cambia password --}}
    <x-panel title="Cambia password" :padded="true">
      @include('profile.partials.update-password-form')
    </x-panel>

  </div>

  {{-- Elimina account — solo se non sei l'unico admin --}}
  <div class="u-mt-md">
    @can('system.admin')
    <x-panel title="Zona pericolosa" :padded="true">
      @include('profile.partials.delete-user-form')
    </x-panel>
    @endcan
  </div>
</x-app-layout>
