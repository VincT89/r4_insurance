<div class="communication-batches" data-guide-batches="{{ ($batchLimited??false) ? 'limited' : (filled(request('_table_filters')) ? 'filtered' : (count($batches) ? 'ready' : 'empty')) }}">
    @if($batchLimited??false)
        <p>Più di 500 operazioni nel periodo. Usa «Filtra elenco» per scegliere un’agenzia o restringere le date.</p>
    @elseif(filled(request('_table_filters')))
        <p>È attivo un filtro sulle singole righe. Usa la selezione nell’elenco sottostante.</p>
    @else
    @forelse($batches as $batch)
        @php($batchAgency=$batch['agency'])
        <form method="POST" action="{{ route($batchRoute,$batchAgency) }}" x-data="{busy:false}" @submit="busy=true">
            @csrf
            <input type="hidden" name="all_matching" value="1">
            @foreach($filters as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <div>
                <strong>{{ $batchAgency->name }}</strong>
                <p class="form-help">{{ $batch['count'] }} {{ $batch['count']===1 ? $batchSingular : $batchPlural }} · {{ $batchAgency->email ?: 'Email agenzia da completare' }}</p>
            </div>
            <button class="btn btn-p" type="submit" aria-label="Prepara email per {{ $batchAgency->name }}" @disabled($batchAgency->status!=='active') :disabled="busy || {{ $batchAgency->status==='active' ? 'false' : 'true' }}" :aria-busy="busy" x-text="busy ? 'Preparo…' : 'Prepara email'">Prepara email</button>
        </form>
    @empty
        <p>Nessuna comunicazione da preparare nel periodo e con i filtri scelti.</p>
        <a class="btn btn-g" href="#communication-search">Cambia periodo o filtri</a>
    @endforelse
    @endif
</div>
