<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.default')!=='sqlite') throw new RuntimeException('Richiesto database di collaudo isolato.');
$admin=App\Models\User::where('email','ui-test@example.test')->firstOrFail();
Illuminate\Support\Facades\Auth::login($admin);
$policy=App\Models\InsurancePolicy::orderBy('id')->firstOrFail();
$service=app(App\Services\InsuranceClaims::class);
$claim=$service->create($admin,$policy,['context_hash'=>$service->context($policy),'request_key'=>(string)Illuminate\Support\Str::uuid(),'number'=>'SIN-2026-731','event_on'=>today()->subDay()->toDateString(),'reported_on'=>today()->toDateString(),'assigned_to'=>$admin->id,'description'=>'Pratica usata esclusivamente per la verifica della paginazione.'],[]);
for($i=1;$i<=20;$i++)$claim->events()->create(['actor_id'=>$admin->id,'actor_name'=>$admin->name,'kind'=>'note','request_key'=>(string)Illuminate\Support\Str::uuid(),'details'=>['nota'=>'Annotazione '.str_pad((string)$i,2,'0',STR_PAD_LEFT)],'created_at'=>now()->addSeconds($i)]);
echo json_encode(['claim_id'=>$claim->id,'event_count'=>$claim->events()->count()],JSON_THROW_ON_ERROR);
