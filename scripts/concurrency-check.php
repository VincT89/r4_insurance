<?php

/** Explicitly separate this destructive fixture setup from the configured business database and storage. */
require dirname(__DIR__).'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\{Artisan, DB};

$operation = $argv[1] ?? '';
$workspace = $argv[2] ?? '';
$database = $argv[3] ?? '';
if (!in_array($operation, ['create', 'run', 'worker'], true)
    || !preg_match('/^r4_concurrency_[a-z0-9_]{8,40}$/D', $database)
    || !is_dir($workspace) || is_link($workspace) || !is_file($workspace.'/.concurrency-workspace')) {
    fwrite(STDERR, "Richiesti create|run|worker, cartella esterna con .concurrency-workspace e database r4_concurrency_ID.\n");
    exit(1);
}
$workspace = realpath($workspace);
if (str_starts_with(strtolower(str_replace('\\', '/', $workspace)).'/', strtolower(str_replace('\\', '/', dirname(__DIR__))).'/')) {
    throw new RuntimeException('La cartella di collaudo deve essere esterna al repository.');
}
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = config('database.connections.mysql');
if (config('database.default') !== 'mysql' || $database === ($source['database'] ?? null)) {
    throw new RuntimeException('Database operativo escluso; il collaudo richiede MySQL.');
}
$keyFile = $workspace.'/application-key';
if ($operation === 'create') {
    if (file_exists($keyFile)) throw new RuntimeException('Cartella già inizializzata: nessuna sovrascrittura.');
    $pdo = new PDO('mysql:host='.$source['host'].';port='.$source['port'].';charset=utf8mb4', $source['username'], $source['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    /** No IF NOT EXISTS: an existing schema must never be adopted or cleared by this test. */
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
    'app.env'=>'testing', 'app.debug'=>false, 'app.key'=>trim(file_get_contents($keyFile)), 'app.previous_keys'=>[],
    'app.url'=>'http://localhost', 'database.connections.mysql.database'=>$database, 'database.connections.mysql.url'=>null,
    'cache.default'=>'array', 'session.driver'=>'array', 'queue.default'=>'sync', 'mail.default'=>'array',
    'logging.default'=>'single', 'logging.channels.single.path'=>$workspace.'/storage/logs/laravel.log',
    'view.compiled'=>$workspace.'/storage/framework/views',
    'filesystems.disks.attachments.root'=>$workspace.'/storage/app/attachments',
    'filesystems.disks.local.root'=>$workspace.'/storage/app/private', 'filesystems.disks.public.root'=>$workspace.'/storage/app/public',
]);
$app->forgetInstance('encrypter');
DB::purge('mysql');
DB::statement('SET SESSION innodb_lock_wait_timeout = 20');
Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-10-21 12:00:00', 'Europe/Rome'));
$app->make(Illuminate\Foundation\Vite::class)->useHotFile($workspace.'/unused-hot');
require dirname(__DIR__).'/tests/Support/ConcurrentOperations.php';

/** Configuration and transport are replaced before any scenario; no SMTP settings or mail connection are used. */
$app->instance(App\Services\AgencyMailConfiguration::class, new Tests\Support\ConcurrentMailConfiguration);
$app->instance(App\Services\AgencyEmailTransport::class, new Tests\Support\ConcurrentMailTransport($workspace));
if ($operation === 'create') {
    if (Artisan::call('migrate', ['--force'=>true]) !== 0) throw new RuntimeException('Migrazioni isolate non riuscite.');
    echo "Schema isolato pronto: $database\n";
} elseif ($operation === 'worker') {
    Tests\Support\ConcurrentOperations::worker($workspace, $argv[4] ?? '', $argv[5] ?? '');
} else {
    exit((new Tests\Support\ConcurrentOperations($workspace, $database))->run($argv[4] ?? null));
}
