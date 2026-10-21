# R4 Insurance

Gestionale assicurativo con anagrafica clienti e portafoglio per cliente.

Restano anagrafica clienti, contatti e identificativi di persone e aziende,
documenti dei clienti, utenti, accessi, blocco note e registro attività.
La scheda comprende persone fisiche e aziende, polizze suddivise in tab per ramo,
veicoli e targhe, documenti riservati e scadenze ricavate dai dati registrati.
I ruoli esistenti e l'identità grafica del gestionale sono conservati.

## Prestazioni delle pagine: aggiornamento del 21 ottobre 2026

Ridotte le letture ripetute del portafoglio in dashboard e del menu notifiche.
Elenco degli avvisi e contatore dei non letti usano la stessa lettura dei dati
attuali. Non viene introdotta una cache che ritardi incassi, storni o cambi di
permessi. Gli esiti delle comunicazioni vengono risolti per insieme, conservando
la distinzione fra agenzie e l'ordine dello storico anche con invii incerti.

Le ricerche per mese e intervallo interrogano direttamente le colonne delle
date. Il limite finale comprende l'intera giornata richiesta ed esclude quella
successiva. Lo scadenzario applica i filtri a ciascuna origine prima di unire
polizze, rate, documenti e richieste; le scelte nei menu restano riferite alle
scadenze accessibili. Grafica, campi e comandi del frontend non sono cambiati.

La migrazione `2026_10_21_100000_index_insurance_reporting_periods` aggiunge cinque
indici per decorrenza e inserimento delle polizze, periodo delle rate, movimenti
provvigionali e inserimento delle variazioni. Applicata al database di collaudo
e a quello del gestionale, senza modificare i dati delle anagrafiche o i movimenti.

Confronto locale sullo stesso archivio sintetico di 40.000 polizze e con un
arretrato esteso. Ogni pagina viene richiesta una volta inizialmente e tre
volte successive nello stesso processo; la tabella riporta la mediana di queste
ultime, in secondi. Le richieste attraversano il kernel HTTP, le viste e il menu
delle notifiche, ma non misurano il caricamento grafico nel browser.

| Pagina | Prima | Dopo |
| --- | ---: | ---: |
| Dashboard | 10,73 | 5,81 |
| Polizze | 11,93 | 4,31 |
| Polizze con filtro mensile e ramo | 6,28 | 4,79 |
| Clienti | 5,11 | 4,04 |
| Rate e quietanze | 5,47 | 3,04 |
| Incassi | 4,79 | 3,22 |
| Report portafoglio | 5,23 | 4,23 |
| Report provvigioni | 6,49 | 2,93 |
| Scadenze | 11,64 | 5,28 |
| Comunicazioni | 7,78 | 4,99 |
| Scheda cliente | 4,68 | 2,92 |
| Scheda polizza | 6,20 | 3,01 |

Tutte le 48 richieste finali hanno risposto con HTTP 200. Superati 97 test
applicativi mirati, con 1.184 asserzioni, e 13 test frontend. Le verifiche
comprendono limiti delle date, esclusione dei clienti accorpati, conteggi,
filtri per agenzia e ramo, paginazione, selezioni fra pagine, esportazioni dei
risultati completi e file Excel riaperti con date e importi tipizzati. Controllati
anche incasso parziale e completo, storno, promemoria, permessi, invii incerti
e storico della comunicazione dopo un cambio di agenzia. Il confronto delle
letture del menu ha confermato gli stessi identificativi e lo stesso contatore.

I tempi dipendono dalla macchina locale e non equivalgono a un collaudo con
utenti simultanei; alcune pagine restano sopra i cinque secondi nello scenario
di arretrato. Nessuna nuova verifica visiva è stata eseguita: l'accesso al
browser era stato bloccato e non è stato aggirato. Nessuna sessione browser o
server web di collaudo aperta in questo intervento; verificate libere le porte
8028 e 8029. I risultati dettagliati rimangono nella cartella privata di collaudo,
fuori dal repository.

## Comunicazioni delle variazioni: aggiornamento del 20 ottobre 2026

Comunicazioni > Polizze, rinnovi e variazioni include le aggiunte di coperture
nella data in cui vengono registrate, anche per contratti inseriti in precedenza.
Ogni appendice ha una riga indipendente, con riferimento, numero della polizza
invariato e premio aggiuntivo. Si possono filtrare le sole aggiunte, il periodo,
l'agenzia, la compagnia e il contraente, con paginazione ed esportazioni Excel/PDF.

Polizze e appendici della stessa agenzia possono essere riunite in un unico
riepilogo. L'anteprima distingue i premi del contratto dagli importi delle
coperture aggiunte e riporta descrizione e date delle variazioni. Il riepilogo
non registra un incasso e non attesta l'emissione di una quietanza.

Si possono selezionare più documenti già presenti nel cliente, nella polizza
e nelle appendici scelte, oppure caricare più file. Se sono selezionate soltanto
alcune appendici, i documenti di altre appendici dello stesso contratto non
sono proposti. I file scelti sono conservati come copie private nello storico;
una modifica degli allegati richiede un nuovo controllo prima della conferma.

L'invio rimane manuale e riservato all'admin secondo i permessi esistenti.
Le appendici hanno un controllo autonomo contro duplicati e selezioni
sovrapposte: il cambio dell'indirizzo email non abilita il reinvio dello stesso
evento alla stessa agenzia. Dati cambiati, allegati mancanti o un esito incerto
bloccano l'invio e richiedono una nuova verifica. Lo storico distingue il numero
di polizze selezionate da quello delle appendici, senza ricontare il contratto
di riferimento come nuova operazione.

Superati 56 test mirati sul flusso assicurativo e sulle comunicazioni e 13 test
frontend; compilazione completata. Verificati nel browser filtri, selezione
mista, controllo degli allegati, invio simulato, blocco dei duplicati, storico,
Excel/PDF e layout a 1440 e 390 px. La migrazione
`2026_10_20_140000_link_policy_changes_to_agency_communications` è applicata
anche al database principale. I dati e gli invii di collaudo sono isolati:
restano da configurare la casella SMTP reale e da verificarne la consegna.

## Sostituzioni, coperture aggiuntive e aspetto: aggiornamento del 20 ottobre 2026

Dalla scheda polizza si può preparare una sostituzione o un'aggiunta di
coperture, partendo da un preventivo approvato con proposta e prova
dell'approvazione allegate. La sostituzione crea una nuova polizza con
numero diverso e collega la precedente, che passa allo stato Sostituita.
La conferma è disponibile dalla decorrenza della nuova polizza: i preventivi
possono essere preparati prima senza cessare anticipatamente la precedente.

L'aggiunta mantiene numero, periodo e premio del contratto di base e registra
un'appendice con coperture, periodo e premio aggiuntivo concordato. I suoi
titoli si inseriscono esplicitamente, anche se il periodo coincide con rate
del contratto, entro l'importo approvato. Non prolungano la copertura di base.
Le rate, gli incassi e le provvigioni preesistenti sono conservati; rate
residue e rimborsi della sostituzione richiedono la verifica manuale concordata.

Lo storico conserva i dati dell'operazione, il preventivo, l'autore e gli
allegati. I nuovi moduli e le relative firme sono collegati alla variazione
e rimangono consultabili anche dalla scheda cliente. Le firme dei moduli
precedenti non attestano automaticamente le nuove coperture. Sono disponibili
allegati multipli, filtri ed esportazioni dello storico. Restano validi i
permessi e il blocco delle modifiche dal giorno dopo la chiusura lavorazione.

In Impostazioni > Aspetto si possono scegliere anche le tonalità delle
intestazioni e dei riepiloghi. Bordi, pulsanti e hover seguono il tema
personale, con anteprima e adattamento del contrasto. Le scelte già salvate
mantengono le sfumature precedenti finché non vengono cambiate esplicitamente.
I colori degli avvisi conservano il loro significato.

Superati 96 test mirati (2.598 asserzioni) su variazioni, moduli, preventivi,
contabilità, permessi e aspetto; compilazione frontend completata. Verificati
nel browser i flussi di sostituzione per un privato e aggiunta per un'azienda,
i moduli generati, i filtri e le esportazioni Excel/PDF, e le schermate a
1440 e 390 px. Excel conserva date e premi come valori tipizzati. Il collaudo
usa dati fittizi in un database isolato; la migrazione strutturale è applicata
anche al database principale. Eseguiti logout, chiusura del browser e arresto
del server di collaudo sulla porta 8028.

## Riepilogo provvigioni in cliente e polizza: aggiornamento del 20 ottobre 2026

Le schede cliente, nelle tab Anagrafica e Polizze, mostrano all'admin le
provvigioni previste, maturate e liquidate dell'intero portafoglio del cliente.
La scheda polizza mostra gli stessi saldi limitati alle sue rate, inclusi i
rinnovi. Si leggono i saldi già registrati, una sola volta per rata, senza
generare calcoli o movimenti durante la consultazione.

Le tre voci rappresentano fasi dello stesso importo e non si sommano. Le rate
senza condizioni sufficienti risultano da configurare; se solo parte delle
rate è calcolata, il riepilogo avvisa che i totali sono parziali. Un'aliquota
effettivamente pari a zero resta distinta da una condizione mancante. I residui
da liquidare e le liquidazioni da riconciliare dopo storni sono evidenziati
in rosso, senza compensarli fra rate diverse.

Il collegamento al dettaglio apre il report sull'intero storico del cliente
o della singola polizza. I vincoli restano applicati cambiando periodo,
raggruppamento o vista, azzerando gli altri filtri e nelle esportazioni
Excel/PDF. Le schede e il report mantengono la visibilità riservata all'admin.
Nessuna nuova migrazione o aliquota inserita nel database principale:
le condizioni reali dei mandati restano da concordare con il cliente.

Superati 53 test mirati complessivi su anagrafica, polizze, permessi e
provvigioni, oltre ai 12 test frontend e alla compilazione. Verificati nel
browser i riepiloghi a 1440 e 390 px, i collegamenti al report, il filtro
mensile e il suo ripristino, gli importi e i filtri dei file Excel/PDF.
Le prove usano dati fittizi nel database temporaneo. Al termine effettuati
logout, chiusura delle schede del browser e arresto del server di collaudo.

## Provvigioni nella scheda agenzia: aggiornamento del 20 ottobre 2026

La scheda Agenzia contiene le tab Anagrafica e Provvigioni. La seconda
riunisce le aliquote per compagnia, mandato, ramo e sottoramo, con filtri
per validità, base di calcolo e versioni correnti, precedenti o annullate.
L'admin configura e revisiona le condizioni e accede al report già filtrato
per l'agenzia; gli operatori possono consultarle. Il percorso precedente
reindirizza alla tab conservando filtri e pagina.

Le revisioni conservano lo storico e i calcoli già registrati. I valori
contrattuali non definiti restano da concordare con il cliente. Non è
necessaria una migrazione e non sono state inserite aliquote nel database
principale.

