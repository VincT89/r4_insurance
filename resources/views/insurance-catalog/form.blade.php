@php($label = $kind === 'branch' ? 'ramo' : 'sottoramo')
<x-app-layout :title="($record->exists ? 'Modifica ' : 'Nuovo ').$label">
    <x-page-header><x-slot:title>{{ ($record->exists ? 'Modifica ' : 'Nuovo ').$label }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('insurance-catalog.index', ['kind'=>$kind]) }}">Rami e sottorami</a></x-slot:actions></x-page-header>
    <x-panel padded>
        <form method="POST" action="{{ $record->exists ? route('insurance-catalog.update', [$kind, $record->id]) : route('insurance-catalog.store', $kind) }}">
            @csrf @if($record->exists) @method('PATCH') @endif
            <x-form-section title="Classificazione e stato">
            <div class="form-row">
                @if($kind==='subbranch')<x-form-group label="Ramo principale" name="insurance_branch_id" required><select class="form-sel" id="field-insurance-branch-id" name="insurance_branch_id" required><option value="">Seleziona il ramo</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)old('insurance_branch_id', $record->insurance_branch_id)===(string)$branch->id)>{{ $branch->name }}{{ $branch->status==='inactive' ? ' (inattivo)' : '' }}</option>@endforeach</select></x-form-group>@endif
                <x-form-group :label="'Nome '.$label" name="name" required><input class="form-in" id="field-name" name="name" value="{{ old('name', $record->name) }}" maxlength="100" required></x-form-group>
                <x-form-group label="Stato" name="status" required><select class="form-sel" id="field-status" name="status" required><option value="active" @selected(old('status', $record->status)==='active')>Attivo</option><option value="inactive" @selected(old('status', $record->status)==='inactive')>Inattivo</option></select><p class="u-text-meta">Le voci inattive restano nello storico e non sono proposte per nuove polizze.</p></x-form-group>
                <x-form-group label="Verifica della classificazione" name="needs_review" required><select class="form-sel" id="field-needs-review" name="needs_review"><option value="0" @selected(!old('needs_review', $record->needs_review))>Nessuna verifica aperta</option><option value="1" @selected(old('needs_review', $record->needs_review))>Da verificare</option></select></x-form-group>
            </div>
            <x-form-group label="Note" name="notes"><textarea class="form-ta" id="field-notes" name="notes" rows="3" maxlength="10000">{{ old('notes', $record->notes) }}</textarea></x-form-group>
            </x-form-section>
            @can('system.admin')
            <x-form-section title="Checklist documentale">
                <p class="u-text-meta">Seleziona i documenti concordati con l’agenzia. I documenti del sottoramo si aggiungono a quelli del ramo; le voci ripetute compaiono una sola volta.</p>
                <input type="hidden" name="document_checklist_present" value="1">
                <h3 class="u-mt-md">Comuni a privati e aziende</h3>
                <div class="agency-company-options">@foreach(\App\Models\Attachment::DOCUMENT_CATEGORIES as $value => $name)<label><input type="checkbox" name="document_categories[]" value="{{ $value }}" @checked(in_array($value, (array)old('document_categories', $record->document_categories ?? []), true))><span>{{ $name }}</span></label>@endforeach</div>
                @error('document_categories')<p class="invalid-feedback">{{ $message }}</p>@enderror
                @error('document_categories.*')<p class="invalid-feedback">{{ $message }}</p>@enderror
                <input type="hidden" name="typed_checklist_present" value="1">
                @foreach(['person_document_categories'=>'In aggiunta per i privati', 'company_document_categories'=>'In aggiunta per le aziende'] as $checklistField=>$checklistTitle)
                    <details class="document-checklist-config">
                        <summary>{{ $checklistTitle }}</summary>
                        <div class="agency-company-options">@foreach(\App\Models\Attachment::DOCUMENT_CATEGORIES as $value=>$name)
                            <label><input type="checkbox" name="{{ $checklistField }}[]" value="{{ $value }}" @checked(in_array($value, (array)old($checklistField, $record->$checklistField ?? []), true))><span>{{ $name }}</span></label>
                        @endforeach</div>
                        @error($checklistField)<p class="invalid-feedback">{{ $message }}</p>@enderror
                        @error($checklistField.'.*')<p class="invalid-feedback">{{ $message }}</p>@enderror
                    </details>
                @endforeach
                <p class="u-text-meta u-mt-md">La configurazione si applica ai nuovi inserimenti della checklist. Le richieste e i file già registrati restano nello storico.</p>
            </x-form-section>
            @endcan
            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('insurance-catalog.index', ['kind'=>$kind]) }}">Annulla</a><button class="btn btn-p" type="submit">Salva {{ $label }}</button></div>
        </form>
    </x-panel>
    @if($record->source_values)
        <details class="source-reference"><summary>Origine dati</summary><x-panel>
            <div class="client-table-scroll" role="region" aria-label="Classificazioni originali" tabindex="0"><table class="t-table"><thead><tr><th>Ramo originale</th><th>Sottoramo originale</th><th>Righe del file</th><th>Nota di preparazione</th></tr></thead><tbody>@foreach($record->source_values as $source)<tr><td>{{ $source['source_branch'] }}</td><td>{{ $source['source_subbranch'] ?: 'Non indicato' }}</td><td>{{ $source['rows'] }}</td><td>{{ $source['reason'] }}</td></tr>@endforeach</tbody></table></div>
        </x-panel></details>
    @endif
    @can('system.admin')@if($record->exists)<x-delete-modal action="{{ route('insurance-catalog.destroy', [$kind, $record->id]) }}" title="Elimina voce" message="Puoi eliminare solo voci senza collegamenti. In alternativa puoi impostarle come inattive."><button class="btn btn-g btn-danger-outline" type="button">Elimina {{ $label }}</button></x-delete-modal>@endif@endcan
</x-app-layout>
