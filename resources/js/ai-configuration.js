const initialized = new WeakSet();

export function initializeAiConfiguration(root) {
    if (initialized.has(root)) return;
    initialized.add(root);
    const settingsForm = root.querySelector('[data-ai-settings-form]');
    const checkForm = root.querySelector('[data-ai-connection-form]');
    const editor = root.querySelector('#ai-key-editor');
    const key = settingsForm.elements.ai_api_key;
    const replacement = settingsForm.elements.replace_api_key;
    const model = settingsForm.elements.model;
    const enabled = settingsForm.querySelector('input[type="checkbox"][name="enabled"]');
    const edit = root.querySelector('[data-ai-key-edit]');
    const cancel = root.querySelector('[data-ai-key-cancel]');
    const check = root.querySelector('[data-ai-connection-button]');
    const hint = root.querySelector('[data-ai-check-hint]');
    const result = root.querySelector('[data-ai-connection-result]');
    const saved = root.dataset.keySaved === 'true';
    let pending = null;

    const unsaved = () => !saved || replacement.value === '1' || model.value !== root.dataset.savedModel;
    const refresh = () => {
        check.disabled = !!pending || unsaved();
        check.textContent = pending ? 'Verifica in corso…' : 'Verifica collegamento';
        checkForm.setAttribute('aria-busy', pending ? 'true' : 'false');
        hint.hidden = !unsaved();
        hint.textContent = saved ? 'Salva la nuova chiave o il modello prima di verificare.' : 'Salva la chiave per verificare il collegamento.';
        key.required = replacement.value === '1' || (!saved && enabled.checked);
    };
    const invalidate = () => {
        pending?.abort();
        pending = null;
        result.hidden = true;
        result.textContent = '';
        refresh();
    };
    edit?.addEventListener('click', () => {
        editor.hidden = false;
        key.disabled = false;
        replacement.value = '1';
        edit.hidden = true;
        edit.setAttribute('aria-expanded', 'true');
        invalidate();
        key.focus();
    });
    cancel?.addEventListener('click', () => {
        /** Annullare elimina solo il testo appena inserito: la credenziale salvata non arriva mai nel browser. */
        key.value = '';
        key.disabled = true;
        replacement.value = '0';
        editor.hidden = true;
        edit.hidden = false;
        edit.setAttribute('aria-expanded', 'false');
        invalidate();
        edit.focus();
    });
    model.addEventListener('change', invalidate);
    enabled.addEventListener('change', refresh);
    settingsForm.addEventListener('submit', invalidate);
    checkForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (pending || unsaved()) return;
        const controller = new AbortController();
        pending = controller;
        refresh();
        result.hidden = false;
        result.dataset.state = 'pending';
        result.textContent = 'Verifica del collegamento OpenAI in corso.';
        const timeout = window.setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(checkForm.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(checkForm), signal: controller.signal,
            });
            if (pending !== controller) return;
            if (!response.ok) {
                result.dataset.state = 'error';
                result.textContent = response.status === 429 ? 'Troppe verifiche ravvicinate. Attendi un minuto e riprova.'
                    : [401, 403, 419].includes(response.status) ? 'La sessione non consente la verifica. Ricarica la pagina e accedi nuovamente se richiesto.'
                    : 'Verifica non completata. Ricarica la pagina e riprova.';
                return;
            }
            const data = await response.json();
            if (pending !== controller) return;
            if (!['success', 'error'].includes(data.state) || typeof data.message !== 'string') throw new Error('Invalid connection response');
            result.dataset.state = data.state;
            result.textContent = data.message + (typeof data.checked_at === 'string' ? ` Verifica del ${data.checked_at}.` : '');
        } catch {
            if (pending !== controller) return;
            result.dataset.state = 'error';
            result.textContent = 'La verifica non è terminata. Controlla la connessione e riprova.';
        } finally {
            window.clearTimeout(timeout);
            if (pending === controller) {
                pending = null;
                refresh();
            }
        }
    });
    refresh();
}

function initialize() {
    document.querySelectorAll('[data-ai-configuration]').forEach(initializeAiConfiguration);
}

document.addEventListener('DOMContentLoaded', initialize);
document.addEventListener('livewire:navigated', initialize);
