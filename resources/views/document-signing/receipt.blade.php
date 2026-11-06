<!doctype html><html lang="it"><head><meta charset="utf-8"><style>
@page { margin:36pt; } body {font-family:'DejaVu Sans',sans-serif;font-size:10pt;color:#172033;line-height:1.5} h1{font-size:17pt;color:#0b498c} dt{font-weight:bold;margin-top:12pt} dd{margin:0} .hash{font-size:8pt;overflow-wrap:break-word} .note{margin-top:20pt;border-top:1pt solid #aab4bf;padding-top:12pt}
</style></head><body>
<h1>Registrazione della firma</h1>
<p>Soluzioni Assicurative SAS</p>
<dl>
<dt>Documento sottoscritto</dt><dd>{{ $evidence['document'] }}</dd>
<dt>Contraente</dt><dd>{{ $evidence['client'] }}</dd>
@if($evidence['policy'])<dt>Polizza</dt><dd>{{ $evidence['policy'] }}</dd>@endif
<dt>Firmatario</dt><dd>{{ $evidence['signer_name'] }}@if($evidence['signer_role']) · {{ $evidence['signer_role'] }}@endif</dd>
<dt>Data di registrazione</dt><dd>{{ $evidence['signed_at_display'] }}</dd>
<dt>Operatore presente</dt><dd>{{ $evidence['operator'] }}</dd>
<dt>Conferma del firmatario</dt><dd>{{ $evidence['consent_text'] }}</dd>
<dt>Punti firma</dt><dd>{{ count($evidence['placements']) }} · Pagine {{ collect($evidence['placements'])->pluck('page')->unique()->implode(', ') }}</dd>
<dt>Riferimento della registrazione</dt><dd>{{ $evidence['request_key'] }}</dd>
<dt>Impronta SHA-256 del PDF originale</dt><dd class="hash">{{ $evidence['source_hash'] }}</dd>
</dl>
@if($evidence['note'])<p>{{ $evidence['note'] }}</p>@endif
<p class="note">Firma grafica raccolta nel gestionale in presenza dell’operatore, con conferma di avvenuta verifica dell’identità. Questa registrazione non dichiara una firma elettronica avanzata o qualificata. L’originale e la copia con firma sono conservati separatamente.</p>
</body></html>
