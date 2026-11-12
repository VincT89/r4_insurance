<?php

$root = dirname(__DIR__, 2);
$qaPath = getenv('INBOUND_EMAIL_QA_PATH');
if (!$qaPath || str_replace('\\', '/', dirname($qaPath)) !== str_replace('\\', '/', __DIR__) || !preg_match('/\Arun-[a-f0-9-]+\z/', basename($qaPath))) throw new RuntimeException('Cartella di prova isolata richiesta.');
if (getenv('APP_ENV') !== 'local' || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== $qaPath.'/test.sqlite' || !getenv('APP_KEY')) throw new RuntimeException('Ambiente di prova isolato richiesto.');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
/** La prova non carica le credenziali o la configurazione dell'archivio operativo. */
$app->loadEnvironmentFrom('.env.inbound-preview-not-used');
$app->useStoragePath($qaPath.'/storage');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $qaPath.'/test.sqlite' || config('mail.default') !== 'array' || config('inbound-email.enabled')) throw new RuntimeException('Isolamento del collaudo non confermato.');
Illuminate\Support\Facades\Http::preventStrayRequests();
$app->make(Illuminate\Foundation\Vite::class)->useHotFile($qaPath.'/unused-hot');
return $app;