La tabella mantiene paginazione, filtri avanzati ed esportazioni della
ricerca completa. Excel conserva date e percentuali come valori utilizzabili;
i PDF con più di dieci colonne usano il formato A3 orizzontale per mantenere
leggibili le intestazioni. Verificati filtri, versione precedente ed esclusione
delle azioni dai file scaricati.

Superati 37 test mirati su agenzie, condizioni e registro provvigioni
(483 asserzioni), i 12 test frontend e la compilazione. Controllati nel browser
la navigazione fra scheda, storico e revisione, i filtri, le esportazioni e il
layout a 1440 e 390 px, senza overflow della pagina. I dati del collaudo sono
fittizi e isolati dal database principale.

## Rinnovi e verifiche contabili: aggiornamento del 20 ottobre 2026

Il rinnovo registra la propria data di operazione, conserva le rate e i
movimenti del periodo precedente e genera i nuovi titoli dopo anteprima e
conferma. L'anteprima è legata alla polizza e ai dati del contraente: una
modifica intervenuta nel frattempo richiede un nuovo controllo. Il blocco
della lavorazione resta quello richiesto nelle task, dal giorno successivo
alla chiusura per gli operatori; una polizza bloccata si rinnova tramite admin,
senza riapertura automatica della lavorazione.

Comunicazioni → Polizze e rinnovi comprende gli inserimenti e i rinnovi
registrati nel periodo, con un filtro per separarli e la colonna Ultimo rinnovo.
Anche una polizza importata può comparire dopo un rinnovo effettivo. Non sono
state ricostruite date di rinnovo passate. L'anteprima dell'email riporta il
rinnovo e conserva i controlli contro dati modificati e invii duplicati.
Le comunicazioni degli incassi riportano anche lo stato attuale della polizza.

Annullamento e sostituzione conservano rate, incassi, provvigioni e documenti.
La scheda mostra una verifica contabile con numero e totale delle rate residue
in rosso e collegamenti filtrati ai movimenti. Non offre la generazione di
nuovi titoli o il rinnovo di un contratto chiuso. Gli importi ancora dovuti
e i rimborsi richiedono la verifica manuale concordata con l'utente.
Lo storno corregge un incasso errato: non esegue né registra un rimborso
al contraente. Le provvigioni già liquidate rimangono da riconciliare.
Il collegamento esplicito fra contratto sostituito e nuovo contratto resta
da completare; una richiesta di coperture aggiuntive non equivale a una
sostituzione automatica.

La regola di numerazione confermata distingue i contratti dalle operazioni
sullo stesso contratto: una nuova stipula dopo cessazione e una sostitutiva
devono avere un numero diverso dal precedente, assegnato dalla compagnia.
Rinnovo e aggiunta di coperture mantengono invece il numero esistente.
Il rinnovo conserva già la stessa scheda e rifiuta richieste che tentano
di assegnare un numero. Il collegamento fra i contratti e la registrazione
delle aggiunte sul contratto originario devono rispettare questa distinzione;
non va usata la conversione generica in una nuova polizza per un'aggiunta.
Verificato il vincolo del rinnovo sia in anteprima sia in conferma:
8 test del ciclo di vita superati, con 168 asserzioni.

Il collaudo verifica rinnovo e quietanza, condizioni provvigionali del nuovo
periodo, documenti da aggiornare, promemoria, comunicazioni e report mensili.
Una rata successiva pagata non può superare un periodo precedente insoluto:
lo storno ricalcola la copertura registrata senza saltare il debito riaperto.
Sono verificati anche permessi, conferme obsolete e tentativi ripetuti.
Passano 329 test applicativi (5.421 asserzioni); dopo l'ultima correzione del
riepilogo sono ripassati i 7 test del ciclo di vita (152 asserzioni). Passano
12 test frontend e la compilazione. Controllati browser desktop e smartphone
da 390 px, selezioni, collegamenti, Excel con date effettive e PDF filtrato.
Le prove usano dati fittizi isolati e posta simulata, senza invii reali.

Applicata la migrazione `2026_10_20_120000_track_policy_renewal_date`.
La verifica in sola lettura sul database principale conferma 50 clienti,
50 polizze e nessun rinnovo di collaudo. Restano da confermare con il cliente
le condizioni dei mandati e gli eventuali rimborsi; per l'invio effettivo
servono la casella SMTP e i destinatari approvati.

## Moduli e checklist: aggiornamento del 20 ottobre 2026

La checklist si configura dall'admin nel catalogo rami e sottorami. Comprende
documenti comuni, aggiunte per privati e aggiunte per aziende. Il sottoramo
si somma al ramo senza duplicare categorie. Scegliendo una polizza, ramo e
sottoramo seguono il contratto anche se il sottoramo viene omesso nella richiesta.
Le richieste già registrate non vengono cancellate o riscritte quando cambia
la configurazione.

La scheda polizza mostra richieste, file ricevuti e stato del controllo,
insieme ai fascicoli del cliente e della polizza. Ogni richiesta accetta più
file e permette di collegare documenti esistenti della stessa categoria,
appartenenti all'anagrafica o alla polizza selezionata. Il collegamento
conserva l'originale, la scadenza, la validazione e lo storico; non equivale
a una validazione. I file di altri clienti, altre polizze o archivi riservati
a sinistri, contabilità e firme vengono esclusi. Una versione sostituita
richiede un nuovo collegamento esplicito; i documenti scaduti sono segnalati
in rosso e non possono essere validati. Le richieste ricevute cessano di
comparire fra i documenti ancora da ricevere, mantenendo la relativa storia.

I modelli documentali distinguono privati, aziende o entrambi, oltre a
cliente/polizza e ramo/sottoramo. Restano approvazione dell'admin, preparazione
PDF, firmatario e qualifica per l'azienda, caricamenti multipli, controllo
manuale delle firme e versioni storiche. Gli allegati del preventivo restano
facoltativi. Il riepilogo documentale non introduce un blocco automatico
dell'emissione o della chiusura per documenti mancanti.

Verificati due flussi isolati, privato e azienda: creazione anagrafica,
preventivo con documenti, proposta, approvazione allegata, conversione in
polizza, checklist, collegamento e validazione multipla, moduli cliente e
polizza, firme con controllo, rate semestrali, incasso, copertura registrata,
comunicazione cumulativa controllata e blocco delle modifiche il giorno dopo.
La posta è simulata; nessuna email reale è stata inviata. I testi usati nei
test sono chiaramente di collaudo e non sono stati attivati nel database locale.
Passano 322 test applicativi (5.272 asserzioni) e 12 test frontend; dopo le
rifiniture visive sono ripassati i 42 test documentali pertinenti.
Controllati inoltre desktop, viewport smartphone da 390 px, PDF dei moduli,
caricamento multiplo e corrispondenza tra filtro della checklist e Excel/PDF.

La migrazione `2026_10_20_110000_complete_insurance_document_checklists` è stata
applicata al database locale. Verifica in sola lettura: 50 clienti, 50 polizze,
2 modelli in bozza, 5 modelli precedenti e nessuna checklist configurata.
Per l'uso operativo il cliente deve definire i documenti per ramo/sottoramo
e tipo di contraente, confermare i testi aggiornati dei moduli, precisare
chi può firmare per le aziende e indicare se le mancanze debbano avvisare
o bloccare emissione/chiusura. Nessun obbligo documentale è stato presunto.

## Stato precedente al 18 ottobre 2026

Il database locale contiene 50 clienti con nomi di fantasia (35 persone e
15 aziende) e 50 polizze con numerazione SA-2026-001–050, collegate ai rami,
sottorami e mandati agenzia/compagnia già presenti. I dati del precedente
portafoglio sono stati rimossi su richiesta, comprese le relative registrazioni
di importazione. Utenti, credenziali, preferenze personali e cataloghi sono
stati confrontati con la copia precedente e risultano invariati. I resoconti
di importazione nelle sezioni successive descrivono lo storico del lavoro,
non il contenuto attuale del database.

InsuranceDemoSeeder genera il portafoglio di collaudo soltanto su clienti e
polizze vuoti. Non è richiamato dal seeder generale. Il ripristino effettuato
da InsuranceDemoReset usa una transazione, mantiene i controlli delle chiavi
esterne e conserva il registro attività. Prima della rimozione crea copie
cifrate nell'area privata storage/app/private/insurance-demo-backup-*;
la chiave applicativa è necessaria per leggerle. I nomi e i numeri visibili
sono fittizi, i recapiti usano il dominio riservato example.test.

La sidebar comprende Operatività, Anagrafica, Gestione e Amministrazione.
Team è stato eliminato anche dal database. La dashboard mostra le polizze
aperte oggi con stato Attiva o Rinnovata e scadenza non trascorsa, il loro
premio totale in euro, le scadenze e, per gli amministratori, le attività
recenti. La data di apertura è quella di inserimento, distinta dalla decorrenza.
Nuova polizza apre direttamente il modulo, con ricerca o creazione del
cliente, menu collegati al catalogo, allegati PDF, classe universale CU e
classe interna della compagnia. I campi sono raccolti in riquadri per argomento;
il bordo segue il colore dei pulsanti oppure il colore personale impostato.

Gestione → Preventivi raccoglie richieste, risposte e approvazione del cliente.
Le richieste alle compagnie prioritarie Nobis ed EUROPE ASSISTANCE devono
essere registrate prima di richiedere offerte alle altre compagnie. Sono usate
le voci già presenti nel catalogo; la distinta voce EUROPE non è stata unita.
L'operatore può inviare la richiesta dal gestionale: sceglie la compagnia,
modifica oggetto e testo, carica più PDF e controlla l'anteprima prima della
conferma. Ogni messaggio ha un solo destinatario. Le altre compagnie diventano
disponibili dopo gli invii prioritari confermati oppure dopo la registrazione
delle proposte prioritarie già ricevute fuori dal gestionale, con i relativi
PDF. Una bozza o il solo contatto manuale non confermano un invio email.
Restano disponibili la registrazione dei contatti manuali e dei PDF ricevuti;
l'approvazione interna richiede una prova allegata. La nuova stipula usa un
preventivo approvato e ancora valido, riportando compagnia, prodotto, date,
premio e frazionamento. La conversione è unica e conserva i documenti nel
cliente e nella polizza. Solo l'admin può registrare una polizza preesistente
senza questo percorso, indicando il motivo. Le API delle compagnie non sono
ancora implementate e le risposte ricevute si caricano manualmente.

Lo storico delle richieste email conserva testo, destinatario, allegati,
operatore ed esito; ha filtri per compagnia, stato e mese, con Excel/PDF.
Anteprima valida per 30 minuti, controllo dei dati prima dell'invio e vincolo
contro i duplicati per pratica/compagnia. Un esito incerto richiede una
verifica dell'admin prima di ritentare. Il testo è cifrato nel database e gli
allegati rimangono privati e protetti dalle normali modifiche/cancellazioni.
Il server di posta deve accettare il messaggio prima della registrazione
della richiesta; tale accettazione non certifica la consegna al destinatario.
Nel database locale mancano ancora SMTP e indirizzi email di Nobis ed EUROPE
ASSISTANCE: devono essere inseriti e confermati dall'agenzia prima dell'uso.

