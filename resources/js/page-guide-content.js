const target = (selector, text) => ({ selector, ...(text ? { text } : {}) });
const action = (text, selector, label) => ({ text, target: selector ? target(selector, label) : null });
const guide = (title, purpose, actions, check, notice = '') => ({ title, purpose, actions, check, notice });
const save = action('Rileggi i dati, poi salva. Gli eventuali errori indicano cosa correggere.', 'button[type="submit"], button:not([type])', /^Salva|^Crea|^Registra|^Conferma/);
const search = action('Usa la ricerca o i filtri per trovare quello che ti serve.', 'input[type="search"], input[name="q"], input[name="search"], #communication-search > summary, form[method="get" i] select, form[method="get" i] input:not([type="hidden"])');
const rows = action('Apri la riga che ti interessa per vedere i dettagli e le operazioni disponibili.', 'table tbody a[href]');
const form = action('Compila i campi richiesti. Le altre informazioni si possono aggiungere quando sono disponibili.', 'form input:not([type="hidden"]):not([type="checkbox"]), form select, form textarea');
const file = action('Scegli il file da caricare e controlla il risultato prima di confermare.', 'input[type="file"]');
const list = (title, purpose, check, extra = []) => guide(title, purpose, [search, ...extra, rows], check);
const entry = (title, purpose, check, extra = []) => guide(title, purpose, [form, ...extra, save], check);
const sectionLink = (key) => `.policy-section-nav a[href*="tab=${key}"]`;

function paymentGuide(state, title = 'Titoli e pagamenti') {
    if (state.paymentState === 'unprepared') return guide(title, 'Prima di registrare un pagamento serve un titolo, cioè la rata con il suo importo e la sua scadenza.', [
        action('Prepara i titoli dal frazionamento della polizza e controlla le date.', 'a', 'Prepara titoli'),
        action('Consulta il contratto se devi verificare importi e frazionamento.', sectionLink('overview')),
    ], 'La somma delle rate deve corrispondere al periodo e al premio previsti dal contratto.');
    if (state.paymentState === 'settled') return guide(title, 'Non ci sono titoli con un residuo da registrare.', [
        action('Consulta i pagamenti già registrati e gli eventuali storni.', '#payments'),
        action('Controlla lo stato delle singole rate nell’elenco dei titoli.', '.policy-title-list'),
    ], 'Non registrare un secondo pagamento per una rata già saldata. Controlla anche gli eventuali titoli annullati.');
    return guide(title, 'Registra il pagamento sulla rata corretta della polizza.', [
        action(state.titleChosen ? 'Controlla la rata selezionata e il suo importo.' : 'Scegli il titolo da saldare. Controlla scadenza e importo.', '[data-collection-entry] select[name="title_id"], [data-collection-entry] [name="amount"]'),
        action('Indica la data effettiva, la modalità e chi ha ricevuto il pagamento. Per un rimborso indica chi lo ha effettuato.', '[data-collection-entry] [name="received_on"]'),
        action('Rileggi il riepilogo e registra il pagamento una sola volta.', '[data-collection-entry] button[type="submit"], [data-collection-entry] button:not([type])'),
    ], 'Un incasso comunica che il pagamento è già avvenuto. La registrazione non invia denaro e non invia automaticamente un’email all’agenzia.', state.titleChosen ? '' : 'Prima di confermare seleziona il titolo: una rata futura può richiedere una scelta esplicita.');
}

