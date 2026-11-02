<x-app-layout title="Richiedi preventivo">
<x-page-header><x-slot:title>Richiedi preventivo</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('quotes.show',$case) }}">Torna alla pratica</a></x-slot:actions></x-page-header>
<x-panel padded><form method="POST" enctype="multipart/form-data" action="{{ route('quotes.emails.review',$case) }}">
@csrf
@if($draft)<input type="hidden" name="draft_id" value="{{ $draft->id }}"><p class="u-mb-md">Stai modificando la richiesta preparata. Testo, destinatari e allegati sono stati recuperati. Controlla la nuova anteprima prima dell’invio.</p>@endif
<input type="hidden" name="document_selection_present" value="1">
<x-form-section title="Cliente e destinatari">
<p class="u-mb-md">{{ $case->client->name }} · {{ $case->branch->name }}{{ $case->subbranch ? ' / '.$case->subbranch->name : '' }}</p>
@if($case->recipient_group_snapshot)<p class="u-mb-md">Selezione iniziale dal gruppo {{ $case->recipient_group_snapshot['name'] }}@if($case->merit_class_cu) · CU {{ $case->merit_class_cu }}@endif. Puoi modificarla liberamente.</p>@endif
@include('quotes.recipient-checkboxes',['emailMode'=>true])
<p class="u-text-meta u-mt-md">Mittente: {{ $sender['address'] ?: 'Casella da configurare dall’admin in Impostazioni email' }}. Ogni destinatario riceverà un’email separata.</p>
</x-form-section>
<x-form-section title="Testo della richiesta">
<x-form-group label="Oggetto" name="subject" required><input id="field-subject" class="form-in" name="subject" maxlength="180" value="{{ old('subject',$draftValues['subject'] ?? \Illuminate\Support\Str::limit('Richiesta preventivo #'.$case->id.' - '.$case->subject,180,'')) }}" required></x-form-group>
@php
    $defaultBody = "Buongiorno,\n\nrichiediamo un preventivo per ".$case->client->name.".\nRamo: ".$case->branch->name.($case->subbranch ? ' / '.$case->subbranch->name : '').".\nRichiesta: ".$case->subject;
    if ($case->requestContext()) $defaultBody .= "\n\n".$case->requestContext();
    $defaultBody .= "\n\nRestiamo in attesa della proposta e delle relative condizioni.\n\n".config('branding.wordmark');
@endphp
<div class="u-mt-md"><x-form-group label="Testo dell’email" name="body" required><textarea id="field-body" class="form-ta" name="body" rows="12" minlength="20" maxlength="20000" required>{{ old('body',$draftValues['body'] ?? $defaultBody) }}</textarea><p class="u-text-meta">Controlla anche i riferimenti della polizza e le coperture da quotare. Le note interne della pratica non vengono aggiunte automaticamente.</p></x-form-group></div>
</x-form-section>
@if($documents->isNotEmpty())
<x-form-section title="Documenti da inviare"><p class="u-text-meta u-mb-md">I documenti selezionati saranno inviati a tutti i destinatari scelti. Puoi aggiungere o togliere gli allegati prima della nuova anteprima.</p>
@error('document_ids')<p class="invalid-feedback">{{ $message }}</p>@enderror
@foreach($documents as $document)<div class="document-file-row"><label class="email-review-confirm"><input type="checkbox" name="document_ids[]" value="{{ $document->id }}" @checked(in_array($document->id,old('document_ids',old('document_selection_present') ? [] : ($draftValues['document_ids'] ?? []))))> <span>{{ $document->original_name }} · {{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$document->document_category] ?? 'Documento' }}</span></label>
<p class="u-text-meta u-mt-sm"><a href="{{ $document->downloadUrl() }}">Apri documento</a>@if($document->document_category==='identity') · <x-status-text :alert="$document->expires_on?->lt(today())">{{ $document->validity_label }}</x-status-text>@endif</p></div>@endforeach
</x-form-section>
@endif
<x-form-section title="Aggiungi nuovi allegati da inviare">@include('quotes.document-fields')</x-form-section>
<div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('quotes.show',$case) }}">Annulla</a><button class="btn btn-p">Controlla richiesta</button></div>
</form></x-panel></x-app-layout>
