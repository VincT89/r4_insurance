@if($coverageTitle->allowance_cents > 0 && $coverageTitle->affects_coverage && !$coverageTitle->cancelled_at)
<div class="allowance-coverage" id="allowance-coverage-{{ $coverageTitle->id }}">
    <h3>Copertura del titolo #{{ $coverageTitle->id }}</h3>
    <p class="u-text-meta">Periodo {{ $coverageTitle->coverage_from->format('d/m/Y') }} – {{ $coverageTitle->coverage_to->format('d/m/Y') }}.</p>
    @if($coverageTitle->coverage_confirmation_valid)
        <p>Copertura confermata da {{ $coverageTitle->coverageConfirmedBy?->name ?: 'Operatore registrato nello storico' }} il {{ $coverageTitle->coverage_confirmed_at->format('d/m/Y H:i') }}.</p>
        <p class="u-whitespace-pre-wrap">{{ $coverageTitle->coverage_confirmation_note }}</p>
        @can('ledger.manage')@can('updateRegistry',$coverageTitle->policy->client)
            <details class="u-mt-md"><summary>Annulla conferma copertura</summary>
                <form method="POST" action="{{ route('titles.allowance-coverage',$coverageTitle) }}" class="u-mt-md">
                    @csrf<input type="hidden" name="coverage_action" value="revoke"><input type="hidden" name="coverage_fingerprint" value="{{ $coverageTitle->coverageConfirmationFingerprint() }}"><input type="hidden" name="allowance_origin" value="{{ $allowanceOrigin ?? 'title' }}">
                    <x-form-group label="Motivo dell’annullamento" name="coverage_note" :for="'revoke-coverage-'.$coverageTitle->id" required><textarea class="form-ta" name="coverage_note" rows="2" minlength="5" maxlength="1000" required></textarea></x-form-group>
                    <div class="insurance-form-actions"><button class="btn btn-g">Annulla conferma</button></div>
                </form>
            </details>
        @endcan @endcan
    @elseif($coverageTitle->balance_cents > 0)
        <p>Copertura da confermare dopo il saldo del titolo. Residuo {{ \App\Support\Money::format($coverageTitle->balance_cents) }}.</p>
    @else
        <p><strong>Copertura da confermare.</strong> Il titolo è saldato con abbuono: la copertura non si aggiorna automaticamente.</p>
        @if($coverageTitle->coverage_confirmed_at)<p class="u-text-meta">La precedente conferma del {{ $coverageTitle->coverage_confirmed_at->format('d/m/Y H:i') }} non è più valida perché i movimenti del titolo sono cambiati.</p>@endif
        @can('ledger.manage')@can('updateRegistry',$coverageTitle->policy->client)
            <form method="POST" action="{{ route('titles.allowance-coverage',$coverageTitle) }}" class="u-mt-md" data-allowance-coverage-form>
                @csrf<input type="hidden" name="coverage_action" value="confirm"><input type="hidden" name="coverage_fingerprint" value="{{ $coverageTitle->coverageConfirmationFingerprint() }}"><input type="hidden" name="allowance_origin" value="{{ $allowanceOrigin ?? 'title' }}">
                <x-form-group label="Riferimento della conferma della compagnia o agenzia" name="coverage_note" :for="'confirm-coverage-'.$coverageTitle->id" required><textarea class="form-ta" name="coverage_note" rows="2" minlength="5" maxlength="1000" required>{{ old('coverage_fingerprint') === $coverageTitle->coverageConfirmationFingerprint() && is_scalar(old('coverage_note')) ? old('coverage_note') : '' }}</textarea></x-form-group>
                <div class="insurance-form-actions"><button class="btn btn-p">Conferma copertura</button></div>
            </form>
        @endcan @endcan
    @endif
</div>
@endif
