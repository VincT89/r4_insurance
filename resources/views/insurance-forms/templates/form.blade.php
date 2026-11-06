@php
    $safeOld = fn ($key,$default='') => is_scalar($value = old($key,$default)) ? $value : '';
    $heading = !$template->exists ? 'Nuovo modello' : ($editable ? 'Modifica modello' : $template->title);
    $fields = \App\Services\InsuranceForms::fieldLabels();
    $initialEditorData = $editorDocument + ['fields'=>$fields];
    $rich = \App\Support\FormTemplateContent::isFormatted((string)$template->body);
@endphp
<x-app-layout :title="$heading">
    <x-page-header>
        <x-slot:title>{{ $heading }}</x-slot:title>
        @if($template->exists)
            <x-slot:meta>{{ $template->title }} · Versione {{ $template->version }} · {{ \App\Models\InsuranceFormTemplate::STATUSES[$template->status] }}</x-slot:meta>
        @endif
        <x-slot:actions>
            <a class="btn btn-g" href="{{ route('form-templates.index') }}">Tutti i modelli</a>
            @if($revision)<a class="btn btn-g" href="{{ route('form-templates.edit',$template) }}">Versione salvata</a>@endif
            @if(!$editable)
                <a class="btn btn-g" target="_blank" rel="noopener" href="{{ route('form-templates.preview',[$template,'viewer'=>1]) }}">Anteprima PDF</a>
                @if(!$template->next)<a class="btn btn-p" href="{{ route('form-templates.edit',[$template,'revise'=>1]) }}">Modifica modello</a>@endif
            @endif
        </x-slot:actions>
    </x-page-header>
    @if($template->source_warning)
        <x-panel padded><p>{{ $template->source_warning }}</p><p class="u-text-meta">Foglio di origine · {{ $template->source_sheet }}</p></x-panel>
    @endif
    @if($editable)
        <x-panel padded>
            <form class="template-workspace" method="POST" action="{{ $template->exists ? route('form-templates.update',$template) : route('form-templates.store') }}"
                  data-template-editor x-data="{scope:@js($safeOld('scope',$template->scope)),branch:@js((string)$safeOld('insurance_branch_id',$template->insurance_branch_id)),sub:@js((string)$safeOld('insurance_subbranch_id',$template->insurance_subbranch_id)),children:@js($branches->mapWithKeys(fn($b)=>[(string)$b->id=>$b->subbranches->map(fn($s)=>['id'=>(string)$s->id,'name'=>$s->name])]))}">
                @csrf
                @if($template->exists)@method('PATCH')<input type="hidden" name="edit_key" value="{{ $safeOld('edit_key',$template->editKey()) }}">@endif
                @if($revision)<input type="hidden" name="revise" value="1"><p class="template-version-note">Le modifiche saranno disponibili per i nuovi fascicoli dopo l’approvazione.</p>@endif
                <div class="template-edit-head">
                    <x-form-group label="Nome del modello" name="title" required><input id="title" class="form-in" name="title" maxlength="150" value="{{ $safeOld('title',$template->title) }}" required></x-form-group>
                    <div class="template-edit-actions">
                        <button class="btn btn-g" type="submit" name="_method" value="POST" formaction="{{ route('form-templates.draft-preview') }}" formtarget="_blank" formnovalidate>Anteprima PDF</button>
                        <button class="btn btn-p" type="submit" data-save-template @disabled($rich)>{{ $revision ? 'Salva nuova versione' : 'Salva bozza' }}</button>
                    </div>
                </div>
                @include('insurance-forms.templates.editor')
                <details class="template-options" @if($errors->hasAny(['category','scope','client_type','insurance_branch_id','insurance_subbranch_id','signature_required','required'])) open @endif @invalid.capture="$el.open = true">
                    <summary>Uso e impostazioni del modello</summary>
                    <div class="form-row">
                        <x-form-group label="Categoria" name="category" required><select name="category" class="form-sel" required>@foreach(\App\Models\Attachment::DOCUMENT_CATEGORIES as $key=>$label)<option value="{{ $key }}" @selected($safeOld('category',$template->category)===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
                        <x-form-group label="Ambito" name="scope"><select name="scope" class="form-sel" x-model="scope" @change="if(scope==='client'){branch='';sub=''}">@foreach(\App\Models\InsuranceFormTemplate::SCOPES as $key=>$label)<option value="{{ $key }}" @selected($safeOld('scope',$template->scope)===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
                        <x-form-group label="Destinatari" name="client_type"><select name="client_type" class="form-sel">@foreach(\App\Models\InsuranceFormTemplate::CLIENT_TYPES as $key=>$label)<option value="{{ $key }}" @selected($safeOld('client_type',$template->client_type ?? 'all')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
                    </div>
                    <div class="form-row" x-show="scope==='policy'">
                        <x-form-group label="Ramo" name="insurance_branch_id"><select name="insurance_branch_id" class="form-sel" x-model="branch" :disabled="scope!=='policy'" @change="sub=''"><option value="">Tutti i rami</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></x-form-group>
                        <x-form-group label="Sottoramo" name="insurance_subbranch_id"><select name="insurance_subbranch_id" class="form-sel" x-model="sub" :disabled="!branch || scope!=='policy'"><option value="">Tutti i sottorami</option><template x-for="item in (children[branch]||[])" :key="item.id"><option :value="item.id" x-text="item.name" :selected="item.id===sub"></option></template></select></x-form-group>
                    </div>
                    <div class="template-flags">
                        <input type="hidden" name="signature_required" value="0"><label><input type="checkbox" name="signature_required" value="1" @checked($safeOld('signature_required',$template->signature_required))> Richiede la firma</label>
                        <input type="hidden" name="required" value="0"><label><input type="checkbox" name="required" value="1" @checked($safeOld('required',$template->required))> Preseleziona nel promemoria moduli</label>
                    </div>
                </details>
                @if($revision)
                    <x-form-group label="Motivo della modifica" name="revision_reason" required><input class="form-in" name="revision_reason" maxlength="2000" minlength="10" required placeholder="Descrivi la modifica in almeno 10 caratteri" value="{{ $safeOld('revision_reason') }}"></x-form-group>
                @endif
            </form>
        </x-panel>
    @else
        <x-panel title="Testo conservato" padded><div class="document-text">{!! \App\Support\FormTemplateContent::html($template->body,\App\Services\InsuranceForms::previewFields()) !!}</div></x-panel>
    @endif
    @if($template->exists && !$revision)
        @if($template->status==='draft' && !$template->next)
            <x-panel title="Approva il modello" padded><form method="POST" action="{{ route('form-templates.approve',$template) }}">@csrf
                <input type="hidden" name="edit_key" value="{{ $template->editKey() }}">
                <p>Controlla testo, riferimenti e firme nell’anteprima della bozza salvata prima di renderla disponibile agli operatori.</p>
                <p data-approval-hint role="status" hidden>Salva le modifiche prima di approvare la bozza.</p>
                <label class="insurance-checkbox"><input type="checkbox" name="confirmed" value="1" required> Ho revisionato la versione salvata e ne confermo l’uso in agenzia.</label>
                <div class="insurance-form-actions"><a class="btn btn-g" target="_blank" rel="noopener" href="{{ route('form-templates.preview',[$template,'viewer'=>1]) }}">Anteprima della bozza salvata</a><button class="btn btn-p" data-approve-template>Approva versione</button></div>
            </form></x-panel>
        @endif
        @if($template->status==='active')
            <x-panel padded><details class="template-options"><summary>Ritira modello dall’uso</summary><form method="POST" action="{{ route('form-templates.retire',$template) }}">@csrf
                <x-form-group label="Motivo del ritiro" name="reason" required><textarea class="form-ta" name="reason" minlength="10" maxlength="2000" rows="2" required></textarea></x-form-group>
                <label class="insurance-checkbox"><input type="checkbox" name="confirmed" value="1" required> Confermo il ritiro dai nuovi fascicoli.</label><button class="btn btn-g">Ritira modello</button>
            </form></details></x-panel>
        @endif
        <div class="insurance-form-actions">
            @if($template->previous)<a class="btn btn-g" href="{{ route('form-templates.edit',$template->previous) }}">Versione precedente</a>@endif
            @if($template->next)<a class="btn btn-g" href="{{ route('form-templates.edit',$template->next) }}">Versione successiva</a>@endif
        </div>
    @endif
</x-app-layout>
