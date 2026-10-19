<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection=Illuminate\Support\Facades\DB::connection();
if($connection->getDriverName()!=='mysql' || $connection->getDatabaseName()!=='r4_insurance')throw new RuntimeException('Database inatteso.');
echo json_encode(['database'=>$connection->getDatabaseName(),'clients'=>App\Models\Client::count(),'policies'=>App\Models\InsurancePolicy::count(),'companies'=>App\Models\SupplierCompany::orderBy('name')->get(['id','name','status'])->toArray(),'operation_migration'=>Illuminate\Support\Facades\DB::table('migrations')->where('migration','like','%insurance_operations%')->get()->toArray(),'policy_columns'=>Illuminate\Support\Facades\Schema::getColumnListing('insurance_policies'),'operations_migrated'=>Illuminate\Support\Facades\Schema::hasTable('insurance_titles')],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
