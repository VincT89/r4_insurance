from pathlib import Path

root = Path(__file__).resolve().parent
target = root / 'recap-audit-2026-10-16.html'
text = target.read_text(encoding='utf-8')
text = text.replace('16 ottobre 2026 · Stato del gestionale', 'Aggiornato al 18 ottobre 2026 · Stato del gestionale')
text = text.replace('Recap e audit del gestionale — 16 ottobre 2026', 'Recap e audit del gestionale — 18 ottobre 2026')
text = text.replace('223 test automatici e 3.796 asserzioni superati', '236 test automatici e 3.977 asserzioni superati')
old = '<tr><td>Moduli da firmare a emissione o variazione</td><td>Parziale</td><td>Caricamento, versioni, consensi documentati e checklist disponibili. Non è ancora presente la generazione automatica dei moduli compilati da far firmare; servono modelli e campi approvati.</td></tr>'
new = '<tr><td>Moduli da firmare a emissione o variazione</td><td>Funzione pronta; testi da revisionare</td><td>Modelli per cliente/polizza e ramo/sottoramo, generazione PDF dai dati registrati, allegati firmati multipli, controllo manuale delle firme e storico conservato. Cambi contrattuali segnalano moduli da ripreparare. I sette modelli forniti restano cinque precedenti e due bozze: nessuna approvazione automatica.</td></tr><tr><td>Controllo RCA tramite targa</td><td>Pronto, con controllo manuale</td><td>Collegamento al servizio ufficiale, registrazione di esito, data/ora, operatore, note e prove multiple. Non verifica l’incasso o altre garanzie e non modifica lo stato della polizza.</td></tr>'
assert old in text
text = text.replace(old, new)
text = text.replace('La generazione dei moduli può procedere quando sono confermati modelli e campi.', 'La generazione dei moduli è disponibile dopo l’approvazione esplicita dei relativi testi nelle impostazioni.')
text = text.replace('<li><a href="agency-commission-suite.xml">Ultimo esito dei 223 test automatici</a></li>', '<li><a href="signing-suite.xml">Esito completo dei 236 test automatici</a></li><li><a href="signing-tests.xml">Moduli e RCA: 13 test mirati dopo l’allineamento dello schema</a></li><li><a href="signing-live-report.json">Moduli e RCA: stato del database e nove pagine reali</a></li><li><a href="insurance-ui/signing-report.json">Moduli e RCA: quattro flussi e 36 viste desktop/mobile</a></li><li><a href="insurance-ui/signing-pdf-report.json">Controllo di 11 PDF, per 23 pagine</a></li>')
marker = '<section id="task">'
addition = '''<section id="moduli"><h2>Moduli e controllo RCA: aggiornamento del 18 ottobre</h2>
<p>Le schede cliente e polizza includono <strong>Moduli e firme</strong>. L’admin gestisce i modelli in <strong>Impostazioni → Modelli documentali</strong>, con anteprima PDF, approvazione esplicita, versioni e ritiro. I moduli compilati e i file firmati restano collegati sia al cliente sia alla polizza, con storico protetto.</p>
<p>I file firmati si caricano anche insieme. Ogni versione indica firmatario, qualifica, data, operatore e motivazione; il controllo successivo delle firme è manuale. La sola ricezione di un file non vale come consenso e non autentica una firma. Le modifiche contrattuali segnalano la necessità di nuovi moduli; annotazioni interne e pagamenti non invalidano da soli il fascicolo.</p>
<p class="note"><strong>Testi ancora da approvare:</strong> i quattro vecchi allegati e la ricevuta di consegna sono conservati come modelli precedenti. Coerenza e consenso elettronico sono bozze. Nessuno è stato abilitato automaticamente. Il file fornito non contiene i testi completi dei MUP correnti, dell’informativa privacy, del mandato e del questionario esigenze. I testi e i dati dell’intermediario devono essere verificati dall’agenzia; il gestionale consente di registrarne le versioni approvate.</p>
<p>In <strong>Polizza → Verifica RCA</strong> l’operatore apre il <a href="https://www.ilportaledellautomobilista.it/web/portale-automobilista/ext/verifica-copertura-rc">servizio ufficiale del Portale dell’Automobilista</a>, effettua la ricerca e registra l’esito con eventuali prove multiple. Non sono state inviate targhe al portale durante il collaudo. L’esito riguarda il veicolo al momento del controllo, non certifica l’incasso né la validità dello specifico contratto nel gestionale.</p>
<p>Superati 236 test automatici e 3.977 asserzioni; dopo l’ampliamento dello spazio per i documenti cifrati sono passati nuovamente i 13 test mirati. Quattro flussi browser e 36 viste desktop/mobile non mostrano errori JavaScript, richieste esterne, campi visibili senza etichetta o sbordamenti orizzontali della pagina. Sono stati controllati visivamente i layout e 11 PDF per 23 pagine. L’audit PHP del 18 ottobre non riporta avvisi o pacchetti abbandonati; restano i limiti frontend descritti sotto.</p>
<p>Le nuove strutture sono presenti in r4_insurance e nove pagine reali rispondono correttamente. Clienti, polizze, cataloghi, provvigioni, titoli, incassi, preventivi e allegati preesistenti risultano invariati: <strong>50 clienti e 50 polizze</strong>. Nessun fascicolo firmato o controllo RCA fittizio è stato inserito nel database locale.</p>
<details><summary>Preparazione dei moduli su desktop</summary><figure><img src="insurance-ui/signing-prepare-client-desktop.png" alt="Preparazione dei moduli con dati del contraente e firmatario separati"><figcaption>Dati fittizi del database isolato usato per il collaudo.</figcaption></figure></details>
<details><summary>Allegati multipli e controllo RCA su telefono</summary><figure><img src="insurance-ui/signing-signatures-upload-mobile-bottom.png" width="390" alt="Caricamento multiplo dei documenti firmati"><img src="insurance-ui/signing-coverage-mobile-bottom.png" width="390" alt="Registrazione e storico del controllo RCA su telefono"></figure></details>
</section>
'''
assert marker in text
text = text.replace(marker, addition + marker)
target.write_text(text, encoding='utf-8')
print('Recap aggiornato al 18 ottobre 2026.')
