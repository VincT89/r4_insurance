import test from 'node:test';
import assert from 'node:assert/strict';
import { resolvePageGuide } from '../../resources/js/page-guide-content.js';

const policy = (extra = {}) => resolvePageGuide({ route: 'clients.policies.show', section: 'overview', paymentState: 'open', ...extra });
const email = (extra = {}) => resolvePageGuide({ route: 'agency-communications.show', messageState: 'review', mailReady: true, recipientReady: true, ...extra });

test('policy guidance follows the active tab rather than always proposing an incasso', () => {
    assert.equal(policy({ section: 'payments' }).title, 'Titoli e pagamenti');
    assert.equal(policy({ section: 'forms' }).title, 'Moduli e firme');
    assert.equal(policy({ section: 'documents' }).title, 'Contratto e documenti');
    assert.equal(policy({ section: 'history' }).title, 'Storico e lavorazione');
    assert.equal(policy({ section: 'commissions' }).title, 'Provvigioni della polizza');
    assert.ok(!policy({ section: 'forms' }).actions.some(item => /incass/i.test(item.text)));
});

test('no outstanding title means history, not instructions to collect again', () => {
    const result = policy({ section: 'payments', paymentState: 'settled' });
    assert.match(result.purpose, /Non ci sono titoli con un residuo/);
    assert.ok(!result.actions.some(item => /^(Registra\b|Scegli il titolo)/i.test(item.text)));
    assert.ok(!policy({ paymentState: 'settled' }).actions.some(item => /incasso|rimborso/.test(item.text)));
});

test('missing titles and an explicit title selection are distinct workflow states', () => {
    assert.match(policy({ section: 'payments', paymentState: 'unprepared' }).purpose, /serve un titolo/);
    const blank = policy({ section: 'payments', titleChosen: false });
    assert.match(blank.notice, /seleziona il titolo/);
    const chosen = policy({ section: 'payments', titleChosen: true });
    assert.equal(chosen.notice, '');
    assert.match(chosen.actions[0].text, /Controlla la rata selezionata/);
});

test('operators without mail settings get a useful explanation without an admin action', () => {
    const operator = email({ mailReady: false, canConfigureMail: false });
    assert.match(operator.notice, /amministratore/);
    assert.ok(!operator.actions.some(item => item.target?.text === 'Impostazioni email'));
    assert.ok(!operator.actions.some(item => /spunta la conferma/.test(item.text)));
    const admin = email({ mailReady: false, canConfigureMail: true });
    assert.ok(admin.actions.some(item => item.target?.text === 'Impostazioni email'));
});

test('a missing recipient prevents advice to confirm sending even with a configured mailbox', () => {
    const result = email({ recipientReady: false });
    assert.match(result.notice, /indirizzo valido/);
    assert.ok(!result.actions.some(item => /spunta/.test(item.text)));
    assert.ok(email().actions.some(item => /spunta/.test(item.text)));
});

test('sent, sending, uncertain and failed messages never suggest a second immediate send', () => {
    assert.equal(email({ messageState: 'sent' }).title, 'Comunicazione inviata');
    for (const messageState of ['sending', 'uncertain', 'failed']) {
        const result = email({ messageState });
        assert.ok(!result.actions.some(item => /spunta|invia ora/i.test(item.text)));
        assert.match(result.check, /prima di|Prima di/);
    }
});

test('expired, reversed and other-user reviews require a fresh summary instead of a send confirmation', () => {
    for (const reviewBlock of ['expired', 'voided', 'other-user']) {
        const result = email({ reviewBlock });
        assert.equal(result.title, 'Aggiorna il riepilogo');
        assert.equal(result.actions.length, 1);
        assert.equal(result.actions[0].target.text, 'Torna alla selezione');
    }
    const dirty = email({ attachmentsChanged: true });
    assert.match(dirty.notice, /Aggiorna allegati/);
    assert.ok(!dirty.actions.some(item => /spunta/.test(item.text)));
});

test('empty or restricted communication groups explain why the quick preparation is unavailable', () => {
    for (const batchState of ['empty', 'limited', 'filtered']) {
        const result = resolvePageGuide({ route: 'agency-communications.collections', batchState });
        assert.ok(result.notice);
        assert.ok(!result.actions.some(item => /usa Prepara email/.test(item.text)));
    }
    assert.ok(resolvePageGuide({ route: 'agency-communications.collections', batchState: 'ready' }).actions.some(item => /usa Prepara email/.test(item.text)));
});

test('company signatures request the real person and their role without assuming consent', () => {
    const company = resolvePageGuide({ route: 'attachments.sign', companySigner: true, documentState: 'loading' });
    assert.match(company.actions[1].text, /qualifica della persona/);
    assert.match(company.actions[2].text, /effettivamente verificato/);
    assert.match(company.notice, /Attendi/);
    const person = resolvePageGuide({ route: 'attachments.sign', companySigner: false, documentState: 'ready' });
    assert.ok(!person.actions[1].text.includes('azienda'));
    assert.equal(person.notice, '');
});

