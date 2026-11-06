<x-app-layout title="Fascicolo moduli">
@if($package->insurance_policy_change_id)<div class="page-back-row"><a class="btn btn-g" href="{{ route('clients.policy-changes.show',[$client,$package->insurance_policy_change_id]) }}">Variazione contrattuale collegata</a></div>@endif
<x-page-header>
    <x-slot:title>Fascicolo #{{ $package->id }} · {{ $client->name }}</x-slot:title>
    <x-slot:actions><a class="btn btn-g" href="{{ route('clients.forms.index',[$client,'policy'=>$package->insurance_policy_id]) }}">Storico fascicoli</a>
        @if($package->policy)
            <a class="btn btn-g" href="{{ route('clients.policies.show',[$client,$package->policy,'tab'=>'forms']).'#policy-signatures' }}">{{ $package->documents->contains('signature_required',true) ? 'Firme della polizza' : 'Scheda polizza' }}</a>
        @else<a class="btn btn-g" href="{{ route('clients.show',$client) }}">Scheda cliente</a>@endif
    </x-slot:actions>
</x-page-header>
<x-panel title="Riferimenti" padded>
    <dl class="client-registry-details">
        <div><dt>Stato</dt><dd>{{ $package->state_label }}</dd></div>
        <div><dt>Motivo</dt><dd>{{ \App\Models\InsuranceFormPackage::REASONS[$package->reason] }}</dd></div>
        <div><dt>Polizza</dt><dd>{{ $package->policy_number ?? 'Documenti del cliente' }}</dd></div>
        @if($period = $package->policy_period)<div><dt>Periodo del contratto</dt><dd>{{ $period }}</dd></div>@endif
        <div><dt>Preparato da</dt><dd>{{ $package->author?->name }} · {{ $package->created_at->format('d/m/Y H:i') }}</dd></div>
    </dl>
    @if($package->note)<p class="form-template-text">{{ $package->note }}</p>@endif
    @if(!$package->currentData())<p>I dati attuali differiscono da quelli usati per questi moduli. Questo fascicolo rimane nello storico.</p>@endif
</x-panel>
@foreach($package->documents as $document)
<x-panel :id="'form-document-'.$document->id" :title="$document->title.' · modello v'.$document->template_version" data-signature-destination padded>
<div class="form-document-actions"><p>{{ $document->state_label }}</p>@if($document->generated)@if($document->generated->canPreview())<a class="btn btn-g" href="{{ $document->generated->openUrl() }}" target="_blank" rel="noopener">Apri modulo PDF</a>@endif<a class="btn btn-g" href="{{ $document->generated->downloadUrl() }}">Scarica modulo PDF</a>@endif</div>
@if($document->signature_required)
@can('updateRegistry',$client)
    @if($document->generated && (!$document->latestSubmission || $document->latestSubmission->status==='rejected'))
        <div class="form-document-actions"><a class="btn btn-p" href="{{ route('attachments.sign',$document->generated) }}">Firma sul tablet</a></div>
    @endif
    @if(($latest=$document->submissions->firstWhere('id',$document->latestSubmission?->id)) && $latest->status==='received')
        <h3 class="signature-history-title">Controlla copia ricevuta</h3>
        <p>{{ $latest->signer_name }} · Firma del {{ $latest->signed_on->format('d/m/Y') }} · Copia {{ $latest->version }}</p>
        <div class="signature-links">
            @foreach($latest->files as $file)
                <a href="{{ $file->openUrl() }}" @if($file->canPreview()) target="_blank" rel="noopener" @endif>{{ $latest->files->count()===1 ? 'Apri copia ricevuta' : $file->original_name }}</a>
            @endforeach
        </div>
        @include('insurance-forms.signature-review',['origin'=>'package'])
    @endif
    <details class="form-signing-upload" @if(!$document->latestSubmission || $document->latestSubmission->status==='rejected') data-signature-next @endif @if((int)old('signature_document')===$document->id && old('signature_action')==='upload') open @endif>
        <summary>{{ !$document->latestSubmission || $document->latestSubmission->status==='rejected' ? $document->signatureActionLabel() : 'Carica nuova versione firmata' }}</summary>
        @include('insurance-forms.signature-upload',['origin'=>'package'])
    </details>
@endcan
@if($document->submissions->isEmpty())<p>Nessuna firma ricevuta.</p>
@else
    <h3 class="signature-history-title">Copie firmate</h3>
    <div class="client-table-scroll policy-list-scroll"><table class="t-table policy-documents-table form-package-history signature-history">
        <thead><tr><th>Copia e file</th><th>Firmatario e data</th><th>Controllo</th><th>Registrazione</th></tr></thead>
        <tbody>@foreach($document->submissions as $submission)<tr>
            <td data-label="Copia e file"><strong>Copia {{ $submission->version }}{{ $document->latestSubmission?->id !== $submission->id ? ' / storica' : '' }}</strong>
                @foreach($submission->files as $file)<div><a href="{{ $file->openUrl() }}" @if($file->canPreview()) target="_blank" rel="noopener" @endif>{{ $file->original_name }}</a></div>@endforeach
                @if($submission->note)<div class="u-text-meta">{{ $submission->note }}</div>@endif
            </td>
            <td data-label="Firmatario e data">{{ $submission->signer_name }}@if($submission->signer_role)<div>{{ $submission->signer_role }}</div>@endif<div>{{ $submission->signed_on->format('d/m/Y') }}</div></td>
            <td data-label="Controllo">{{ ['received'=>'Da controllare','validated'=>'Firma controllata','rejected'=>$document->latestSubmission?->id === $submission->id ? 'Da correggere' : 'Correzione richiesta'][$submission->status] }}
                @if($submission->reviewed_at)<div class="u-text-meta">{{ $submission->reviewed_at->format('d/m/Y H:i') }} · {{ $submission->reviewer?->name }}</div>@endif
                @if($submission->review_note)<div>{{ $submission->review_note }}</div>@endif
            </td>
            <td data-label="Registrazione">{{ $submission->author?->name }}<div class="u-text-meta">{{ $submission->created_at->format('d/m/Y H:i') }}</div></td>
        </tr>@endforeach</tbody>
    </table></div>
@endif
@endif
</x-panel>
@endforeach
</x-app-layout>