function policyGuide(state) {
    const section = state.section || 'overview';
    if (section === 'payments') return paymentGuide(state);
    if (section === 'documents') return guide('Contratto e documenti', 'Qui trovi i documenti collegati a questa polizza.', [
        action('Apri il contratto per leggerlo o scaricarlo.', '#policy-contracts a[href], #policy-documents a[href]'),
        action('Carica i documenti mancanti scegliendo la categoria corretta.', 'a', /^Carica (altro )?documento$/),
        action('Per i moduli da firmare apri Moduli e firme.', sectionLink('forms')),
    ], 'Verifica cliente, numero di polizza e contenuto prima di far firmare un documento.');
    if (section === 'forms') return guide('Moduli e firme', 'Prepara i moduli per questa polizza e completa le firme mancanti.', [
        action('Usa il comando di preparazione se i moduli non sono ancora presenti.', 'a', /^Prepara moduli/),
        action('Apri il documento da firmare o da controllare. Lo stato indica cosa manca.', '#policy-signatures a[href], [data-policy-signatures] a[href]'),
        action('Per il contratto della compagnia usa la firma del contratto.', 'a', /Firma contratto|Contratto e documenti/),
    ], 'Preparare i moduli non significa averli firmati. Controlla lo stato di ogni documento prima di chiudere la pratica.');
    if (section === 'history') return guide('Storico e lavorazione', 'Ricostruisci cosa è stato fatto sulla polizza e quali verifiche restano aperte.', [
        action('Leggi lo storico e le eventuali richieste di correzione.', '[data-policy-section="history"]'),
        action('Apri i contratti collegati per seguire rinnovi e sostituzioni.', '.policy-lineage a[href], a', /precedente|successiva|sostitutiva/i),
    ], 'Un rinnovo o un cambio compagnia conserva il collegamento alla polizza precedente.');
    if (section === 'commissions') return guide('Provvigioni della polizza', 'Controlla i calcoli e i movimenti collegati ai titoli.', [
        action('Apri il titolo interessato per vedere il dettaglio del calcolo.', '[data-policy-section="commissions"] a[href]'),
    ], 'Verifica accordo applicato, importi e movimenti già registrati prima di confermare altre operazioni.');
    const actions = [action('Controlla compagnia, date, premio e cosa è assicurato.', '#policy-overview')];
    if (state.paymentState === 'open') actions.push(action('Per registrare un incasso o un rimborso apri il pagamento.', '[data-policy-primary-payment]'));
    if (state.paymentState === 'unprepared') actions.push(action('Prepara i titoli se devi registrare le rate della polizza.', 'a', 'Prepara titoli'));
    actions.push(action('Per preparare i moduli e completare le firme apri Moduli e firme.', sectionLink('forms')));
    return guide('La polizza', 'Tutti i dati e le operazioni di questo contratto sono raccolti qui.', actions,
        'Per rinnovi, cambio compagnia e sostituzioni usa i comandi della polizza: lo storico resta collegato.',
        state.paymentState === 'settled' ? 'Non ci sono titoli con un residuo da registrare. I pagamenti restano consultabili in Titoli e pagamenti.' : '');
}

function communicationGuide(state) {
    if (state.messageState === 'sent') return guide('Comunicazione inviata', 'Qui trovi il riepilogo dell’invio e i documenti inclusi.', [
        action('Consulta lo storico per gli altri invii.', 'a', 'Storico invii'),
        action('Controlla il contenuto e gli allegati di questa comunicazione.', '.communication-message-preview > summary'),
    ], 'Evita di preparare un secondo invio degli stessi dati senza aver controllato lo storico.');
    if (['sending', 'uncertain'].includes(state.messageState)) return guide('Verifica l’invio', state.messageState === 'sending' ? 'L’invio è in corso.' : 'Il servizio di posta non ha confermato l’esito.', [
        action('Controlla lo stato e le istruzioni presenti in fondo alla pagina.', '[name="outcome"]'),
    ], 'Prima di riprovare verifica l’esito con il servizio di posta. Se serve, chiedi all’amministratore di registrare l’esito. Non ripetere subito l’invio.');
    if (state.messageState && state.messageState !== 'review') return guide('Esito della comunicazione', 'Leggi lo stato registrato per questo tentativo di invio.', [
        action('Torna alla selezione per preparare un riepilogo aggiornato quando necessario.', 'a', 'Torna alla selezione'),
        action('Consulta gli invii precedenti.', 'a', 'Storico invii'),
    ], 'Prima di riprovare controlla che gli stessi dati non siano già stati inviati.');
    if (state.reviewBlock) {
        const reasons = {
            expired: 'Il riepilogo è scaduto: prepara una nuova selezione con i dati aggiornati.',
            voided: 'Uno o più incassi sono stati stornati: prepara una nuova selezione con gli incassi aggiornati.',
            'other-user': 'Il riepilogo può essere confermato solo dall’utente che lo ha preparato. Puoi prepararne uno nuovo.',
        };
        return guide('Aggiorna il riepilogo', reasons[state.reviewBlock] || 'Controlla il motivo del blocco indicato nella pagina.', [
            action('Torna alla selezione e prepara il riepilogo aggiornato per l’agenzia.', 'a', 'Torna alla selezione'),
        ], 'Controlla di nuovo destinatario, contenuto e allegati prima di confermare l’invio.');
    }
    const actions = [
        action('Controlla destinatario, polizze o incassi selezionati e importi.', '.communication-selected-records > summary, .communication-summary'),
        action('Controlla gli allegati. Se li cambi, salva la selezione prima dell’invio.', '.communication-attachment-edit > summary'),
    ];
    let notice = '';
    if (!state.mailReady) {
        notice = state.canConfigureMail ? 'Invio bloccato: configura la casella in Impostazioni email, poi prepara un nuovo riepilogo.' : 'Invio bloccato: chiedi a un amministratore di configurare la casella email, poi prepara un nuovo riepilogo.';
        if (state.canConfigureMail) actions.push(action('Apri le impostazioni per completare la casella email.', 'a', 'Impostazioni email'));
    } else if (!state.recipientReady) notice = 'Manca un indirizzo valido dell’agenzia. Completa l’email nella sua scheda, poi prepara un nuovo riepilogo.';
    else if (state.attachmentsChanged) notice = 'Hai modificato gli allegati: premi Aggiorna allegati e ricontrolla il riepilogo prima dell’invio.';
    else actions.push(action('Dopo il controllo spunta la conferma e usa il pulsante di invio.', '.email-review-confirm input[type="checkbox"]'));
    if (!state.mailReady && !state.recipientReady) notice += ' Manca anche un indirizzo valido dell’agenzia.';
    return guide('Controlla l’email', 'Questo è il riepilogo da verificare prima dell’invio all’agenzia.', actions, 'Preparare il riepilogo non invia l’email. L’invio richiede la tua conferma e tutti i controlli previsti dalla pagina.', notice);
}

