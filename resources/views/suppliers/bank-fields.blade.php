<x-form-section title="Amministrazione e coordinate bancarie">
    <div class="form-row">
    @foreach(['billing_email'=>['Email amministrativa','email',255],'sdi_code'=>['Codice destinatario SDI','text',7],'account_holder'=>['Intestatario del conto','text',255],'bank_name'=>['Banca','text',255],'iban'=>['IBAN','text',42],'bic'=>['BIC/SWIFT','text',11]] as $field=>[$label,$type,$length])
        <x-form-group :label="$label" :name="$field"><input id="field-{{ \Illuminate\Support\Str::slug($field) }}" class="form-in @error($field) is-invalid @enderror" type="{{ $type }}" name="{{ $field }}" maxlength="{{ $length }}" value="{{ is_scalar(old($field,$entity->$field)) ? old($field,$entity->$field) : '' }}" @if(in_array($field,['iban','bic','sdi_code'],true)) spellcheck="false" autocapitalize="characters" @endif></x-form-group>
    @endforeach
    </div>
    <p class="u-text-meta">Puoi incollare l’IBAN anche con gli spazi. Se lo inserisci, indica anche l’intestatario del conto. Il controllo del formato non verifica l’esistenza del conto o il suo intestatario.</p>
</x-form-section>
