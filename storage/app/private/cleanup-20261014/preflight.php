<?php
$root = dirname(__DIR__, 4);
chdir($root);
require $root.'/vendor/autoload.php';
$config = Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
$connection = $config['DB_CONNECTION'] ?? 'mysql';
$db = $config['DB_DATABASE'] ?? '';
$host = $config['DB_HOST'] ?? '127.0.0.1';
if ($connection !== 'mysql' || $db !== 'r4_insurance' || !in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || !empty($config['DB_URL'])) {
    throw new RuntimeException('Configurazione inattesa: applicazione interrotta.');
}
$pdo = new PDO('mysql:host='.$host.';port='.($config['DB_PORT'] ?? '3306').';dbname='.$db.';charset=utf8mb4', $config['DB_USERNAME'] ?? '', $config['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$counts=[];
foreach ($tables as $table) {
    $counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM `'.str_replace('`', '``', $table).'`')->fetchColumn();
}
$retired=json_decode(file_get_contents('C:/Users/vince/Documents/Codex/2026-10-14/riprendiamo-il-gestionale-per-soluzioni-assicurative/work/cleanup-tables.json'),true);
$nonempty=array_filter(array_intersect_key($counts,array_flip($retired)),fn($count)=>$count>0);
$admins=$pdo->query("SELECT * FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_ASSOC);
file_put_contents(__DIR__.'/admin-backup.json', json_encode($admins,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
$identity=[];
foreach($admins as $admin) $identity[]=['id'=>$admin['id'],'fingerprint'=>hash('sha256',json_encode($admin))];
file_put_contents(__DIR__.'/preflight.json',json_encode(['database'=>$db,'counts'=>$counts,'admin_fingerprints'=>$identity,'retired_nonempty'=>$nonempty],JSON_PRETTY_PRINT));
echo json_encode(['database'=>$db,'table_count'=>count($tables),'admins'=>count($admins),'admin_backup_verified'=>json_decode(file_get_contents(__DIR__.'/admin-backup.json'),true)===$admins,'nonempty_tables'=>array_filter($counts),'retired_nonempty'=>$nonempty],JSON_PRETTY_PRINT)."\n";
if($nonempty) exit(2);
