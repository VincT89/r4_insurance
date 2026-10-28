<?php

/** Loaded only through the guarded capacity-check entry point. */
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Cache};

if (!isset($workspace,$database) || !app()->environment('testing')) throw new RuntimeException('Contesto di collaudo richiesto.');
$label=preg_replace('/[^a-z0-9_-]/i','',$argv[4]??'baseline');
$kernel=app(\Illuminate\Contracts\Http\Kernel::class);
$user=User::findOrFail(1);
Auth::setUser($user);
app(\Illuminate\Foundation\Vite::class)->useHotFile($workspace.'/unused-hot');
$queries=0; $sqlMs=0; $slow=[];
DB::listen(function($event) use(&$queries,&$sqlMs,&$slow) {
    $queries++; $sqlMs+=$event->time;
    if ($event->time>100 && count($slow)<20) $slow[]=['milliseconds'=>$event->time,'sql'=>$event->sql];
});
$request=function(string $uri, bool $export=false) use($kernel,$user,&$queries,&$sqlMs,&$slow,$workspace) {
    $queries=0; $sqlMs=0; $slow=[]; $start=microtime(true);
    Auth::setUser($user);
    $request=Request::create('http://127.0.0.1:8029'.$uri,'GET');
    if ($export) $request->headers->set('X-Table-Export','1');
    $response=$kernel->handle($request); $body=$response->getContent();
    $measurement=['ms'=>round((microtime(true)-$start)*1000,1),'queries'=>$queries,'sql_ms'=>round($sqlMs,1),
        'status'=>$response->getStatusCode(),'bytes'=>strlen($body),'slow'=>$slow];
    if ($response->getStatusCode()!==200) {
        file_put_contents($workspace.'/failed-response.html',$body);
        throw new RuntimeException('Risposta '.$response->getStatusCode().' per '.$uri);
    }
    $kernel->terminate($request,$response);
    return [$measurement,$body];
};
if ($label==='profile-menu') {
    $ordered=fn($query)=>$query->reorder()->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
        ->orderByRaw('CASE WHEN type = ? THEN 0 WHEN type = ? THEN 1 ELSE 2 END',[\App\Services\CollectionCommunicationReminders::REVERSAL_TYPE,\App\Services\CollectionCommunicationReminders::TYPE])
        ->orderBy('data->due_on')->orderByDesc('updated_at')->orderByDesc('id')->limit(10);
    $variants=[
        'separate'=>function()use($user,$ordered){$rows=$ordered($user->visibleNotifications())->get();return [$rows,$user->visibleNotifications()->whereNull('read_at')->count()];},
        'wide'=>function()use($user,$ordered){$rows=$ordered($user->visibleNotifications()->select('notifications.*')->selectRaw('SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) OVER () AS inbox_unread_count'))->get();return [$rows,(int)($rows->first()?->inbox_unread_count??0)];},
        'identifiers'=>function()use($user,$ordered){
            $ids=$ordered($user->visibleNotifications()->select('notifications.id as notification_id')->selectRaw('SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) OVER () AS inbox_unread_count'));
            $rows=$ordered($user->notifications()->joinSub($ids,'inbox',fn($join)=>$join->on('inbox.notification_id','=','notifications.id'))->select('notifications.*','inbox.inbox_unread_count'))->get();
            return [$rows,(int)($rows->first()?->inbox_unread_count??0)];
        },
    ];
    $profiles=[]; $reference=null;
    for($round=0;$round<3;$round++) foreach($variants as $name=>$variant) {
        $start=microtime(true);[$rows,$count]=$variant();$elapsed=round((microtime(true)-$start)*1000,1);
        $signature=[$rows->modelKeys(),$count];$reference??=$signature;
        if($reference!==$signature)throw new RuntimeException('Elenco o contatore diversi fra le letture.');
        $profiles[$name][]=$elapsed;echo "$name: $elapsed ms\n";
    }
    file_put_contents($workspace.'/notification-query-profiles.json',json_encode(['samples_ms'=>$profiles,'visible_count'=>$reference[1],'same_rows'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    return;
}
if (str_starts_with($label, 'inbox-')) {
    $samples = [];
    for ($i=0; $i<4; $i++) {
        $queries=0; $sqlMs=0; $slow=[]; $start=microtime(true);
        $data = app(\App\Livewire\Notifications\NotificationDropdown::class)->render()->getData();
        $samples[] = ['ms'=>round((microtime(true)-$start)*1000,1), 'queries'=>$queries, 'sql_ms'=>round($sqlMs,1),
            'unread'=>$data['unreadNotificationsCount'], 'displayed'=>$data['latestNotifications']->count()];
        echo 'notifiche richiesta '.($i+1).': '.$samples[$i]['ms']." ms\n";
    }
    file_put_contents($workspace.'/measure-'.$label.'.json', json_encode(['method'=>'notification refresh and current inbox query, independently from page HTML', 'samples'=>$samples], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    return;
}
$routes=['dashboard'=>'/dashboard','policies'=>'/policies','policies_filtered'=>'/policies?month=2026-10&date_basis=starts_on&category=RC%20auto',
    'clients'=>'/clients','titles'=>'/titles?month=2026-10','collections'=>'/collections?month=2026-10',
    'reports'=>'/reports?month=2026-10','commissions'=>'/reports/commissions?month=2026-10',
    'deadlines'=>'/deadlines','communications'=>'/agency-communications?period=month&month=2026-10',
    'client'=>'/clients/1','policy'=>'/clients/1/policies/1'];
if (isset($argv[5])) $routes=array_intersect_key($routes,array_flip(explode(',',$argv[5])));
$results=['database'=>$database,'generated_at'=>now()->toIso8601String(),'policies'=>DB::table('insurance_policies')->count(),
    'method'=>'single-process HTTP kernel including page views and deferred notification placeholder; inbox processing measured separately; one cold and three warm requests per route','routes'=>[]];
foreach ($routes as $name=>$uri) {
    $samples=[];
    for($i=0;$i<4;$i++) { [$sample]=$request($uri); $samples[]=$sample; echo $name.' richiesta '.($i+1).': '.$sample['ms']." ms\n"; }
    $times=array_column(array_slice($samples,1),'ms'); sort($times);
    $results['routes'][$name]=['url'=>$uri,'cold_ms'=>$samples[0]['ms'],'warm_median_ms'=>$times[1],'warm_max_ms'=>max($times),'samples'=>$samples];
    echo $name.': '.$samples[0]['ms'].' ms iniziale, '.$times[1]." ms mediana\n";
    file_put_contents($workspace.'/measure-'.$label.'.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}
$results['peak_memory_mb']=round(memory_get_peak_usage(true)/1048576,1);
file_put_contents($workspace.'/measure-'.$label.'.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
