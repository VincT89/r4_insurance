<nav class="client-section-nav client-main-nav" aria-label="Sezioni della scheda agenzia">
    <a href="{{ route('suppliers.show', [$supplier, 'tab'=>'registry']) }}" @if($tab === 'registry') aria-current="page" @endif>Anagrafica</a>
    <a href="{{ route('suppliers.show', [$supplier, 'tab'=>'commissions']) }}" @if($tab === 'commissions') aria-current="page" @endif>Provvigioni</a>
</nav>