test('client guidance follows the section and keeps policy signing separate from registry work', () => {
    assert.equal(resolvePageGuide({ route: 'clients.show', section: 'vehicles' }).title, 'Veicoli del cliente');
    assert.equal(resolvePageGuide({ route: 'clients.show', section: 'registry' }).title, 'Anagrafica del cliente');
    assert.match(resolvePageGuide({ route: 'clients.show', section: 'documents' }).check, /polizza specifica/);
});

test('a new policy starts with its actual stage: client selection, PDF upload or review', () => {
    const start = resolvePageGuide({ route: 'policies.create', startingPolicy: true, approvedProposalAvailable: false, canRegisterExisting: false });
    assert.equal(start.title, 'Inizia una polizza');
    assert.ok(!start.actions.some(item => /amministratore/.test(item.text)));
    assert.ok(resolvePageGuide({ route: 'policies.create', startingPolicy: true, canRegisterExisting: true }).actions.some(item => /amministratore/.test(item.text)));
    assert.equal(resolvePageGuide({ route: 'policies.create', selectingPolicyClient: true }).title, 'Scegli il cliente');
    assert.equal(resolvePageGuide({ route: 'clients.policies.create', readingPolicyContract: true, contractFieldsReady: false }).title, 'Leggi il contratto PDF');
    assert.equal(resolvePageGuide({ route: 'clients.policies.create', readingPolicyContract: true, contractFieldsReady: true }).title, 'Nuova polizza');
});

test('every workspace family has purposeful guidance, including standalone signing and imports', () => {
    const routes = ['dashboard', 'daily-notes.index', 'clients.index', 'clients.create', 'clients.edit', 'clients.show', 'clients.duplicates', 'clients.merge.preview', 'clients.records.create', 'clients.relationships.edit', 'clients.households.create', 'clients.materials.index', 'clients.documents.create', 'clients.document-requests.edit', 'clients.vehicles.create', 'clients.forms.index', 'clients.forms.create', 'clients.forms.show', 'clients.policies.coverage', 'clients.policies.create', 'clients.policies.edit', 'clients.policies.show', 'clients.policies.changes.create', 'clients.policy-changes.show', 'policies.index', 'policies.create', 'vehicles.index', 'titles.index', 'titles.show', 'titles.create', 'titles.schedule', 'titles.renewal', 'titles.renewal-preview', 'policy-transitions.create', 'policy-transitions.preview', 'collections.index', 'allowances.index', 'allowances.show', 'agency-communications.index', 'agency-communications.collections', 'agency-communications.show', 'agency-communications.history', 'quotes.index', 'quotes.create', 'quotes.show', 'quotes.emails.create', 'quotes.emails.review', 'quote-emails.show', 'quote-email-batches.show', 'claims.index', 'claims.create', 'claims.edit', 'claims.show', 'claims.activity', 'deadlines.index', 'reports.index', 'commissions.index', 'commissions.show', 'suppliers.commissions.index', 'suppliers.commissions.create', 'suppliers.commissions.show', 'suppliers.commissions.edit', 'suppliers.index', 'suppliers.create', 'suppliers.edit', 'suppliers.show', 'supplier-companies.index', 'supplier-companies.create', 'supplier-companies.edit', 'supplier-companies.show', 'insurance-catalog.index', 'insurance-catalog.create', 'insurance-catalog.edit', 'registry-imports.index', 'registry-imports.mapping', 'registry-imports.preview', 'insurance-import.index', 'insurance-import.compare', 'insurance-import.policy-source', 'quote-groups.index', 'quote-groups.create', 'quote-groups.edit', 'form-templates.index', 'form-templates.create', 'form-templates.edit', 'users.index', 'users.create', 'users.edit', 'email-settings.edit', 'appearance.edit', 'profile.edit', 'audit-logs.index', 'attachments.preview', 'attachments.sign', 'login', 'password.setup', 'legal.privacy-policy', 'legal.terms-of-service'];
    for (const route of routes) {
        const result = resolvePageGuide({ route });
        assert.notEqual(result.title, 'Questa pagina', route);
        assert.ok(result.actions.length > 0 && result.actions.length <= 4, route);
        assert.ok(result.check, route);
    }
});

test('unknown routes and unexpected section values have a safe readable fallback', () => {
    assert.equal(resolvePageGuide({ route: 'unknown.<script>' }).title, 'Questa pagina');
    assert.equal(policy({ section: '<img onerror=alert(1)>' }).title, 'La polizza');
    assert.ok(!JSON.stringify(policy({ section: '<script>' })).includes('<script>'));
});
