<?php

namespace Tests\Feature;

use App\Services\{BackupFiles, InsuranceBackup};
use Tests\TestCase;

class BackupSafetyTest extends TestCase
{
    private string $directory;
    private BackupFiles $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory=storage_path('framework/testing/backup-'.bin2hex(random_bytes(8)));
        mkdir($this->directory,0700,true);
        $this->files=new BackupFiles;
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory.'/payload')) $this->files->removePayload($this->directory);
        foreach (glob($this->directory.'/*') as $file) if (is_file($file)) unlink($file);
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_backup_requires_explicit_quiescence_and_maintenance_before_accessing_database(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Attivare la manutenzione');
        app(InsuranceBackup::class)->create(false);
    }

    public function test_restore_rejects_operational_database_names_before_any_process_runs(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('r4_restore_ID');
        app(InsuranceBackup::class)->restore(str_repeat('a',64),$this->directory.'/target','r4_insurance');
    }

    public function test_restore_requires_explicit_snapshot_and_never_chooses_latest_implicitly(): void
    {
        $this->expectException(\RuntimeException::class);
        app(InsuranceBackup::class)->restore('latest',$this->directory.'/target','r4_restore_20261020a');
    }

    public function test_relative_paths_reject_traversal_and_windows_alternate_streams(): void
    {
        foreach (['../secret','files/../../secret','C:\\secret','/secret','docs/./secret','docs/file.pdf:secret','docs\\..\\secret'] as $path) {
            try { $this->files->relative($path); $this->fail('Percorso accettato: '.$path); }
            catch (\RuntimeException $exception) { $this->assertStringContainsString('Percorso documento',$exception->getMessage()); }
        }
        $this->assertSame('client/1/firma.pdf',$this->files->relative('client\\1\\firma.pdf'));
    }

    public function test_external_paths_reject_source_descendants_and_ancestors(): void
    {
        foreach ([$this->directory.'/payload/repo',$this->directory] as $target) {
            try { $this->files->external($target,[$this->directory.'/payload']); $this->fail('Sovrapposizione accettata.'); }
            catch (\RuntimeException $exception) { $this->assertStringContainsString('separata',$exception->getMessage()); }
        }
        $this->assertNotEmpty($this->files->external($this->directory.'/backup',[$this->directory.'/source']));
    }

    private function payload(): array
    {
        $payload=$this->directory.'/payload';
        mkdir($payload.'/disks/attachments',0700,true);
        file_put_contents($payload.'/database.sql','synthetic database');
        file_put_contents($payload.'/recovery.json','{"test":true}');
        file_put_contents($payload.'/disks/attachments/firma.pdf','synthetic PDF');
        $entry=['path'=>'disks/attachments/firma.pdf','bytes'=>13,'sha256'=>hash('sha256','synthetic PDF')];
        $entry['bytes']=strlen('synthetic PDF');
        file_put_contents($payload.'/files.jsonl',json_encode($entry)."\n");
        return ['files'=>1,'file_bytes'=>$entry['bytes'],'files_sha256'=>hash_file('sha256',$payload.'/files.jsonl'),
            'database_sha256'=>hash_file('sha256',$payload.'/database.sql'),'recovery_sha256'=>hash_file('sha256',$payload.'/recovery.json')];
    }

    public function test_restore_checks_file_contents_and_not_only_names_or_sizes(): void
    {
        $manifest=$this->payload();
        $this->assertSame(['count'=>1,'bytes'=>13],$this->files->verify($this->directory.'/payload',$manifest));
        file_put_contents($this->directory.'/payload/disks/attachments/firma.pdf','changed!! PDF');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('non corrisponde');
        $this->files->verify($this->directory.'/payload',$manifest);
    }

    public function test_missing_document_prevents_success(): void
    {
        $manifest=$this->payload();
        unlink($this->directory.'/payload/disks/attachments/firma.pdf');
        $this->expectException(\RuntimeException::class);
        $this->files->verify($this->directory.'/payload',$manifest);
    }

    public function test_modified_database_dump_prevents_restore(): void
    {
        $manifest=$this->payload();
        file_put_contents($this->directory.'/payload/database.sql','changed database');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('incompleto o alterato');
        $this->files->verify($this->directory.'/payload',$manifest);
    }

    public function test_manifest_cannot_traverse_outside_restore_directory(): void
    {
        $manifest=$this->payload();
        file_put_contents($this->directory.'/payload/files.jsonl',json_encode(['path'=>'../secret','bytes'=>1,'sha256'=>str_repeat('a',64)])."\n");
        $manifest['files_sha256']=hash_file('sha256',$this->directory.'/payload/files.jsonl');
        $this->expectException(\RuntimeException::class);
        $this->files->verify($this->directory.'/payload',$manifest);
    }

    public function test_capacity_seeder_refuses_normal_test_database(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('collaudo vuoto');
        (new \Database\Seeders\CapacitySeeder)->run();
    }

    public function test_fingerprints_exclude_unrelated_databases_and_detect_changed_values(): void
    {
        $connection=new \Illuminate\Database\SQLiteConnection(new \PDO('sqlite::memory:'),':memory:');
        $connection->statement('CREATE TABLE backup_rows (id INTEGER PRIMARY KEY, value TEXT)');
        $connection->table('backup_rows')->insert([['id'=>2,'value'=>'second'],['id'=>1,'value'=>'first']]);
        $connection->statement("ATTACH DATABASE ':memory:' AS unrelated");
        $connection->statement('CREATE TABLE unrelated.outside_backup (value TEXT)');
        $connection->statement("INSERT INTO unrelated.outside_backup VALUES ('excluded')");
        $service=app(InsuranceBackup::class);
        $before=$service->fingerprints($connection);
        $this->assertSame(['backup_rows'],array_keys($before));
        $this->assertSame(2,$before['backup_rows']['rows']);
        $connection->statement("UPDATE unrelated.outside_backup SET value='changed elsewhere'");
        $this->assertSame($before,$service->fingerprints($connection));
        $connection->table('backup_rows')->where('id',1)->update(['value'=>'changed']);
        $after=$service->fingerprints($connection);
        $this->assertSame(2,$after['backup_rows']['rows']);
        $this->assertNotSame($before['backup_rows']['sha256'],$after['backup_rows']['sha256']);
    }

    public function test_staging_cleanup_preserves_original_documents_when_linking_is_enabled(): void
    {
        $source=$this->directory.'/source';
        mkdir($source,0700);
        file_put_contents($source.'/original.pdf','synthetic document');
        $permissions=fileperms($source.'/original.pdf');
        $handle=fopen($this->directory.'/files.jsonl','xb');
        try {
            $result=$this->files->copyTree($source,$this->directory.'/payload/disks/attachments',$handle,'disks/attachments',true);
            fclose($handle); $handle=null;
            $this->assertSame(1,$result['count']);
            $this->assertSame(hash_file('sha256',$source.'/original.pdf'),hash_file('sha256',$this->directory.'/payload/disks/attachments/original.pdf'));
            $this->files->removePayload($this->directory);
            clearstatcache();
            $this->assertSame('synthetic document',file_get_contents($source.'/original.pdf'));
            $this->assertSame($permissions,fileperms($source.'/original.pdf'));
        } finally {
            if (is_resource($handle)) fclose($handle);
            unlink($source.'/original.pdf');
            rmdir($source);
        }
    }

    public function test_partial_restore_account_creation_cleans_up_and_hides_native_diagnostics(): void
    {
        $statements=[];
        $pdo=$this->createMock(\PDO::class);
        $pdo->method('quote')->willReturnCallback(fn($value)=>"'".$value."'");
        $pdo->method('exec')->willReturnCallback(function($sql) use (&$statements) {
            $statements[]=preg_replace("/ IDENTIFIED BY '.+'$/",' IDENTIFIED BY [redacted]',$sql);
            if (str_starts_with($sql,'GRANT ') && str_contains($sql,"@'127.0.0.1'")) throw new \PDOException('private-native-diagnostic');
            return 0;
        });
        $connection=new \Illuminate\Database\Connection($pdo);
        $service=new class(new BackupFiles) extends InsuranceBackup {
            public function prepare(\Illuminate\Database\Connection $connection): array { return $this->createRestoreAccount($connection,'r4_restore_20261021x'); }
        };
        try { $service->prepare($connection); $this->fail('Errore nella concessione dei permessi ignorato.'); }
        catch (\RuntimeException $exception) {
            $this->assertStringContainsString('accesso temporaneo',$exception->getMessage());
            $this->assertStringNotContainsString('private-native-diagnostic',$exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $created=[]; $removed=[];
        foreach ($statements as $statement) {
            if (preg_match("/^CREATE USER (.+) IDENTIFIED BY /",$statement,$match)) $created[]=$match[1];
            if (str_starts_with($statement,'DROP USER ')) $removed[]=substr($statement,10);
            if (str_starts_with($statement,'GRANT ')) {
                $this->assertStringContainsString('ON `r4\\_restore\\_20261021x`.* TO ',$statement);
                $this->assertDoesNotMatchRegularExpression('/ALL PRIVILEGES|GRANT OPTION|FILE|DROP|DELETE|UPDATE/',$statement);
            }
        }
        $this->assertCount(2,$created);
        $this->assertSame($created,$removed);
    }
}
