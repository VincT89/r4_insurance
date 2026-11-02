<x-form-section title="Titoli da creare">
    <x-form-group label="Gestione dei titoli" name="titles_mode"><select id="field-titles-mode" name="titles_mode" class="form-sel"><option value="automatic" @selected(old('titles_mode','automatic')==='automatic')>Genera dal frazionamento del contratto</option><option value="manual" @selected(old('titles_mode')==='manual')>Inserirò i titoli manualmente</option></select></x-form-group>
    <p data-contract-schedule-status role="status" aria-live="polite">Completa date, premio, frazionamento e metodo di pagamento per vedere i titoli.</p>
    <div data-contract-schedule class="contract-schedule" hidden></div>
    <input type="hidden" name="schedule_fingerprint" value="" data-contract-fingerprint>
    <button class="btn btn-g btn-sm" type="button" data-refresh-contract-schedule>Ricalcola titoli</button>
</x-form-section>
@if($selectedQuote)
    <div class="contract-quote-differences" data-contract-differences hidden>
        <h2 class="workspace-section-title">Differenze rispetto al preventivo</h2>
        <ul data-contract-difference-list></ul>
        <label class="contract-confirm"><input type="checkbox" name="quote_changes_confirmed" value="1"> Ho verificato le differenze: confermo i dati del contratto.</label>
    </div>
@endif
<label class="contract-confirm"><input type="checkbox" name="contract_reviewed" value="1" required> Ho controllato il contraente, i dati della polizza e i titoli da creare.</label>
<div class="invalid-feedback" role="alert" tabindex="-1" data-contract-errors hidden></div>
