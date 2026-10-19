<?php
$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$config = Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
$host = $config['DB_HOST'] ?? '127.0.0.1';
if (($config['DB_CONNECTION'] ?? '') !== 'mysql' || ($config['DB_DATABASE'] ?? '') !== 'r4_insurance'
    || ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || ! empty($config['DB_URL'])) {
    throw new RuntimeException('Configurazione inattesa.');
}
$pdo = new PDO('mysql:host='.$host.';port='.($config['DB_PORT'] ?? '3306').';dbname=r4_insurance;charset=utf8mb4', $config['DB_USERNAME'] ?? '', $config['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
$processes = $pdo->query("SELECT ID, COMMAND, TIME, STATE, INFO,
    CASE
        WHEN LOWER(INFO) LIKE '%key_column_usage%' OR LOWER(INFO) LIKE '%referential_constraints%' THEN 'controllo relazioni'
        WHEN LOWER(INFO) LIKE '%information_schema%' THEN 'controllo struttura'
        WHEN LOWER(INFO) LIKE 'drop table%' THEN 'rimozione tabella'
        WHEN LOWER(INFO) LIKE 'alter table%' THEN 'aggiornamento tabella'
        ELSE 'altra operazione'
    END AS operation
    FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND ID <> CONNECTION_ID()")->fetchAll(PDO::FETCH_ASSOC);
foreach ($processes as &$process) {
    if ($process['operation'] === 'controllo relazioni'
        && preg_match("/table_name\\s*=\\s*'([a-z0-9_]+)'/i", $process['INFO'] ?? '', $match)) {
        $process['table'] = $match[1];
    }
    unset($process['INFO']);
}
unset($process);
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$retired = json_decode(file_get_contents($root.'/tests/Fixtures/retired-table-names.json'), true, 512, JSON_THROW_ON_ERROR);
$remaining = array_values(array_intersect($retired, $tables));
$completed = (bool) $pdo->query("SELECT COUNT(*) FROM migrations WHERE migration='2026_10_14_120000_remove_retired_modules'")->fetchColumn();
$admins = $pdo->query("SELECT * FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_ASSOC);
$backup = json_decode(file_get_contents(__DIR__.'/admin-backup.json'), true, 512, JSON_THROW_ON_ERROR);
$saved = array_column($backup, null, 'id');
$identityUnchanged = count($admins) === count($backup);
foreach ($admins as $admin) {
    $original = $saved[$admin['id']] ?? [];
    foreach (['id', 'email', 'password', 'role', 'status'] as $field) {
        $identityUnchanged = $identityUnchanged && (($admin[$field] ?? null) === ($original[$field] ?? null));
    }
}
echo json_encode(['completed' => $completed, 'table_count' => count($tables), 'retired_tables_remaining' => count($remaining), 'admin_identity_unchanged' => $identityUnchanged, 'processes' => $processes], JSON_PRETTY_PRINT)."\n";
