<x-panel title="Sostituzioni e coperture aggiuntive">
    <x-slot:headerActions>
        <div class="dashboard-section-actions">
        @if($policy->editableBy(auth()->user()))
            @if(in_array($policy->status,['active','renewed','expired','suspended','ceased'],true))<a class="btn btn-g btn-sm" href="{{ route('clients.policies.changes.create',[$client,$policy,'kind'=>'replacement']) }}">Sostituisci polizza</a>@endif
            @if(in_array($policy->status,['active','renewed'],true))<a class="btn btn-p btn-sm" href="{{ route('clients.policies.changes.create',[$client,$policy,'kind'=>'addition']) }}">Aggiungi coperture</a>@endif
        @endif
        </div>
    </x-slot:headerActions>
    <div class="client-table-scroll"><table class="t-table"><thead><tr><th>Operazione</th><th>Riferimento</th><th>Polizza precedente</th><th>Polizza risultante</th><th>Decorrenza</th><th>Scadenza</th><th>Premio concordato</th><th>Operatore</th><th>Azioni</th></tr></thead><tbody>
        @forelse($changes as $change)<tr><td>{{ \App\Models\InsurancePolicyChange::KINDS[$change->kind] }}</td><td>{{ $change->reference }}</td><td>{{ $change->snapshot['before']['policy']['number'] }}</td><td>{{ $change->snapshot['after']['policy']['number'] }}</td><td>{{ $change->effective_on->format('d/m/Y') }}</td><td><x-status-text :alert="$change->expires_on->lt(today())">{{ $change->expires_on->format('d/m/Y') }}</x-status-text></td><td>{{ \App\Support\Money::format($change->premium_cents) }}</td><td>{{ $change->author?->name }}</td><td><a class="btn btn-g btn-sm" href="{{ route('clients.policy-changes.show',[$client,$change]) }}">Storico e moduli</a></td></tr>
        @empty<tr><td colspan="9">Nessuna sostituzione o aggiunta di coperture registrata.</td></tr>@endforelse
    </tbody></table></div>{{ $changes->links() }}
</x-panel>
