<x-app-layout title="Prepara invio">
    <x-page-header>
        <x-slot:title>Prepara invio</x-slot:title>
        <x-slot:meta>{{ $case->client->name }} · {{ $case->subject }}</x-slot:meta>
        <x-slot:actions><a class="btn btn-g" href="{{ route('quotes.show',$case) }}">Torna alla pratica</a></x-slot:actions>
    </x-page-header>
    @php
        $defaultBody = "Buongiorno,\n\nrichiediamo un preventivo per ".$case->client->name.".\nRamo: ".$case->branch->name.($case->subbranch ? ' / '.$case->subbranch->name : '').".\nRichiesta: ".$case->subject;
        if ($case->requestContext()) $defaultBody .= "\n\n".$case->requestContext();
        $defaultBody .= "\n\nRestiamo in attesa della proposta e delle relative condizioni.\n\n".config('branding.wordmark');
        $subjectValue = old('subject', $draftValues['subject'] ?? \Illuminate\Support\Str::limit('Richiesta preventivo #'.$case->id.' - '.$case->subject,180,''));
        $bodyValue = old('body', $draftValues['body'] ?? $defaultBody);
        $selectedDocuments = array_filter((array) old('document_ids', old('document_selection_present') ? [] : ($draftValues['document_ids'] ?? [])), 'is_scalar');
        $attachmentErrors = collect($errors->keys())->contains(fn ($key) => \Illuminate\Support\Str::startsWith($key, ['identity_file','vehicle_file','files','document_ids']));
    @endphp
    <form method="POST" enctype="multipart/form-data" action="{{ route('quotes.emails.review',$case) }}" class="quote-dispatch">
        @csrf
        @if($draft)
            <input type="hidden" name="draft_id" value="{{ $draft->id }}">
            <p class="u-mb-md">Stai modificando una richiesta già preparata. Ritrovi le spunte, il testo e gli allegati dell’anteprima.</p>
        @endif
        <input type="hidden" name="document_selection_present" value="1">
        <x-panel title="Destinatari" padded>
            <p class="dispatch-context">{{ $case->branch->name }}{{ $case->subbranch ? ' / '.$case->subbranch->name : '' }}@if($case->merit_class_cu) · Classe CU {{ $case->merit_class_cu }}@endif</p>
            @include('quotes.recipient-checkboxes',['emailMode'=>true])
        </x-panel>
        <div class="dispatch-extras">
            <details class="dispatch-disclosure" @if($errors->hasAny(['subject','body'])) open @endif>
                <summary>Oggetto e testo dell’email <small data-dispatch-subject>{{ is_scalar($subjectValue) ? $subjectValue : '' }}</small></summary>
                <div class="dispatch-disclosure-body">
                    <x-form-group label="Oggetto" name="subject" required>
                        <input id="field-subject" class="form-in" name="subject" maxlength="180" value="{{ is_scalar($subjectValue) ? $subjectValue : '' }}" required>
                    </x-form-group>
                    <x-form-group label="Testo dell’email" name="body" required>
                        <textarea id="field-body" class="form-ta" name="body" rows="8" minlength="20" maxlength="20000" required>{{ is_scalar($bodyValue) ? $bodyValue : '' }}</textarea>
                    </x-form-group>
                </div>
            </details>
            <details class="dispatch-disclosure" @if($attachmentErrors) open @endif>
                <summary>Allegati <small data-dispatch-files>{{ $selectedDocuments ? count($selectedDocuments).' in anteprima' : 'Facoltativi' }}</small></summary>
                <div class="dispatch-disclosure-body">
                    @if($documents->isNotEmpty())
                        <p>I documenti spuntati saranno allegati a ogni email.</p>
                        @error('document_ids')<p class="invalid-feedback">{{ $message }}</p>@enderror
                        @foreach($documents as $document)
                            <div class="document-file-row">
                                <label class="email-review-confirm"><input type="checkbox" name="document_ids[]" value="{{ $document->id }}" @checked(in_array($document->id,$selectedDocuments))>
                                    <span>{{ $document->original_name }} · {{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$document->document_category] ?? 'Documento' }}</span>
                                </label>
                                <p class="u-text-meta"><a href="{{ $document->downloadUrl() }}">Apri documento</a>@if($document->document_category==='identity') · <x-status-text :alert="$document->expires_on?->lt(today())">{{ $document->validity_label }}</x-status-text>@endif</p>
                            </div>
                        @endforeach
                    @endif
                    @include('quotes.document-fields')
                </div>
            </details>
        </div>
        <p class="dispatch-sender u-text-meta">Ogni destinatario riceverà un’email separata.
            @if($sender['address']) Mittente {{ $sender['address'] }}.
            @else Puoi preparare l’anteprima. Per inviare, l’admin deve configurare la casella email.@endif
        </p>
        <div class="dispatch-actions">
            <strong data-recipient-count role="status"></strong>
            <button class="btn btn-p" type="submit">Vai all’anteprima</button>
        </div>
    </form>
</x-app-layout>
