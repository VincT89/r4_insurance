<!doctype html>
<html lang="it">
<head><meta charset="utf-8"><style>
@page {
@if($complete)
    margin: {{ $layout['margin_top'] + ($layout['repeat_header'] && $header !== '' ? $layout['header_space'] : 0) }}mm {{ $layout['margin_right'] }}mm {{ $layout['margin_bottom'] + ($layout['repeat_footer'] && $footer !== '' ? $layout['footer_space'] : 0) }}mm {{ $layout['margin_left'] }}mm;
@else
    margin: 34pt 38pt 45pt;
@endif
}
body { font-family:'DejaVu Sans',sans-serif; font-size:{{ $complete ? 11 : 10 }}pt; line-height:1.45; color:#172033; }
h1 { font-size:15pt; line-height:1.3; margin:8pt 0; }
p { margin:0 0 9pt; white-space:pre-wrap; overflow-wrap:break-word; }
.draft { border:1pt solid #124d85; padding:8pt; margin-bottom:12pt; font-weight:bold; }
.document-text table { width:100%; border-collapse:collapse; table-layout:fixed; margin:6pt 0; }
.document-text td { border:0.6pt solid #77818c; padding:5pt; vertical-align:top; overflow-wrap:break-word; }
.document-text h1,.document-text h2,.document-text h3 { page-break-after:avoid; margin:8pt 0; }
.document-text h2 { font-size:13pt; } .document-text h3 { font-size:11pt; }
.document-text li { margin:2pt 0; } .document-text img { max-width:100%; }
.form-page-break { page-break-before:always; }
@if($complete)
header { margin-bottom:6mm; }
footer { margin-top:6mm; font-size:8pt; color:#485568; }
@if($layout['repeat_header'])
header { position:fixed; top:-{{ $layout['header_space'] }}mm; left:0; right:0; margin:0; }
header img { max-height:{{ $imageLimits['header'] }}mm; width:auto !important; }
@endif
@if($layout['repeat_footer'])
footer { position:fixed; bottom:-{{ $layout['footer_space'] }}mm; left:0; right:0; margin:0; }
footer p { margin-bottom:2pt; }
footer img { max-height:{{ $imageLimits['footer'] }}mm; width:auto !important; }
@endif
@else
header { border-bottom:1pt solid #124d85; margin-bottom:16pt; padding-bottom:8pt; }
footer { position:fixed; bottom:-27pt; font-size:7pt; color:#485568; }
@endif
</style></head>
<body>
@if($complete)
    @if($header !== '')<header class="document-text">{!! $header !!}</header>@endif
    @if($footer !== '' && $layout['repeat_footer'])<footer class="document-text">{!! $footer !!}</footer>@endif
@else
    <header>Soluzioni Assicurative SAS<h1>{{ $title }}</h1></header>
@endif
@if($draft)<div class="draft">BOZZA DA REVISIONARE - NON DESTINATA ALLA FIRMA</div>@endif
<div class="document-text">{!! $content !!}</div>
@if($complete)
    @if($footer !== '' && !$layout['repeat_footer'])<footer class="document-text">{!! $footer !!}</footer>@endif
@else
    <footer>{{ $draft?'BOZZA DA REVISIONARE / ':'' }}{{ $reference }}</footer>
@endif
</body></html>
