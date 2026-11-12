<?php

$root=dirname(__DIR__,2);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$public=realpath($root.'/public');
$file=realpath($public.'/'.$path);
if($path!=='/' && $file && str_starts_with($file,$public.DIRECTORY_SEPARATOR) && is_file($file)) return false;
define('LARAVEL_START',microtime(true));
$app=require __DIR__.'/bootstrap.php';
$app->handleRequest(Illuminate\Http\Request::capture());
