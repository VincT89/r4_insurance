function listContext(root) {
    const base = new URL(root.dataset.policyListUrl, location.origin);
    const key = 'r4.policy-list.' + root.dataset.listUser;
    const read = () => {
        try {
            const saved = JSON.parse(sessionStorage.getItem(key));
            const url = new URL(saved.path, location.origin);
            /** Il ritorno all'elenco non deve accettare destinazioni esterne o altre sezioni. */
            return url.origin === base.origin && url.pathname === base.pathname ? { ...saved, path:url.pathname + url.search } : null;
        } catch { return null; }
    };
    const write = saved => { try { sessionStorage.setItem(key, JSON.stringify(saved)); } catch {} };
    return { key, read, write };
}

function initializePolicyDesk() {
    const root = document.querySelector('[data-policy-register], [data-policy-record]');
    if (!root || root.dataset.navigationBound) return;
    root.dataset.navigationBound = '1';
    const context = listContext(root);
    const saved = context.read();
    if (root.hasAttribute('data-policy-register')) {
        const path = location.pathname + location.search;
        if (saved?.restore && saved.path === path) {
            requestAnimationFrame(() => {
                document.getElementById('content-area')?.scrollTo(0, Math.max(0, Number(saved.scroll) || 0));
                window.scrollTo(0, Math.max(0, Number(saved.windowScroll) || 0));
            });
            context.write({ ...saved, restore:false });
        }
        root.addEventListener('click', event => {
            if (!event.target.closest('[data-policy-list-link]')) return;
            context.write({ path, scroll:document.getElementById('content-area')?.scrollTop || 0, windowScroll:window.scrollY, restore:true });
        });
    } else {
        const back = root.querySelector('[data-policy-list-return]');
        if (back && saved) back.href = saved.path;
        root.addEventListener('keydown', event => {
            const menu = root.querySelector('.policy-other-actions[open]');
            if (event.key === 'Escape' && menu) { menu.open = false; menu.querySelector('summary')?.focus(); }
        });
        document.addEventListener('click', event => {
            const menu = root.querySelector('.policy-other-actions[open]');
            if (menu && (!menu.contains(event.target) || event.target.closest('.policy-other-actions a'))) menu.open = false;
        });
    }
    document.querySelectorAll('form[action]').forEach(form => {
        if (new URL(form.action).pathname.endsWith('/logout')) form.addEventListener('submit', () => {
            try { sessionStorage.removeItem(context.key); } catch {}
        }, { once:true });
    });
}

document.addEventListener('DOMContentLoaded', initializePolicyDesk);
document.addEventListener('livewire:navigated', initializePolicyDesk);
