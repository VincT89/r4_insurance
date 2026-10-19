<!doctype html><html lang="it"><head><meta charset="utf-8"><style>
@page { margin: 34pt 38pt 45pt; } body{font-family:'DejaVu Sans',sans-serif;font-size:10pt;line-height:1.45;color:#172033}header{border-bottom:1pt solid #124d85;margin-bottom:16pt;padding-bottom:8pt}h1{font-size:15pt;line-height:1.3;margin:8pt 0}p{margin:0 0 9pt;white-space:pre-wrap;overflow-wrap:break-word}footer{position:fixed;bottom:-27pt;font-size:7pt;color:#485568}.draft{border:1pt solid #124d85;padding:8pt;margin-bottom:12pt;font-weight:bold}
</style></head><body><header>Soluzioni Assicurative SAS<h1>{{ $title }}</h1></header>@if($draft)<div class="draft">BOZZA DA REVISIONARE - NON DESTINATA ALLA FIRMA</div>@endif
@foreach(preg_split('/\n\s*\n/u',$body) as $paragraph)<p>{{ $paragraph }}</p>@endforeach
<footer>{{ $draft?'BOZZA DA REVISIONARE / ':'' }}{{ $reference }}</footer></body></html>
