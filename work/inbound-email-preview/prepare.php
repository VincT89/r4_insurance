<?php

$app = require __DIR__.'/bootstrap.php';
$db = config('database.connections.sqlite.database');
if (file_exists($db)) throw new RuntimeException('Il database di collaudo deve essere nuovo.');
touch($db);
if (Illuminate\Support\Facades\Artisan::call('migrate', ['--force'=>true]) !== 0) throw new RuntimeException('Preparazione dello schema di prova non riuscita.');
$operator = App\Models\User::factory()->create(['name'=>'Operatore dimostrativo email', 'email'=>'email-preview@example.test', 'role'=>App\Enums\UserRole::Operator, 'status'=>'active']);
auth()->setUser($operator);
$client = App\Models\Client::factory()->create(['name'=>'Cliente dimostrativo Email', 'email'=>'cliente-email@example.test']);
App\Models\Client::factory()->create(['name'=>'Altro cliente dimostrativo', 'email'=>'altro-cliente@example.test']);
$branch = App\Models\InsuranceBranch::create(['name'=>'RC auto', 'name_key'=>hash('sha256','RC AUTO'), 'status'=>'active']);
$case = $client->quoteCases()->create(['insurance_branch_id'=>$branch->id, 'subject'=>'Pratica dimostrativa email']);
$company = App\Models\SupplierCompany::create(['name'=>'Compagnia dimostrativa A','status'=>'active']);
$policy = $client->policies()->create(['insurance_branch_id'=>$branch->id, 'number'=>'DEMO-EMAIL-001', 'company'=>$company->name, 'supplier_company_id'=>$company->id, 'category'=>'RC auto', 'status'=>'active', 'starts_on'=>today(), 'expires_on'=>today()->addYear(), 'premium'=>'100.00','payment_frequency'=>'Annuale','payment_method'=>'Bonifico a Intermediario']);
$titleValues = ['insurance_policy_id'=>$policy->id,'kind'=>'quietance','due_on'=>today()->addDays(7),'coverage_from'=>today(),'coverage_to'=>today()->addYear(),'amount_cents'=>10000,'collected_cents'=>0,'payment_frequency'=>'Annuale','payment_method'=>'Bonifico a Intermediario'];
App\Models\InsuranceTitle::create($titleValues+['reference'=>'RATA-DEMO-APERTA']);
$paid = App\Models\InsuranceTitle::create(array_replace($titleValues,['reference'=>'RATA-DEMO-SALDATA','amount_cents'=>2500,'due_on'=>today()]));
app(App\Services\InsuranceLedger::class)->collect($paid,['received_on'=>today()->toDateString(),'amount_cents'=>2500,'payment_method'=>'Bonifico a Intermediario','recipient'=>'our_agency','document_type'=>'record','idempotency_key'=>(string)Illuminate\Support\Str::uuid(),'reference'=>'Pagamento esclusivamente dimostrativo']);
/** PDF minimo valido, creato soltanto con contenuto dimostrativo per la prova dell'interfaccia. */
$stream = 'BT /F1 14 Tf 50 750 Td (Offerta esclusivamente dimostrativa per il collaudo R4) Tj ET';
$objects = ['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>','<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream"];
$pdf = "%PDF-1.4\n"; $offsets = [0];
foreach ($objects as $index=>$object) { $offsets[] = strlen($pdf); $pdf .= ($index+1)." 0 obj\n".$object."\nendobj\n"; }
$xref = strlen($pdf); $pdf .= "xref\n0 6\n0000000000 65535 f \n";
foreach (array_slice($offsets,1) as $offset) $pdf .= sprintf("%010d 00000 n \n",$offset);
$pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
file_put_contents($qaPath.'/offerta-dimostrativa.pdf',$pdf);
foreach ([$company,App\Models\SupplierCompany::create(['name'=>'Compagnia dimostrativa B','status'=>'active'])] as $index=>$offerCompany) {
    $offer = $case->quotes()->create(['supplier_company_id'=>$offerCompany->id,'status'=>'received','requested_at'=>now()->subDay(),'received_at'=>now(),'reference'=>'OFFERTA-DEMO-'.($index+1),'product'=>'Prodotto dimostrativo','premium_cents'=>10000+$index*1000,'starts_on'=>today(),'expires_on'=>today()->addYear(),'valid_until'=>today()->addDays(20),'payment_frequency'=>'Unico']);
    app(App\Services\ClientDocumentRegistry::class)->upload($client,new Illuminate\Http\UploadedFile($qaPath.'/offerta-dimostrativa.pdf','offerta-dimostrativa-'.($index+1).'.pdf','application/pdf',null,true),['document_category'=>'quote','insurance_quote_id'=>$offer->id]);
}
file_put_contents($qaPath.'/documento-dimostrativo.txt', "Documento esclusivamente dimostrativo per il collaudo della ricezione email.\nNessun contratto reale.\n");
$receiver = app(App\Services\InboundEmailReceiver::class);
$base = ['external_id'=>'preview-1', 'from'=>['name'=>'Agenzia dimostrativa','address'=>'agenzia@example.test'], 'to'=>['ricezione@example.test'], 'subject'=>'Preventivo dimostrativo per Cliente Email', 'text'=>"Buongiorno,\nin allegato il documento dimostrativo per la pratica.\n\nMessaggio esclusivamente di prova.", 'sent_at'=>now()->format('Y-m-d\TH:i:sP')];
$receiver->receive($base, [new Illuminate\Http\UploadedFile($qaPath.'/documento-dimostrativo.txt','preventivo-dimostrativo.txt','text/plain',null,true)]);
$receiver->receive(array_replace($base, ['external_id'=>'preview-2', 'subject'=>'Contratto dimostrativo da associare', 'text'=>null, 'html'=>'<p>Trasmettiamo il <strong>contratto dimostrativo</strong>.</p><p>Documento di prova, senza valore contrattuale.</p>']), [new Illuminate\Http\UploadedFile($qaPath.'/documento-dimostrativo.txt','contratto-dimostrativo.txt','text/plain',null,true)]);
$receiver->receive(array_replace($base, ['external_id'=>'preview-3', 'subject'=>'Messaggio dimostrativo senza allegati', 'text'=>'Comunicazione di prova senza documenti.']), []);
file_put_contents($qaPath.'/ready', 'Dati dimostrativi per email, clienti, preventivi, polizze, titoli e contabilità.');
echo 'Archivio di prova pronto per il collaudo dei flussi principali.'.PHP_EOL;
