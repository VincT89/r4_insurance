document.addEventListener('submit', async (event) => {
    if (event.defaultPrevented) return;

    const toggleForm = event.target.closest('.js-checklist-toggle');
    const storeForm = event.target.closest('.js-checklist-store');
    const destroyForm = event.target.closest('.js-checklist-destroy');

    if (toggleForm) {
        event.preventDefault();
        await handleToggle(toggleForm);
    } else if (storeForm) {
        event.preventDefault();
        await handleStore(storeForm);
    } else if (destroyForm) {
        event.preventDefault();
        await handleDestroy(destroyForm);
    }
});

async function handleToggle(form) {
    const row = form.closest('[data-checklist-item]');
    const button = row?.querySelector('[data-checklist-toggle-button]');
    const title = row?.querySelector('[data-checklist-title]');
    const completedBy = row?.querySelector('[data-checklist-completed-by]');
    const counter = document.querySelector('[data-checklist-counter]');

    if (!row || !button || !title) return;

    button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
        });

        if (!response.ok) throw new Error('Errore aggiornamento checklist');

        const data = await response.json();

        button.textContent = data.is_completed ? '✓' : '○';

        title.classList.toggle('u-text-muted', data.is_completed);
        title.classList.toggle('task-checklist-completed', data.is_completed);
        title.classList.toggle('u-text-strong', !data.is_completed);

        const completedByWrapper = row.querySelector('[data-checklist-completed-by-wrapper]');
        if (completedByWrapper) {
            completedByWrapper.classList.toggle('task-checklist-hidden', !data.is_completed);
        }

        if (completedBy) {
            completedBy.textContent = data.is_completed ? (data.completed_by_name || data.completed_by) : '';
        }

        if (counter) {
            counter.textContent = `${data.done}/${data.total} completati`;
        }
    } catch (error) {
        console.error(error);
        alert('Non riesco ad aggiornare la checklist. Riprova.');
    } finally {
        button.disabled = false;
    }
}

async function handleStore(form) {
    const container = document.querySelector('.js-checklist-container');
    const counter = document.querySelector('[data-checklist-counter]');
    const emptyState = document.querySelector('.js-checklist-empty');
    const button = form.querySelector('button[type="submit"]');
    const input = form.querySelector('input[name="title"]');

    if (!container || !input) return;

    if (button) button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
        });

        if (!response.ok) throw new Error('Errore aggiunta checklist');

        const data = await response.json();

        if (emptyState) emptyState.remove();

        container.insertAdjacentHTML('beforeend', data.html);
        input.value = '';

        if (counter) {
            counter.textContent = `${data.done}/${data.total} completati`;
        }
    } catch (error) {
        console.error(error);
        alert('Non riesco ad aggiungere la voce alla checklist. Riprova.');
    } finally {
        if (button) button.disabled = false;
    }
}

async function handleDestroy(form) {
    let row = form.closest('[data-checklist-item]');
    
    if (!row && form.dataset.itemId) {
        row = document.querySelector(`[data-checklist-item="${form.dataset.itemId}"]`);
    }

    const counter = document.querySelector('[data-checklist-counter]');
    const button = form.querySelector('button[type="submit"]');

    if (!row) return;
    if (button) button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
        });

        if (!response.ok) throw new Error('Errore eliminazione checklist');

        const data = await response.json();

        row.remove();

        if (counter) {
            counter.textContent = `${data.done}/${data.total} completati`;
        }
    } catch (error) {
        console.error(error);
        alert('Non riesco ad eliminare la voce. Riprova.');
        if (button) button.disabled = false;
    }
}