Agenzie → scheda agenzia → Provvigioni consente all'admin di configurare
condizioni distinte per compagnia collegata, ramo e sottoramo. Ogni voce si
seleziona con una checkbox ed è autonoma: il ramo senza sottoramo specificato
non assegna automaticamente percentuali ai sottorami. Base di calcolo,
precisazioni contrattuali e aliquote di nuova emissione/rinnovo sono separate;
un valore vuoto significa da definire, mentre zero è una percentuale nulla.
Le percentuali ammettono fino a quattro decimali e sono memorizzate come interi.
Sono presenti decorrenza, termine facoltativo, evento di maturazione, note,
filtri ed esportazioni Excel/PDF. La maturazione resta da definire quando
non è nota. I ruoli già autorizzati alla consultazione delle agenzie possono
leggere; creazione, revisione e annullamento sono riservati all'admin.

Una revisione motivata crea una nuova versione e conserva la precedente;
l'annullamento richiede conferma e motivo. Non sono ammesse sovrapposizioni
di validità per lo stesso mandato e la stessa voce. I collegamenti impediscono
di cancellare agenzia, compagnia o catalogo usati nello storico e di staccare
il mandato. Le operazioni sono registrate nel registro attività. Nessuna
aliquota fittizia è stata inserita nel database locale.

Questa configurazione non calcola ancora importi o guadagni. Il cliente ha
confermato che base e aliquote dipendono dal mandato/ramo e dalla compagnia;
la maturazione non è stata definita. Per il calcolo occorrono le condizioni
effettive: quota spettante a Soluzioni Assicurative, base e dati economici
necessari (il premio imponibile non è registrato separatamente), percentuali,
data che individua la tariffa applicabile, evento di maturazione, trattamento
di rate/acconti, pagamenti diretti, rettifiche, annullamenti e storni.
Provvigione maturata e provvigione effettivamente ricevuta restano distinte.

Gestione → Titoli e quietanze gestisce inserimento manuale e generazione dal
frazionamento dopo un'anteprima. Sono presenti periodi, scadenze, metodo
previsto, stato calcolato, residuo e giacenze. I calcoli usano centesimi interi;
l'ultima rata assorbe il resto. Periodi non allineati al frazionamento o premi
rata incoerenti richiedono i dati effettivi della compagnia. Le quietanze
ricevute possono essere allegate in PDF multipli; non si genera un documento
ufficiale della compagnia. Il rinnovo, controllato e confermato dall'admin,
genera il nuovo periodo e conserva i titoli precedenti. La copertura registrata
avanza soltanto su periodi consecutivi interamente saldati e si ricalcola dopo
storni o annullamenti, senza saltare titoli insoluti.

Gestione → Incassi registra acconti e saldi, metodo effettivo, destinatario,
consulente e documenti. Comprende i pagamenti diretti a compagnia o agenzia e
distingue tali somme da quelle ricevute dalla nostra agenzia. Importi oltre
il residuo, richieste ripetute e collegamenti fra clienti diversi sono
controllati. Gli storni motivati conservano il movimento originale. Titoli,
annullamenti, rinnovi e storni sono riservati all'admin; gli operatori
autorizzati possono registrare incassi sui clienti visibili. I dati economici
derivati da un preventivo approvato o collegati a titoli non sono modificabili
dal modulo generico della polizza.

Gestione → Comunicazioni filtra le polizze per giorno, ultimi 15 o 30 giorni,
mese o intervallo, agenzia, compagnia, stato e ricerca libera. Dopo la selezione
mostra destinatario, polizze e testo completo; una conferma esplicita consente
di inviare una sola email cumulativa per agenzia direttamente dal gestionale.
L'admin configura la casella in Impostazioni → Email: password cifrata, SMTP
autenticato e TLS obbligatorio. La casella reale non è ancora configurata.
Sono esclusi dalla selezione i contratti sospesi, annullati, cessati, sostituiti,
scaduti o da verificare e i caricamenti storici Excel. Una decorrenza futura
non impedisce di comunicare una polizza aperta nel periodo selezionato.
Il controllo dura 30 minuti e viene invalidato se cambiano i dati mostrati.
Invii duplicati della stessa versione sono bloccati; un esito SMTP incerto
richiede verifica manuale prima di ritentare. Lo storico conserva il testo
controllato e distingue accettazione SMTP da consegna nella casella finale.
La scheda Titoli e incassi prepara comunicazioni cumulative dei movimenti
selezionati con importi, metodo effettivo, destinatario del pagamento, periodo
del titolo e residuo. Il controllo viene invalidato da variazioni o storni
prima dell'invio; ogni movimento può essere comunicato una volta per
destinatario. I documenti privati non vengono allegati automaticamente.
Uno storno successivo all'invio non modifica il messaggio già inviato né
genera automaticamente una rettifica.
Le tabelle includono filtri ed esportazione Excel/PDF; i report partono dal mese.

Gli allegati supportano selezione multipla, limiti e validazione di ogni file.
I documenti di identità distinguono carta elettronica, carta cartacea e patente.
La lettura di PDF e immagini avviene nel browser, senza servizi OCR esterni;
l'operatore conferma separatamente la scadenza di ogni file. I messaggi sono
«Documento valido», «Documento non valido — scaduto il …» oppure
«Da verificare, data di scadenza non leggibile». Validità temporale e verifica
manuale del documento restano distinte. Per sostituire una singola versione o
rispondere a una richiesta documentale si usa un unico file, eventualmente
un PDF con fronte e retro.

Le notifiche interne coprono le scadenze entro 15 giorni, rispettano la
visibilità del cliente e non vengono duplicate. Si aggiornano durante l'uso
del gestionale; è predisposto anche il comando giornaliero
insurance:deadline-reminders. L'attivazione permanente dello scheduler sul
server deve essere verificata prima dell'uso in produzione. Titoli da
incassare e giacenze sono integrati anche in scadenze e dashboard; i titoli
saldati o annullati non generano promemoria.

Gestione → Sinistri collega ogni pratica a una polizza e al suo contraente.
Agenzia, compagnia, ramo, sottoramo e targa sono consultabili dalla scheda;
una copia cifrata dei riferimenti all'apertura resta nello storico. Sono
disponibili numero della compagnia (anche da aggiungere in seguito), data
evento, data denuncia, descrizione e operatore attivo autorizzato al cliente.
La data dell'evento può precedere il periodo attuale della polizza: viene
segnalata la necessità di controllare il contratto, senza decidere la copertura.

Gli stati approvati sono Aperto, In attesa documenti, In gestione presso
compagnia/agenzia, In attesa liquidazione, Chiuso con liquidazione, Chiuso
senza liquidazione e Annullato per errore. Ogni cambio è motivato e registrato.
L'admin può annullare o riaprire; dopo la chiusura l'operatore consulta la pratica.
Nessuno stato del sinistro modifica automaticamente contratto, titoli o incassi.

L'operatore programma attività e date, ne registra l'esito oppure riprogramma
o annulla motivando. Gli avvisi interni vanno all'assegnatario e agli admin entro
3 giorni dalla data e il giorno previsto; l'arretrato resta visibile fino
alla risoluzione. Un'attività creata già in ritardo viene segnalata subito.
La chiusura annulla le attività pendenti conservandole nello storico; una
riapertura non le riattiva automaticamente. La dashboard e il collegamento
dallo scadenzario consentono di consultarle. Il comando giornaliero già
previsto include i sinistri; il menu notifiche si aggiorna anche durante l'uso.

Allegati multipli PDF/JPG/PNG privati, fino a 10 file, 10 MB ciascuno e 50 MB
totali, sono collegati al sinistro, al cliente e alla polizza. Gli originali
non sono cancellabili o riscrivibili dai moduli generici. Descrizioni,
attività e dettagli storici sono cifrati nel database; il registro globale
annota operazione e riferimenti senza copiare i testi del sinistro.
Sono presenti filtri per cliente/polizza/sinistro, agenzia, compagnia,
ramo/sottoramo, operatore, stato, date e attività, con Excel/PDF sulle tabelle.
Le modifiche simultanee e gli invii ripetuti sono controllati; i permessi
vengono verificati anche su allegati, richieste dirette e notifiche.

Restano da approvare i testi dei moduli e da completare
il calcolo delle provvigioni e i report sui guadagni e
le integrazioni con le compagnie. Le preferenze di pagamento e il
frazionamento registrati sulla polizza non costituiscono da soli un incasso:
la generazione dei titoli e la registrazione dei movimenti sono esplicite.
Il caricamento dei moduli firmati, le richieste documentali e lo storico sono
disponibili. I vecchi moduli Excel non vengono trasformati automaticamente in
documenti contrattuali aggiornati.

Verifiche finali: 251 test superati con 4.267 asserzioni. I 15 test dei sinistri
sono passati nuovamente dopo la rifinitura, con 290 asserzioni. Il collaudo dei
sinistri comprende 52 viste dei flussi e 8 controlli visivi finali su notifiche,
storico e file esportati, oltre a quattro test dei filtri tabellari. La nuova compilazione
del frontend è riuscita ed è stata usata nel collaudo. Il precedente controllo browser isolato
desktop e smartphone comprende 68 viste e cinque flussi
completi per preventivi, approvazione, conversione in polizza, titoli, acconti,
pagamenti diretti, quietanze multiple, rinnovi, esportazioni Excel/PDF e
revisione delle email cumulative. Nessun errore JavaScript, richiesta esterna
o campo visibile privo di etichetta nel controllo. Il precedente audit di
61 viste resta documentato in work/insurance-ui/audit-ui-report.json.
Le migrazioni aggiuntive sono applicate al database locale; il confronto dei
dati conferma 50 clienti e 50 polizze invariati e le 10 pagine coinvolte
rispondono correttamente. Nessun preventivo, titolo o incasso di prova inserito
nel database dell'utente; nessun invio email reale. Evidenze aggiornate:
work/insurance-operations-suite.xml, work/insurance-ui/operations-report.json
e work/insurance-operations-live-report.json.

Il controllo aggiuntivo della fine lavorazione comprende il passaggio alla
mezzanotte successiva, una polizza ancora attiva, permessi, richieste ripetute,
segnalazioni e correzioni motivate. Nel browser sono state verificate altre
14 viste desktop/mobile e l'intero percorso tra operatore e admin, senza
errori JavaScript o campi visibili senza etichetta. La migrazione dedicata
è applicata al database locale; i dati preesistenti sono invariati e nessuna
lavorazione è stata chiusa automaticamente. Evidenze:
work/policy-workflow-suite.xml, work/insurance-ui/workflow-report.json e
work/policy-workflow-live-report.json.

