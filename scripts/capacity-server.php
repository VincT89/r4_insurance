<?php

/** Only the explicit loopback test server uses this router; it exposes no application route or login bypass. */
if (PHP_SAPI!=='cli-server') throw new RuntimeException('Router riservato al server di collaudo.');
$public=realpath(dirname(__DIR__).'/public');
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$file=realpath($public.'/'.$path);
if ($path!=='/' && $file && str_starts_with($file,$public.DIRECTORY_SEPARATOR) && is_file($file)) return false;
define('LARAVEL_START',microtime(true));
$argv=[__FILE__,'http',getenv('CAPACITY_WORKSPACE')?:'',getenv('CAPACITY_DATABASE')?:''];
require __DIR__.'/capacity-check.php';
