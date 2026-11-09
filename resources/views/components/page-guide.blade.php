@props(['floating' => false])
@unless(\App\Support\TableListing::exporting())
<div @class(['page-guide', 'page-guide-floating' => $floating]) data-page-guide data-guide-route="{{ request()->route()?->getName() }}">
    <button class="btn btn-g page-guide-trigger" type="button" data-guide-open aria-haspopup="dialog" aria-controls="page-guide-panel" hidden><i data-lucide="circle-help" width="16" height="16" aria-hidden="true"></i><span>Guida</span></button>
    <dialog class="page-guide-panel" id="page-guide-panel" aria-labelledby="page-guide-title">
        <header class="page-guide-header">
            <h2 id="page-guide-title">Guida</h2>
            <button class="btn btn-g" type="button" data-guide-close autofocus>Chiudi</button>
        </header>
        <div class="page-guide-content" data-guide-content></div>
    </dialog>
    <span class="sr-only" role="status" aria-live="polite" data-guide-status></span>
</div>
@endunless