Il nuovo invio dei preventivi aggiunge 20 viste desktop/mobile e tre flussi:
ordine delle compagnie con revisione e allegati multipli, storico filtrabile
con Excel/PDF e percorso completo fino alla polizza approvata. Nessun errore
JavaScript, richiesta esterna o campo visibile senza etichetta rilevato.
Il trasporto di prova costruisce il messaggio reale senza connessioni SMTP.
La migrazione dedicata è applicata a r4_insurance; cinque pagine rispondono
correttamente e clienti, polizze, cataloghi e documenti preesistenti risultano
invariati. Le registrazioni temporanee sono state annullate. Evidenze:
work/quote-email-suite.xml, work/insurance-ui/quote-email-report.json e
work/quote-email-live-report.json. Nessuna email reale è stata inviata.

Moduli e firme è disponibile nelle schede cliente e polizza. L'admin gestisce
i modelli in Impostazioni → Modelli documentali: bozze, anteprima PDF,
approvazione esplicita, versioni e ritiro. I sette modelli del file fornito
sono conservati senza i dati del precedente contraente: quattro allegati
precedenti e la ricevuta di consegna restano storici da aggiornare; coerenza
e consenso elettronico sono bozze da revisionare. Nessun modello è stato
approvato automaticamente. I testi completi dei MUP correnti, privacy,
mandato e questionario esigenze non sono presenti nel file di origine.

Da un modello approvato si preparano PDF con i dati registrati e firmatario
distinto dal contraente, con qualifica obbligatoria per le aziende.
Fascicoli di emissione, variazione e rinnovo conservano dati e testo cifrati,
PDF privati, impronte dei file e storico. Una variazione contrattuale segnala
la necessità di nuovi moduli. Il caricamento di più PDF/JPG/PNG firmati
richiede il successivo controllo esplicito dell'operatore; nuove versioni
conservano le precedenti. Non vengono autenticati automaticamente i documenti
o le firme e non viene registrato un consenso soltanto perché un file è caricato.
Il normale editor degli allegati non può riscrivere o eliminare questi file.

La polizza con veicolo offre Verifica RCA: collegamento al servizio ufficiale
del Portale dell'Automobilista, registrazione manuale dell'esito, data/ora,
operatore, note e più prove allegate. Non interroga automaticamente il portale,
non conferma l'incasso, non verifica altre garanzie e non modifica lo stato
della polizza. Una copertura risultante per una targa non prova da sola la
validità dello specifico contratto registrato.

Il collaudo aggiunge 13 test mirati (181 asserzioni), quattro flussi browser
e 36 viste desktop/mobile; controllati anche 11 PDF per 23 pagine. Nessun
errore JavaScript, richiesta esterna, campo visibile senza etichetta o
sbordamento orizzontale della pagina nel controllo automatico. Layout e PDF
sono stati esaminati visivamente. Applicate le migrazioni dedicate e
l'ampliamento dello spazio per i documenti cifrati: nove pagine reali
rispondono correttamente; 50 clienti, 50 polizze, cataloghi, preventivi,
titoli, incassi, provvigioni e allegati preesistenti sono invariati.
Il database locale conserva sette modelli (cinque precedenti e due bozze),
zero fascicoli fittizi e zero controlli RCA simulati. Audit PHP del 18 ottobre:
nessun avviso o pacchetto abbandonato; restano le segnalazioni frontend
descritte nel precedente audit. Evidenze: work/signing-suite.xml,
work/signing-tests.xml, work/signing-live-report.json,
work/insurance-ui/signing-report.json e work/insurance-ui/signing-pdf-report.json.

Le condizioni provvigionali aggiungono 12 test su permessi, collegamenti,
precisione delle percentuali, campi mancanti, date, sovrapposizioni, revisioni,
annullamenti, conservazione dello storico, filtri e protezione dei moduli.
Il browser ha completato tre flussi e 20 viste desktop/mobile, con controllo
visivo di moduli e tabelle ed esportazione Excel/PDF. Non sono stati rilevati
errori JavaScript, richieste esterne, campi visibili senza etichetta o sbordamenti
orizzontali della pagina. La migrazione è applicata a r4_insurance: cinque
pagine rispondono correttamente, i dati preesistenti sono invariati e le
configurazioni temporanee del controllo sono state annullate. Tabelle delle
provvigioni vuote, in attesa delle condizioni reali. Evidenze:
work/agency-commission-suite.xml, work/insurance-ui/commission-report.json e
work/agency-commission-live-report.json.

Sicurezza: controllati autorizzazioni, collegamenti fra clienti, validazione
degli upload, conservazione privata e risposte di download protette. Una
sessione esistente viene revocata quando l'utente è disattivato; gli URL delle
notifiche non possono portare fuori dal gestionale. Cancellazione di polizze
e documenti registrati riservata all'admin; i collegamenti alle comunicazioni
impediscono di eliminare le polizze. Gli aggiornamenti compatibili PHP hanno
risolto 16 avvisi su cinque pacchetti: Laravel 13.37.0, Livewire 4.4.7,
CommonMark 2.10.3, Flysystem 3.36.0 e Symfony HttpFoundation 7.4.21, con
aggiornamento necessario di Laravel Prompts 0.3.26. Il nuovo Composer audit
non segnala avvisi o pacchetti abbandonati.
npm audit riporta ancora 5 segnalazioni alte e 4 moderate, soprattutto nella catena
di compilazione CSS e in dipendenze delle esportazioni. Gli avvisi includono
esaurimento di risorse su pattern/selector costruiti appositamente e un problema
di controllo dei buffer in particolari funzioni UUID. Servono aggiornamenti
con verifica di compatibilità; non è stata applicata una migrazione forzata
a una nuova versione principale di Tailwind. Dettagli in
work/agency-email-npm-audit.json e work/agency-email-composer-audit-final.json.
Questi controlli non costituiscono una
certificazione dell'assenza di vulnerabilità.

Il recap delle task, i risultati dell'audit e le decisioni mancanti sono in
work/recap-audit-2026-10-16.html. La modifica dal giorno successivo alla
fine lavorazione è bloccata per gli operatori anche se il contratto è attivo.
L'azione esplicita Chiudi lavorazione registra data e autore; il blocco scatta
alla mezzanotte successiva, nel fuso Europe/Rome. Nel giorno della chiusura
restano valide le autorizzazioni ordinarie. Gli stati contrattuali e il vecchio
campo closed_at non determinano questa chiusura e non retrodatano il blocco.
La data non può essere spostata o azzerata dal modulo della polizza.
L'admin conserva l'accesso ma deve motivare le correzioni successive alla fine
lavorazione. L'operatore può segnalare un errore dalla scheda anche dopo il
blocco: gli admin attivi ricevono una notifica interna. Segnalazione, esito,
autori e date restano nello storico; l'esito viene notificato al richiedente
se conserva l'accesso al cliente. Non viene inviata un'email esterna.
Provvigioni e generazione automatica dei moduli rimangono da completare.

## Anagrafica assicurativa

Le sezioni seguenti documentano le implementazioni precedenti; il riepilogo
aggiornato sopra prevale sui riferimenti storici a funzioni allora mancanti.

I 23 campi dell'Excel sono riportati nella scheda, distribuiti tra identità,
contatti, indirizzo, referente e riferimenti in agenzia. Contraente corrisponde
a clients.name; codice fiscale e partita IVA restano separati. I nuovi campi
comprendono titolo, nascita, cellulare, fax, target, contatti del referente,
ufficio, consulente, collaboratore, operatore e notizie salienti. Questi ultimi
riferimenti sono testi dell'anagrafica; l'assegnazione del commerciale nel
gestionale continua invece a determinare la visibilità. I clienti precedenti
senza tipologia rimangono da classificare, senza deduzioni automatiche.

Ogni cliente può avere più polizze, anche dello stesso ramo. La sezione Polizze
contiene i tab Tutte le polizze, RC auto, Infortuni, RC persone e le categorie
aggiunte dall'operatore. Nuova polizza riprende il ramo del tab selezionato.
Compagnia e prodotto sono inseriti dall'operatore, senza elenchi commerciali
presunti. Veicoli e documenti possono essere collegati solo allo stesso cliente.

Le scadenze di polizze e documenti sono derivate dalle rispettive date, con filtri
per scadute, 7/15/30/45/60 giorni e intervallo libero. Le polizze cessate restano
nello storico e non entrano nello scadenzario. Non vengono inviate comunicazioni
automatiche e non vengono aperte pratiche di rinnovo: sono sviluppi successivi.
Le nuove funzioni non reintroducono contabilità, pagamenti, task o calendario.

La migrazione 2026_10_14_150000_add_insurance_client_registry aggiunge i campi
anagrafici, client_vehicles, insurance_policies e i metadati dei documenti.
È già applicata al database locale r4_insurance. Su altre installazioni si usa
php artisan migrate; non utilizzare migrate:fresh su dati da conservare.
L'Excel non è stato importato e nessun dato dimostrativo è stato inserito in MySQL.

## Fornitori: compagnie, agenzie e subagenzie

La voce Fornitori apre le compagnie, ciascuna con nome, contatti, referente,
note e stato attiva/inattiva. Dalla scheda della compagnia si consultano e
aggiungono le agenzie; ogni agenzia può avere subagenzie. Il secondo tab
permette di cercare tutte le agenzie e subagenzie, con filtri per compagnia e stato.
Le loro schede conservano nome, ragione sociale, codice agenzia, partita IVA,
codice fiscale, contatti e PEC, referente principale, sede, note e stato.
La ragione sociale è distinta dalla compagnia assicurativa di appartenenza.
Non sono presenti collegamenti fra fornitori e polizze, come richiesto.

Ogni agenzia o subagenzia può lavorare con più compagnie, attraverso
supplier_agency_company. Una subagenzia può dipendere da una sola agenzia
principale e usare solo compagnie presenti tra i mandati del genitore.
Non sono consentiti collegamenti a sé stessa, cicli o ulteriori livelli.
Le anagrafiche precedenti restano disponibili come compagnia da assegnare,
senza assegnazioni dedotte dai nomi. Per salvarle occorre scegliere la compagnia.
Una compagnia con agenzie o un'agenzia con subagenzie non può essere eliminata.
Prima di cambiare compagnia a un'agenzia con subagenzie, occorre riassegnare
esplicitamente queste ultime. Le modifiche sono verificate in transazione.

Amministratori e responsabili operativi gestiscono le anagrafiche; il ruolo
Amministrazione può consultarle. Gli altri ruoli non accedono al modulo.
Le modifiche sono registrate nel registro attività. La prima migrazione crea
supplier_agencies; la migrazione 2026_10_14_170000_add_supplier_company_hierarchy
aggiunge supplier_companies e le relazioni fra le anagrafiche, con vincoli
contro le cancellazioni dei genitori. Entrambe sono applicate a r4_insurance.
L'admin e le anagrafiche esistenti sono stati conservati; le tabelle delle
polizze non sono state modificate e non sono stati inseriti dati dimostrativi.

I fornitori sono inclusi nel collaudo completo descritto sotto. Oltre ai test
automatici di gerarchia, permessi e audit, nel browser sono state provate
creazione e modifica di compagnie, agenzie e subagenzie, selezioni dipendenti,
filtri e protezione dei collegamenti durante le cancellazioni.

## Rimozione dei moduli

