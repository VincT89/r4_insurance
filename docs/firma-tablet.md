# Firma interna sul tablet

La funzione raccoglie una firma grafica in presenza dell'operatore. Non dichiara una firma elettronica avanzata o qualificata. Per i contratti di terze compagnie va verificata l'accettazione della modalità da parte delle mandanti.

## Utilizzo

Aprire il gestionale dal browser del tablet e accedere con il proprio account. Dalla polizza scegliere **Firma contratto sul tablet**, oppure **Moduli e firme** e **Firma sul tablet** accanto al modulo.

Leggere il PDF, indicare gli spazi di firma, controllare il nome del firmatario e l'eventuale qualifica. Consegnare il dispositivo al cliente per la lettura, la firma con dito o pennino e la conferma. L'operatore conferma la verifica dell'identità. **Conferma e salva firma** conserva la copia nella stessa polizza. I moduli seguono anche il controllo delle copie già previsto nel gestionale.

La funzione consente fino a dieci punti firma nello stesso PDF e usa la firma disegnata solo per quel documento. Originale e copie precedenti rimangono conservati. Per una nuova copia è necessario indicarne il motivo. Un cambio del documento o una nuova copia ricevuta nel frattempo richiedono di riaprire la pagina.

## Installazione e limiti

Applicare la migrazione `2026_11_06_090000_create_document_signatures.php` dopo un backup del database. Il motore usa Node.js e la dipendenza gratuita `pdf-lib` inclusa in `package.json`: sul server installare le dipendenze di produzione e configurare `DOCUMENT_SIGNING_NODE_BINARY` con il percorso effettivo di Node se non disponibile nel PATH di PHP. Il processo PHP deve poter avviare il comando locale. Nessuna chiamata a un servizio di firma o SMS viene effettuata.

Verificare l'installazione mediante un PDF di collaudo e un account operatore prima dell'uso operativo. Compilare gli asset con il normale build del progetto. La cartella privata `storage/app/signing-tmp` deve essere scrivibile; i file temporanei vengono rimossi al termine dell'operazione.

Sono ammessi PDF accessibili fino a 20 MB e 100 pagine. I PDF protetti e quelli già firmati crittograficamente non vengono riscritti. Il risultato conserva le pagine e il testo, rende statici gli eventuali campi compilabili e aggiunge una registrazione finale con firmatario, operatore, data, punti firma e impronta dell'originale. Le prove e le impronte dei file restano anche nel database; i tratti grafici sono cifrati e non includono pressione, velocità o altri parametri biometrici.

Il tablet deve raggiungere l'indirizzo HTTPS dell'installazione operativa. `127.0.0.1` indica il dispositivo stesso e non permette di raggiungere dal tablet il gestionale avviato su un altro computer. Per un'installazione in rete locale occorre utilizzare l'indirizzo effettivo del server e una configurazione di rete autorizzata.
