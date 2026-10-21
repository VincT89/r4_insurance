<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class InsuranceBackup
{
    public function __construct(private BackupFiles $files) {}

    private function settings(): array
    {
        $settings=config('insurance-backup');
        foreach (['repository','password_file','work_directory'] as $key) if (!is_string($settings[$key] ?? null) || $settings[$key]==='') throw new RuntimeException('Configurare la destinazione, la chiave e la cartella privata del backup.');
        $roots=[base_path()];
        foreach ($settings['disks'] as $disk) {
            if (config("filesystems.disks.$disk.driver")!=='local') throw new RuntimeException('Questa procedura richiede archivi documentali locali.');
            $roots[]=$this->files->absolute(config("filesystems.disks.$disk.root"));
        }
        $settings['work_directory']=$this->files->external($settings['work_directory'],$roots);
        $settings['repository']=$this->files->external($settings['repository'],[...$roots,$settings['work_directory']]);
        $settings['password_file']=$this->files->external($settings['password_file'],[...$roots,$settings['work_directory'],$settings['repository']]);
        if (!is_dir($settings['work_directory']) || !is_readable($settings['password_file'])
            || strlen(trim(file_get_contents($settings['password_file'])))<20) throw new RuntimeException('Cartella privata o chiave di backup non disponibili.');
        return $settings;
    }

    private function process(array $args, string $label, array $environment=[], ?string $cwd=null, mixed $input=null): string
    {
        $process=new Process($args,$cwd,$environment,$input,config('insurance-backup.timeout_seconds',7200));
        $process->run();
        /** Native process errors can contain credentials, SQL rows or filenames: never echo their output. */
        if (!$process->isSuccessful()) throw new RuntimeException($label.' non riuscito (codice '.$process->getExitCode().').');
        return $process->getOutput();
    }

    private function restic(array $arguments, array $settings, ?string $cwd=null): string
    {
        return $this->process([$settings['restic_binary'],...$arguments], 'Operazione Restic', [
            'RESTIC_REPOSITORY'=>$settings['repository'], 'RESTIC_PASSWORD_FILE'=>$settings['password_file'],
            'RESTIC_PASSWORD'=>false, 'RESTIC_PASSWORD_COMMAND'=>false, 'RESTIC_REPOSITORY_FILE'=>false,
            'RESTIC_CACHE_DIR'=>$settings['work_directory'].'/cache',
        ],$cwd);
    }

    private function connection(): Connection
    {
        $connection=DB::connection();
        if ($connection->getDriverName()!=='mysql') throw new RuntimeException('Backup e ripristino supportano MySQL.');
        if (!$connection->getConfig('unix_socket') && !in_array($connection->getConfig('host'),['127.0.0.1','localhost','::1'],true)) {
            throw new RuntimeException('Questa configurazione supporta MySQL locale; definire TLS prima di usare un server remoto.');
        }
        return $connection;
    }

    private function mysqlArguments(Connection $connection): array
    {
        $cfg=$connection->getConfig();
        $args=['--no-defaults','--default-character-set=utf8mb4','--user='.$cfg['username']];
        if (!empty($cfg['unix_socket'])) $args[]='--socket='.$cfg['unix_socket'];
        else array_push($args,'--host='.$cfg['host'],'--port='.($cfg['port']??3306));
        return $args;
    }

    protected function createRestoreAccount(Connection $connection, string $database): array
    {
        if (!preg_match('/^r4_restore_[a-z0-9_]{8,40}$/D',$database)) throw new RuntimeException('Destinazione del ripristino non valida.');
        $account=['username'=>'r4restore_'.bin2hex(random_bytes(8)),'password'=>bin2hex(random_bytes(32)),'hosts'=>[]];
        $pdo=$connection->getPdo();
        try {
            foreach (['localhost','127.0.0.1','::1'] as $host) {
                /** Generated identifiers and PDO quoting keep credentials out of Laravel query logs and error messages. */
                $identity=$pdo->quote($account['username']).'@'.$pdo->quote($host);
                $pdo->exec('CREATE USER '.$identity.' IDENTIFIED BY '.$pdo->quote($account['password']));
                $account['hosts'][]=$host;
                /** The SQL client can create and fill tables only in this new database; it cannot drop tables or access other databases. */
                /** Database-level GRANT treats underscores as wildcards unless they are escaped. */
                $grantDatabase=str_replace('_','\\_',$database);
                $pdo->exec('GRANT SELECT, INSERT, CREATE, ALTER, INDEX, REFERENCES, LOCK TABLES ON `'.$grantDatabase.'`.* TO '.$identity);
            }
        } catch (\Throwable) {
            $this->dropRestoreAccount($connection,$account);
            throw new RuntimeException('Impossibile predisporre l’accesso temporaneo limitato al database di ripristino. Verificare i permessi amministrativi del backup.');
        }
        return $account;
    }

    protected function dropRestoreAccount(Connection $connection, array $account): void
    {
        $pdo=$connection->getPdo(); $failed=false;
        foreach ($account['hosts'] as $host) {
            try { $pdo->exec('DROP USER '.$pdo->quote($account['username']).'@'.$pdo->quote($host)); }
            catch (\Throwable) { $failed=true; }
        }
        if ($failed) throw new RuntimeException('Rimozione dell’accesso temporaneo non riuscita: verificare l’utente '.$account['username'].' prima di ripetere il collaudo.');
    }

    protected function importDatabase(Connection $connection, string $database, string $path): void
    {
        $settings=$this->settings();
        $stream=fopen($path,'rb');
        if ($stream===false) throw new RuntimeException('Esportazione del database non leggibile.');
        $account=null;
        try {
            $account=$this->createRestoreAccount($connection,$database);
            $arguments=array_values(array_filter($this->mysqlArguments($connection),fn($argument)=>!str_starts_with($argument,'--user=')));
            /** MariaDB binary mode and one-database together silently skip the dump; a temporary database-scoped account provides actual isolation instead. */
            $this->process([$settings['mysql_binary'],...$arguments,'--user='.$account['username'],'--binary-mode','--local-infile=0','--database='.$database],
                'Ripristino database',['MYSQL_PWD'=>$account['password']],null,$stream);
        } finally {
            fclose($stream);
            if ($account!==null) $this->dropRestoreAccount($connection,$account);
        }
    }

    public function initialize(): void
    {
        $s=$this->settings();
        if (is_dir($s['repository']) && count(scandir($s['repository']))>2) throw new RuntimeException('La destinazione contiene già dei dati.');
        $this->restic(['init','--repository-version','2'],$s);
    }

    public function check(): void
    {
        $this->restic(['check','--read-data'],$this->settings());
    }

    public function fingerprints(Connection $connection): array
    {
        $schema=$connection->getSchemaBuilder(); $result=[];
        /** Schema discovery must never include other databases accessible to the same account. */
        foreach ($schema->getTables($schema->getCurrentSchemaListing()) as $table) {
            $name=$table['name'];
            $primary=collect($schema->getIndexes($name))->firstWhere('primary',true)['columns']??[];
            if (!$primary) throw new RuntimeException('Tabelle senza chiave primaria: definire una procedura di verifica.');
            $hash=hash_init('sha256'); $rows=0; $query=$connection->table($name);
            foreach ($primary as $column) $query->orderBy($column);
            $query->chunk(1000,function ($items) use (&$rows,$hash) {
                foreach ($items as $row) { $values=(array)$row; ksort($values); hash_update($hash,json_encode($values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n"); $rows++; }
            });
            $result[$name]=['rows'=>$rows,'sha256'=>hash_final($hash)];
        }
        ksort($result);
        return $result;
    }

    private function assertSupportedSchema(Connection $db): void
    {
        $name=$db->getDatabaseName();
        foreach (['VIEWS'=>'TABLE_SCHEMA','TRIGGERS'=>'TRIGGER_SCHEMA','ROUTINES'=>'ROUTINE_SCHEMA','EVENTS'=>'EVENT_SCHEMA'] as $table=>$column) {
            if ($db->table('information_schema.'.$table)->where($column,$name)->exists()) throw new RuntimeException('Lo schema contiene oggetti SQL aggiuntivi: verificare prima la procedura di ripristino.');
        }
        if ($db->table('information_schema.TABLES')->where('TABLE_SCHEMA',$name)->where('ENGINE','!=','InnoDB')->exists()) throw new RuntimeException('Richieste tabelle InnoDB per un backup coerente.');
    }

    public function create(bool $writersStopped): array
    {
        if (!$writersStopped || !app()->isDownForMaintenance()) throw new RuntimeException('Attivare la manutenzione, sospendere scheduler e worker e attendere la fine delle richieste prima del backup.');
        $s=$this->settings(); $db=$this->connection(); $this->assertSupportedSchema($db);
        $lock=fopen($s['work_directory'].'/operation.lock','c');
        if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Un backup è già in corso.');
        $payload=$s['work_directory'].'/payload'; $created=false; $start=microtime(true);
        try {
            if (file_exists($payload)) throw new RuntimeException('Cartella temporanea già presente: controllare la precedente interruzione prima di proseguire.');
            if (!mkdir($payload,0700)) throw new RuntimeException('Cartella di lavoro non disponibile.');
            $created=true;
            $this->restic(['snapshots','--json'],$s);
            $before=$this->fingerprints($db);
            $dumpVersion=$this->process([$s['dump_binary'],'--version'],'Controllo versione mysqldump');
            $vendorOptions=str_contains($dumpVersion,'MariaDB')?[]:['--set-gtid-purged=OFF','--column-statistics=0'];
            $this->process([$s['dump_binary'],...$this->mysqlArguments($db),'--single-transaction','--quick','--no-tablespaces',
                ...$vendorOptions,'--hex-blob','--skip-comments','--skip-add-drop-table','--skip-triggers',
                '--result-file='.$payload.'/database.sql',$db->getDatabaseName()],
                'Esportazione database',['MYSQL_PWD'=>(string)$db->getConfig('password')]);
            chmod($payload.'/database.sql',0600);
            $recovery=['key'=>config('app.key'),'previous_keys'=>config('app.previous_keys'),'cipher'=>config('app.cipher'),
                'probe'=>app('encrypter')->encryptString('r4-insurance-backup-v1')];
            $this->json($payload.'/recovery.json',$recovery);
            $handle=fopen($payload.'/files.jsonl','xb'); $count=0; $bytes=0;
            try {
                foreach ($s['disks'] as $disk) {
                    $copy=$this->files->copyTree(config("filesystems.disks.$disk.root"),$payload.'/disks/'.$disk,$handle,'disks/'.$disk,true);
                    $count+=$copy['count']; $bytes+=$copy['bytes'];
                }
            } finally { fclose($handle); }
            $this->assertDocumentReferences($db,$payload,$s['disks']);
            $after=$this->fingerprints($db);
            if ($before!==$after || !app()->isDownForMaintenance()) throw new RuntimeException('Il database è cambiato durante il backup: nessuna copia accettata.');
            $manifest=['format'=>1,'created_at'=>now()->toIso8601String(),'source_database'=>$db->getDatabaseName(),
                'tables'=>$after,'files'=>$count,'file_bytes'=>$bytes,'disks'=>$s['disks'],
                'composer_lock_sha256'=>hash_file('sha256',base_path('composer.lock')),
                'database_sha256'=>hash_file('sha256',$payload.'/database.sql'),
                'recovery_sha256'=>hash_file('sha256',$payload.'/recovery.json'),
                'files_sha256'=>hash_file('sha256',$payload.'/files.jsonl')];
            $this->json($payload.'/manifest.json',$manifest);
            $this->files->verify($payload,$manifest);
            $output=$this->restic(['backup','--json','--tag','r4-insurance','--compression','auto','.'],$s,$payload);
            /** Reject a copy if linked documents changed while Restic was reading them. */
            $this->files->verify($payload,$manifest);
            $summary=null;
            foreach (explode("\n",trim($output)) as $line) {
                $message=json_decode($line,true);
                if (($message['message_type']??'')==='summary') $summary=$message;
            }
            if (!preg_match('/^[a-f0-9]{64}$/D',$summary['snapshot_id']??'')) throw new RuntimeException('Identificativo della copia non confermato.');
            $result=['snapshot'=>$summary['snapshot_id'],'completed_at'=>now()->toIso8601String(),'tables'=>count($after),
                'files'=>$count,'file_bytes'=>$bytes,'database_bytes'=>filesize($payload.'/database.sql'),
                'seconds'=>round(microtime(true)-$start,2),'added_bytes'=>$summary['data_added_packed']??null];
            $this->json($s['work_directory'].'/last-backup.json',$result);
            return $result;
        } finally {
            try { if ($created) $this->files->removePayload($s['work_directory']); }
            finally { flock($lock,LOCK_UN); fclose($lock); }
        }
    }

    private function assertDocumentReferences(Connection $db,string $payload,array $disks): void
    {
        $check=function(string $disk,string $path,?string $hash=null) use($payload,$disks) {
            if (!in_array($disk,$disks,true)) throw new RuntimeException('Un documento utilizza un archivio non incluso nel backup.');
            $full=$payload.'/disks/'.$disk.'/'.$this->files->relative($path);
            if (!is_file($full) || ($hash && !hash_equals($hash,(string)hash_file('sha256',$full)))) throw new RuntimeException('Un documento collegato al database manca o è alterato.');
        };
        foreach ($db->table('attachments')->orderBy('id')->lazyById(500) as $file) $check($file->disk,$file->path);
        foreach ($db->table('agency_communication_attachments')->orderBy('id')->lazyById(500) as $file) $check('attachments',$file->path,$file->sha256);
        foreach ($db->table('insurance_form_documents as f')->leftJoin('attachments as a',function($join) {
            $join->on('a.insurance_form_document_id','=','f.id')->where('a.review_status','generated');
        })->whereNotNull('f.pdf_hash')->select('f.id','f.pdf_hash','a.disk','a.path')->orderBy('f.id')->lazy(500) as $file) {
            if (!$file->path) throw new RuntimeException('Un modulo generato non ha il PDF collegato.');
            $check($file->disk,$file->path,$file->pdf_hash);
        }
        foreach ($db->table('clients')->whereNotNull('logo_path')->orderBy('id')->lazyById(500) as $client) $check('public',$client->logo_path);
    }

    public function restore(string $snapshot,string $target,string $database): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$snapshot) || !preg_match('/^r4_restore_[a-z0-9_]{8,40}$/D',$database)) throw new RuntimeException('Richiesti un identificativo completo e un nuovo database r4_restore_ID.');
        $s=$this->settings(); $source=$this->connection();
        if ($database===$source->getDatabaseName()) throw new RuntimeException('Il database operativo non è una destinazione di ripristino.');
        $excluded=[base_path(),$s['repository'],$s['work_directory'],dirname($s['password_file']).'/'.basename($s['password_file'])];
        foreach ($s['disks'] as $disk) $excluded[]=config("filesystems.disks.$disk.root");
        $target=$this->files->external($target,$excluded);
        if (file_exists($target)) throw new RuntimeException('La cartella di ripristino deve essere nuova.');
        if ($source->table('information_schema.SCHEMATA')->where('SCHEMA_NAME',$database)->exists()) throw new RuntimeException('Il database di ripristino esiste già.');
        if (!is_dir(dirname($target))) throw new RuntimeException('La cartella superiore della destinazione deve esistere ed essere privata.');
        mkdir($target,0700); $start=microtime(true);
        $this->restic(['restore',$snapshot,'--target',$target,'--overwrite','never','--verify'],$s);
        $manifest=json_decode(file_get_contents($target.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
        if (($manifest['format']??null)!==1) throw new RuntimeException('Versione del backup non riconosciuta.');
        $verified=$this->files->verify($target,$manifest);
        $recovery=json_decode(file_get_contents($target.'/recovery.json'),true,512,JSON_THROW_ON_ERROR);
        $decode=fn(string $key)=>str_starts_with($key,'base64:')?base64_decode(substr($key,7),true):$key;
        $encrypter=new Encrypter($decode($recovery['key']),$recovery['cipher']);
        $encrypter->previousKeys(array_map($decode,$recovery['previous_keys']??[]));
        if ($encrypter->decryptString($recovery['probe'])!=='r4-insurance-backup-v1') throw new RuntimeException('Chiave del gestionale non recuperabile.');
        /** A restored database is always new; no DROP, overwrite, automatic switch or outbound job is executed. */
        $source->statement("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->importDatabase($source,$database,$target.'/database.sql');
        config(['database.connections.insurance_restore'=>array_replace($source->getConfig(),['database'=>$database,'url'=>null])]);
        DB::purge('insurance_restore');
        try {
            $restored=DB::connection('insurance_restore');
            if ($this->fingerprints($restored)!==$manifest['tables']) throw new RuntimeException('I dati ripristinati non corrispondono al backup.');
            $this->assertDocumentReferences($restored,$target,$manifest['disks']);
            $foreignKeys=$this->verifyRelations($restored);
            $encrypted=$this->verifyEncryption($restored,$encrypter);
            $result=['snapshot'=>$snapshot,'database'=>$database,'verified_at'=>now()->toIso8601String(),
                'tables'=>count($manifest['tables']),'files'=>$verified['count'],'file_bytes'=>$verified['bytes'],
                'foreign_keys'=>$foreignKeys,'encrypted_values'=>$encrypted,'seconds'=>round(microtime(true)-$start,2)];
            $this->json($target.'/verification.json',$result);
            $this->json($s['work_directory'].'/last-restore-test.json',$result);
            return $result;
        } finally { DB::purge('insurance_restore'); }
    }

    private function verifyRelations(Connection $db): int
    {
        $count=0; $schema=$db->getSchemaBuilder();
        foreach ($schema->getTables($schema->getCurrentSchemaListing()) as $table) foreach ($schema->getForeignKeys($table['name']) as $fk) {
            $query=$db->table($table['name'].' as child');
            foreach ($fk['columns'] as $column) $query->whereNotNull('child.'.$column);
            $query->whereNotExists(function($sub) use($fk) {
                $sub->selectRaw('1')->from($fk['foreign_table'].' as parent');
                foreach ($fk['columns'] as $index=>$column) $sub->whereColumn('parent.'.$fk['foreign_columns'][$index],'child.'.$column);
            });
            if ($query->exists()) throw new RuntimeException('Collegamenti incoerenti nel database ripristinato.');
            $count++;
        }
        return $count;
    }

    private function verifyEncryption(Connection $db,Encrypter $encrypter): int
    {
        $count=0;
        foreach (['AgencyCommunication','AgencyCommunicationAttachment','InsuranceClaim','InsuranceClaimActivity','InsuranceClaimEvent',
            'InsuranceFormPackage','InsuranceFormDocument','InsurancePolicyChange','InsuranceQuoteCase','InsuranceQuoteEmail','OutboundMailSetting'] as $name) {
            $class='App\\Models\\'.$name; $model=new $class;
            foreach ($model->getCasts() as $column=>$cast) if (str_starts_with($cast,'encrypted')) {
                foreach ($db->table($model->getTable())->whereNotNull($column)->orderBy($model->getKeyName())->lazy(500) as $row) {
                    $encrypter->decryptString($row->{$column}); $count++;
                }
            }
        }
        return $count;
    }

    private function json(string $path,array $data): void
    {
        if (file_put_contents($path,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Scrittura del manifest non riuscita.');
        chmod($path,0600);
    }
}
