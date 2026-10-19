<?php
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.default') !== 'sqlite' || realpath(dirname(config('database.connections.sqlite.database'))) !== realpath($root.'/work/insurance-ui') || !preg_match('/^ui-[0-9]+\.sqlite$/', basename(config('database.connections.sqlite.database')))) throw new RuntimeException('Database di collaudo richiesto.');
Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-10-19 12:00', 'Europe/Rome'));
if (App\Models\InsurancePolicy::where('number', 'like', 'PV-%')->exists()) throw new RuntimeException('Fixture già presente.');
$client = App\Models\Client::firstOrFail(); $agency = App\Models\SupplierAgency::firstOrFail(); $company = App\Models\SupplierCompany::firstOrFail(); $user = App\Models\User::firstOrFail();
$otherAgency = App\Models\SupplierAgency::create(['name'=>'Agenzia Borgoverde', 'email'=>'borgoverde@example.test', 'status'=>'active']);
$otherCompany = App\Models\SupplierCompany::create(['name'=>'Compagnia Aurora', 'status'=>'active']);
$user->forceFill(['appearance'=>['primary'=>'#0b498c', 'form_border'=>'#0b498c', 'primary_hover'=>'#083b73', 'heading'=>'#0b498c']])->save();
for ($i=1; $i<=527; $i++) {
    $p = $client->policies()->create(['number'=>'PV-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'category'=>'RC auto', 'company'=>$i%2 ? $company->name : $otherCompany->name, 'supplier_company_id'=>$i%2 ? $company->id : $otherCompany->id, 'supplier_agency_id'=>$i<=300 ? $agency->id : $otherAgency->id, 'starts_on'=>today(), 'expires_on'=>today()->addYear(), 'status'=>'active', 'premium'=>'1234.56', 'payment_method'=>'Bonifico a Intermediario', 'payment_frequency'=>'Semestrale']);
    $t = App\Models\InsuranceTitle::create(['insurance_policy_id'=>$p->id, 'kind'=>'emission', 'due_on'=>today(), 'coverage_from'=>today(), 'coverage_to'=>today()->addMonths(6), 'amount_cents'=>61728, 'collected_cents'=>61728, 'payment_method'=>'Bonifico a Intermediario', 'payment_frequency'=>'Semestrale', 'created_by'=>$user->id]);
    App\Models\InsuranceCollection::create(['insurance_title_id'=>$t->id, 'received_on'=>today(), 'amount_cents'=>61728, 'payment_method'=>'Bonifico a Intermediario', 'recipient'=>$i%2 ? 'our_agency' : 'company', 'document_type'=>'transfer', 'reference'=>'IN-'.str_pad((string)$i, 5, '0', STR_PAD_LEFT), 'idempotency_key'=>(string) Illuminate\Support\Str::uuid(), 'request_fingerprint'=>hash('sha256',(string)$i), 'created_by'=>$user->id]);
}
for ($i=1; $i<=30; $i++) App\Models\AgencyCommunication::create(['supplier_agency_id'=>$agency->id, 'user_id'=>$user->id, 'status'=>'review', 'payload'=>['recipient'=>['name'=>$agency->name,'address'=>$agency->email], 'policies'=>[], 'range'=>'19/10/2026', 'body'=>'Riepilogo di collaudo', 'subject'=>'Collaudo storico', 'filters'=>[]], 'fingerprint'=>hash('sha256',(string)$i), 'review_expires_at'=>now()->addMinutes(30)]);
echo json_encode(['policies'=>527, 'collections'=>527, 'history'=>30, 'agency'=>$agency->id, 'other_agency'=>$otherAgency->id, 'company'=>$company->id, 'other_company'=>$otherCompany->id]);
