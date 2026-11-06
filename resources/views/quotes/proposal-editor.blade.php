@php
    $failedAction = (int) old('_proposal_id') === $quote->id ? old('_proposal_action') : null;
    $proposalErrors = $errors->getBag('proposal_'.$quote->id.'_'.$failedAction);
    $proposalOld = fn ($field, $action, $default = '') => $failedAction === $action && is_scalar($value = old($field)) ? $value : $default;
    $approvalForm = $mayWork && $quote->status === 'pending';
    $receiveForm = $mayWork && $quote->status === 'requested';
    $heading = $receiveForm ? 'Registra risposta' : ($approvalForm ? 'Registra approvazione' : 'Dettagli proposta');
    $back = route('quotes.show', $case).'#'.($quote->status==='requested' ? 'risposte-attese' : 'confronto-proposte');
@endphp
<x-panel :title="$heading" padded class="proposal-editor" :id="'proposal-'.$quote->id">
    <x-slot:headerActions><a class="btn btn-g btn-sm" href="{{ $back }}">{{ $receiveForm ? 'Torna alle richieste' : 'Torna al confronto' }}</a></x-slot:headerActions>
    <p><strong>{{ $quote->company->name }} · {{ $quote->agency?->name ?: 'Rapporto diretto' }}</strong></p>
    @if($quote->product)<p>{{ $quote->product }} · {{ $quote->premium_cents !== null ? \App\Support\Money::format($quote->premium_cents) : 'Premio non indicato' }} · {{ $quote->payment_frequency }}</p>@endif
    <p class="u-text-meta">Richiesta del {{ $quote->requested_at->format('d/m/Y') }}@if($quote->reference) · Rif. {{ $quote->reference }}@endif</p>
    @if($proposalErrors->any())<div class="flash flash-error" role="alert"><p>Controlla i dati di questa proposta.</p><ul>@foreach($proposalErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($receiveForm)
        @include('quotes.receive')
    @else
        @if($quote->request_recipient && (($quote->request_recipient['agency_name'] ?? null) !== $quote->agency?->name || ($quote->request_recipient['company_name'] ?? null) !== $quote->company->name))
            <p class="u-text-meta">Richiesta iniziale a {{ $quote->request_recipient['company_name'] }}{{ !empty($quote->request_recipient['agency_name']) ? ' tramite '.$quote->request_recipient['agency_name'] : ' direttamente' }}.</p>
        @endif
        @if($quote->starts_on)<p>Copertura dal {{ $quote->starts_on->format('d/m/Y') }} al {{ $quote->expires_on?->format('d/m/Y') }}.</p>@endif
        @if($quote->valid_until)<p>Proposta valida fino al {{ $quote->valid_until->format('d/m/Y') }}.</p>@endif
        @if($quote->approved_on)<p>Approvata dal cliente il {{ $quote->approved_on->format('d/m/Y') }}.</p>@endif
        @if($quote->approval_note)<p>{{ $quote->approval_note }}</p>@endif
        @if($quote->documents->isNotEmpty())
            <ul class="proposal-documents">@foreach($quote->documents as $document)
                <li>{{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$document->document_category] ?? 'Documento' }} · <a href="{{ $document->openUrl() }}" @if($document->canPreview()) target="_blank" rel="noopener" @endif>{{ $document->original_name }}</a></li>
            @endforeach</ul>
        @endif
        @if($approvalForm)
            <form method="POST" enctype="multipart/form-data" action="{{ route('quotes.change',[$quote,'approve']) }}" class="proposal-form">@csrf
                <div class="form-row">
                    <x-form-group label="Data approvazione" name="approved_on" :for="'approved-on-'.$quote->id" :error-bag="'proposal_'.$quote->id.'_approve'" required>
                        <input class="form-in" type="date" name="approved_on" min="{{ $quote->received_at?->toDateString() }}" max="{{ $quote->valid_until?->lt(today()) ? $quote->valid_until->toDateString() : today()->toDateString() }}"
                            value="{{ $proposalOld('approved_on','approve',$quote->valid_until?->lt(today()) ? '' : today()->toDateString()) }}" required>
                    </x-form-group>
                    <x-form-group label="Prova di approvazione (PDF)" name="files" :for="'approval-files-'.$quote->id" :error-bag="'proposal_'.$quote->id.'_approve'" required>
                        <input class="form-in" type="file" name="files[]" accept="application/pdf,.pdf" multiple required>
                        @if($failedAction === 'approve')<p class="u-text-meta">Riseleziona i PDF prima di salvare.</p>@endif
                    </x-form-group>
                </div>
                <x-form-group label="Riferimenti dell’approvazione (facoltativi)" name="approval_note" :for="'approval-note-'.$quote->id" :error-bag="'proposal_'.$quote->id.'_approve'">
                    <textarea class="form-ta" name="approval_note" rows="2" maxlength="5000">{{ $proposalOld('approval_note','approve') }}</textarea>
                </x-form-group>
                <label class="email-review-confirm"><input type="checkbox" name="confirmed" value="1" required> Il PDF documenta la scelta del cliente per questa proposta.</label>
                <div class="insurance-form-actions"><button class="btn btn-p">Registra approvazione</button></div>
            </form>
        @endif
        @if($mayWork && in_array($quote->status,['received','pending'],true))
            <details class="dispatch-disclosure" @if($failedAction === 'reject') open @endif>
                <summary>Registra un rifiuto del cliente</summary>
                <form method="POST" action="{{ route('quotes.change',[$quote,'reject']) }}">@csrf
                    <x-form-group label="Motivo o riferimento del rifiuto" name="approval_note" :for="'reject-note-'.$quote->id" :error-bag="'proposal_'.$quote->id.'_reject'" required>
                        <input class="form-in" name="approval_note" value="{{ $proposalOld('approval_note','reject') }}" minlength="5" maxlength="5000" required>
                    </x-form-group>
                    <div class="insurance-form-actions"><button class="btn btn-g">Registra rifiuto</button></div>
                </form>
            </details>
        @endif
    @endif
</x-panel>
