<?php

/** Isolated synthetic data only: this entry point never targets the configured business database. */
require dirname(__DIR__).'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\{Artisan, DB};

$operation = $argv[1] ?? '';
$workspace = $argv[2] ?? '';
$database = $argv[3] ?? '';
if (!in_array($operation, ['create', 'migrate', 'rebuild-empty', 'seed', 'measure', 'stats', 'diagnose', 'compare-restore', 'probe-restore', 'access', 'http', 'backup-init', 'backup-create', 'backup-check', 'restore'], true)
    || !preg_match('/^r4_capacity_[a-z0-9_]{8,40}$/D', $database)
    || !is_dir($workspace) || is_link($workspace)
    || !is_file($workspace.'/.capacity-workspace')) {
    fwrite(STDERR, "Uso: php scripts/capacity-check.php create|seed|measure WORKSPACE r4_capacity_ID\nLa cartella privata esterna deve contenere .capacity-workspace.\n");
    exit(1);
}
$workspace = realpath($workspace);
if (str_starts_with(strtolower(str_replace('\\', '/', $workspace)).'/', strtolower(str_replace('\\', '/', dirname(__DIR__))).'/')) {
    throw new RuntimeException('Il collaudo richiede una cartella esterna al repository.');
}
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = config('database.connections.mysql');
if ($database === ($source['database'] ?? null)) throw new RuntimeException('Database operativo escluso.');
if (config('database.default') !== 'mysql') throw new RuntimeException('Il collaudo richiede MySQL.');
$keyFile = $workspace.'/application-key';
if ($operation === 'create') {
    if (file_exists($keyFile)) throw new RuntimeException('Collaudo già inizializzato: nessun dato sovrascritto.');
    $pdo = new PDO('mysql:host='.$source['host'].';port='.$source['port'].';charset=utf8mb4', $source['username'], $source['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    /** CREATE without IF NOT EXISTS deliberately refuses to reuse any existing schema. */
    $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    file_put_contents($keyFile, 'base64:'.base64_encode(random_bytes(32)), LOCK_EX);
    chmod($keyFile, 0600);
}
if (!is_file($keyFile)) throw new RuntimeException('Inizializzare prima il collaudo.');
foreach (['app/attachments', 'app/public', 'app/private', 'framework/views', 'framework/sessions', 'framework/cache/data', 'logs'] as $path) {
    if (!is_dir($workspace.'/storage/'.$path)) mkdir($workspace.'/storage/'.$path, 0700, true);
}
$app->useStoragePath($workspace.'/storage');
$app->instance('env', 'testing');
config([
    'app.env'=>'testing', 'app.debug'=>false, 'app.key'=>trim(file_get_contents($keyFile)),
    'app.previous_keys'=>[], 'app.url'=>'http://127.0.0.1:8029',
    'database.connections.mysql.database'=>$database, 'database.connections.mysql.url'=>null,
    'cache.default'=>'array', 'session.driver'=>'array', 'queue.default'=>'sync',
    'mail.default'=>'array', 'logging.default'=>'single', 'logging.channels.single.path'=>$workspace.'/storage/logs/laravel.log',
    'view.compiled'=>$workspace.'/storage/framework/views',
    'filesystems.disks.attachments.root'=>$workspace.'/storage/app/attachments',
    'filesystems.disks.local.root'=>$workspace.'/storage/app/private',
    'filesystems.disks.public.root'=>$workspace.'/storage/app/public',
]);
$app->forgetInstance('encrypter');
DB::purge('mysql');

if ($operation === 'create' || $operation === 'migrate') {
    if (Artisan::call('migrate', ['--force'=>true]) !== 0) throw new RuntimeException('Migrazioni non riuscite.');
    echo "Schema isolato creato: $database\n";
} elseif ($operation === 'rebuild-empty') {
    if (DB::table('clients')->exists() || DB::table('insurance_policies')->exists()
        || DB::table('users')->where('email', '!=', 'capacity@example.test')->exists()) {
        throw new RuntimeException('Ricostruzione consentita soltanto allo schema di collaudo ancora senza clienti e polizze.');
    }
    if (Artisan::call('migrate:fresh', ['--force'=>true]) !== 0) throw new RuntimeException('Ricostruzione non riuscita.');
    echo "Schema di collaudo vuoto ricostruito.\n";
} elseif ($operation === 'seed') {
    (new Database\Seeders\CapacitySeeder)->run();
} elseif ($operation==='http') {
    if (PHP_SAPI!=='cli-server' || !in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)) throw new RuntimeException('Server di collaudo locale richiesto.');
    config(['session.driver'=>'file','session.files'=>$workspace.'/storage/framework/sessions','session.cookie'=>'r4_capacity_session',
        'cache.default'=>'file','cache.stores.file.path'=>$workspace.'/storage/framework/cache/data']);
    $app->make(\Illuminate\Foundation\Vite::class)->useHotFile($workspace.'/unused-hot');
    $app->handleRequest(\Illuminate\Http\Request::capture());
} elseif ($operation==='access') {
    $credentials=$workspace.'/qa-credentials.json';
    if (file_exists($credentials)) throw new RuntimeException('Accesso di collaudo già predisposto.');
    $password=bin2hex(random_bytes(24));
    $updated=DB::table('users')->where('id',1)->where('email','capacity@example.test')->update(['password'=>password_hash($password,PASSWORD_BCRYPT,['cost'=>4])]);
    if ($updated!==1) throw new RuntimeException('Utente di collaudo non trovato.');
    file_put_contents($credentials,json_encode(['email'=>'capacity@example.test','password'=>$password],JSON_THROW_ON_ERROR),LOCK_EX);
    chmod($credentials,0600);
    echo "Accesso di collaudo predisposto nella cartella privata.\n";
} elseif ($operation==='diagnose') {
    $query=\App\Models\User::findOrFail(1)->visibleNotifications()->whereNull('read_at')->reorder()->selectRaw('count(*) as aggregate');
    $plan=DB::select('EXPLAIN FORMAT=JSON '.$query->toSql(),$query->getBindings());
    file_put_contents($workspace.'/notification-plan.json',((array)$plan[0])['EXPLAIN']);
    echo DB::selectOne('SELECT VERSION() AS version')->version."\n";
    $profile=DB::select('ANALYZE FORMAT=JSON '.$query->toSql(),$query->getBindings());
    file_put_contents($workspace.'/notification-analysis.json',((array)$profile[0])['ANALYZE']);
    echo "Piano salvato.\n";
} elseif ($operation==='stats') {
    $progressDatabase=$argv[4]??null;
    if ($progressDatabase!==null && !preg_match('/^r4_restore_[a-z0-9_]{8,40}$/D',$progressDatabase)) throw new RuntimeException('Database di ripristino non valido.');
    $observedDatabases=array_filter([$database,$progressDatabase]);
    echo json_encode(['notifications'=>DB::table('notifications')->count(),
        'restored_tables'=>$progressDatabase?DB::table('information_schema.TABLES')->where('TABLE_SCHEMA',$progressDatabase)->count():null,
        'queries'=>DB::table('information_schema.PROCESSLIST')->whereIn('DB',$observedDatabases)->where('COMMAND','!=','Sleep')->get(['ID','DB','TIME','STATE','COMMAND'])->all()],JSON_PRETTY_PRINT)."\n";
} elseif ($operation==='probe-restore') {
    $restoreId=$argv[4]??'';
    if (!preg_match('/^[a-z0-9_]{8,40}$/D',$restoreId)) throw new RuntimeException('Identificativo del ripristino non valido.');
    $restoreDatabase='r4_restore_'.$restoreId;
    $manifest=json_decode(file_get_contents($workspace.'/restore-'.$restoreId.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if (($manifest['source_database']??null)!==$database || DB::table('information_schema.TABLES')->where('TABLE_SCHEMA',$restoreDatabase)->exists()) throw new RuntimeException('La prova richiede il solo database di ripristino vuoto.');
    config(['insurance-backup'=>array_replace(config('insurance-backup'),json_decode(file_get_contents($workspace.'/backup-config.json'),true,512,JSON_THROW_ON_ERROR))]);
    $service=app(\App\Services\InsuranceBackup::class);
    $import=new ReflectionMethod($service,'importDatabase');
    $temporaryAccounts=fn()=>DB::table('mysql.user')->where('User','like','r4restore\\_%')->orderBy('User')->orderBy('Host')->get(['User','Host'])->toJson();
    $accountsBefore=$temporaryAccounts(); $probeFile=$workspace.'/restore-sql-probe.sql';
    if (file_exists($probeFile)) throw new RuntimeException('File di prova già presente.');
    try {
        file_put_contents($probeFile,'CREATE TABLE restore_probe (id INT PRIMARY KEY); INSERT INTO restore_probe VALUES (42);',LOCK_EX);
        $import->invoke($service,DB::connection(),$restoreDatabase,$probeFile);
        $validImport=DB::table($restoreDatabase.'.restore_probe')->where('id',42)->count()===1;
        file_put_contents($probeFile,'SELECT COUNT(*) FROM `'.$database.'`.`clients`;',LOCK_EX);
        $outsideDenied=false;
        try { $import->invoke($service,DB::connection(),$restoreDatabase,$probeFile); }
        catch (RuntimeException $exception) { $outsideDenied=str_starts_with($exception->getMessage(),'Ripristino database non riuscito'); }
        $accountsRemoved=$accountsBefore===$temporaryAccounts();
        echo json_encode(['native_import'=>$validImport,'source_database_access_denied'=>$outsideDenied,'temporary_accounts_removed_after_success_and_failure'=>$accountsRemoved],JSON_PRETTY_PRINT)."\n";
    } finally { if (file_exists($probeFile)) unlink($probeFile); }
    if (!$validImport || !$outsideDenied || !$accountsRemoved) exit(1);
} elseif ($operation==='compare-restore') {
    $restoreId=$argv[4]??'';
    if (!preg_match('/^[a-z0-9_]{8,40}$/D',$restoreId)) throw new RuntimeException('Identificativo del ripristino non valido.');
    $restoreDatabase='r4_restore_'.$restoreId;
    $manifest=json_decode(file_get_contents($workspace.'/restore-'.$restoreId.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if (($manifest['source_database']??null)!==$database) throw new RuntimeException('Origine del backup non corrispondente.');
    config(['database.connections.capacity_restore'=>array_replace(config('database.connections.mysql'),['database'=>$restoreDatabase,'url'=>null])]);
    $restoreConnection=DB::connection('capacity_restore');
    $restored=app(\App\Services\InsuranceBackup::class)->fingerprints($restoreConnection);
    if ($restored===[]) {
        echo json_encode(['database'=>$restoreDatabase,'restored_tables'=>0,'expected_tables'=>count($manifest['tables'])],JSON_PRETTY_PRINT)."\n";
        exit(1);
    }
    $sourceFingerprints=app(\App\Services\InsuranceBackup::class)->fingerprints(DB::connection());
    $differences=[];
    foreach (array_unique([...array_keys($manifest['tables']),...array_keys($restored)]) as $table) {
        $expected=$manifest['tables'][$table]??null; $actual=$restored[$table]??null;
        if ($expected===$actual) continue;
        $differentColumns=[];
        if ($expected && $actual && ($sourceFingerprints[$table]??null)===$expected) {
            $primary=collect($restoreConnection->getSchemaBuilder()->getIndexes($table))->firstWhere('primary',true)['columns']??[];
            $sourceRows=DB::table($table); $restoredRows=$restoreConnection->table($table);
            foreach ($primary as $column) { $sourceRows->orderBy($column); $restoredRows->orderBy($column); }
            $sourceSample=$sourceRows->limit(50)->get(); $restoredSample=$restoredRows->limit(50)->get();
            foreach ($sourceSample as $index=>$row) foreach ((array)$row as $column=>$value) {
                if (!isset($restoredSample[$index]) || $value!==($restoredSample[$index]->{$column}??null)) $differentColumns[$column]=true;
            }
        }
        $differences[$table]=['expected'=>$expected,'actual'=>$actual,'source_unchanged'=>($sourceFingerprints[$table]??null)===$expected,'different_columns_in_first_50_rows'=>array_keys($differentColumns)];
    }
    /** Diagnostics report counts, hashes and column names, never client data or encrypted values. */
    echo json_encode(['database'=>$restoreDatabase,'differences'=>$differences],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    DB::purge('capacity_restore');
} elseif (str_starts_with($operation,'backup-') || $operation==='restore') {
    try {
    $backupConfig=json_decode(file_get_contents($workspace.'/backup-config.json'),true,512,JSON_THROW_ON_ERROR);
    config(['insurance-backup'=>array_replace(config('insurance-backup'),$backupConfig)]);
    $service=app(\App\Services\InsuranceBackup::class);
    if ($operation==='backup-init') $service->initialize();
    elseif ($operation==='backup-check') $service->check();
    elseif ($operation==='restore') {
        $result=$service->restore($argv[4]??'', $workspace.'/restore-'.($argv[5]??''), 'r4_restore_'.($argv[5]??''));
        echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    } else {
        /** Only this isolated app storage is put in maintenance; the operational application is untouched. */
        $alreadyDown=$app->isDownForMaintenance();
        if (!$alreadyDown) Artisan::call('down');
        try { echo json_encode($service->create(true),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n"; }
        finally { if (!$alreadyDown) Artisan::call('up'); }
    }
    echo "Operazione di collaudo completata.\n";
    } catch (\Throwable $exception) {
        /** SQL and native-process diagnostics may contain private data; report only controlled errors. */
        fwrite(STDERR,get_class($exception)===\RuntimeException::class?$exception->getMessage()."\n":"Operazione di collaudo non riuscita (".get_class($exception).").\n");
        exit(1);
    }
} else {
    require __DIR__.'/capacity-measure.php';
}
