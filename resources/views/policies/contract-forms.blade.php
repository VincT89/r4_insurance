<x-form-section title="Moduli da preparare">
    <div data-contract-preparation>
        <script type="application/json" data-contract-templates>{!! json_encode($contractTemplates,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
        <script type="application/json" data-contract-client-context>{!! json_encode($contractClientContext,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
        <script type="application/json" data-contract-form-restore>{!! json_encode(old('contract_forms'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
        @if($contractTemplates)
            <p class="u-text-meta u-mb-md">I moduli impostati come necessari vengono già selezionati per cliente e ramo. Puoi cambiare le spunte: i PDF useranno i dati che confermi in questa pagina.</p>
            <x-form-group name="contract_forms.mode" label="Preparazione dei moduli"><select class="form-sel" name="contract_forms[mode]" data-contract-forms-mode><option value="prepare">Prepara ora i moduli selezionati</option><option value="later">Sceglierò i moduli più tardi</option></select></x-form-group>
            <div data-contract-form-tools class="module-selection-tools"><button type="button" class="btn btn-g btn-sm" data-contract-forms-select>Seleziona i moduli adatti</button><button type="button" class="btn btn-g btn-sm" data-contract-forms-clear>Deseleziona tutti</button></div>
            <div data-contract-form-choices></div>
            <p class="u-text-meta" role="status" data-contract-form-status></p>
            <div class="form-row" data-contract-form-details hidden>
                <x-form-group name="contract_forms.signer_name" label="Nome del firmatario"><input class="form-in" name="contract_forms[signer_name]" data-contract-form-signer maxlength="150" value="{{ old('contract_forms.signer_name') }}" disabled></x-form-group>
                <x-form-group name="contract_forms.signer_role" label="Qualifica del firmatario"><input class="form-in" name="contract_forms[signer_role]" data-contract-form-role maxlength="150" value="{{ old('contract_forms.signer_role') }}" disabled></x-form-group>
                <x-form-group name="contract_forms.place" label="Luogo di preparazione"><input class="form-in" name="contract_forms[place]" data-contract-form-place maxlength="150" value="{{ old('contract_forms.place') }}" disabled></x-form-group>
                <x-form-group name="contract_forms.document_date" label="Data di preparazione"><input class="form-in" type="date" name="contract_forms[document_date]" data-contract-form-date value="{{ old('contract_forms.document_date',today()->toDateString()) }}" disabled></x-form-group>
            </div>
        @else
            <input type="hidden" name="contract_forms[mode]" value="later">
            <p>Nessun modello approvato disponibile. La polizza può essere creata: il promemoria dei moduli rimarrà aperto.</p>
            @can('forms.manage')<a href="{{ route('form-templates.index') }}" target="_blank" rel="noopener">Configura modelli</a>@endcan
        @endif
    </div>
</x-form-section>