La pulizia applicata il 14 ottobre 2026 ha eliminato 50 tabelle dedicate
a social, shooting, hosting, progetti, task, ticket, contabilità, calendario,
offerte, disponibilità e relative integrazioni.

L'account admin e la sua password sono stati confrontati con la copia precedente:
sono invariati. Sono state rimosse 118 migrazioni dei moduli eliminati e,
dopo averne verificato l'esecuzione, anche la migrazione di pulizia temporanea.
La pulizia ha conservato 26 migrazioni; l'anagrafica assicurativa ne aggiunge
una e i fornitori due, per un totale di 29 migrazioni.
La struttura client_user e il campo clients.country_code fanno ora parte della
migrazione di creazione clienti, così funzionano anche le nuove installazioni.
I nomi delle migrazioni conservate non cambiano, per rispettare il registro esistente;
il database locale è stato aggiornato anche con la nuova migrazione assicurativa.
La migrazione temporanea applicata è archiviata in
storage/app/private/cleanup-20261014/migrazione-applicata.zip.
Il ripristino della versione precedente richiede insieme il backup dei dati e del codice.

Le assegnazioni ai clienti usano la relazione client_user.
L'archivio tecnico module_cleanup_attachment_archive rimane solo nel database
aggiornato, a conservazione degli eventuali metadati dei moduli dismessi.
I documenti dei clienti e il registro attività rimangono attivi.
Non vengono creati nuovi admin, cambiate password o ricreate anagrafiche.

## Verifiche

Il collaudo aggiornato del 15 ottobre 2026 ha superato 84 test con 966 asserzioni,
senza errori, fallimenti o test saltati. Comprende persone e aziende, campi
Excel, più polizze e tab per ramo, veicoli, documenti riservati, permessi,
identificativi duplicati, audit e gerarchia dei fornitori. Sono stati aggiunti
tre test di regressione per i limiti inclusivi dei filtri sulle scadenze,
la riassegnazione e rimozione coerente dei documenti e il rifiuto di modifiche
non valide o file troppo grandi senza alterare i dati esistenti.
Comprende anche le nuove funzioni dell’anagrafica: referenti aggiuntivi,
relazioni reciproche, nuclei familiari, beni collegati alle polizze, preferenze,
storico contatti, consensi documentati e unificazione controllata dei duplicati.
I test verificano gli accessi tra clienti, le prove dei consensi, la conservazione
dei file, i conflitti e le anteprime obsolete prima dell’unificazione.
Anche la compilazione Vite è riuscita.

Sul database locale MySQL sono stati verificati in sola lettura 35 vincoli
relazionali e 14 controlli di coerenza tra clienti, targhe, polizze, documenti,
consensi, nuclei familiari e fornitori. Nessuna migrazione o colonna pendente,
nessuna delle 50 tabelle dismesse, nessun documento mancante e cinque pagine
principali caricate correttamente. La migrazione dell’anagrafica estesa risultava
già applicata al controllo; lo schema effettivo è stato verificato senza rieseguirla. L'admin
corrisponde alla copia precedente, inclusa la password. Al momento della
verifica le tabelle di clienti, veicoli, polizze, documenti e fornitori sono
vuote: le prove di inserimento, modifica e cancellazione usano esclusivamente
SQLite e file di prova separati, non il database MySQL.

Il collaudo nel browser senza finestre ha verificato dieci scenari operativi,
24 combinazioni di pagina e dimensione schermo (1440x1050 e 390x844) e
42 collegamenti interni. Include caricamento e download di documenti,
cancellazioni bloccate quando esistono dipendenze, eliminazioni consentite,
ricerca per targa o numero polizza, tutti i filtri delle scadenze e accessi
anonimi negati. Nessun errore JavaScript, risposta del server con stato 500
o fuoriuscita orizzontale dell'intera pagina. Durante il controllo aggiuntivo su
smartphone è stato corretto un doppio scorrimento nelle tabelle di polizze,
veicoli, scadenze e fornitori: ora il solo contenitore scorre e permette di
raggiungere anche l'ultima colonna. La verifica di questa condizione è inclusa
nel collaudo di tutte le pagine interessate. Le schermate prodotte sono state
ispezionate visivamente.

Un controllo mirato aggiuntivo ha superato cinque scenari: navigazione desktop,
menu mobile con Escape e chiusura dopo il cambio pagina, tab e scorrimento delle
tabelle, raggiungibilità dei pulsanti nei moduli lunghi e conferme di eliminazione
con focus da tastiera, Escape e annullamento senza perdita di dati.

Le evidenze aggiornate sono salvate in work/registry-full-suite.xml,
work/registry-live-report.json e work/insurance-ui/full-report.json;
il controllo aggiuntivo di navigazione è documentato in
work/insurance-ui/navigation-report.json. Gli script e i dati dimostrativi del
browser sono sotto work/insurance-ui.

Il collaudo specifico delle nuove funzioni è documentato in
work/insurance-ui/registry-report.json: sette scenari, 30 combinazioni di pagina e schermo,
39 collegamenti, prove di registrazione, modifica, relazione reciproca,
consenso e revoca, filtro ramo/sottoramo e unificazione con scelta dei dati.
Su smartphone sono stati provati anche cambio sottoramo, campi condizionali
e salvataggio di una dichiarazione negata, con i pulsanti finali raggiungibili.

## Stato delle task al 15 ottobre 2026

Il confronto usa le specifiche R4 fornite e le decisioni successive della chat.
Le voci completate sono circoscritte alle funzioni effettivamente realizzate e
collaudate; il CRM completo e l'intero MVP della proposta rimangono da completare.

| Task | Stato | Perimetro verificato |
| --- | --- | --- |
| Pulizia dei moduli dismessi | Completata | Navigazione, rotte, codice e tabelle dei moduli indicati; admin conservato. |
| Anagrafica persone e aziende | Completata, importazione eseguita con rilievi | I 23 campi dell'Excel, creazione, modifica, ricerca e filtri. Importati 4.884 clienti; 71 candidati in sospeso, come dettagliato sotto. |
| Più polizze per cliente e tab per ramo/sottoramo | Funzione completata, catalogo da confermare | Due livelli distinti, sottotab del ramo selezionato, prodotto separato. Le classificazioni già inserite non vengono cambiate automaticamente. |
| Preferenze e referenti aggiuntivi | Completata | Canale, fascia oraria e indicazioni; referente principale dell’Excel e ulteriori contatti con ruolo e recapiti. Le preferenze non assegnano consensi. |
| Relazioni e nuclei familiari | Completata | Relazioni reciproche tra clienti persone/aziende, ruoli e note; nuclei di persone fisiche. Visibilità limitata alle anagrafiche autorizzate. |
| Immobili e altri beni | Completata per i dati definiti | Tipologia, identificativo, indirizzo e note, collegamento alla polizza e cancellazione protetta. |
| Storico dei contatti | Completata per l’inserimento manuale | Data, canale, oggetto, dettaglio, esito e autore; modifiche tracciate. Nessun invio automatico. |
| Registro dei consensi documentati | Completata | Esito esplicito, data, dichiarante e ruolo, riferimento/versione del modulo, prova e storico non sovrascrivibile. Invio elettronico per tutti i documenti oppure per una polizza. |
| Duplicati e unificazione | Completata con verifica amministrativa | Segnalazioni per nome, identificativi e recapiti; confronto dei campi, conservazione dello storico e trasferimento dei collegamenti. Nessuna unificazione automatica. |
| Veicoli e targhe | Completata | Più veicoli per cliente, normalizzazione della targa e collegamenti coerenti alle polizze. |
| Documenti del cliente | Completata per la gestione base | Caricamento, download riservato, categoria, metadati, scadenza, modifica e cancellazione; collegamenti a cliente, polizza e veicolo. |
| Scadenze nella scheda cliente | Completata | Polizze e documenti, scadute, 7/15/30/45/60 giorni e intervallo libero; esclusione delle polizze cessate. |
| Anagrafica fornitori | Completata | Compagnie, agenzie e subagenzie, filtri, gerarchia e cancellazioni protette. |
| Permessi e integrità dei collegamenti | Verificata per questi moduli | Visibilità per ruolo/assegnazione, controllo degli identificativi duplicati, audit e blocco dei collegamenti tra clienti o compagnie diversi. |

Restano aperti nelle specifiche:

- Portafoglio generale, dashboard assicurativa e report operativi; lo scadenzario generale è disponibile.
- Pratiche di rinnovo con responsabile, stati, cronologia ed esito che aggiorna il portafoglio.
- Richieste e preventivi assicurativi di più compagnie, confronto e storico delle proposte.
- Definizione dei documenti obbligatori per ramo e raccolta tramite link cliente; checklist manuali, versioni e validazione sono disponibili.
- Risoluzione dei 2.254 gruppi di polizze e delle 71 anagrafiche in sospeso. Sono state importate 2.605 polizze con collegamenti verificati, incluse le 281 approvate il 16 ottobre 2026; Archivio/OLD restano da interpretare e non assegnano uno stato in vigore.
- Comunicazioni e promemoria email/WhatsApp, OCR/AI, integrazioni, portale clienti e altre evoluzioni della proposta.

I vecchi moduli task, ticket, calendario, offerte commerciali e contabilità
sono stati rimossi per decisione dell'utente: non vanno riattivati per soddisfare
automaticamente voci della proposta precedente. Eventuali nuovi flussi
assicurativi richiedono una definizione dedicata.

Fonti: Anagrafica 08_10_2026 10.45 [IVAN SPADONE ].xls, foglio Dati,
intestazioni A7:W7; R4_Insurance_Specifiche_Funzionali_PreSviluppo.pdf,
pagine 5, 15, 16, 21, 29 e 33; Bozza_Progetto_R4_Insurance_Sodano_Consulting.pdf,
pagine 3, 4 e 8. I file sorgente sono nella cartella superiore al gestionale.

## Riscontro di tutti i fogli dei Moduli e decisioni ancora necessarie

Sono stati esaminati tutti i nove fogli di
Moduli 215797176 08_10_2026 10.51.Xlsx, incluse le formule di collegamento:

| Foglio | Utilizzo e riscontro |
| --- | --- |
| Dati | Campi del contraente, referente e ruolo; compagnia, ramo, numero e decorrenza della polizza. Nessun catalogo ramo/sottoramo. |
| Allegato 3 | Informazioni sul distributore e intermediari; documento da collegare al fascicolo, non nuovi campi personali del cliente. |
| Allegato 4 Ter | Regole di comportamento e riferimento alla valutazione delle esigenze; non contiene il questionario. |
| Allegato 4bis | Informazioni specifiche per prodotti di investimento; non è un modulo universale per tutte le polizze. |
| Allegato 4 | Distribuzione di prodotti non IBIP, con riferimento specifico alle provvigioni RCA. |
| Consegna Documenti | Elenco dei documenti e ricevuta, con parti condizionate a investimento/RCA (A14:D27). |
| Dichiarazione Coerenza | Dichiarazione collegata alla polizza, al contraente e al firmatario; richiama il questionario senza definirne i campi. |
| Consenso Invio Elettronico | Ambito generale o singola polizza e revocabilità; esclusione espressa dei messaggi promozionali (A10:D19), firmatario e qualità. |
| Iter | Documenti delle fasi operative (A3:D14), compresi privacy, mandato, questionario, DIP e target market; manca una matrice per singolo ramo. |

