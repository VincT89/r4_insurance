<?php

$root = dirname(__DIR__,2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = getenv('DB_DATABASE');
if (getenv('DB_CONNECTION') !== 'sqlite' || dirname($db) !== __DIR__ || !preg_match('/^ui-[0-9]+\.sqlite$/',basename($db)) || !is_file($db)) throw new RuntimeException('Database di prova inatteso.');
for ($i=1;$i<=520;$i++) App\Models\Client::factory()->create(['name'=>sprintf('Cliente DEMO %04d',$i),'email'=>"demo{$i}@example.test",'phone'=>'000'.str_pad((string)$i,6,'0',STR_PAD_LEFT),'status'=>$i%2 ? 'active' : 'inactive','salient_notes'=>'Dati dimostrativi per il collaudo']);
App\Models\SupplierCompany::create(['name'=>'Compagnia DEMO A']);
App\Models\SupplierCompany::create(['name'=>'Compagnia DEMO B']);
echo 'Dati dimostrativi preparati nel database di prova.'.PHP_EOL;
