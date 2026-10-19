<x-form-section title="Ruoli e permessi">
    @foreach($roles as $availableRole)
        <p class="role-permission-description"><strong>{{ $availableRole->label() }}</strong>: {{ $availableRole->description() }}</p>
    @endforeach
</x-form-section>