La ricerca nelle fonti IVASS distingue i rami ufficiali dalle suddivisioni operative:
RC auto è il ramo 10, Infortuni il ramo 1, RC generale il ramo 13.
RC famiglia e RC professionale sono coperture da distinguere nell’ambito della
responsabilità civile. La proposta operativa è usare il ramo e un sottoramo
modificabile, con prodotto della compagnia separato. Non è stato imposto un
catalogo definitivo né convertita automaticamente la voce «RC persone».
Fonti: [gestione rami IVASS](https://www.ivass.it/pubblicazioni-e-statistiche/statistiche/elaborazioni-statistiche/gr/index.html),
[guida IVASS alla responsabilità civile](https://www.ivass.it/consumatori/imparaconivass/guide/Guida_5_Le_assicurazioni_della_responsabilita_civile_versione_web.pdf).

Il [Provvedimento IVASS 147/2024](https://www.ivass.it/normativa/nazionale/secondaria-ivass/normativi-provv/2024/provv-147/index.html)
ha sostituito i precedenti Allegati 3, 4, 4-bis e 4-ter con i Moduli unici
precontrattuali (MUP). I titoli e i contenuti dei fogli forniti corrispondono ai
modelli precedenti. Le categorie documentali distinguono quindi MUP e allegati
precedenti, conservabili come storico. Non sono stati generati testi legali né
modificati i file Excel originali.

Decisioni/documenti da confermare con l’agenzia:

- Normalizzazione del catalogo ricavato dal nuovo export Gestionale e significato
  preciso di «RC persone»; gestione delle polizze con più rami/garanzie.
- Versioni aggiornate dei moduli da usare, compresa l’informativa privacy
  completa e le finalità approvate. Nel workbook la privacy è richiamata ma
  non ne è riportato il testo. Il consenso elettronico non copre il marketing.
- Matrice di campi e documenti obbligatori per ramo/sottoramo e questionari
  effettivi delle compagnie: il foglio Iter ne cita l’esistenza senza riportarli.
- Correzioni delle formule nei moduli prima di un’eventuale compilazione
  automatica: il consenso elettronico D6 punta a Dati!D30 (Referente), mentre
  Consegna Documenti D5 e Dichiarazione Coerenza D8 puntano a Dati!D31 (Ruolo).
  La riga del firmatario e quella del contraente richiamano entrambe Dati!D26.
  Nel gestionale contraente, dichiarante e ruolo sono registrati separatamente.

L’unificazione è disponibile solo all’amministratore, richiede identità e
permessi coerenti e un’anteprima ancora attuale. Targhe duplicate, polizze con
stessa compagnia/numero e relazioni discordanti richiedono una verifica prima
di procedere. Il record assorbito rimane archiviato; le prove dei consensi e le
polizze cui fanno riferimento non possono essere eliminate.

## Sviluppo e aggiornamenti

PHP 8.3 o superiore, Composer, Node.js e npm sono necessari per lo sviluppo.
Installare le dipendenze usando i lockfile del pacchetto e compilare con npm run build.
La suite si esegue con vendor/bin/phpunit e usa un database SQLite in memoria.
Nell'aggiornamento conservare il file .env esistente, la APP_KEY e tutti i file
di storage. Non sovrapporre semplicemente questo archivio al vecchio codice:
le rimozioni elencate nel dettaglio di pulizia sono parte della modifica.

I testi legali con identità, indirizzo e riferimenti di Sodano non sono stati
riscritti: richiedono testi e dati verificati dell'agenzia. Il marchio visivo
del gestionale e delle pagine di accesso è stato sostituito.

## Marchio e aspetto personale

La firma blu «gs» è isolata dal logo originale tramite una maschera SVG e usata
nel menu, nelle schermate di accesso e nella favicon. I tratti provengono dal
file fornito; il colore principale rilevato è #0B498C. La scritta è
«Soluzioni Assicurative SAS», con logo e testo ingranditi nella barra.

Impostazioni → Aspetto offre 17 colori personali: sidebar, voce attiva, barra
superiore, sfondo generale, pannelli, campi, intestazioni e righe delle tabelle,
hover delle righe, pulsanti, titoli/collegamenti, testo preferito, hover del menu,
dei pulsanti principali e secondari e dei collegamenti, sfondo del login.
Il modulo di accesso ha sfondo nero iniziale, indipendente dai pannelli interni.
Anteprima immediata, annullamento e ripristino sono disponibili. I colori di
testo vengono adattati quando il contrasto è insufficiente.

Ogni utente salva solo le proprie preferenze in users.appearance, senza poter
modificare quelle di altri account. Una preferenza cifrata nel browser conserva
solo i colori per la schermata di accesso, senza dati identificativi. Su un
browser nuovo il tema personale viene caricato dopo l'autenticazione; il tema
dell'account autenticato prevale sempre sulla preferenza del browser.
La migrazione aggiuntiva è 2026_10_15_140000_add_appearance_to_users.

Verifica finale: 94 test e 2.283 asserzioni superati; collaudo nel browser
su desktop e smartphone con salvataggio, annullamento, ripristino, hover,
navigazione e login. Il controllo locale in sola lettura conferma la migrazione,
le pagine aggiornate e la password admin invariata.

## Nuovo export Gestionale esaminato il 15 ottobre 2026

Il file Gestionale 08_10_2026 10.14 [IVAN SPADONE ].xls contiene il foglio Dati,
con intestazioni in A7:BB7. Le 7.411 righe dotate di numero polizza non
equivalgono a 7.411 polizze distinte: si ripetono 1.494 chiavi formate da
compagnia, agenzia e numero polizza. La riga finale senza numero è esclusa
dall'analisi dei record.

Sono presenti 45 etichette di ramo, 43 etichette non vuote di sottoramo,
21 etichette di compagnia e 18 di agenzia. Sono valori originali da verificare:
fra i rami compaiono SPESE EMISSIONE, FATTURA e QUOTA ASSOCIATIVA; SPESE compare
anche come compagnia. Sottoramo è vuoto in 1.441 righe. AUTO/Auto,
COMMERCIO/Commercio e altre varianti non sono state unificate automaticamente.

Il nuovo file contiene 2.512 identificativi fiscali distinti: 2.448 risultano
anche nell'export Anagrafica, mentre 64 non vi trovano corrispondenza esatta.
Telefono, email, indirizzo e altri dati completi restano da collegare
dall'Anagrafica. Per 18 identificativi fiscali il nuovo export usa più
denominazioni: serve verifica prima di deduplicare.

Alcune agenzie, fra cui MINERVA ASSICURAZIONI e CARDASCIO, compaiono associate
a più compagnie. La scelta confermata è una sola anagrafica agenzia con più
compagnie; la migrazione 2026_10_15_180000_add_agency_company_mandates realizza
questa relazione e conserva i collegamenti precedenti.
Il file distingue compagnie/agenzie, ma non esplicita una gerarchia completa
agenzia/subagenzia. Le colonne sui documenti non sostituiscono i relativi file.
Contiene inoltre premi, rate, frazionamento, rinnovo, pagamenti e provvigioni;
la loro presenza non riattiva i moduli amministrativi eliminati.

Il contenitore XLS presenta un'incongruenza OLE: la prima analisi ha usato
xlrd in lettura tollerante, mantenendo l'originale invariato. Prima del
caricamento è stato confrontato ogni valore con un secondo lettore indipendente,
python-calamine: 112.700 celle Anagrafica e 400.248 celle Gestionale, nessuna
differenza. I riferimenti alle fonti e le impronte SHA-256 sono conservati.
L'analisi iniziale aggregata resta in work/policy-export-analysis.json;
il confronto indipendente è in work/source-reader-verification.json.

## Importazione clienti e agenzie eseguita

Il database locale r4_insurance contiene 4.884 clienti, 16 agenzie, 20
denominazioni di compagnie e 26 collegamenti agenzia/compagnia. Il caricamento
successivo delle polizze è descritto nella sezione dedicata sotto. Le 7 anagrafiche con campi discordanti e le 64 presenti
solo nel file polizze restano in sospeso: non sono state unificate o inventate.
SPESE e DI EMISSIONE sono esclusi dai fornitori. I ruoli e i nomi ambigui
(EUROPE/EUROPE ASSISTANCE, PRIMA, DUAL, BHITALIA) sono mantenuti distinti e
segnalati; il file non consente di dedurre una gerarchia di subagenzie.

Impostazioni → Importazione Excel contiene i collegamenti aggiornati di rami e
sottorami, i controlli e il prospetto originale scaricabile. Esito anagrafiche mostra
gli elementi importati o in sospeso e apre le schede create. La sezione è
riservata agli amministratori. Le proposte per le polizze restano da confermare:
Archivio/OLD non determinano lo stato, rate e rinnovi non diventano duplicati.

Gli identificativi fiscali dubbi, i codici numerici non classificati come
CF/P.IVA, le province non espresse come sigla, le date e le email non valide
restano nei dati originali consultabili nella scheda cliente. Le email valide
condivise sono conservate in tutte le schede: non identificano da sole una
persona. Una modifica che mantiene la medesima email importata è ammessa;
la scelta di un'email diversa mantiene il controllo dei duplicati. Non vengono
dedotti consensi, documenti, assegnazioni commerciali o tipo azienda dai nomi.
Lo stato cliente Attivo indica disponibilità della scheda, non polizze attive.

La migrazione 2026_10_15_190000_create_registry_import_entries conserva chiavi,
impronte, riferimenti alle righe, esiti e valori da verificare. L'importazione
è in transazione e riutilizza il registro per non duplicare un lotto già
caricato; modifiche alle fonti già importate richiedono confronto. Gli utenti,
la password admin e le polizze sono stati confrontati prima e dopo: invariati.
La copia preventiva è nell'area privata storage/app/private/registry-import-backup-*.

La preparazione riutilizzabile è scripts/prepare_insurance_import.py,
con dipendenze in scripts/requirements-import.txt. Il lotto completo si trova
in storage/app/private/insurance-import e non viene servito pubblicamente.
work/import-registries.php esegue la sola anteprima; l'opzione --apply esegue
il caricamento e la migrazione prevista, con guardia sul database locale,
backup, verifica delle fonti e registro. Il prospetto generato con artifact-tool
è outputs/01a13a0a-2993-7ac0-944b-064563a2c934/Preparazione-importazione-R4.xlsx.

## Filtri, esportazioni e favicon

Ogni tabella effettiva dispone di Filtra tabella, Esporta Excel ed Esporta PDF.
I pulsanti dei campi aggiungono criteri leggibili, con condizioni selezionabili,
valore, Applica filtri, Rimuovi filtro e Azzera filtri. Per più criteri si può
scegliere Tutti o Almeno uno. Date e importi adottano controlli appropriati
senza richiedere la scelta tecnica del tipo di confronto. I colori e gli
hover seguono il tema personale; i controlli si adattano allo smartphone.

L'esportazione include tutti i risultati filtrati, anche oltre la pagina
corrente. Le letture successive mantengono ricerca, autorizzazioni e visibilità;
un errore interrompe l'export anziché scaricare un insieme incompleto.
Excel conserva identificativi con zeri iniziali e testi come stringhe, senza
eseguirli come formule. Il PDF include filtri, intestazioni e pagine numerate,
con font Ubuntu incorporato. ExcelJS e jsPDF sono caricati solo quando servono.

La favicon GS è ingrandita dentro un cerchio bianco. Sono presenti SVG,
PNG 16/32/48, Apple 180 e ICO multirisoluzione, con versione aggiornata dei
collegamenti anche nel login. Il logo della navigazione resta quello fornito.

Verifica aggiornata: 104 test PHP, 2.380 asserzioni, quattro test dei confronti
dei filtri e compilazione Vite superati. Il browser di prova ha verificato
filtri Tutti/Almeno uno, rimozione e azzeramento, 520/260 righe esportate su
più pagine, Excel senza formule involontarie, PDF, fornitori plurimandatari,
anteprima e smartphone. Nessun errore JavaScript o risposta 500. Undici pagine
reali e i collegamenti importati sono stati controllati in sola lettura.
Evidenze: work/registry-import-suite.xml, work/registry-import-report.json,
work/import-live-report.json e work/insurance-ui/import-tables-report.json.

## Catalogo rami, agenzie, documenti e scadenze

Il catalogo operativo è disponibile da Fornitori → Rami e sottorami e dalle
Impostazioni. Dopo i cinque accorpamenti approvati contiene 34 rami e 44
sottorami collegati. RCA/RCAUTO confluiscono in RC auto; FIDEIUSSIONE/FIDEJUSSIONI
in Fideiussioni; GLOBALE FABBRICATI/GLOBALI FABBRICATI in Globale fabbricati;
VIAGGI/VIAGGIO in Viaggi; CANE& GATTO/CANE&GATTO in Cane e gatto.
RC auto mantiene AUTO e MOTO: le ripetizioni RCA/RCA e RCAUTO/RCAUTO diventano
sottoramo non specificato, senza dedurre il tipo di veicolo. I nomi equivalenti
dei sottorami Viaggi e Cane e gatto sono unificati; CONDOMINIO e FABBRICATO
restano distinti. Le righe amministrative sono escluse. Il filtro Solo da
verificare mostra 12 rami e 17 sottorami ancora da confermare, incluso PROVVIS.
Le classificazioni dubbie e le etichette che potrebbero indicare prodotti
restano invariate: non è stato assegnato loro automaticamente un nuovo ramo.
Le voci si possono inserire, modificare o disattivare; quelle già utilizzate
conservano nomi e collegamenti. La selezione della polizza propone soltanto
i sottorami del ramo scelto, mantenendo le tab nella scheda cliente.

Il nome commerciale del prodotto usa il campo product già esistente, distinto
da category e subcategory nel modulo, nella scheda, nelle tabelle, nei filtri
e nelle esportazioni Excel/PDF. Le etichette ambigue dell'Excel non compilano
automaticamente il prodotto. L'anteprima mostra il catalogo corrente con
originali affiancati; il prospetto iniziale e il lotto firmato sono invariati.
Per esportare la situazione aggiornata si usano i pulsanti della tabella.

La migrazione 2026_10_15_220000_create_insurance_catalog_aliases conserva 21
collegamenti dalle denominazioni sorgente ai record accorpati, anche quando
il sottoramo deve restare vuoto. InsuranceCatalog::importPreview li rispetta
senza ricreare i doppioni. I destinatari degli accorpamenti non si cancellano
né si spostano; è possibile disattivarli. ApprovedInsuranceCatalogMerges applica
solo i cinque gruppi autorizzati, in transazione: modifiche inattese, checklist
o collegamenti a polizze/richieste fermano l'intera operazione.

Le 16 agenzie conservano i 26 collegamenti alle 20 denominazioni di compagnie.
Il controllo dei duplicati considera nome e codice agenzia; più mandati si
aggiungono alla stessa scheda. I dati che l'Excel non fornisce, come recapiti
e gerarchie non esplicite, restano da completare. Con l'importazione delle
polizze, compagnia e agenzia sono collegate tramite riferimenti verificati.

La scheda Documenti gestisce richieste manuali con termine modificabile,
ricezione, validazione con operatore e data, nuove versioni e storico dei file.
Una nuova versione o una modifica dei dati richiede una nuova verifica.
Le versioni precedenti restano scaricabili e sono escluse dallo scadenzario.
File collegati a richieste o versioni non si eliminano; i collegamenti sono
conservati anche quando si unificano due clienti. Ogni ramo e sottoramo può
avere una checklist di categorie documentali, applicabile a cliente o polizza
senza duplicare le richieste. Le checklist partono vuote: i moduli disponibili
non definiscono obblighi completi per ciascun ramo. L'aggiunta di una richiesta
non invia messaggi al cliente.

Operatività → Scadenze mostra polizze non cessate, documenti correnti e
richieste ancora da ricevere. Sono disponibili periodi 7/15/30/45/60 giorni,
scadute, intervallo personalizzato e filtri per tipo, cliente, ramo, compagnia
e responsabile cliente. L'ultimo giorno è incluso. Visibilità e scaricamenti
rispettano le autorizzazioni dell'anagrafica. Le nuove tabelle mantengono
filtri avanzati a pulsanti e scaricamenti Excel/PDF.

Applicate le migrazioni 2026_10_15_200000_create_insurance_catalog e
2026_10_15_210000_add_document_workflow. In questa fase nessuna polizza importata, nessuna
richiesta o documento dimostrativo nel database reale; le 71 anagrafiche
sospese sono invariate. I 4.884 clienti e le credenziali esistenti sono stati
confrontati prima e dopo. Copie preventive nell'area privata catalog-backup-*
e document-workflow-backup-*; nessuna migrazione rimasta da eseguire.

Verifica: 121 test PHP e 2.618 asserzioni superati; sei flussi browser con
database isolato, sette controlli di layout desktop/mobile, scaricamento del
file originale e lettura degli Excel filtrati; nessun errore JavaScript o
risposta 500. Sedici pagine reali e i collegamenti del catalogo sono stati
verificati in transazione di sola lettura. Superati anche i quattro test dei
filtri e la compilazione Vite; controllato visivamente il PDF esportato.
Evidenze:
work/catalog-documents-suite.xml, work/catalog-apply-report.json,
work/document-workflow-apply-report.json, work/catalog-documents-live-report.json
e work/insurance-ui/catalog-report.json.

Accorpamenti applicati con work/apply-approved-catalog-merges.php --apply,
copia preventiva privata catalog-merges-backup-*, controllo delle fonti e
confronto di utenti, clienti, registro importazioni, agenzie, compagnie,
documenti e richieste: invariati. Gli accorpamenti non hanno importato polizze e non hanno lasciato migrazioni
pendente. Provata anche la ripetizione dell'importazione del catalogo in un
savepoint poi annullato: zero rami e zero sottorami ricreati.
Verifica aggiornata: 127 test PHP, 2.702 asserzioni; browser isolato con otto
controlli desktop/mobile, selezione ramo/sottoramo, prodotto indipendente,
tab, filtro prodotto, Excel letto e PDF scaricato; nessun errore JavaScript.
Evidenze: work/approved-catalog-merges-suite.xml,
work/approved-catalog-merges-report.json,
work/insurance-ui/catalog-merges-ui-report.json.

## Prima importazione polizze eseguita

Caricate 2.324 polizze nel database locale r4_insurance, tutte con stato
Da verificare. I restanti 2.535 gruppi del lotto sono registrati come in
sospeso: nessuna riga è stata scelta automaticamente tra rinnovi o rate.
Il registro contiene tutti i 4.859 gruppi e conserva le 6.465 righe assicurative
originali. Sono rimasti invariati i 4.884 clienti, le 71 anagrafiche sospese,
fornitori, catalogo, utenti, documenti, richieste, veicoli e beni.

L'importazione usa il registro anagrafiche per identificare il cliente, con
confronto della fonte, del nome e dell'identificativo; non deduce nuovi clienti
da corrispondenze approssimative. Applica gli accorpamenti approvati ai valori
originali di ramo/sottoramo, senza usare automaticamente le proposte iniziali.
Compagnia e agenzia devono essere presenti, attive e collegate da un mandato.
Il prodotto resta vuoto quando non definito; Interesse Assic. resta testo e
non genera veicoli. Il premio annuale è distinto dal premio rata; la scadenza
contrattuale non viene sostituita dalla prossima rata. Non si creano allegati
o consensi da riferimenti testuali dell'Excel.

I rilievi sui gruppi sospesi si sovrappongono: 1.495 numeri ripetuti, 841
denominazioni di compagnia da confermare, 648 classificazioni dubbie, 233
identità cliente da verificare, 167 identificativi fiscali non validi, 157
premi non validi, 152 clienti non collegabili, 109 numeri associati a più
contraenti e due gruppi con scadenza precedente alla decorrenza. Il controllo
dei campi obbligatori segnala inoltre questi due gruppi con date non coerenti.
Questi conteggi non si sommano per ottenere il totale dei sospesi.

Impostazioni → Importazione Excel → Esito polizze mostra importate e sospese,
con ricerca, filtri avanzati ed esportazioni Excel/PDF. Dati originali apre
il confronto delle righe conservate, disponibile agli amministratori. Nella
scheda cliente restano le tab per ramo e sottoramo; la polizza mostra anche
l'agenzia e i collegamenti ai fornitori, rispettando i permessi. In modifica,
le agenzie dipendono dalla compagnia; non si possono eliminare fornitori o
rimuovere mandati utilizzati. I riferimenti all'importazione impediscono di
cancellare una polizza importata; lo stato Cessata consente di conservarla.

Archivio/OLD sono conservati nella fonte. Le date delle polizze Da verificare
appaiono nello scadenzario con la situazione Stato polizza da verificare,
senza presentarle come coperture certamente in vigore o scadute. La modifica
dello stato rimane una decisione dell'operatore.

Applicata la migrazione 2026_10_15_230000_add_policy_imports. PolicyExcelImporter
verifica impronte dei file e riferimenti, registra le fonti e lavora in
transazione. Un secondo passaggio non duplica le polizze e non sovrascrive
correzioni degli operatori. Fonti modificate dopo l'importazione o riferimenti
mancanti interrompono il caricamento. work/import-policies.php produce una
verifica; --apply esegue il caricamento sul solo database locale previsto,
con copia preventiva privata policy-import-backup-* e confronto dei dati
fuori ambito. Il controllo finale conferma zero duplicati, riferimenti validi
e nessuna migrazione pendente.

Verifica: 135 test PHP superati, più verifica mirata dello stato aggiornato nell’esito importazione; browser isolato con otto
controlli desktop/mobile, modifica polizza, collegamenti ai fornitori, gruppi
sospesi, scadenze con stato incerto, Excel con zeri iniziali conservati e PDF
controllato visivamente. Dodici pagine reali rispondono correttamente.
Evidenze: work/policy-import-suite.xml, work/policy-import-report.json,
work/policy-import-live-report.json, work/insurance-ui/policy-import-ui-report.json.

## Accorpamento approvato delle polizze del 16 ottobre 2026

Importati i 281 gruppi approvati, creando una polizza per gruppo con stato
Da verificare e conservando integralmente tutte le 564 righe originali.
Il totale aggiornato è di 2.605 polizze importate e 2.254 gruppi sospesi.
Il registro continua a contenere 4.859 gruppi e le 6.465 righe assicurative.

La corrispondenza richiede valori identici di contraente e identificativo
fiscale, compagnia, agenzia, numero, ramo, sottoramo, interesse assicurato,
decorrenza, scadenza, premio annuale e rata, frazionamento, tacito rinnovo e
note. Le differenze nei dati delle rate rimangono nella fonte e non creano
rate o movimenti. Archivio e OLD restano consultabili senza dedurre lo stato
della copertura. Il confronto delle righe mostra la decisione di accorpamento,
la data e l'operatore; ogni gruppo ha un evento nel registro attività.

ApprovedEquivalentPolicyImport verifica l'elenco approvato e le impronte
delle fonti in transazione. La ripetizione salta le 281 polizze già create
senza duplicarle o sovrascrivere modifiche. Il caricamento conserva utenti,
clienti, fornitori, catalogo, documenti, richieste, veicoli, beni, polizze
precedenti e tutti gli altri gruppi sospesi. Copia preventiva e approvazione
sono nell'area privata equivalent-policy-backup-*.

Superati 13 test mirati con 145 asserzioni. Il browser isolato ha verificato
desktop e smartphone, collegamenti tra esito, fonte, scheda cliente, tab e
scadenze, conservazione di entrambe le righe e assenza di errori JavaScript.
Schermate controllate visivamente. La verifica sul database locale conferma
281 accorpamenti con 564 righe collegate, zero duplicati, collegamenti validi,
nessuna migrazione pendente e 14 pagine correttamente raggiungibili.
Evidenze: work/equivalent-policy-suite.xml,
work/equivalent-policy-import-report.json, work/policy-import-live-report.json,
work/insurance-ui/equivalent-policy-ui-report.json.

## Backup cifrato e ripristino isolato

`insurance:backup` usa Restic per conservare copie cifrate e deduplicate del
database e dei dischi `attachments`, `local` e `public`. Include la chiave
applicativa e le precedenti chiavi necessarie a leggere i campi cifrati.
Non include il codice del gestionale: conservare separatamente la versione
del progetto e le dipendenze corrispondenti. Il manifest registra l'impronta
di `composer.lock`, i conteggi e le impronte SHA-256 di dati e documenti.

Richiede Restic e i programmi `mysqldump` e `mysql` della stessa famiglia del
server. La prima implementazione supporta MySQL/MariaDB locale, tabelle
InnoDB e archivi documentali locali. Rifiuta schemi con viste, trigger,
procedure o eventi, che richiederebbero una procedura dedicata.
Non configura account cloud, trasferimenti esterni o esecuzioni automatiche.

Variabili da configurare nell'ambiente di installazione:

| Variabile | Contenuto |
| --- | --- |
| `INSURANCE_BACKUP_RESTIC` | Percorso del programma Restic |
| `INSURANCE_BACKUP_MYSQLDUMP` | Percorso di mysqldump |
| `INSURANCE_BACKUP_MYSQL` | Percorso del client mysql |
| `INSURANCE_BACKUP_REPOSITORY` | Percorso assoluto di una destinazione locale, disco esterno o volume montato |
| `INSURANCE_BACKUP_WORK_DIRECTORY` | Cartella temporanea privata, esterna al progetto e agli archivi |
| `INSURANCE_BACKUP_PASSWORD_FILE` | File privato con una password casuale robusta, almeno 20 caratteri |

Repository, cartella di lavoro e file password devono essere separati fra
loro e dagli archivi del gestionale. Su Windows limitare esplicitamente le
ACL all'account del servizio e agli amministratori autorizzati: i permessi
PHP non sostituiscono le ACL. La cartella di lavoro contiene temporaneamente
SQL, documenti e chiavi in chiaro; viene ripulita alla fine dell'operazione.
Una cartella temporanea rimasta dopo un'interruzione blocca il nuovo backup
finché non viene esaminata. Non versionare copie o password in Git.
Custodire una seconda copia della password fuori dal computer: senza di essa
il repository cifrato non è recuperabile.

Quando possibile, la preparazione utilizza hard link temporanei sullo stesso
filesystem; altrimenti copia i file. Non modifica contenuto, permessi o date
degli originali. Restic archivia i contenuti cifrati, indipendentemente dai
collegamenti temporanei, che vengono poi rimossi. Gli hash vengono
controllati anche dopo l'archiviazione. Gli allegati devono quindi restare
fermi fino al termine dell'intera operazione, come il database.

Inizializzare una destinazione nuova:

```console
php artisan insurance:backup init
```

Prima di creare una copia, mettere l'applicazione in manutenzione, sospendere
scheduler e worker e attendere la conclusione delle richieste già avviate.
Il flag conferma che queste operazioni sono state realmente eseguite.
Riattivare l'applicazione e i servizi anche se il backup non riesce.

```console
php artisan down
php artisan insurance:backup create --writers-stopped
php artisan up
php artisan insurance:backup check
```

`check` legge e verifica tutti i dati cifrati del repository. La creazione
confronta il database prima e dopo la copia, controlla che i documenti
collegati esistano e salva `last-backup.json` nella cartella di lavoro.
Non cancella automaticamente copie precedenti.

La prova di ripristino richiede l'identificativo completo della copia, una
cartella nuova e un database nuovo con prefisso `r4_restore_`. I valori tra
parentesi angolari nell'esempio sono segnaposto da sostituire.

```console
php artisan insurance:backup restore-test --snapshot=<identificativo-completo> --target=<nuova-cartella-privata-assoluta> --database=r4_restore_20261020a
```

Il ripristino rifiuta destinazioni già esistenti, non sovrascrive il database
operativo e non avvia email o processi applicativi. Confronta ogni tabella
e documento con il manifest, controlla i collegamenti del database e la
decifrabilità dei campi applicativi. Salva `verification.json` nella
destinazione e `last-restore-test.json` nella cartella di lavoro.
In caso di errore conserva la destinazione parziale per l'analisi.
La destinazione ripristinata contiene dati in chiaro e deve restare privata.

Il caricamento SQL usa un account casuale temporaneo, limitato al nuovo
database e alle connessioni locali. Non dispone di permessi sugli altri
database, di eliminazione delle tabelle o di lettura dei file del server.
Viene rimosso sia dopo il caricamento sia in caso di errore. La connessione
amministrativa usata per questa prova deve poter creare e rimuovere questi
account e concedere i permessi sul nuovo database. Le credenziali temporanee
non vengono salvate nei report o nei log applicativi. Un'interruzione forzata
richiede anche il controllo degli eventuali account `r4restore_*` rimasti.

Questa procedura non equivale a un servizio di conservazione attivato per
dieci anni. Prima dell'uso operativo servono una destinazione fuori dal PC,
una capacità dimensionata su documenti reali, una finestra concordata, un
controllo degli errori e prove periodiche di recupero. La riattivazione su un
altro server richiede anche il codice corrispondente, la configurazione
dell'ambiente, i permessi degli archivi e una verifica prima di riabilitare
scheduler, accessi e invio delle email.

## Collaudo di capacità isolato

`scripts/capacity-check.php` e `CapacitySeeder` operano esclusivamente su
database `r4_capacity_*` distinti dal database operativo, con archivi esterni
al progetto e una chiave applicativa dedicata. Il seeder rifiuta database
già popolati. Il server di collaudo accetta soltanto connessioni locali e
mantiene il normale accesso con autenticazione e protezione CSRF.

Il campione contiene 10.000 clienti, 40.000 polizze su dieci anni, 60.000
rate, 53.853 incassi, 60.000 posizioni provvigionali, 107.224 movimenti,
50.000 PDF e 40.000 eventi di attività. Sono dati sintetici; i PDF ridotti
servono a verificare collegamenti e integrità e non stimano lo spazio
necessario per scansioni reali. Lo scenario conserva volutamente un forte
arretrato di notifiche e comunicazioni.

Le misure includono le viste reali di dodici pagine e quattro richieste per
pagina. Non sono un test di accessi simultanei né una garanzia di tempi in
produzione. La migrazione `2026_10_20_150000` collega i promemoria alle
notifiche con un indice dedicato; i promemoria vengono generati in blocchi
transazionali, senza duplicare quelli già letti. Le esportazioni continuano
a rispettare i permessi e non rigenerano il menu delle notifiche per ogni
pagina di dati.

Esito del collaudo del 20 ottobre 2026: dodici pagine hanno risposto con HTTP
200. Nello scenario con arretrato esteso, le mediane delle richieste dopo il
primo caricamento vanno da 3,6 a 9,5 secondi: il risultato non costituisce
un'approvazione delle prestazioni per la produzione. Sono passati 49 test
mirati distinti. Il browser ha completato l'esportazione di 40.000 righe;
un campione filtrato di 66 polizze è stato inoltre verificato nei file Excel
e PDF effettivamente scaricati. Il campione PDF è stato controllato anche
visivamente. Verificati il menu notifiche su smartphone e l'assenza di
scorrimento orizzontale nella pagina controllata.

Il 21 ottobre 2026 è stato completato il ripristino isolato della copia
cifrata scritta prima dell'interruzione del giorno precedente. Il controllo
Restic con lettura integrale è riuscito. Il ripristino definitivo ha
verificato 69 tabelle, 50.000 allegati (68.088.894 byte), 144 vincoli di
relazione e il valore cifrato presente nel campione; durata 290,3 secondi.
La copia include i 40.000 contratti e gli altri dati di collaudo sopra
descritti. Nessun dato operativo è stato sostituito.

La prova ha rilevato e permesso di correggere un'incompatibilità fra le
opzioni `--binary-mode` e `--one-database` del client MariaDB utilizzato,
che terminava senza errori lasciando vuoto il database di destinazione.
L'isolamento è ora garantito dall'account temporaneo con permessi limitati;
restano attivi la modalità binaria e il divieto di caricamento di file locali.
Una prova nativa ha confermato il caricamento corretto, il rifiuto di accesso
al database originale e la rimozione degli account dopo successo ed errore.
Superati 13 test mirati del backup, con 43 asserzioni, compresa la pulizia
dopo un errore durante la concessione dei permessi.

Nessuna destinazione esterna o esecuzione automatica è stata attivata.
Il server locale usato per la prova è MariaDB 10.4.32, una versione fuori
supporto: prevedere un aggiornamento collaudato prima dell'uso in produzione.
