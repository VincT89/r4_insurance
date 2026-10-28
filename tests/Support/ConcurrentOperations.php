<?php

namespace Tests\Support;

use App\Enums\UserRole;
use App\Models\{AgencyCommunication, AuditLog, Client, InsuranceBranch, InsuranceCollection, InsurancePolicy, InsuranceTitle, SupplierAgency, SupplierCompany, User};
use App\Services\{AgencyCollectionEmailWorkflow, AgencyEmailTransport, AgencyEmailWorkflow, AgencyMailConfiguration, CollectionCommunicationReminders, DeadlineReminders, InsuranceLedger};
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class ConcurrentMailConfiguration extends AgencyMailConfiguration
{
    public function ready(): bool { return true; }
    public function sender(): array { return ['address'=>'sender@example.test', 'name'=>'Collaudo isolato']; }
    public function transport(): array { return ['transport'=>'array']; }
}

final class ConcurrentMailTransport extends AgencyEmailTransport
{
    public function __construct(private string $workspace, private ?string $signal=null, private ?string $resume=null) {}
    public function send(array $payload, array $configuration): string
    {
        /** Store only synthetic identifiers: the real SMTP implementation is never invoked. */
        file_put_contents($this->workspace.'/mail.jsonl', json_encode(['pid'=>getmypid(), 'kind'=>$payload['kind'] ?? 'policies',
            'collections'=>array_column($payload['collections'] ?? [], 'id'), 'policies'=>$payload['policy_ids'] ?? [],
            'attachments'=>count($payload['_attachments'] ?? [])], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
        if ($this->signal) touch($this->signal);
        if ($this->resume) ConcurrentOperations::waitFile($this->resume);
        usleep(400000);
        return Str::uuid().'@example.test';
    }
}

final class ConcurrentOperations
{
    private array $users;
    private Client $client;
    private InsuranceBranch $branch;
    private SupplierCompany $company;
    private SupplierAgency $agency;
    private array $results = [];
    private int $sequence = 0;
    private string $run;
    private ?string $filter = null;

    public function __construct(private string $workspace, private string $database) {}

    public static function waitFile(string $path): void
    {
        $limit = microtime(true) + 40;
        do {
            clearstatcache(true, $path);
            if (is_file($path)) return;
            usleep(10000);
        } while (microtime(true) < $limit);
        throw new \RuntimeException('Barriera non raggiunta: '.basename($path));
    }

    public static function worker(string $workspace, string $run, string $slot): void
    {
        if (!preg_match('/^run-[a-f0-9]{16}$/D', $run) || !preg_match('/^job-\d+-\d+$/D', $slot)) throw new \RuntimeException('Job non valido.');
        $directory = $workspace.'/'.$run;
        $job = json_decode(file_get_contents($directory.'/'.$slot.'.json'), true, 512, JSON_THROW_ON_ERROR);
        if (isset($job['transport_signal'])) app()->instance(AgencyEmailTransport::class,new ConcurrentMailTransport($workspace,$directory.'/'.$job['transport_signal'],isset($job['transport_resume']) ? $directory.'/'.$job['transport_resume'] : null));
        $user = User::findOrFail($job['user']);
        Auth::setUser($user);
        $started = null;
        try {
            $paused = false;
            DB::listen(function ($query) use ($job, $directory, &$paused) {
                if (!$paused && isset($job['pause_query']) && str_contains($query->sql, $job['pause_query'])
                    && (!($job['locking'] ?? false) || str_contains($query->sql, 'for update'))) {
                    $paused = true;
                    touch($directory.'/'.$job['signal']);
                    if (isset($job['pause_ms'])) usleep($job['pause_ms']*1000);
                    else self::waitFile($directory.'/'.$job['resume']);
                }
            });
            touch($directory.'/'.$slot.'.ready');
            self::waitFile($directory.'/'.$job['barrier']);
            if (isset($job['await'])) self::waitFile($directory.'/'.$job['await']);
            $started = microtime(true);
            $responses = [];
            if (in_array($job['operation'] ?? 'http', ['reminders','communication-reminders'], true)) {
                if ($job['operation']==='reminders') app(DeadlineReminders::class)->forUser($user);
                app(CollectionCommunicationReminders::class)->forUser($user);
            } else {
                foreach ($job['requests'] as $spec) {
                    $files=[];
                    foreach ($spec['uploads'] ?? [] as $name) $files['files'][]=\Illuminate\Http\UploadedFile::fake()->createWithContent($name,"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
                    $request = Request::create('http://localhost'.$spec['uri'], $spec['method'] ?? 'GET', $spec['data'] ?? [], [], $files,
                        ['HTTP_ACCEPT'=>'application/json', 'HTTP_X_TABLE_EXPORT'=>($spec['export'] ?? false) ? '1' : '0']);
                    $kernel = app(Kernel::class);
                    $response = $kernel->handle($request);
                    $body = $response->getContent();
                    $entry = ['status'=>$response->getStatusCode(), 'actor'=>Auth::id(), 'bytes'=>strlen($body)];
                    if ($response->getStatusCode() >= 400) $entry['error'] = json_decode($body, true)['errors'] ?? json_decode($body, true)['message'] ?? 'HTTP error';
                    if (($spec['method'] ?? 'GET') === 'GET' && $response->isSuccessful()) {
                        $dom = new \DOMDocument;
                        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$body);
                        $xpath = new \DOMXPath($dom);
                        $entry['table_rows'] = $xpath->query('//table[contains(@class,"t-table")]/tbody/tr[td[2]]')->length;
                        $entry['selection'] = [];
                        foreach ($xpath->query('//*[@data-communication-item]') as $item) $entry['selection'][] = ['id'=>$item->getAttribute('value'), 'agency'=>$item->getAttribute('data-agency')];
                        $entry['selection_user'] = $xpath->query('//*[@data-selection-user]')->item(0)?->getAttribute('data-selection-user');
                        $entry['selection_scope'] = $xpath->query('//*[@data-selection-scope]')->item(0)?->getAttribute('data-selection-scope');
                        $entry['numbers'] = [];
                        foreach ($xpath->query('//table[contains(@class,"t-table")]/tbody/tr') as $row) {
                            preg_match_all('/QA-[a-f0-9]{8}-\d+/', $row->textContent, $matches);
                            array_push($entry['numbers'], ...$matches[0]);
                        }
                    }
                    $responses[] = $entry;
                    $kernel->terminate($request, $response);
                }
            }
            if (isset($job['completed'])) touch($directory.'/'.$job['completed']);
            $result = ['user'=>$user->id, 'pid'=>getmypid(), 'started'=>$started, 'finished'=>microtime(true), 'responses'=>$responses];
        } catch (\Throwable $exception) {
            $result = ['user'=>$user->id, 'pid'=>getmypid(), 'started'=>$started, 'finished'=>microtime(true), 'exception'=>$exception::class, 'message'=>$exception->getMessage()];
        } finally {
            Auth::forgetGuards();
            DB::disconnect();
        }
        file_put_contents($directory.'/'.$slot.'.result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function parallel(array $jobs): array
    {
        $round = ++$this->sequence;
        $directory = $this->workspace.'/'.$this->run;
        $processes = [];
        try {
            foreach ($jobs as $index=>$job) {
                $slot = "job-$round-$index";
                $job['barrier'] = 'go-'.$round;
                file_put_contents($directory.'/'.$slot.'.json', json_encode($job, JSON_THROW_ON_ERROR));
                $process = new Process([PHP_BINARY, base_path('scripts/concurrency-check.php'), 'worker', $this->workspace, $this->database, $this->run, $slot], base_path(), timeout:70);
                $process->start();
                $processes[$slot] = $process;
            }
            foreach ($processes as $slot=>$process) self::waitFile($directory.'/'.$slot.'.ready');
            touch($directory.'/go-'.$round);
            $results = [];
            foreach ($processes as $slot=>$process) {
                $process->wait();
                if (!$process->isSuccessful()) throw new \RuntimeException('Worker non riuscito: '.$process->getErrorOutput().$process->getOutput());
                $result = json_decode(file_get_contents($directory.'/'.$slot.'.result.json'), true, 512, JSON_THROW_ON_ERROR);
                if (isset($result['exception'])) throw new \RuntimeException($result['exception'].': '.$result['message']);
                $results[] = $result;
            }
            return $results;
        } finally {
            foreach ($processes as $process) if ($process->isRunning()) $process->stop(0);
        }
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) throw new \RuntimeException($message);
    }

    private function scenario(string $name, callable $callback): void
    {
        if ($this->filter && !str_contains($name,$this->filter)) return;
        try { $details = $callback(); $this->results[] = ['name'=>$name, 'passed'=>true, 'details'=>$details]; echo "PASS $name\n"; }
        catch (\Throwable $e) { $this->results[] = ['name'=>$name, 'passed'=>false, 'error'=>$e->getMessage()]; echo "FAIL $name: ".$e->getMessage()."\n"; }
    }

    private function job(int $actor, string $uri, string $method='GET', array $data=[]): array
    {
        return ['user'=>$this->users[$actor]->id, 'requests'=>[['uri'=>$uri, 'method'=>$method, 'data'=>$data]]];
    }

    private function readJob(): array { return $this->job(0, '/collections?month=2026-10'); }

    private function statuses(array $results): array
    {
        return array_map(fn ($result) => $result['responses'][0]['status'], $results);
    }

    private function policy(array $extra=[]): InsurancePolicy
    {
        static $number=0;
        return $this->client->policies()->create(array_replace(['number'=>'QA-'.substr($this->run,4,8).'-'.(++$number),
            'insurance_branch_id'=>$this->branch->id, 'category'=>$this->branch->name, 'supplier_company_id'=>$this->company->id,
            'company'=>$this->company->name, 'supplier_agency_id'=>$this->agency->id, 'starts_on'=>'2026-10-21', 'expires_on'=>'2027-10-21',
            'status'=>'active', 'premium'=>'100.00', 'payment_frequency'=>'Annuale', 'payment_method'=>'Bonifico a Intermediario'], $extra))->refresh();
    }

    private function title(?InsurancePolicy $policy=null): InsuranceTitle
    {
        $policy ??= $this->policy();
        $ledger = app(InsuranceLedger::class);
        $ledger->generate($policy, $ledger->previewFingerprint($policy,$ledger->plan($policy)));
        return $policy->titles()->firstOrFail();
    }

    private function payment(int $cents=10000): array
    {
        return ['received_on'=>'2026-10-21', 'amount'=>\App\Support\Money::decimal($cents), 'payment_method'=>'Bonifico a Intermediario',
            'recipient'=>'our_agency', 'document_type'=>'transfer', 'idempotency_key'=>(string) Str::uuid()];
    }

    private function paid(InsuranceTitle $title, int $cents): InsuranceCollection
    {
        $data = $this->payment($cents); unset($data['amount']);
        return app(InsuranceLedger::class)->collect($title, $data+['amount_cents'=>$cents,'consultant_id'=>$this->users[0]->id]);
    }

    private function values(InsurancePolicy $policy, string $notes): array
    {
        return $policy->only(['number','category','company','status','premium','payment_frequency','payment_method','supplier_agency_id'])
            + ['policy_revision'=>$policy->editFingerprint(),'starts_on'=>'2026-10-21','expires_on'=>'2027-10-21','notes'=>$notes];
    }

    public function run(?string $filter=null): int
    {
        $this->filter=$filter;
        $this->run = 'run-'.bin2hex(random_bytes(8));
        mkdir($this->workspace.'/'.$this->run,0700);
        $this->users = [];
        foreach (['Marta Bellandi','Luca Ferrandi','Elena Montesi'] as $i=>$name) {
            $this->users[] = User::factory()->create(['name'=>$name, 'email'=>$this->run.'-'.$i.'@example.test',
                'role'=>$i===0 ? UserRole::Admin : UserRole::Operator, 'password'=>password_hash(bin2hex(random_bytes(32)),PASSWORD_BCRYPT,['cost'=>4])]);
        }
        Auth::setUser($this->users[0]);
        $this->client = Client::factory()->create(['name'=>'Alessio Valdieri','client_type'=>'person']);
        $this->branch = InsuranceBranch::create(['name'=>'RC auto '.$this->run,'name_key'=>\App\Services\InsuranceCatalog::key('RC auto '.$this->run),'status'=>'active']);
        $this->company = SupplierCompany::create(['name'=>'Compagnia Valdoro '.$this->run,'status'=>'active']);
        $this->agency = SupplierAgency::create(['name'=>'Agenzia Rivaforte '.$this->run,'email'=>'rivaforte@example.test','status'=>'active']);
        $this->agency->companies()->attach($this->company);

        $this->scenario('same_balance_two_operators', function () {
            $title=$this->title();
            $results=$this->parallel([$this->readJob(),$this->job(1,"/titles/$title->id/collections",'POST',$this->payment()),$this->job(2,"/titles/$title->id/collections",'POST',$this->payment())]);
            $statuses=$this->statuses($results); sort($statuses);
            $this->check($statuses===[200,302,422], json_encode($results));
            $this->check($title->fresh()->collected_cents===10000 && $title->collections()->count()===1,'Saldo o doppio incasso errato.');
            $payment=$title->collections()->sole();
            $this->check(in_array($payment->created_by,[$this->users[1]->id,$this->users[2]->id]) && AuditLog::where('action','collection_recorded')->where('new_values->insurance_collection_id',$payment->id)->value('user_id')===$payment->created_by,'Autore non coerente con il log.');
            return $results;
        });
        $this->scenario('partial_payments_same_title', function () {
            $title=$this->title();
            $results=$this->parallel([$this->readJob(),$this->job(1,"/titles/$title->id/collections",'POST',$this->payment(4000)),$this->job(2,"/titles/$title->id/collections",'POST',$this->payment(6000))]);
            $this->check($this->statuses($results)===[200,302,302] && $title->fresh()->collected_cents===10000 && $title->collections()->count()===2,json_encode($results));
            return $results;
        });
        $this->scenario('duplicate_submission', function () {
            $title=$this->title(); $data=$this->payment(); $job=$this->job(1,"/titles/$title->id/collections",'POST',$data);
            $results=$this->parallel([$this->readJob(),$job,$job]);
            $this->check($this->statuses($results)===[200,302,302] && $title->collections()->count()===1,json_encode($results));
            return $results;
        });
        $this->scenario('two_policy_edits', function () {
            $policy=$this->policy(); $uri="/clients/{$policy->client_id}/policies/$policy->id";
            $results=$this->parallel([$this->readJob(),$this->job(1,$uri,'PATCH',$this->values($policy,'Nota primo operatore')),$this->job(2,$uri,'PATCH',$this->values($policy,'Nota secondo operatore'))]);
            $statuses=$this->statuses($results); sort($statuses);
            $this->check($statuses===[200,302,422],json_encode($results));
            return $results;
        });
        $this->scenario('two_operators_issue_the_same_approved_quote', function () {
            $case=$this->client->quoteCases()->create(['insurance_branch_id'=>$this->branch->id,'subject'=>'Copertura veicolo','purpose'=>'new','status'=>'approved']);
            $quote=$case->quotes()->create(['supplier_company_id'=>$this->company->id,'supplier_agency_id'=>$this->agency->id,'status'=>'approved',
                'requested_at'=>now()->subDay(),'received_at'=>now(),'approved_on'=>today(),'approved_by'=>$this->users[0]->id,
                'starts_on'=>'2026-10-21','expires_on'=>'2027-10-21','valid_until'=>'2026-11-01','premium_cents'=>10000,'payment_frequency'=>'Annuale']);
            foreach(['quote','quote_approval'] as $category)app(\App\Services\ClientDocumentRegistry::class)->upload($this->client,
                \Illuminate\Http\UploadedFile::fake()->createWithContent($category.'.pdf',"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"),
                ['document_category'=>$category,'insurance_quote_id'=>$quote->id,'insurance_quote_case_id'=>$case->id]);
            $data=['client_id'=>$this->client->id,'entry_mode'=>'new','insurance_quote_id'=>$quote->id,'category'=>$this->branch->name,
                'company'=>$this->company->name,'supplier_agency_id'=>$this->agency->id,'status'=>'active','starts_on'=>'2026-10-21','expires_on'=>'2027-10-21','payment_method'=>'Bonifico a Intermediario'];
            $results=$this->parallel([$this->readJob(),$this->job(1,'/policies','POST',$data+['number'=>'QA-'.substr($this->run,4,8).'-10001']),$this->job(2,'/policies','POST',$data+['number'=>'QA-'.substr($this->run,4,8).'-10002'])]);
            $statuses=$this->statuses($results);sort($statuses);
            $this->check($statuses===[200,302,422],json_encode($results));
            $policy=InsurancePolicy::where('insurance_quote_id',$quote->id)->sole();
            $this->check($quote->fresh()->status==='converted' && $policy->documents()->count()===2,'Preventivo o documenti non collegati alla nuova polizza.');
            return $results;
        });
        $this->scenario('payment_with_two_files_while_policy_is_edited', function () {
            $title=$this->title(); $policy=$title->policy->fresh();
            $payer=$this->job(1,"/titles/$title->id/collections",'POST',$this->payment());
            $payer['requests'][0]['uploads']=['contabile.pdf','ricevuta.pdf'];
            $payer+=['pause_query'=>'from `insurance_policies`','locking'=>true,'signal'=>'payment-policy-locked','pause_ms'=>700];
            $editor=$this->job(2,"/clients/{$policy->client_id}/policies/$policy->id",'PATCH',$this->values($policy,'Nota contemporanea all’incasso'));
            $editor+=['await'=>'payment-policy-locked'];
            $results=$this->parallel([$this->readJob(),$payer,$editor]);
            $this->check($this->statuses($results)===[200,302,422],json_encode($results));
            $this->check($title->collections()->count()===1 && $title->documents()->count()===2,'Incasso o allegati multipli non conservati.');
            return $results;
        });
        $this->scenario('double_reversal_and_reminders', function () {
            $title=$this->title(); $payment=$this->paid($title,10000);
            foreach ($this->users as $user) app(DeadlineReminders::class)->forUser($user);
            $results=$this->parallel([$this->readJob(),$this->job(1,"/collections/$payment->id/void",'POST',['reason'=>'Registrazione da stornare']),$this->job(2,"/collections/$payment->id/void",'POST',['reason'=>'Registrazione da stornare'])]);
            $this->check($this->statuses($results)===[200,302,302] && $title->fresh()->collected_cents===0,json_encode($results));
            $this->check(AuditLog::where('action','collection_voided')->where('new_values->insurance_collection_id',$payment->id)->count()===1,'Storno duplicato nello storico.');
            $jobs=array_map(fn($user)=>['user'=>$user->id,'operation'=>'reminders'],$this->users);
            $this->parallel($jobs);
            foreach ($this->users as $user) $this->check($user->visibleNotifications()->where('data->kind','title')->where('data->source_id',$title->id)->count()===1,'Avviso rata mancante o duplicato.');
            return $results;
        });
        $this->scenario('next_day_permissions', function () {
            $policy=$this->policy(); $policy->forceFill(['work_closed_at'=>now()->subDay(),'work_closed_by'=>$this->users[1]->id])->save();
            $uri="/clients/{$policy->client_id}/policies/$policy->id";
            $values=$this->values($policy,'Correzione amministratore');
            $results=$this->parallel([$this->job(0,$uri,'PATCH',$values+['correction_reason'=>'Verifica eseguita sul documento della compagnia']),$this->job(1,$uri,'PATCH',$values),$this->job(2,$uri,'DELETE')]);
            $this->check($this->statuses($results)===[302,403,403],json_encode($results));
            return $results;
        });
        $this->scenario('overlapping_communication_confirmations', function () {
            $policy=$this->policy(); $workflow=app(AgencyEmailWorkflow::class);
            $a=$workflow->prepare($this->users[0],$this->agency,collect([$policy]),[],'21/10/2026');
            $b=$workflow->prepare($this->users[0],$this->agency,collect([$policy]),[],'21/10/2026');
            $results=$this->parallel([$this->job(0,"/agency-communications/messages/$a->id/send",'POST',['reviewed'=>1]),$this->job(0,"/agency-communications/messages/$b->id/send",'POST',['reviewed'=>1]),$this->job(2,"/agency-communications/messages/$a->id/send",'POST',['reviewed'=>1])]);
            $statuses=$this->statuses($results); sort($statuses);
            $this->check($statuses===[302,403,422] && AgencyCommunication::whereIn('id',[$a->id,$b->id])->where('status','sent')->count()===1,json_encode($results));
            return $results;
        });
        $this->scenario('collection_review_changes_during_send', function () {
            $title=$this->title(); $payment=$this->paid($title,4000);
            $message=app(AgencyCollectionEmailWorkflow::class)->prepare($this->users[0],$this->agency,collect([$payment->fresh()->load('title.policy.client')]),[],'21/10/2026');
            $sender=$this->job(0,"/agency-communications/messages/$message->id/send",'POST',['reviewed'=>1]);
            /** Freeze the reviewed transaction after a consistent read, then let another connection commit the balance change. */
            $sender+=['pause_query'=>'select * from `agency_communication_attachments`', 'signal'=>'send-snapshot', 'resume'=>'payment-committed'];
            $operator=$this->job(1,"/titles/$title->id/collections",'POST',$this->payment(6000));
            $operator+=['await'=>'send-snapshot','completed'=>'payment-committed'];
            $results=$this->parallel([$sender,$operator,$this->job(2,"/titles/$title->id")]);
            $this->check($this->statuses($results)===[422,302,200] && $message->fresh()->status==='review',json_encode($results));
            return $results;
        });
        $this->scenario('policy_edit_while_confirmation_locks_agency', function () {
            $policy=$this->policy();
            $message=app(AgencyEmailWorkflow::class)->prepare($this->users[0],$this->agency,collect([$policy]),[],'21/10/2026');
            $sender=$this->job(0,"/agency-communications/messages/$message->id/send",'POST',['reviewed'=>1]);
            $sender+=['pause_query'=>'from `supplier_agencies`','locking'=>true,'signal'=>'agency-locked','pause_ms'=>700];
            $editor=$this->job(1,"/clients/{$policy->client_id}/policies/$policy->id",'PATCH',$this->values($policy,'Nota aggiunta dopo il controllo dell’invio'));
            $editor+=['await'=>'agency-locked'];
            $results=$this->parallel([$sender,$editor,$this->job(2,"/clients/{$policy->client_id}/policies/$policy->id")]);
            $this->check($this->statuses($results)===[302,302,200],json_encode($results));
            return $results;
        });
        $this->scenario('reversal_while_reviewed_email_is_being_delivered', function () {
            $title=$this->title();
            foreach($this->users as $user)app(DeadlineReminders::class)->forUser($user);
            $payment=$this->paid($title,10000);
            $message=app(AgencyCollectionEmailWorkflow::class)->prepare($this->users[0],$this->agency,collect([$payment->fresh()->load('title.policy.client')]),[],'21/10/2026');
            $sender=$this->job(0,"/agency-communications/messages/$message->id/send",'POST',['reviewed'=>1]);
            $sender+=['transport_signal'=>'delivery-started','transport_resume'=>'reversal-done'];
            $operator=$this->job(1,"/collections/$payment->id/void",'POST',['reason'=>'Rettifica richiesta dopo il controllo']);
            $operator+=['await'=>'delivery-started','completed'=>'reversal-done'];
            $results=$this->parallel([$sender,$operator,$this->job(2,"/titles/$title->id")]);
            $this->check($this->statuses($results)===[302,302,200] && $message->fresh()->status==='sent' && $payment->fresh()->voided_at!==null,json_encode($results));
            $this->check($this->users[0]->notifications()->where('type',CollectionCommunicationReminders::REVERSAL_TYPE)->where('data->url',route('agency-communications.show',$message->id,false))->count()===1,'Avviso di storno dopo comunicazione mancante.');
            foreach($this->users as $user)$this->check($user->visibleNotifications()->where('data->kind','title')->where('data->source_id',$title->id)->whereNull('read_at')->count()===1,'Rata non riaperta nelle notifiche.');
            return $results;
        });
        $this->scenario('notification_creation_same_admin', function () {
            $this->paid($this->title(),10000);
            $job=['user'=>$this->users[0]->id,'operation'=>'reminders'];
            $results=$this->parallel([$job,$job,$job]);
            $this->check($this->users[0]->notifications()->where('type',CollectionCommunicationReminders::TYPE)->where('data->agency_id',$this->agency->id)->count()===1,'Promemoria cumulativo duplicato.');
            return $results;
        });
        $this->scenario('older_notification_refresh_cannot_overwrite_newer_balance', function () {
            $title=$this->title();
            app(CollectionCommunicationReminders::class)->forUser($this->users[0]);
            $this->paid($title,4000);
            $reader=['user'=>$this->users[0]->id,'operation'=>'communication-reminders',
                'pause_query'=>'select * from `notifications`','signal'=>'old-reminder-read','pause_ms'=>1500];
            $payer=$this->job(1,"/titles/$title->id/collections",'POST',$this->payment(6000));
            $payer+=['await'=>'old-reminder-read','completed'=>'new-payment-written'];
            $fresh=['user'=>$this->users[0]->id,'operation'=>'communication-reminders','await'=>'new-payment-written','completed'=>'new-reminder-written'];
            $results=$this->parallel([$reader,$payer,$fresh]);
            $before=$this->users[0]->notifications()->where('type',CollectionCommunicationReminders::TYPE)->where('data->agency_id',$this->agency->id)->sole()->data;
            app(CollectionCommunicationReminders::class)->forUser($this->users[0]);
            $after=$this->users[0]->notifications()->where('type',CollectionCommunicationReminders::TYPE)->where('data->agency_id',$this->agency->id)->sole()->data;
            $this->check($before['fingerprint']===$after['fingerprint'],'Un aggiornamento lento ha sovrascritto il riepilogo più recente.');
            return $results;
        });
        $this->scenario('client_changes_during_policy_send', function () {
            $policy=$this->policy();
            $message=app(AgencyEmailWorkflow::class)->prepare($this->users[0],$this->agency,collect([$policy]),[],'21/10/2026');
            $sender=$this->job(0,"/agency-communications/messages/$message->id/send",'POST',['reviewed'=>1]);
            $sender+=['pause_query'=>'select * from `agency_communication_attachments`','signal'=>'client-snapshot','resume'=>'client-updated'];
            $editor=$this->job(1,"/clients/{$policy->client_id}",'PUT',['name'=>'Alessio Valdieri','status'=>'active','client_type'=>'person','notes'=>'Anagrafica aggiornata durante il controllo']);
            $editor+=['await'=>'client-snapshot','completed'=>'client-updated'];
            $results=$this->parallel([$sender,$editor,$this->job(2,"/clients/{$policy->client_id}/policies/$policy->id")]);
            $this->check($this->statuses($results)===[422,302,200] && $message->fresh()->status==='review',json_encode($results));
            return $results;
        });
        $this->scenario('filtered_pages_exports_and_permissions', function () {
            for($i=0;$i<30;$i++) $this->paid($this->title(),10000);
            $base='/agency-communications/collections?scope=pending&agency='.$this->agency->id;
            $admin=$this->job(0,$base);
            $admin['requests'][]=['uri'=>$base.'&page=2'];
            $admin['requests'][]=['uri'=>$base.'&_table_all=1','export'=>true];
            $operator=$this->job(1,'/policies?agency='.$this->agency->id.'&_table_all=1'); $operator['requests'][0]['export']=true;
            $other=$this->job(2,$base.'&_table_all=1'); $other['requests'][0]['export']=true;
            $results=$this->parallel([$admin,$operator,$other]);
            $this->check(array_column($results[0]['responses'],'status')===[200,200,200] && $results[1]['responses'][0]['status']===200 && $results[2]['responses'][0]['status']===403,json_encode($results));
            [$first,$second,$export]=$results[0]['responses'];
            $this->check(count($first['selection'])===25 && count($second['selection'])>0 && count($export['selection'])===count($first['selection'])+count($second['selection']),'Righe esportate diverse dalle pagine filtrate.');
            $this->check(count(array_intersect(array_column($first['selection'],'id'),array_column($second['selection'],'id')))===0,'Righe duplicate tra le pagine.');
            $this->check(array_unique(array_column($export['selection'],'agency'))===[(string)$this->agency->id] && $first['selection_user']===(string)$this->users[0]->id && $first['selection_scope']===$second['selection_scope'],'Selezione non isolata per filtro/utente/agenzia.');
            return $results;
        });
        $this->scenario('filtered_export_while_operators_record_payments', function () {
            $title=$this->title();
            $reader=$this->job(0,'/collections?month=2026-10&agency='.$this->agency->id.'&_table_all=1');
            $reader['requests'][0]['export']=true;
            $reader['requests'][]=['uri'=>'/policies?agency='.$this->agency->id.'&_table_all=1','export'=>true];
            $results=$this->parallel([$reader,$this->job(1,"/titles/$title->id/collections",'POST',$this->payment(4000)),$this->job(2,"/titles/$title->id/collections",'POST',$this->payment(6000))]);
            $this->check($this->statuses($results)===[200,302,302] && $results[0]['responses'][1]['status']===200,json_encode($results));
            foreach($results[0]['responses'] as $response)foreach($response['numbers'] as $number)$this->check(str_starts_with($number,'QA-'.substr($this->run,4,8).'-'),'Il filtro dell’agenzia ha incluso un’altra selezione.');
            $this->check($title->fresh()->collected_cents===10000,'Saldo incompleto durante esportazione.');
            return $results;
        });
        $this->scenario('notification_ownership', function () {
            $notification=$this->users[1]->visibleNotifications()->firstOrFail();
            $uri='/notifications/'.$notification->id.'/read';
            $results=$this->parallel([$this->job(0,$uri,'POST'),$this->job(1,$uri,'POST'),$this->job(2,$uri,'POST')]);
            $this->check($this->statuses($results)===[404,302,404],json_encode($results));
            return $results;
        });
        file_put_contents($this->workspace.'/'.$this->run.'/report.json',json_encode(['database'=>$this->database,'actors'=>array_map(fn($u)=>['id'=>$u->id,'role'=>$u->role->value],$this->users),'results'=>$this->results],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $failures=count(array_filter($this->results,fn($r)=>!$r['passed']));
        echo count($this->results)." scenari; $failures non riusciti. Report: $this->workspace/$this->run/report.json\n";
        Auth::forgetGuards(); DB::disconnect();
        return $failures ? 1 : 0;
    }
}
