function initializeStations() {
    document.querySelectorAll('[data-signing-terminal], [data-station-progress]').forEach(root => {
        if (root.dataset.stationBound) return;
        root.dataset.stationBound = 'true';
        const terminal = root.hasAttribute('data-signing-terminal');
        if (terminal && root.dataset.terminalState === 'unpaired') return;
        if (!terminal && root.dataset.status !== 'pending') return;
        let timer, controller, stopped = false;
        const error = root.querySelector('[data-terminal-error], [data-station-progress-error], [data-signing-error]');
        const navigate = url => {
            const destination = new URL(url, location.href);
            if (destination.origin === location.origin) location.replace(destination.href);
        };
        const poll = async () => {
            if (stopped || !root.isConnected || document.hidden) return;
            controller?.abort(); const activeController = new AbortController(); controller = activeController;
            try {
                const response = await fetch(terminal ? root.dataset.currentUrl : root.dataset.statusUrl, {
                    credentials:'same-origin', cache:'no-store', signal:controller.signal, headers:{Accept:'application/json'},
                });
                if (!response.ok) throw new Error('Collegamento interrotto. Verifica la connessione; il controllo riprende automaticamente.');
                const data = await response.json();
                if (terminal) {
                    if (data.state === 'unpaired' || (root.dataset.terminalState === 'pairing' && data.state !== 'pairing')) { navigate(root.dataset.home); return; }
                    if (data.request_id && data.request_id !== root.dataset.requestId) { navigate(data.url); return; }
                    if (root.dataset.requestId && !data.request_id) { navigate(root.dataset.home); return; }
                } else if (data.status !== root.dataset.status || String(data.completed) !== root.dataset.completed) {
                    location.reload(); return;
                }
                if (error?.hasAttribute('data-terminal-error') || error?.hasAttribute('data-station-progress-error')) error.hidden = true;
            } catch (failure) {
                if (failure.name !== 'AbortError' && error) { error.textContent = failure.message; error.hidden = false; }
            } finally {
                if (!stopped && controller === activeController) timer = setTimeout(poll, 3000);
            }
        };
        root.dataset.completed ||= root.querySelector('[data-station-completed]')?.textContent || '0';
        const wake = () => { clearTimeout(timer); if (!document.hidden) poll(); };
        const stop = () => { stopped = true; clearTimeout(timer); controller?.abort(); document.removeEventListener('visibilitychange',wake); };
        document.addEventListener('visibilitychange',wake);
        window.addEventListener('pagehide',stop,{once:true});
        document.addEventListener('livewire:navigating',stop,{once:true});
        window.addEventListener('pageshow',event=>{ if (event.persisted) location.reload(); });
        poll();
    });
}

document.addEventListener('DOMContentLoaded',initializeStations);
document.addEventListener('livewire:navigated',initializeStations);
