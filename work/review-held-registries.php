<?php

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'r4_insurance' || !in_array($db->getConfig('host'), ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Database inatteso.');
$preview = app(App\Services\InsuranceImportPreview::class);
foreach (['summary.json','clients.json','source-rows.jsonl'] as $file) $preview->verifiedFile($file);
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $held = $db->table('registry_import_entries')->where('kind','client')->where('status','held')->orderBy('id')->get();
    $clients = $db->table('clients')->whereNull('merged_into_id')->get(['id','name','client_type','company_name','tax_code','vat_number','birth_date','birth_place','email','phone','mobile','city','province','address','postal_code','title','updated_at']);
    $imported = $db->table('registry_import_entries')->where('kind','client')->where('status','imported')->get(['id','source_key','entity_id','deferred_data','source_rows']);
    $counts = ['held'=>$held->count(),'clients'=>$clients->count(),'policies'=>$db->table('insurance_policies')->count()];
} finally { $db->rollBack(); }
$out = $preview->directory().'/held-review';
if (!is_dir($out) && !mkdir($out,0700,true)) throw new RuntimeException('Cartella privata non disponibile.');
file_put_contents($out.'/database-snapshot.json',json_encode(['held'=>$held,'clients'=>$clients,'imported'=>$imported],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
echo json_encode($counts,JSON_THROW_ON_ERROR).PHP_EOL;
