<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
if ($db->getDatabaseName()!=='r4_insurance') throw new RuntimeException('Database inatteso.');
$rows=$db->select('SHOW FULL PROCESSLIST');
echo json_encode(array_map(function($r){preg_match_all('/(?:from|join)\s+[`a-zA-Z0-9_.]+/i',$r->Info??'',$tables);return ['seconds'=>$r->Time,'command'=>$r->Command,'state'=>$r->State,'operation'=>preg_match('/^\s*(SELECT|DELETE|UPDATE|INSERT|SHOW|SET|ALTER)/i',$r->Info??'', $m)?strtoupper($m[1]):null,'tables'=>$tables[0]];},array_filter($rows,fn($r)=>$r->db==='r4_insurance' && $r->Command!=='Sleep')),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
