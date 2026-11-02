function revealDetails() {
    let anchor;
    try { anchor = decodeURIComponent(location.hash.slice(1)); } catch { return; }
    const target = document.getElementById(anchor);
    if (!target) return;
    let details = target.closest('details');
    while (details) {
        details.open = true;
        details = details.parentElement?.closest('details');
    }
}

document.addEventListener('DOMContentLoaded', revealDetails);
document.addEventListener('livewire:navigated', revealDetails);
window.addEventListener('hashchange', revealDetails);
document.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#"]');
    if (link && link.hash === location.hash) revealDetails();
});
