<?php

use App\Models\{AgencyCommunication, Client, InsurancePolicy, User};
use App\Services\{AgencyEmailWorkflow, AgencyMailConfiguration};
use Illuminate\Support\Facades\{Cache, DB};

require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=DB::connection();
if ($db->getDriverName()!=='mysql' || $db->getDatabaseName()!=='r4_insurance' || !in_array($db->getConfig('host'),['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Database locale inatteso.');
$admin=User::where('email','admin@admin.it')->firstOrFail();
if (!$admin->isAdmin() || $admin->status!=='active') throw new RuntimeException('Amministratore non disponibile.');
config(['session.driver'=>'array','cache.default'=>'array']);
Cache::forever('deadline-reminders:'.$admin->id,true);
$app['auth']->guard('web')->setUser($admin);
$tables=['clients','insurance_policies','supplier_agencies','supplier_companies','insurance_branches','insurance_subbranches','agency_communications','agency_communication_policies','audit_logs','outbound_mail_settings'];
$counts=fn()=>collect($tables)->mapWithKeys(fn($table)=>[$table=>$db->table($table)->count()])->all();
$before=$counts();
$report=['counts'=>$before,'smtp_configured'=>app(AgencyMailConfiguration::class)->ready(),'email_sent'=>false];
$db->beginTransaction();
try {
    $policy=InsurancePolicy::with(['client','supplierAgency'])->whereIn('status',['active','renewed'])->whereDate('expires_on','>=',today())->whereHas('supplierAgency',fn($q)=>$q->where('status','active'))->firstOrFail();
    $agency=$policy->supplierAgency;
    $report['temporary_recipient_used']=!filter_var($agency->email,FILTER_VALIDATE_EMAIL);
    /** The inspection recipient exists only inside this transaction; no mail transport is called. */
    if ($report['temporary_recipient_used']) $agency->update(['email'=>'inspection@example.test']);
    $message=app(AgencyEmailWorkflow::class)->prepare($admin,$agency,collect([$policy]),['period'=>'day','date'=>today()->toDateString()],today()->format('d/m/Y'));
    $report['mysql_review_saved']=$message->fresh()->status==='review';
    $report['review_encrypted']=!str_contains($db->table('agency_communications')->where('id',$message->id)->value('payload'),$policy->number);
    $report['review_audited']=$db->table('audit_logs')->where('action','agency_email_review')->where('auditable_id',$agency->id)->where('description','like','%'.$message->id.'%')->exists();
    $report['no_delivery_claim']=$db->table('agency_communication_policies')->where('agency_communication_id',$message->id)->whereNotNull('delivery_key')->doesntExist();
    $report['pending_migrations']=array_values(array_diff(array_keys($app['migrator']->getMigrationFiles(database_path('migrations'))),$db->table('migrations')->pluck('migration')->all()));
    $report['orphan_policy_links']=$db->table('insurance_policies as p')->leftJoin('clients as c','c.id','=','p.client_id')->leftJoin('insurance_branches as b','b.id','=','p.insurance_branch_id')->leftJoin('supplier_companies as co','co.id','=','p.supplier_company_id')->leftJoin('supplier_agencies as a','a.id','=','p.supplier_agency_id')->where(fn($q)=>$q->whereNull('c.id')->orWhereNull('b.id')->orWhereNull('co.id')->orWhereNull('a.id'))->count();
    $paths=['/dashboard','/policies','/policies/create','/clients','/clients/create','/clients/'.$policy->client_id.'?tab=policies','/clients/'.$policy->client_id.'?tab=documents','/clients/'.$policy->client_id.'/policies/'.$policy->id,'/clients/'.$policy->client_id.'/policies/'.$policy->id.'/edit','/clients/'.$policy->client_id.'/documents/create','/suppliers','/suppliers/'.$agency->id,'/supplier-companies/'.$policy->supplier_company_id,'/deadlines','/reports','/agency-communications','/agency-communications?period=15','/agency-communications?period=month&month='.today()->format('Y-m'),'/agency-communications/history','/agency-communications/messages/'.$message->id,'/settings/email','/settings/appearance','/audit-logs?action=communications','/users'];
    $kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
    foreach ($paths as $path) {
        $app['auth']->forgetGuards(); $app['auth']->guard('web')->setUser($admin);
        $response=$kernel->handle(Illuminate\Http\Request::create('http://localhost'.$path,'GET'));
        $report['pages'][preg_replace('/messages\/[a-f0-9-]+/','messages/review',$path)]=$response->getStatusCode();
    }
    $request=Illuminate\Http\Request::create('/dashboard');$request->setUserResolver(fn()=>$admin);
    $dashboard=app(App\Http\Controllers\DashboardController::class)($request,app(App\Domain\Core\Queries\PolicyPortfolioQuery::class))->getData();
    $report['today_rows']=$dashboard['todayPolicies']->count();
    $report['today_only_open']=$dashboard['todayPolicies']->every(fn($p)=>$p->created_at->isToday() && in_array($p->status,['active','renewed'],true) && !$p->expires_on->lt(today()));
} finally { $db->rollBack(); }
$report['rolled_back']=($before===$counts());
$report['ok']=$report['mysql_review_saved'] && $report['review_encrypted'] && $report['review_audited'] && $report['no_delivery_claim'] && !$report['pending_migrations'] && !$report['orphan_policy_links'] && $report['today_only_open'] && $report['rolled_back'] && !array_filter($report['pages'],fn($status)=>$status!==200);
file_put_contents(__DIR__.'/agency-email-live-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['ok'] ? 0 : 1);
