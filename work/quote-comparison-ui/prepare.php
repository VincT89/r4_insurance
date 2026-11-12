<?php

$app=require __DIR__.'/bootstrap.php';
$db=config('database.connections.sqlite.database');
if(is_file($db)) throw new RuntimeException('Il database di collaudo deve essere nuovo.');
touch($db);
Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
$admin=App\Models\User::factory()->create(['name'=>'Operatore dimostrativo','email'=>'confronto@example.test','password'=>Illuminate\Support\Facades\Hash::make(getenv('QUOTE_COMPARISON_QA_PASSWORD')),'role'=>App\Enums\UserRole::Admin,'status'=>'active','password_changed_at'=>now()]);
auth()->login($admin);
$client=App\Models\Client::factory()->create(['name'=>'Cliente dimostrativo confronto','email'=>'cliente@example.test']);
$branch=App\Models\InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active']);
$case=App\Models\InsuranceQuoteCase::create(['client_id'=>$client->id,'insurance_branch_id'=>$branch->id,'subject'=>'Pratica dimostrativa confronto','vehicle_plate'=>'DEMO01','vehicle_snapshot'=>['plate'=>'DEMO01']]);
$field=fn($key,$value,$page=1)=>['key'=>$key,'value'=>$value,'page'=>$page,'quote'=>'Dato dimostrativo presente nella pagina '.$page,'certainty'=>'certain'];
$ids=[];
foreach(['A'=>62000,'B'=>57000,'C'=>65500] as $name=>$premium) {
    $company=App\Models\SupplierCompany::create(['name'=>'Compagnia dimostrativa '.$name,'status'=>'active']);
    $offer=$case->quotes()->create(['supplier_company_id'=>$company->id,'status'=>'received','requested_at'=>now()->subDay(),'received_at'=>now(),'reference'=>'DEMO-'.$name,'product'=>'Prodotto dimostrativo','premium_cents'=>$premium,'starts_on'=>today()->addMonth(),'expires_on'=>today()->addMonths(13),'valid_until'=>today()->addDays(20),'payment_frequency'=>'Unico']);
    $document=app(App\Services\ClientDocumentRegistry::class)->upload($client,new Illuminate\Http\UploadedFile($qaPath.'/demo.pdf','offerta-dimostrativa-'.$name.'.pdf','application/pdf',null,true),['document_category'=>'quote','insurance_quote_id'=>$offer->id]);
    App\Models\InsuranceQuoteReading::create(['insurance_quote_id'=>$offer->id,'attachment_id'=>$document->id,'extraction'=>['document_kind'=>'quotation','fields'=>[$field('premium',App\Support\Money::decimal($premium)),$field('vehicle.plate','DEMO01')],'coverages'=>[['code'=>'cristalli','name'=>'Cristalli','fields'=>[$field('status',$name==='B'?'excluded':'included',2),$field('deductible',$name==='B'?'Non applicabile':'100 EUR',2)]]],'warnings'=>[]]]);
    $ids[]=$offer->id;
}
$other=$case->replicate(); $other->subject='Altra pratica dimostrativa'; $other->save();
$offer=$case->quotes()->first()->replicate(); $offer->insurance_quote_case_id=$other->id; $offer->save();
app(App\Services\ClientDocumentRegistry::class)->upload($client,new Illuminate\Http\UploadedFile($qaPath.'/demo.pdf','altra-offerta-dimostrativa.pdf','application/pdf',null,true),['document_category'=>'quote','insurance_quote_id'=>$offer->id]);
echo json_encode(['case'=>$case->id,'ids'=>$ids,'other'=>$offer->id]);
