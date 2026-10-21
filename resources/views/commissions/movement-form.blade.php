<x-panel :title="$formTitle" padded>
    <form method="POST" action="{{ route('commissions.record',[$title,$kind]) }}">
        @csrf<input type="hidden" name="version" value="{{ $commission->version }}"><input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
        <x-form-section :title="$kind==='settlement' ? 'Liquidazione ricevuta o compensata' : 'Conferma ricevuta'">
            <p class="u-text-meta u-mb-md">{{ $kind==='settlement' ? 'Registra soltanto la provvigione effettivamente liquidata a favore dell’agenzia, anche in parte, con riferimento alla contabile o al rendiconto. Questa operazione non modifica l’incasso del premio.' : 'Registra l’importo confermato dal mandante. Puoi registrare più conferme parziali, fino alla provvigione prevista.' }}</p>
            <div class="form-row">
                <x-form-group :label="$kind==='settlement' ? 'Data liquidazione' : 'Data conferma'" name="effective_on" :for="$kind.'-date'" required><input class="form-in" name="effective_on" type="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></x-form-group>
                <x-form-group label="Importo (€)" name="amount" :for="$kind.'-amount'" required><input class="form-in" name="amount" type="number" min="0.01" step="0.01" max="{{ \App\Support\Money::decimal($limit) }}" value="{{ \App\Support\Money::decimal($limit) }}" required></x-form-group>
                <x-form-group label="Riferimento documento / rendiconto" name="reference" :for="$kind.'-reference'" required><input class="form-in" name="reference" maxlength="150" required></x-form-group>
                <x-form-group label="Causale" name="reason" :for="$kind.'-reason'" required><textarea class="form-ta" name="reason" rows="2" minlength="5" maxlength="1000" required></textarea></x-form-group>
            </div>
        </x-form-section>
        <label class="email-review-confirm"><input type="checkbox" name="confirmed" value="1" required> Ho controllato importo, data e riferimento del documento.</label>
        <div class="insurance-form-actions"><button class="btn btn-p">{{ $kind==='settlement' ? 'Registra liquidazione' : 'Registra maturazione' }}</button></div>
    </form>
</x-panel>
