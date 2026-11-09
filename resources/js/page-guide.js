import { resolvePageGuide } from './page-guide-content.js';

let disposeGuide = () => {};

export function findGuideTarget(scope, description) {
    if (!description) return null;
    return [...scope.querySelectorAll(description.selector)].find(element => {
        if (element.closest('[data-page-guide], [hidden], [inert], [aria-hidden="true"]') || element.matches(':disabled, [aria-disabled="true"]')) return false;
        if (!element.getClientRects().length || getComputedStyle(element).visibility === 'hidden') return false;
        const text = (element.textContent || '').trim();
        return !description.text || (typeof description.text === 'string' ? text === description.text : description.text.test(text));
    }) || null;
}

export function readGuideState(root, scope = document) {
    const policy = scope.querySelector('[data-policy-workspace]');
    const message = scope.querySelector('[data-guide-message-state]');
    const payment = scope.querySelector('[data-collection-entry]');
    const title = payment?.querySelector('[name="title_id"]');
    return {
        route: root.dataset.guideRoute,
        section: policy?.dataset.currentSection || scope.querySelector('[data-guide-client-section]')?.dataset.guideClientSection,
        paymentState: policy?.dataset.guidePayments || (payment ? 'open' : 'settled'),
        titleChosen: Boolean(payment && (!title || title.value)),
        messageState: message?.dataset.guideMessageState,
        batchState: scope.querySelector('[data-guide-batches]')?.dataset.guideBatches,
        reviewBlock: message?.dataset.guideReviewBlock,
        attachmentsChanged: Boolean(scope.querySelector('.email-review-confirm input[name="reviewed"]:disabled')),
        mailReady: message?.dataset.guideMailReady === '1',
        recipientReady: message?.dataset.guideRecipientReady === '1',
        canConfigureMail: Boolean(findGuideTarget(scope, { selector: 'a', text: 'Impostazioni email' })),
        companySigner: Boolean(scope.querySelector('#signer-role[required]')),
        documentState: scope.querySelector('[data-document-preview]')?.dataset.state,
        startingPolicy: Boolean(scope.querySelector('.policy-start')),
        approvedProposalAvailable: Boolean(scope.querySelector('#field-quote-id')),
        canRegisterExisting: Boolean(findGuideTarget(scope, { selector: 'a', text: 'Registra polizza già stipulata' })),
        selectingPolicyClient: root.dataset.guideRoute === 'policies.create' && Boolean(scope.querySelector('#field-search')) && !scope.querySelector('form[method="post" i]'),
        readingPolicyContract: Boolean(scope.querySelector('[data-policy-contract]')),
        contractFieldsReady: Boolean(scope.querySelector('[data-contract-fields]:not([hidden])')),
    };
}

function initPageGuide() {
    disposeGuide();
    const root = document.querySelector('[data-page-guide]');
    if (!root) return;
    const opener = root.querySelector('[data-guide-open]');
    const dialog = root.querySelector('dialog');
    if (typeof dialog.showModal !== 'function') return;
    const content = root.querySelector('[data-guide-content]');
    const scope = document.getElementById('content-area') || document.querySelector('main') || document.body;
    const events = new AbortController();
    const signal = events.signal;
    let highlighted = null;
    let previousTabIndex = null;
    let highlighting = false;
    let frame = null;
    let revision = '';

    const clearHighlight = () => {
        if (!highlighted) return;
        highlighted.classList.remove('page-guide-highlight');
        if (previousTabIndex === null) highlighted.removeAttribute('tabindex');
        else highlighted.setAttribute('tabindex', previousTabIndex);
        highlighted = null;
        root.querySelector('[data-guide-status]').textContent = '';
    };
    const close = () => {
        dialog.close();
        if (!highlighting && opener.isConnected) opener.focus({ preventScroll: true });
    };
    const showTarget = (description, instruction) => {
        /** Il bersaglio viene cercato di nuovo: tab, permessi e stato potrebbero essere cambiati dalla prima apertura. */
        const element = findGuideTarget(scope, description);
        if (!element) { render(); return; }
        clearHighlight();
        highlighting = true;
        close();
        previousTabIndex = element.getAttribute('tabindex');
        highlighted = element;
        if (!element.matches('a[href], button, input, select, textarea, summary, [tabindex]')) element.setAttribute('tabindex', '-1');
        element.classList.add('page-guide-highlight');
        element.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
        element.focus({ preventScroll: true });
        root.querySelector('[data-guide-status]').textContent = `Controllo evidenziato. ${instruction}`;
        highlighting = false;
    };
    const appendText = (parent, tag, value, className) => {
        const element = document.createElement(tag);
        element.textContent = value;
        if (className) element.className = className;
        parent.append(element);
        return element;
    };
    const render = () => {
        const state = readGuideState(root, scope);
        const model = resolvePageGuide(state);
        const availability = model.actions.map(item => Boolean(findGuideTarget(scope, item.target)));
        const nextRevision = JSON.stringify([model.title, model.purpose, model.actions.map(item => item.text), model.check, model.notice, availability]);
        if (nextRevision === revision) return;
        revision = nextRevision;
        const fragment = document.createDocumentFragment();
        appendText(fragment, 'h3', model.title);
        appendText(fragment, 'p', model.purpose);
        if (model.notice) appendText(fragment, 'p', model.notice, 'page-guide-notice');
        appendText(fragment, 'h4', 'Cosa fare');
        const actions = document.createElement('ul');
        actions.className = 'page-guide-actions';
        model.actions.forEach((item, index) => {
            const row = document.createElement('li');
            appendText(row, 'p', item.text);
            if (availability[index]) {
                const button = appendText(row, 'button', 'Mostrami dove', 'btn btn-g page-guide-show');
                button.type = 'button';
                button.setAttribute('aria-label', `Mostrami dove: ${item.text}`);
                button.addEventListener('click', () => showTarget(item.target, item.text), { signal });
            }
            actions.append(row);
        });
        fragment.append(actions);
        appendText(fragment, 'h4', 'Prima di confermare');
        appendText(fragment, 'p', model.check);
        content.replaceChildren(fragment);
    };
    opener.hidden = false;
    opener.addEventListener('click', () => {
        clearHighlight();
        render();
        dialog.showModal();
    }, { signal });
    root.querySelector('[data-guide-close]').addEventListener('click', close, { signal });
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); }, { signal });
    dialog.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const controls = [...dialog.querySelectorAll('button:not(:disabled), a[href], [tabindex="0"]')].filter(element => element.getClientRects().length);
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }, { signal });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close();
    }, { signal });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') clearHighlight(); }, { signal });
    scope.addEventListener('click', event => {
        if (highlighted && (event.target === highlighted || highlighted.contains(event.target))) clearHighlight();
    }, { signal });
    /** L’osservazione aggiorna le istruzioni quando cambia la disponibilità dei controlli, senza richieste di rete. */
    const observer = new MutationObserver(() => {
        if (!dialog.open || frame !== null) return;
        frame = requestAnimationFrame(() => { frame = null; render(); });
    });
    observer.observe(scope, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'disabled', 'data-current-section', 'data-state', 'aria-disabled'] });
    scope.addEventListener('change', () => { if (dialog.open) render(); }, { signal });
    disposeGuide = () => {
        events.abort();
        observer.disconnect();
        if (frame !== null) cancelAnimationFrame(frame);
        clearHighlight();
        if (dialog.open) dialog.close();
    };
}

document.addEventListener('DOMContentLoaded', initPageGuide);
document.addEventListener('livewire:navigated', initPageGuide);
document.addEventListener('livewire:navigating', () => disposeGuide());