function clientGuide(state) {
    const section = state.section || 'policies';
    const content = {
        policies: ['Polizze del cliente', 'Consulta i contratti del cliente o aprine uno nuovo.', [action('Apri la polizza dall’elenco per lavorare su rate, documenti e firme.', 'table tbody a[href]'), action('Per un nuovo contratto usa Nuova polizza.', 'a', 'Nuova polizza')], 'Controlla sempre di essere nella scheda del contraente corretto.'],
        registry: ['Anagrafica del cliente', 'Controlla identità, recapiti e dati fiscali.', [action('Apri Modifica anagrafica per aggiornare i dati.', 'a', 'Modifica anagrafica')], 'Usa i dati del documento o dell’azienda e controlla le segnalazioni sui duplicati.'],
        vehicles: ['Veicoli del cliente', 'Registra i veicoli da collegare alle polizze auto.', [action('Aggiungi il veicolo o apri quello da modificare.', 'a[href*="/vehicles/create"], a[href*="/vehicles/"][href*="/edit"]')], 'Controlla targa, proprietario e dati del libretto prima di collegare il veicolo a una polizza.'],
        documents: ['Documenti e firme del cliente', 'Trova documenti, richieste e moduli del cliente.', [action('Carica un documento indicando la categoria e l’eventuale polizza.', 'a[href*="/documents/create"]'), action('Apri il documento o il fascicolo per controllare le firme.', 'table tbody a[href]')], 'Per moduli di una polizza specifica apri la polizza e usa Moduli e firme.'],
        deadlines: ['Scadenze del cliente', 'Controlla le attività e i documenti in scadenza per questo cliente.', [rows], 'Apri la pratica interessata prima di registrare un aggiornamento.'],
        network: ['Referenti e relazioni', 'Collega le persone e le relazioni utili alla gestione del cliente.', [action('Aggiungi o modifica il referente o la relazione pertinente.', 'a[href*="/records/contacts/"], a[href*="/relationships/"], a[href*="/households/"]')], 'Una relazione deve descrivere un legame reale e verificato.'],
        assets: ['Immobili e beni', 'Registra il bene che vuoi collegare a una polizza.', [action('Aggiungi o modifica il bene.', 'a[href*="/records/assets/"]')], 'Controlla indirizzo e caratteristiche del bene assicurato.'],
        history: ['Contatti e note', 'Leggi e aggiorna la storia dei contatti con il cliente.', [action('Aggiungi un contatto o una nota, oppure apri una voce da aggiornare.', 'a[href*="/records/interactions/"]')], 'Scrivi solo informazioni utili alla gestione della pratica.'],
        consents: ['Consensi del cliente', 'Consulta e registra i consensi effettivamente raccolti.', [action('Aggiungi o aggiorna una dichiarazione documentata.', 'a[href*="/records/consents/"]')], 'Registra la scelta del cliente e la documentazione disponibile senza presumere il consenso.'],
        commissions: ['Provvigioni del cliente', 'Consulta i calcoli dei titoli collegati alle sue polizze.', [rows], 'Controlla l’accordo applicato e gli importi prima di registrare movimenti.'],
    };
    return guide(...(content[section] || content.policies));
}

