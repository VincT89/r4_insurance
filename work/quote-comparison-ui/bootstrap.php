<?php

$root=dirname(__DIR__,2);
$qaPath=getenv('QUOTE_COMPARISON_QA_PATH');
if(!$qaPath || dirname($qaPath)!==__DIR__ || !preg_match('/\Arun-[0-9]+\z/',basename($qaPath))) throw new RuntimeException('Cartella di collaudo isolata richiesta.');
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->useStoragePath($qaPath.'/storage');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('testing') || config('database.default')!=='sqlite' || config('database.connections.sqlite.database')!==$qaPath.'/test.sqlite') throw new RuntimeException('Database di collaudo isolato richiesto.');
Illuminate\Support\Facades\Http::preventStrayRequests();
$app->make(Illuminate\Foundation\Vite::class)->useHotFile($qaPath.'/unused-hot');
return $app;
