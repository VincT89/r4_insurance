<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Schema,Storage};
$db=DB::connection();
if($db->getDriverName()!=='mysql')throw new RuntimeException('Il controllo richiede il database MySQL configurato.');
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try{
 $tables=['users','clients','insurance_policies','supplier_agencies','supplier_companies','insurance_branches','insurance_subbranches','attachments','insurance_quote_cases','insurance_quotes','insurance_titles','insurance_collections','insurance_claims','insurance_claim_activities','insurance_form_templates','insurance_form_packages','agency_commission_schedules','audit_logs'];
 $report=['date'=>now()->toIso8601String(),'connection'=>$db->getDriverName(),'read_only'=>true,'counts'=>[],'integrity'=>[]];
 foreach($tables as $table)$report['counts'][$table]=DB::table($table)->count();
 $queries=[
  'policies_without_client'=>'SELECT COUNT(*) AS n FROM insurance_policies p LEFT JOIN clients c ON c.id=p.client_id WHERE c.id IS NULL',
  'policy_vehicle_wrong_client'=>'SELECT COUNT(*) AS n FROM insurance_policies p JOIN client_vehicles v ON v.id=p.vehicle_id WHERE v.client_id<>p.client_id',
  'policy_asset_wrong_client'=>'SELECT COUNT(*) AS n FROM insurance_policies p JOIN client_assets a ON a.id=p.client_asset_id WHERE a.client_id<>p.client_id',
  'policy_subbranch_wrong_branch'=>'SELECT COUNT(*) AS n FROM insurance_policies p JOIN insurance_subbranches s ON s.id=p.insurance_subbranch_id WHERE s.insurance_branch_id<>p.insurance_branch_id',
  'policy_expiry_before_start'=>'SELECT COUNT(*) AS n FROM insurance_policies WHERE expires_on<starts_on',
  'title_amount_mismatch'=>'SELECT COUNT(*) AS n FROM insurance_titles t LEFT JOIN (SELECT insurance_title_id, SUM(amount_cents) AS amount FROM insurance_collections WHERE voided_at IS NULL GROUP BY insurance_title_id) c ON c.insurance_title_id=t.id WHERE t.collected_cents<>COALESCE(c.amount,0)',
  'overpaid_titles'=>'SELECT COUNT(*) AS n FROM insurance_titles WHERE collected_cents>amount_cents OR collected_cents<0',
  'claim_policy_wrong_client'=>'SELECT COUNT(*) AS n FROM insurance_claims c JOIN insurance_policies p ON p.id=c.insurance_policy_id WHERE p.client_id<>c.client_id',
  'policy_document_wrong_client'=>'SELECT COUNT(*) AS n FROM attachments a JOIN insurance_policies p ON p.id=a.insurance_policy_id WHERE a.attachable_id<>p.client_id',
 ];
 foreach($queries as $name=>$sql)$report['integrity'][$name]=(int)DB::selectOne($sql)->n;
 $report['active_users_by_role']=DB::table('users')->where('status','active')->selectRaw('role, COUNT(*) AS total')->groupBy('role')->get()->all();
 $report['policies_by_status']=DB::table('insurance_policies')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->get()->all();
 $report['templates_by_status']=DB::table('insurance_form_templates')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->get()->all();
 $report['document_files_missing']=0;
 foreach(DB::table('attachments')->select(['disk','path'])->get() as $a)if(!Storage::disk($a->disk)->exists($a->path))$report['document_files_missing']++;
 $report['runtime']=['environment'=>config('app.env'),'debug'=>config('app.debug'),'https_url'=>str_starts_with(config('app.url'),'https://'),'session_secure'=>config('session.secure'),'session_http_only'=>config('session.http_only'),'session_same_site'=>config('session.same_site'),'attachments_disk_visibility'=>config('filesystems.disks.attachments.visibility'),'public_storage_link'=>is_link(public_path('storage'))];
 $report['database_version']=DB::selectOne('SELECT VERSION() AS version')->version;
 $report['migrations']=DB::table('migrations')->count();
 echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}finally{$db->rollBack();}