export function resolvePageGuide(state = {}) {
    const route = state.route || '';
    if (route === 'dashboard') return guide('Dashboard', 'Parti dalle cose da fare oggi e apri direttamente la pratica interessata.', [
        action('Apri un titolo da incassare per registrare il pagamento.', '#due-titles'),
        action('Controlla le polizze inserite oggi e il loro stato di comunicazione.', '#today-policies'),
        action('Per iniziare un contratto usa Nuova polizza.', 'a', 'Nuova polizza'),
    ], 'Ogni elenco ha i propri filtri. Se manca una pratica, controlla il periodo selezionato.');
    if (route === 'clients.policies.show') return policyGuide(state);
    if (route === 'titles.show' && state.paymentState === 'open') return paymentGuide(state, 'Pagamento del titolo');
    if (route === 'agency-communications.show') return communicationGuide(state);
    if (route === 'clients.show') return clientGuide(state);
    if (route === 'attachments.sign') return guide('Firma sul tablet', 'L’operatore prepara il documento. Il cliente lo legge e firma.', [
        action('Leggi il documento e scegli il punto firma. Controlla che lo spazio sia libero.', '[data-place-toggle]'),
        action(state.companySigner ? 'L’operatore verifica nome, cognome e qualifica della persona che firma per l’azienda.' : 'L’operatore verifica nome e cognome del firmatario.', '#signer-name'),
        action('Il cliente disegna la firma nel riquadro. Prima di salvare, operatore e cliente confermano solo ciò che hanno effettivamente verificato.', '[data-signing-pad]'),
    ], 'Controlla i punti firma e la copia salvata. L’originale resta nello storico. La firma grafica non viene dichiarata avanzata o qualificata.', state.documentState === 'loading' ? 'Attendi che il documento sia leggibile prima di scegliere i punti firma.' : '');
    if (route === 'attachments.preview') return guide('Leggi il documento', 'Sfoglia il PDF senza uscire dal gestionale.', [
        action('Cambia pagina oppure adatta lo zoom.', '[data-page-number], [data-pdf-zoom]'),
        action('Cerca una parola nel documento.', '[data-pdf-query]'),
        action('Se ti serve una copia usa Scarica PDF.', 'a', 'Scarica PDF'),
    ], 'Controlla che il documento riguardi la pratica giusta prima di tornare alla polizza.');
    if (['policies.create', 'clients.policies.create', 'clients.policies.edit'].includes(route)) {
        if (state.startingPolicy) {
            const actions = state.approvedProposalAvailable ? [
                action('Scegli la proposta già approvata dal cliente.', '#field-quote-id'),
                action('Usa Compila la polizza per passare al contratto.', 'button', 'Compila la polizza'),
            ] : [action('Apri i preventivi per completare una proposta oppure richiederne una nuova.', 'a', 'Consulta i preventivi')];
            if (state.canRegisterExisting) actions.push(action('Per un contratto già stipulato usa la registrazione riservata all’amministratore.', 'a', 'Registra polizza già stipulata'));
            return guide('Inizia una polizza', 'Per una nuova stipula parti dalla proposta approvata dal cliente.', actions, 'Controlla contraente e proposta prima di proseguire al contratto PDF.');
        }
        if (state.selectingPolicyClient) return guide('Scegli il cliente', 'Trova il contraente a cui intestare la nuova polizza.', [
            action('Cerca il cliente per nome, dati fiscali o contatto.', '#field-search'),
            action('Premi Seleziona sulla riga del cliente corretto.', 'a[aria-label^="Seleziona "]'),
            action('Se non è ancora registrato, usa Nuovo cliente.', 'a', 'Nuovo cliente'),
        ], 'Controlla che non esista già una scheda dello stesso cliente prima di crearne una nuova.');
        if (state.readingPolicyContract && !state.contractFieldsReady) return guide('Leggi il contratto PDF', 'Inizia dal contratto da registrare. I dati estratti compariranno per il controllo.', [
            action('Scegli il PDF del contratto.', '#field-contract'),
            action('Attendi la lettura e segui le eventuali segnalazioni.', '[data-contract-status]'),
        ], 'I dati letti dal PDF vanno confrontati con il documento prima di creare la polizza.');
        return guide(route.endsWith('.edit') ? 'Modifica polizza' : 'Nuova polizza', 'Collega il contraente e controlla i dati del contratto prima di salvare.', [
        action('Scegli il cliente corretto. Se manca, puoi crearlo dai comandi presenti.', '[data-client-search], #field-search, input[name="number"]'),
        action('Se stai leggendo un contratto PDF, caricalo e verifica i dati estratti confrontandoli con il documento.', '#field-contract, input[name="number"]'),
        action('Controlla compagnia, date, premio, frazionamento e titoli, poi salva.', 'form[method="post" i] button[type="submit"]', /^(Conferma|Salva)/),
    ], 'I dati letti dal PDF vanno verificati. I moduli e le firme si completano dalla polizza dopo il salvataggio.');
    }
    if (route.startsWith('agency-communications.')) {
        if (route.endsWith('.history')) return list('Storico comunicazioni', 'Controlla cosa è stato preparato e quale esito ha avuto l’invio.', 'Apri il messaggio per distinguere una bozza da un invio confermato.');
        const actions = [
            action('Controlla il periodo. Per cambiare data apri Filtra elenco.', '#communication-search > summary'),
        ];
        if (!state.batchState || state.batchState === 'ready') actions.push(action('Per includere il gruppo usa Prepara email accanto all’agenzia.', '.communication-batches button', /^Prepara email/));
        actions.push(action('Se vuoi includere solo alcune righe, apri la scelta dei singoli elementi e selezionali.', 'summary', /^Scegli singol/));
        const notices = {
            empty: 'Non ci sono gruppi pronti con i filtri attuali. Controlla il periodo e le eventuali righe da completare.',
            limited: 'Ci sono più di 500 operazioni: scegli un’agenzia o restringi il periodo in Filtra elenco.',
            filtered: 'È attivo un filtro sulle singole righe: usa la selezione dei singoli elementi nell’elenco.',
        };
        return guide(route.endsWith('.collections') ? 'Comunica gli incassi' : 'Comunica le polizze', 'Prepara il riepilogo per l’agenzia e controllalo prima di inviarlo.', actions, 'Il riepilogo si prepara per agenzia. Prima dell’invio controlla destinatario, importi e allegati.', notices[state.batchState] || '');
    }
    if (route.startsWith('clients.forms.')) {
        if (route.endsWith('.create')) return entry('Prepara i moduli', 'Scegli la polizza e i modelli da compilare con i dati del cliente.', 'Controlla l’anteprima e includi solo i moduli necessari per questa pratica.');
        if (route.endsWith('.show')) return guide('Fascicolo moduli', 'Controlla i documenti preparati e completa le firme richieste.', [
            action('Apri il modulo da leggere o firmare.', 'a', /Firma sul tablet|Firma modulo|Apri modulo PDF|Leggi|Visualizza/),
            action('Se hai una copia firmata, usa il caricamento previsto per quel documento.', 'summary', /Carica|firma/i),
        ], 'Ogni modulo ha il proprio stato. Una copia caricata può richiedere il controllo dell’operatore.');
        return list('Moduli e firme', 'Trova i fascicoli già preparati per questo cliente.', 'Per preparare moduli nuovi scegli la polizza corretta.', [action('Prepara un nuovo fascicolo con i modelli necessari.', 'a', /Prepara moduli|Nuovi moduli/)]);
    }
    if (route.startsWith('form-templates.')) {
        if (route.endsWith('.index')) return list('Modelli dei moduli', 'Gestisci i testi riutilizzabili per preparare i moduli dei clienti.', 'Controlla lo stato del modello: una modifica non aggiorna i documenti già generati.', [action('Crea un modello oppure apri quello da modificare.', 'a', /Nuovo modello/)]);
        return entry('Editor del modello', 'Scrivi il testo e usa i campi del gestionale per compilare i dati del cliente e della polizza.', 'Apri l’anteprima, verifica i campi e controlla lo stato di approvazione prima di usarlo.', [action('Modifica il contenuto e inserisci i campi disponibili nell’editor.', '[contenteditable="true"]')]);
    }
    if (route.startsWith('policy-transitions.') || route === 'titles.renewal' || route === 'titles.renewal-preview') return entry('Rinnova o cambia compagnia', 'Scegli come prosegue la copertura e controlla i dati della nuova annualità.', 'Per un cambio compagnia verifica nuovo contratto e date. La nuova polizza resta collegata alla precedente con tutto lo storico.');
    if (route.startsWith('clients.policies.changes.') || route.startsWith('clients.policy-changes.')) return route.endsWith('.show') ? guide('Variazione della polizza', 'Consulta l’operazione e i documenti che la giustificano.', [file, rows], 'Controlla date di effetto, importi e titoli generati senza perdere il collegamento al contratto di origine.') : entry('Variazione della polizza', 'Registra la variazione prevista dal contratto.', 'Controlla tipo di operazione, decorrenza e impatto sui titoli prima di confermare.');
    if (route.startsWith('clients.policies.coverage')) return entry('Controllo coperture', 'Registra la verifica delle coperture per questa polizza.', 'Confronta la verifica con i documenti disponibili e indica gli elementi ancora da completare.');
    if (route === 'titles.show') return guide('Dettaglio titolo', 'Qui trovi stato, importi, documenti e storico di questa rata.', [action('Leggi i movimenti e il residuo del titolo.', '.content h1'), file], 'Se non è disponibile il modulo di pagamento, controlla se il titolo è già saldato o annullato.');
    if (['titles.create', 'titles.schedule'].includes(route)) return entry('Prepara i titoli', 'Registra le rate da incassare per questa polizza.', 'Controlla importi, periodo coperto e scadenze. La creazione dei titoli non registra un incasso.');
    if (route === 'titles.index') return list('Titoli', 'Trova le rate da incassare e quelle già lavorate.', 'Controlla stato, scadenza e residuo. Apri il titolo per registrare il pagamento.');
    if (route === 'collections.index') return list('Incassi e contabilità', 'Consulta i pagamenti già registrati e i rimborsi.', 'Controlla periodo e destinatario del pagamento. Per registrare un nuovo incasso apri la polizza o il titolo.');
    if (route === 'allowances.show') return entry('Recupero abbuono', 'Controlla l’abbuono e registra gli eventuali recuperi.', 'Verifica quanto resta da recuperare e i movimenti già presenti prima di registrare un importo.');
    if (route === 'allowances.index') return list('Abbuoni', 'Controlla gli importi abbuonati e lo stato dei recuperi.', 'Apri il dettaglio per consultare i movimenti e registrare un recupero.');
    if (route === 'policies.index') return list('Elenco polizze', 'Trova la polizza e scegli subito l’operazione da fare.', 'Controlla contraente e numero di polizza prima di registrare un pagamento.', [action('Per una nuova pratica usa Nuova polizza.', 'a', 'Nuova polizza')]);
    if (route === 'clients.index') return list('Clienti', 'Cerca prima il cliente, così eviti di creare doppioni.', 'Per una persona controlla il codice fiscale; per un’azienda anche la partita IVA.', [action('Se il cliente non esiste usa Nuovo cliente.', 'a', 'Nuovo cliente')]);
    if (['clients.create', 'clients.edit'].includes(route)) return entry(route.endsWith('.edit') ? 'Modifica cliente' : 'Nuovo cliente', 'Scegli Persona o Azienda e inserisci i dati identificativi e i recapiti.', 'Verifica dati fiscali e possibili duplicati prima di salvare. Veicoli e polizze si aggiungono dalla scheda cliente.', [action('Scegli il tipo di cliente prima di compilare i dati specifici.', '[name="client_type"]')]);
    if (route === 'clients.duplicates' || route.startsWith('clients.merge.')) return guide('Clienti duplicati', 'Confronta le schede prima di decidere se unificarle.', [action('Scegli la scheda da confrontare e leggi l’anteprima.', 'select, form input:not([type="hidden"])')], 'Unifica solo schede che appartengono allo stesso soggetto. Controlla quale anagrafica verrà conservata.');
    if (route === 'vehicles.index') return list('Veicoli', 'Trova il veicolo per targa o per cliente.', 'Controlla il proprietario e apri la scheda corretta prima di modificare i dati.');
    if (route.startsWith('clients.vehicles.')) return entry('Dati del veicolo', 'Registra il veicolo da collegare alle polizze del cliente.', 'Confronta targa, telaio e caratteristiche con il libretto. Se usi la lettura del documento, verifica i dati estratti.', [file]);
    if (route.startsWith('clients.documents.') || route.startsWith('clients.document-requests.')) return entry(route.startsWith('clients.document-requests.') ? 'Richiedi un documento' : 'Documento del cliente', 'Indica il tipo di documento e la pratica a cui appartiene.', 'Controlla intestatario, leggibilità e scadenza quando prevista.', route.startsWith('clients.documents.') ? [file] : []);
    if (route === 'clients.materials.index') return guide('Materiali del cliente', 'Consulta i file disponibili per la pratica.', [rows, file], 'Controlla che ogni file appartenga al cliente e alla polizza corretti.');
    if (route.startsWith('clients.records.') || route.startsWith('clients.relationships.') || route.startsWith('clients.households.')) return entry('Dati collegati al cliente', 'Aggiungi o aggiorna le informazioni della sezione aperta.', 'Controlla il cliente e il tipo di collegamento prima di salvare.');
    if (route === 'deadlines.index') return list('Scadenze', 'Trova le pratiche da seguire e i documenti in scadenza.', 'Controlla tipo di scadenza e periodo. Apri la pratica per completare l’attività.');
    if (route === 'daily-notes.index') return guide('Note di lavoro', 'Annota le cose da ricordare e controlla le attività della giornata.', [form, save], 'Una nota non sostituisce la registrazione dell’operazione nella pratica.');
    if (route.startsWith('signing-stations.')) return guide('Postazioni firma', 'Collega un tablet per far leggere e firmare i documenti al cliente.', [action('Sul tablet apri la pagina di firma indicata qui. Dal PC inserisci il codice temporaneo e dai un nome alla postazione.', 'input[name="pairing_code"]')], 'Il tablet deve usare un browser senza accesso operatore al gestionale. Puoi revocare un collegamento dalla tabella.');
    if (route === 'station-signing.create') return entry('Invia al tablet', 'Scegli la postazione e i documenti da firmare.', 'Controlla nome e qualifica del firmatario e conferma personalmente la verifica dell’identità. Il cliente leggerà e firmerà dal tablet.');
    if (route.startsWith('station-signing.')) return guide('Firma sul tablet', 'Segui l’avanzamento dei documenti inviati al cliente.', [action('Controlla lo stato della richiesta e le copie già raccolte.', '[data-station-progress]')], 'Puoi annullare una richiesta ancora in attesa. Le firme già raccolte rimangono nello storico e i moduli seguono il controllo del fascicolo.');
    if (route === 'quotes.index') return list('Preventivi', 'Segui le richieste e confronta le proposte ricevute.', 'Controlla lo stato per distinguere richieste aperte, proposte e contratti già emessi.', [action('Avvia una nuova richiesta per il cliente.', 'a', /Nuovo preventivo/)]);
    if (route === 'quotes.create') return entry('Nuovo preventivo', 'Scegli cliente, copertura richiesta e dati del rischio.', 'Spunta compagnie e agenzie in «Destinatari del preventivo», anche senza un gruppo. Le spunte restano salvate per «Prepara email». Controlla veicolo e documenti: la richiesta non crea ancora una polizza.');
    if (route === 'quotes.show') return guide('Pratica preventivo', 'Gestisci richieste, proposte delle compagnie e scelta del cliente.', [
        action('Prepara le richieste scegliendo i destinatari.', 'a', /Prepara.*email|Richiedi|Invia richieste/),
        action('Registra e confronta le proposte ricevute.', '#confronto-proposte'),
        action('Dopo l’approvazione del cliente usa il comando di emissione previsto dalla proposta.', 'a, button', /Emetti|Crea polizza/),
    ], 'Non considerare approvata una proposta finché non hai raccolto la scelta del cliente.');
    if (route.startsWith('quotes.emails.') || route.startsWith('quote-emails.') || route.startsWith('quote-email-batches.')) return guide('Email dei preventivi', 'Prepara o controlla la richiesta da inviare alle compagnie.', [form, action('Controlla destinatari, contenuto e allegati nel riepilogo.', 'details > summary, .email-review-confirm')], 'Prima di inviare verifica la casella di posta e i destinatari. Se l’esito è incerto, verifica con il servizio di posta prima di ripetere l’invio.');
    if (route === 'claims.index') return list('Sinistri', 'Trova la pratica e le attività da seguire.', 'Controlla cliente, polizza e stato del sinistro.', [action('Per una nuova denuncia apri Nuovo sinistro.', 'a', 'Nuovo sinistro')]);
    if (route === 'claims.create' || route === 'claims.edit') return entry('Dati del sinistro', 'Collega la polizza e descrivi l’evento con i dati disponibili.', 'Verifica data dell’evento, copertura e riferimenti della compagnia.');
    if (route === 'claims.activity') return entry('Attività del sinistro', 'Aggiorna l’attività e la prossima cosa da fare.', 'Segna come completata solo un’attività effettivamente svolta.');
    if (route === 'claims.show') return guide('Pratica sinistro', 'Consulta l’evento, i documenti e le attività da completare.', [action('Controlla o aggiorna la prossima attività.', 'a, button, summary', /Attivit|attivit|Aggiorna/), file], 'Mantieni aggiornati scadenza e stato delle attività, così chi riprende la pratica sa cosa fare.');
    if (route === 'reports.index') return list('Report', 'Scegli periodo e filtri per leggere i risultati del gestionale.', 'Controlla le date e il significato degli importi prima di esportare o confrontare i risultati.');
    if (route.startsWith('commissions.') || route.startsWith('suppliers.commissions.')) return route.endsWith('.index') ? list('Provvigioni', 'Controlla accordi, calcoli e movimenti delle provvigioni.', 'Un calcolo non equivale a un movimento registrato. Verifica stato e importi.') : entry('Dettaglio provvigioni', 'Consulta o aggiorna l’accordo e i movimenti previsti.', 'Controlla decorrenza, percentuali, base di calcolo e movimenti già registrati prima di confermare.');
    if (route.startsWith('registry-imports.')) {
        if (route.endsWith('.mapping')) return entry('Abbina le colonne', 'Associa le colonne del file ai campi del gestionale.', 'Controlla l’intestazione e i valori di esempio prima di preparare l’anteprima.');
        if (route.endsWith('.preview')) return guide('Controlla l’importazione', 'L’anteprima mostra cosa verrà aggiunto o segnalato.', [action('Leggi gli errori e i possibili duplicati.', 'table'), action('Conferma solo dopo aver verificato le righe valide.', 'button', /Conferma/)], 'Le righe con problemi vanno corrette. Verifica il risultato prima di confermare l’importazione.');
        return guide('Importa anagrafiche', 'Carica il file da controllare prima di importare i dati.', [file], 'Usa il modello del tipo di anagrafica corretto e verifica le colonne nell’anteprima.');
    }
    if (route.startsWith('insurance-import.')) return guide('Riconciliazione Excel', 'Confronta i dati del file con quelli presenti nel gestionale.', [file, action('Apri le differenze e controlla l’origine dei valori.', 'table tbody a[href], details > summary')], 'Il confronto non prova che il file sia corretto: verifica le differenze prima di applicare un aggiornamento.');
    const catalogs = [
        ['supplier-companies.', 'Compagnie', 'i dati e i riferimenti della compagnia'],
        ['suppliers.', 'Agenzie', 'i contatti e i dati dell’agenzia'],
        ['insurance-catalog.', 'Rami e sottorami', 'le categorie usate per classificare le polizze'],
        ['quote-groups.', 'Gruppi destinatari', 'i destinatari da riutilizzare nelle richieste di preventivo'],
        ['users.', 'Utenti', 'gli accessi e i ruoli del gestionale'],
    ];
    for (const [prefix, title, subject] of catalogs) if (route.startsWith(prefix)) {
        if (route.endsWith('.index')) return list(title, `Cerca e apri la scheda per gestire ${subject}.`, 'Controlla se la voce esiste già prima di crearne una nuova.');
        if (route.endsWith('.show')) return guide(title, `Consulta ${subject}.`, [action('Apri la modifica per aggiornare i dati.', 'a', /Modifica/), rows], 'Verifica i collegamenti e i riferimenti prima di modificare una voce già utilizzata.');
        return entry(title, `Inserisci o aggiorna ${subject}.`, prefix === 'users.' ? 'Assegna solo il ruolo necessario. Gli accessi amministrativi consentono operazioni riservate.' : 'Controlla nomi, recapiti e collegamenti prima di salvare.');
    }
    if (route === 'email-settings.edit') return entry('Impostazioni email', 'Configura la casella usata per inviare dal gestionale.', 'Usa i dati forniti dal servizio di posta. Salva e verifica la connessione con i controlli presenti prima degli invii. Non inserire dati di prova nelle credenziali.');
    if (route === 'profile.edit') return entry('Il tuo profilo', 'Aggiorna i tuoi dati e la password di accesso.', 'Conserva la password in modo sicuro e verifica l’indirizzo email.');
    if (route === 'appearance.edit') return entry('Aspetto del gestionale', 'Scegli i colori di lavoro e controlla l’anteprima.', 'Verifica che testi e campi restino facili da leggere prima di salvare.');
    if (route === 'audit-logs.index') return list('Registro attività', 'Ricostruisci le operazioni registrate nel gestionale.', 'Controlla data, utente e pratica interessata. Il registro va letto insieme ai dati della pratica.');
    if (route === 'login') return guide('Accedi a R4', 'Usa le credenziali del tuo account.', [action('Inserisci email e password, poi premi Entra.', 'input[name="email"]')], 'Se non riesci ad accedere, chiedi all’amministratore di verificare il tuo account. Non condividere la password.');
    if (route === 'password.setup') return entry('Primo accesso', 'Scegli la tua password per iniziare a usare il gestionale.', 'Segui i requisiti indicati e conferma la stessa password nei due campi.');
    if (route.startsWith('legal.')) return guide('Informazioni del servizio', 'Leggi il documento di questa pagina.', [action('Consulta il testo completo.', 'main, article')], 'Per chiarimenti usa i riferimenti effettivamente indicati nel documento.');
    return guide('Questa pagina', 'Controlla i dati della schermata e le operazioni disponibili.', [form], 'Leggi i messaggi vicino ai campi prima di confermare. Puoi tornare alla pratica dai collegamenti presenti nella pagina.');
}
