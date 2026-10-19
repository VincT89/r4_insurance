<?php

$root=dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{Artisan, Crypt, DB, Schema, Storage};
$db=DB::connection();
if ($db->getDriverName()!=='mysql' || $db->getDatabaseName()!=='r4_insurance' || !in_array($db->getConfig('host'),['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Database inatteso.');
$admin=App\Models\User::where('email','admin@admin.it')->firstOrFail();
if (!$admin->isAdmin() || $admin->status!=='active') throw new RuntimeException('Amministratore attivo richiesto.');
if (in_array('--recover',$argv,true)) { Artisan::call('up'); echo "Manutenzione conclusa; transazione interrotta annullata.\n"; exit; }
$allowed=['2026_10_16_100000_remove_team_module','2026_10_16_101000_add_policy_payment_details','2026_10_16_102000_create_deadline_reminders','2026_10_16_103000_add_identity_type_to_attachments','2026_10_16_104000_add_policy_merit_classes','2026_10_16_105000_complete_policy_renewal_fields'];
$pending=array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))),$db->table('migrations')->pluck('migration')->all()));
if (array_diff($pending,$allowed)) throw new RuntimeException('Migrazioni inattese.');
$report=['mode'=>'preview','pending'=>$pending,'clients'=>App\Models\Client::count(),'policies'=>App\Models\InsurancePolicy::count(),'teams'=>Schema::hasTable('teams')?DB::table('teams')->count():0,'team_members'=>Schema::hasTable('team_user')?DB::table('team_user')->count():0,'php_upload_max'=>ini_get('upload_max_filesize'),'php_post_max'=>ini_get('post_max_size')];
if (in_array('--schema',$argv,true)) {
    $backup='workspace-schema-backup-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
    foreach (['users','insurance_policies','attachments','teams','team_user'] as $table) if (Schema::hasTable($table)) {
        $data=json_encode(DB::table($table)->get()->all(),JSON_THROW_ON_ERROR);
        if (!Storage::disk('local')->put($backup.'/'.$table.'.json.enc',Crypt::encryptString($data))) throw new RuntimeException('Backup non riuscito.');
        if (!hash_equals(hash('sha256',$data),hash('sha256',Crypt::decryptString(Storage::disk('local')->get($backup.'/'.$table.'.json.enc'))))) throw new RuntimeException('Backup non verificato.');
    }
    foreach ($pending as $migration) if (Artisan::call('migrate',['--path'=>'database/migrations/'.$migration.'.php','--force'=>true])!==0) throw new RuntimeException('Migrazione fallita: '.$migration);
    $report['mode']='schema-applied'; $report['backup']=$backup;
}
if (in_array('--reset-demo',$argv,true)) {
    if ($pending) throw new RuntimeException('Applicare prima le migrazioni.');
    $missing=array_diff((new App\Models\InsurancePolicy)->getFillable(),Schema::getColumnListing('insurance_policies'));
    if ($missing) throw new RuntimeException('Colonne mancanti: '.implode(', ',$missing));
    if ($app->isDownForMaintenance()) throw new RuntimeException('Applicazione già in manutenzione: non modifico il suo stato.');
    Artisan::call('down',['--retry'=>30]);
    try { $report=app(App\Services\InsuranceDemoReset::class)->apply($admin)+['mode'=>'demo-applied']; }
    finally { Artisan::call('up'); }
}
if ($report['mode']!=='preview') file_put_contents(__DIR__.'/workspace-'.$report['mode'].'-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
