<?php

if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(403); exit; }
$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = realpath($root.'/public');
$file = realpath($public.'/'.$path);
if ($path !== '/' && $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['css','js','mjs','svg','png','jpg','jpeg','webp','gif','ico','woff','woff2','ttf','wasm'], true)) return false;
define('LARAVEL_START', microtime(true));
$app = require __DIR__.'/bootstrap.php';
$app->handleRequest(Illuminate\Http\Request::capture());
