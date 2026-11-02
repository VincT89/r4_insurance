<section class="client-detail-section">
    <h2 class="sec-lbl">Amministrazione e coordinate bancarie</h2>
    <dl class="client-registry-details">
    @foreach(['billing_email'=>'Email amministrativa','sdi_code'=>'Codice destinatario SDI','account_holder'=>'Intestatario del conto','bank_name'=>'Banca','iban'=>'IBAN','bic'=>'BIC/SWIFT'] as $field=>$label)
        <div><dt>{{ $label }}</dt><dd>{{ $entity->$field ? ($field==='iban' ? trim(chunk_split($entity->$field,4,' ')) : $entity->$field) : 'Non indicato' }}</dd></div>
    @endforeach
    </dl>
</section>
