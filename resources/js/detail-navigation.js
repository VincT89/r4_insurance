function revealDetails() {
    let anchor;
    try { anchor = decodeURIComponent(location.hash.slice(1)); } catch { return; }
    const target = document.getElementById(anchor);
    if (!target) return;
    const signatureAction = target.matches('[data-signature-destination]') && target.querySelector('details[data-signature-next]');
    let details = (signatureAction || target).closest('details');
    while (details) {
        details.open = true;
        details = details.parentElement?.closest('details');
    }
    if (signatureAction) target.scrollIntoView({block:'start'});
}

document.addEventListener('DOMContentLoaded', revealDetails);
document.addEventListener('livewire:navigated', revealDetails);
window.addEventListener('hashchange', revealDetails);
document.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#"]');
    if (link && link.hash === location.hash) revealDetails();
});
