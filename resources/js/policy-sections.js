function selectSection(workspace, key) {
    const sections = [...workspace.querySelectorAll('[data-policy-section]')];
    if (!sections.some(section => section.dataset.policySection === key)) return false;
    for (const section of sections) section.hidden = section.dataset.policySection !== key;
    for (const link of workspace.querySelectorAll('nav [data-policy-section-link]')) {
        if (new URL(link.href).searchParams.get('tab') === key) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    }
    return true;
}

function restoreSection() {
    const workspace = document.querySelector('[data-policy-workspace]');
    if (!workspace) return;
    let target;
    try { target = document.getElementById(decodeURIComponent(location.hash.slice(1))); } catch { return; }
    const anchoredSection = target?.closest('[data-policy-section]');
    const key = anchoredSection?.dataset.policySection || new URL(location.href).searchParams.get('tab');
    if (key && selectSection(workspace, key) && anchoredSection) target.scrollIntoView({ block: 'start' });
}

document.addEventListener('click', event => {
    const link = event.target.closest('[data-policy-section-link]');
    const workspace = document.querySelector('[data-policy-workspace]');
    if (!link || !workspace || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const url = new URL(link.href);
    if (url.origin !== location.origin || url.pathname !== location.pathname) return;
    if (!selectSection(workspace, url.searchParams.get('tab'))) return;
    event.preventDefault();
    history.pushState(null, '', url);
    restoreSection();
    if (url.hash) {
        const target = document.getElementById(url.hash.slice(1));
        if (target) { target.tabIndex = -1; target.focus({ preventScroll: true }); }
    }
});

document.addEventListener('DOMContentLoaded', restoreSection);
document.addEventListener('livewire:navigated', restoreSection);
window.addEventListener('popstate', () => {
    const workspace = document.querySelector('[data-policy-workspace]');
    if (workspace) selectSection(workspace, new URL(location.href).searchParams.get('tab') || 'overview');
    restoreSection();
});
window.addEventListener('hashchange', restoreSection);
