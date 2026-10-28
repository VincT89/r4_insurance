<x-app-layout title="Richiedi preventivo">
<x-page-header><x-slot:title>Richiedi preventivo</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('quotes.show',$case) }}">Torna alla pratica</a></x-slot:actions></x-page-header>
<x-panel padded><form method="POST" enctype="multipart/form-data" action="{{ route('quotes.emails.review',$case) }}" x-data="{company: @js((string)old('supplier_company_id','')), addresses: @js($companies->mapWithKeys(fn($company)=>[(string)$company->id=>$company->email ?: 'Indirizzo da completare nell’anagrafica compagnia']))}">
@csrf
<x-form-section title="Cliente e destinatario">
<p class="u-mb-md">{{ $case->client->name }} · {{ $case->branch->name }}{{ $case->subbranch ? ' / '.$case->subbranch->name : '' }}</p>
@if($case->recipient_group_snapshot)<p class="u-mb-md">Gruppo {{ $case->recipient_group_snapshot['name'] }}@if($case->merit_class_cu) · CU {{ $case->merit_class_cu }}@endif: seleziona una delle compagnie configurate per questa richiesta.</p>@endif
@if($remaining->isNotEmpty())<p class="u-mb-md">Prima invia le richieste a {{ $remaining->pluck('name')->join(' e ') }}. Le altre compagnie saranno disponibili dopo la conferma degli invii. Se hai già ricevuto un preventivo fuori dal gestionale, registralo nella pratica con il PDF ricevuto.</p>@endif
<div class="form-row">
<x-form-group label="Compagnia" name="supplier_company_id" required><select id="field-supplier-company-id" class="form-sel" name="supplier_company_id" x-model="company" required><option value="">Seleziona la compagnia</option>@foreach($companies as $company)<option value="{{ $company->id }}" @disabled($unavailable->contains($company->id) || (!$company->quote_priority && $remaining->isNotEmpty()))>{{ $company->name }}{{ $company->quote_priority ? ' — prioritaria' : '' }}{{ $unavailable->contains($company->id) ? ' — già richiesta' : '' }}</option>@endforeach</select></x-form-group>
<x-form-group label="Indirizzo email destinatario" name="recipient-preview"><input id="field-recipient-preview" class="form-in" :value="addresses[company] || ''" readonly><p class="u-text-meta">Indirizzo presente nell’anagrafica della compagnia. Controllalo prima dell’invio.</p></x-form-group>
</div><p class="u-text-meta">Mittente: {{ $sender['address'] ?: 'Casella da configurare dall’admin in Impostazioni email' }}.</p>
</x-form-section>
<x-form-section title="Richiesta alla compagnia">
<x-form-group label="Oggetto" name="subject" required><input id="field-subject" class="form-in" name="subject" maxlength="180" value="{{ old('subject',\Illuminate\Support\Str::limit('Richiesta preventivo #'.$case->id.' - '.$case->subject,180,'')) }}" required></x-form-group>
@php
    $defaultBody = "Buongiorno,\n\nrichiediamo un preventivo per ".$case->client->name.".\nRamo: ".$case->branch->name.($case->subbranch ? ' / '.$case->subbranch->name : '').".\nRichiesta: ".$case->subject;
    if ($case->requestContext()) $defaultBody .= "\n\n".$case->requestContext();
    $defaultBody .= "\n\nRestiamo in attesa della proposta e delle relative condizioni.\n\n".config('branding.wordmark');
@endphp
<div class="u-mt-md"><x-form-group label="Testo dell’email" name="body" required><textarea id="field-body" class="form-ta" name="body" rows="12" minlength="20" maxlength="20000" required>{{ old('body',$defaultBody) }}</textarea><p class="u-text-meta">Controlla anche i riferimenti della polizza e le coperture da quotare. Le note interne della pratica non vengono aggiunte automaticamente.</p></x-form-group></div>
</x-form-section>
@if($case->documents->isNotEmpty())
<x-form-section title="Scegli documenti già presenti nella pratica"><p class="u-text-meta u-mb-md">Seleziona soltanto i documenti da inviare alla compagnia scelta. Nessun allegato viene aggiunto automaticamente.</p>
@error('document_ids')<p class="invalid-feedback">{{ $message }}</p>@enderror
@foreach($case->documents as $document)<div class="document-file-row"><label class="email-review-confirm"><input type="checkbox" name="document_ids[]" value="{{ $document->id }}" @checked(in_array($document->id,old('document_ids',[])))> <span>{{ $document->original_name }} · {{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$document->document_category] ?? 'Documento' }}</span></label>
<p class="u-text-meta u-mt-sm"><a href="{{ $document->downloadUrl() }}">Apri documento</a>@if($document->document_category==='identity') · <x-status-text :alert="$document->expires_on?->lt(today())">{{ $document->validity_label }}</x-status-text>@endif</p></div>@endforeach
</x-form-section>
@endif
<x-form-section title="Aggiungi nuovi allegati da inviare">@include('quotes.document-fields')</x-form-section>
<div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('quotes.show',$case) }}">Annulla</a><button class="btn btn-p">Controlla richiesta</button></div>
</form></x-panel></x-app-layout>
