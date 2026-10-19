<?php

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('AUDIT_FIXED_DATE') && app()->environment('testing')) Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse(getenv('AUDIT_FIXED_DATE'), 'Europe/Rome'));
$db = getenv('DB_DATABASE');
if (dirname($db) !== __DIR__ || ! preg_match('/^ui-[0-9]+\.sqlite$/', basename($db))) {
    throw new RuntimeException('Percorso del database di prova inatteso.');
}
if (is_file($db)) {
    throw new RuntimeException('Il database di prova esiste già: usare quello preparato.');
}
touch($db);
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $db, 'database.connections.sqlite.url' => null, 'cache.default' => 'array', 'queue.default' => 'sync']);
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
App\Models\User::factory()->create(['name' => 'Admin dimostrativo', 'email' => 'ui-test@example.test', 'password' => Illuminate\Support\Facades\Hash::make('password-testing-only'), 'role' => App\Enums\UserRole::Admin, 'status' => 'active', 'password_changed_at' => now()]);
$client = App\Models\Client::factory()->create(['client_type' => 'company', 'name' => 'Azienda dimostrativa', 'tax_code' => 'DEMO-CF', 'vat_number' => 'DEMO-IVA', 'email' => 'azienda@example.test', 'reference_person' => 'Referente dimostrativo', 'salient_notes' => 'Dati dimostrativi per il collaudo.']);
$vehicle = $client->vehicles()->create(['plate' => 'TEST01', 'make' => 'Marca dimostrativa', 'model' => 'Modello dimostrativo']);
foreach (['RC auto', 'Infortuni', 'RC persone'] as $i => $category) {
    $client->policies()->create(['category' => $category, 'company' => 'Compagnia dimostrativa', 'number' => 'DEMO-'.($i + 1), 'vehicle_id' => $i === 0 ? $vehicle->id : null, 'starts_on' => today()->subMonths(11), 'expires_on' => today()->addDays(($i + 1) * 7), 'status' => 'active']);
}
if (getenv('CATALOG_MERGE_QA') === '1') {
    app(App\Services\InsuranceCatalog::class)->importPreview();
    app(App\Services\ApprovedInsuranceCatalogMerges::class)->apply();
}
if (getenv('POLICY_IMPORT_QA') === '1') {
    $fixture = Tests\Fixtures\PolicyImportFixture::create($db.'.imports');
    $importer = app(App\Services\PolicyExcelImporter::class);
    $importer->apply($importer->plan()['batch_hash']);
    $client = $fixture['client'];
}
$extra = [];
if (getenv('WORKSPACE_QA') === '1') {
    $branch = App\Models\InsuranceBranch::create(['name'=>'RC auto','name_key'=>hash('sha256','RC AUTO'),'status'=>'active','needs_review'=>false]);
    $branch->subbranches()->create(['name'=>'Autovetture','name_key'=>hash('sha256','AUTOVETTURE'),'status'=>'active','needs_review'=>false]);
    $company=App\Models\SupplierCompany::create(['name'=>'Compagnia dimostrativa','status'=>'active']);
    $agency=App\Models\SupplierAgency::create(['name'=>'Agenzia dimostrativa','email'=>'agenzia@example.test','status'=>'active','supplier_company_id'=>$company->id]);
    $client->policies()->update(['supplier_company_id'=>$company->id,'supplier_agency_id'=>$agency->id,'payment_frequency'=>'Semestrale']);
    $extra=['company_id'=>$company->id,'agency_id'=>$agency->id];
}
if (getenv('EQUIVALENT_IMPORT_QA') === '1') {
    $fixture = Tests\Fixtures\EquivalentPolicyFixture::create($db.'.imports');
    app(App\Services\ApprovedEquivalentPolicyImport::class)->apply($fixture['approval'], App\Models\User::where('email', 'ui-test@example.test')->firstOrFail());
    $entry = App\Models\InsurancePolicyImport::where('number', 'REPEAT-QA')->sole();
    $client = $fixture['client'];
    $extra = ['entry_id' => $entry->id, 'policy_id' => $entry->insurance_policy_id];
}
if (getenv('AGENCY_EMAIL_QA') === '1') {
    $client->update(['name'=>'Officine Valdoro S.r.l.','company_name'=>'Officine Valdoro S.r.l.','tax_code'=>null,'vat_number'=>null,'reference_person'=>'Alessandro Varesi']);
    $company->update(['name'=>'Compagnia Alba']);
    $agency->update(['name'=>'Agenzia Valserena']);
    foreach ($client->policies()->orderBy('id')->get() as $index=>$policy) $policy->update(['number'=>'SA-2026-'.str_pad((string)($index+1),3,'0',STR_PAD_LEFT),'company'=>$company->name,'premium'=>$index===0 ? '1234.50' : '640.00']);
    foreach (['suspended','cancelled','expired','replaced','ceased','review'] as $index=>$status) $client->policies()->create(['number'=>'SA-2026-'.(200+$index),'category'=>'RC auto','company'=>$company->name,'starts_on'=>today(),'expires_on'=>today()->addYear(),'status'=>$status,'premium'=>'999.00']);
    $client->policies()->create(['number'=>'SA-2026-206','category'=>'RC auto','company'=>$company->name,'starts_on'=>today()->subYear(),'expires_on'=>today()->subDay(),'status'=>'active','premium'=>'999.00']);
    $old=$client->policies()->create(['number'=>'SA-2026-207','category'=>'RC auto','company'=>$company->name,'starts_on'=>today(),'expires_on'=>today()->addYear(),'status'=>'active','premium'=>'999.00']);
    $old->forceFill(['created_at'=>today()->subSecond()])->save();
    $user=App\Models\User::where('email','ui-test@example.test')->firstOrFail();
    $user->forceFill(['name'=>'Giulia Rossi','appearance'=>['primary'=>'#124d85','form_border'=>'#124d85','primary_hover'=>'#0d365d','heading'=>'#124d85']])->save();
}
if(getenv('OPERATIONS_QA')==='1') {
    $company->update(['name'=>'Nobis','quote_priority'=>true]);$client->policies()->where('supplier_company_id',$company->id)->update(['company'=>'Nobis']);
    $europe=App\Models\SupplierCompany::create(['name'=>'Europ Assistance','status'=>'active','quote_priority'=>true]);
    $p=$client->policies()->orderBy('id')->first();$p->update(['starts_on'=>today(),'expires_on'=>today()->addYear(),'premium'=>'1000.01','payment_frequency'=>'Semestrale','payment_method'=>'Bonifico a Intermediario']);
    $extra+=['policy_id'=>$p->id,'europe_id'=>$europe->id,'branch_id'=>$branch->id];
}
if (getenv('WORKFLOW_QA') === '1') {
    $operator = App\Models\User::factory()->create(['name'=>'Luca Moretti', 'email'=>'operator@example.test', 'password'=>Illuminate\Support\Facades\Hash::make('password-testing-only'), 'role'=>App\Enums\UserRole::Commercial, 'status'=>'active', 'password_changed_at'=>now()]);
    $client->update(['commercial_user_id'=>$operator->id]);
    $extra['operator_id']=$operator->id;
}
if (getenv('QUOTE_EMAIL_QA') === '1') {
    $company->update(['email'=>'nobis@example.test']);
    $europe->update(['name'=>'Europe Assistance','email'=>'europe@example.test']);
    $other = App\Models\SupplierCompany::create(['name'=>'Compagnia Valdoro','email'=>'valdoro@example.test','status'=>'active','quote_priority'=>false]);
    App\Models\OutboundMailSetting::create(['credentials'=>['enabled'=>true,'host'=>'smtp.example.test','port'=>587,'encryption'=>'starttls','username'=>'sender@example.test','password'=>Illuminate\Support\Str::random(32),'from_address'=>'sender@example.test','from_name'=>'Soluzioni Assicurative SAS'],'updated_by'=>$user->id]);
    $extra['other_id']=$other->id;
}
if (getenv('COMMISSION_QA') === '1') {
    $agency->companies()->syncWithoutDetaching([$europe->id]);
    $injury = App\Models\InsuranceBranch::create(['name'=>'Infortuni','name_key'=>hash('sha256','INFORTUNI'),'status'=>'active']);
    $injury->subbranches()->create(['name'=>'Conducente','name_key'=>hash('sha256','CONDUCENTE'),'status'=>'active']);
    $reader = App\Models\User::factory()->create(['name'=>'Elena Riva','email'=>'reader@example.test','password'=>Illuminate\Support\Facades\Hash::make('password-testing-only'),'role'=>App\Enums\UserRole::Administration,'status'=>'active','password_changed_at'=>now()]);
    $extra['reader_id']=$reader->id;
}
if (getenv('SIGNING_QA') === '1') {
    Illuminate\Support\Facades\Artisan::call('db:seed',['--class'=>Database\Seeders\InsuranceFormTemplateSeeder::class,'--force'=>true]);
    $p=$client->policies()->orderBy('id')->first(); $p->update(['insurance_branch_id'=>$branch->id]);
    $template=App\Models\InsuranceFormTemplate::create(['family'=>(string)Illuminate\Support\Str::uuid(),'version'=>1,'title'=>'Dichiarazione di collaudo','scope'=>'policy','category'=>'consistency','body'=>"Documento usato esclusivamente per il collaudo.\n\nContraente: {{contraente}}\n\nPolizza: {{numero_polizza}}\n\nCompagnia: {{compagnia}}\n\nFirmatario: {{firmatario}}\n\nQualifica: {{qualifica_firmatario}}\n\nLuogo e data: {{luogo}}, {{data_documento}}\n\nFirma: __________________",'signature_required'=>true,'status'=>'active','approved_by'=>$user->id,'approved_at'=>now()]);
    $extra+=['policy_id'=>$p->id,'template_id'=>$template->id,'source_template_ids'=>App\Models\InsuranceFormTemplate::whereNotNull('source_sheet')->pluck('id')->all()];
}
if (getenv('CLAIMS_QA') === '1') {
    $operator=App\Models\User::factory()->create(['name'=>'Luca Moretti','email'=>'operator@example.test','password'=>Illuminate\Support\Facades\Hash::make('password-testing-only'),'role'=>App\Enums\UserRole::Commercial,'status'=>'active','password_changed_at'=>now()]);
    $client->update(['commercial_user_id'=>$operator->id]);
    $p=$client->policies()->orderBy('id')->first(); $p->update(['insurance_branch_id'=>$branch->id,'insurance_subbranch_id'=>$branch->subbranches()->first()->id]);
    $extra['operator_id']=$operator->id;
}
echo json_encode(['client_id' => $client->id] + $extra, JSON_THROW_ON_ERROR);
