<?php

namespace App\Services;

use App\Models\{Client, InsuranceClaim, InsuranceClaimActivity, InsuranceClaimEvent, InsurancePolicy, User};
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Validation\ValidationException;

class InsuranceClaims
{
    public function operators(Client $client)
    {
        return User::where('status','active')->orderBy('name')->get()->filter(fn($u)=>$u->can('viewAny',Client::class) && $u->can('updateRegistry',$client));
    }

    public function snapshot(InsurancePolicy $policy): array
    {
        return ['client'=>$policy->client->name,'number'=>$policy->number,'company'=>$policy->company,'agency'=>$policy->supplierAgency?->name,'branch'=>$policy->category,'subbranch'=>$policy->subcategory,'plate'=>$policy->vehicle?->plate,'starts_on'=>$policy->starts_on->toDateString(),'expires_on'=>$policy->expires_on->toDateString()];
    }

    public function context(InsurancePolicy $policy): string { return hash('sha256',json_encode([$policy->id,$policy->client_id,$this->snapshot($policy)],JSON_THROW_ON_ERROR)); }

    public function create(User $actor, InsurancePolicy $policy, array $data, array $files): InsuranceClaim
    {
        abort_unless($actor->status==='active' && $actor->can('viewAny',Client::class),403);
        $stored=[];
        try {
            return DB::transaction(function () use ($actor,$policy,$data,$files,&$stored) {
                $client=Client::current()->whereKey($policy->client_id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('updateRegistry',$client);
                $policy=$client->policies()->whereKey($policy->id)->lockForUpdate()->firstOrFail();
                if (!hash_equals($this->context($policy),$data['context_hash'])) $this->fail('I dati della polizza sono cambiati. Riapri il modulo prima di salvare.');
                if (InsuranceClaim::where('request_key',$data['request_key'])->exists() || InsuranceClaimEvent::where('request_key',$data['request_key'])->exists()) $this->fail('Questo invio è già stato registrato. Riapri la scheda prima di continuare.');
                $this->checkAssignee($client,$data['assigned_to']);
                $this->checkNumber($policy,$data['number']??null);
                $claim=InsuranceClaim::create($this->fields($data)+['client_id'=>$client->id,'insurance_policy_id'=>$policy->id,'policy_snapshot'=>$this->snapshot($policy),'created_by'=>$actor->id,'request_key'=>$data['request_key'],'status'=>'open']);
                $details=['dati'=>$this->historyFields($claim)];
                if (!empty($data['activity_label'])) {
                    $activity=$claim->activities()->create(['label'=>$data['activity_label'],'due_on'=>$data['activity_due_on']]);
                    $details['attività']=$activity->label; $details['data attività']=$activity->due_on->format('d/m/Y');
                }
                $event=$this->event($claim,$actor,'created',$details,$data['request_key']);
                $stored=$this->upload($claim,$event,$files);
                return $claim;
            });
        } catch (\Throwable $e) { $this->cleanup($stored); throw $e; }
    }

    public function change(User $actor, InsuranceClaim $claim, array $data, string $action, array $files=[]): InsuranceClaim
    {
        $stored=[];
        try {
            return DB::transaction(function () use ($actor,$claim,$data,$action,$files,&$stored) {
                /** Lock the owner before the policy and claim, matching merges and document operations. */
                $client=Client::current()->whereKey($claim->client_id)->lockForUpdate()->firstOrFail();
                $policy=$client->policies()->whereKey($claim->insurance_policy_id)->lockForUpdate()->firstOrFail();
                $claim=$client->claims()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->status==='active' && $claim->editableBy($actor),403);
                if ($claim->version!==(int)$data['version']) $this->fail('La pratica è stata aggiornata da un altro operatore. Ricarica la pagina prima di salvare.');
                if (InsuranceClaimEvent::where('request_key',$data['request_key'])->exists()) $this->fail('Questa operazione è già stata registrata.');
                $kind=$action; $details=[];
                if ($action==='updated') {
                    $this->checkAssignee($client,$data['assigned_to']); $this->checkNumber($policy,$data['number']??null,$claim);
                    $details['prima']=$this->historyFields($claim); $claim->fill($this->fields($data)); $details['dopo']=$this->historyFields($claim);
                    if ($details['prima']===$details['dopo']) $this->fail('Nessun dato modificato.');
                    $details['nota']=$data['reason'];
                } elseif ($action==='status') {
                    $status=$data['status'];
                    if ($status===$claim->status) $this->fail('Seleziona uno stato diverso da quello attuale.');
                    if ($status==='cancelled' || $claim->isClosed()) abort_unless($actor->isAdmin(),403);
                    $details=['stato precedente'=>$claim->status_label,'nuovo stato'=>InsuranceClaim::STATUSES[$status],'nota'=>$data['reason']];
                    $claim->status=$status; $claim->closed_at=$claim->isClosed()?now():null;
                    if ($claim->isClosed()) {
                        $pending=$claim->activities()->where('status','pending')->lockForUpdate()->get();
                        $details['attività annullate alla chiusura']=$pending->map(fn($a)=>$a->label.' ('.$a->due_on->format('d/m/Y').')')->all();
                        $claim->activities()->whereIn('id',$pending->modelKeys())->update(['status'=>'cancelled','completed_at'=>now(),'completed_by'=>$actor->id]);
                    }
                } elseif ($action==='note') {
                    if (empty($data['note']) && !$files) $this->fail('Inserisci una nota o almeno un allegato.');
                    $details=['nota'=>$data['note']??null,'allegati'=>array_map(fn($f)=>$f->getClientOriginalName(),$files)];
                } elseif ($action==='activity_added') {
                    if ($claim->isClosed()) $this->fail('Riapri il sinistro prima di programmare un’attività.');
                    $activity=$claim->activities()->create(['label'=>$data['label'],'due_on'=>$data['due_on']]);
                    $details=['attività'=>$activity->label,'data'=>$activity->due_on->format('d/m/Y')];
                } else {
                    $activity=$claim->activities()->whereKey($data['activity_id'])->lockForUpdate()->firstOrFail();
                    if ($activity->status!=='pending' || $claim->isClosed()) $this->fail('L’attività non è più aperta.');
                    $details=['attività'=>$activity->label,'data precedente'=>$activity->due_on->format('d/m/Y'),'nota'=>$data['reason']];
                    if ($action==='activity_rescheduled') {
                        if ($data['due_on']===$activity->due_on->toDateString()) $this->fail('Indica una data diversa per riprogrammare l’attività.');
                        $activity->due_on=$data['due_on']; $activity->revision++;
                        $details['nuova data']=$activity->due_on->format('d/m/Y');
                    } else {
                        $activity->status=$action==='activity_completed'?'completed':'cancelled';
                        $activity->completed_at=now(); $activity->completed_by=$actor->id;
                    }
                    $activity->save();
                }
                $claim->version++; $claim->save();
                $event=$this->event($claim,$actor,$kind,$details,$data['request_key']);
                $stored=$this->upload($claim,$event,$files);
                /** Obsolete reminders disappear immediately; the immutable activity history remains available. */
                if ($action==='status' && $claim->isClosed()) DB::table('notifications')->where('type',ClaimReminders::TYPE)->where('data->claim_id',$claim->id)->delete();
                elseif (in_array($action,['activity_rescheduled','activity_completed','activity_cancelled'],true)) DB::table('notifications')->where('type',ClaimReminders::TYPE)->where('data->activity_id',$data['activity_id'])->delete();
                return $claim;
            });
        } catch (\Throwable $e) { $this->cleanup($stored); throw $e; }
    }

    private function fields(array $data): array
    {
        return ['number'=>filled($data['number']??null)?mb_strtoupper(trim($data['number'])):null,'event_on'=>$data['event_on'],'reported_on'=>$data['reported_on']??null,'description'=>$data['description'],'assigned_to'=>$data['assigned_to']];
    }

    private function historyFields(InsuranceClaim $claim): array
    {
        return ['numero'=>$claim->number,'data evento'=>$claim->event_on->format('d/m/Y'),'data denuncia'=>$claim->reported_on?->format('d/m/Y'),'descrizione'=>$claim->description,'operatore'=>User::find($claim->assigned_to)?->name];
    }

    private function checkAssignee(Client $client,int|string $id): void
    {
        if (!$this->operators($client)->contains('id',(int)$id)) throw ValidationException::withMessages(['assigned_to'=>'Scegli un operatore attivo autorizzato a questa anagrafica.']);
    }

    private function checkNumber(InsurancePolicy $policy,?string $number,?InsuranceClaim $claim=null): void
    {
        if (filled($number) && $policy->claims()->where('number',mb_strtoupper(trim($number)))->when($claim,fn($q)=>$q->whereKeyNot($claim->id))->exists()) throw ValidationException::withMessages(['number'=>'Esiste già un sinistro con questo numero per la polizza.']);
    }

    private function event(InsuranceClaim $claim,User $actor,string $kind,array $details,string $key): InsuranceClaimEvent
    {
        $event=$claim->events()->create(['created_by'=>$actor->id,'actor_name'=>$actor->name,'kind'=>$kind,'details'=>$details,'request_key'=>$key,'created_at'=>now()]);
        app(AuditLogService::class)->log('claim_'.$kind,$claim,[],['status'=>$claim->status],InsuranceClaimEvent::KINDS[$kind].' · sinistro #'.$claim->id.' · polizza #'.$claim->insurance_policy_id.'.',$actor->id);
        app(ClaimReminders::class)->invalidate($claim);
        return $event;
    }

    private function upload(InsuranceClaim $claim,InsuranceClaimEvent $event,array $files): array
    {
        if (collect($files)->sum(fn($f)=>$f->getSize())>50*1024*1024) throw ValidationException::withMessages(['files'=>'Il totale dei file non può superare 50 MB.']);
        return app(PrivateAttachmentBatch::class)->upload($claim->client,$files,['type'=>'document','document_category'=>'claim','insurance_policy_id'=>$claim->insurance_policy_id,'insurance_claim_id'=>$claim->id,'insurance_claim_event_id'=>$event->id,'review_status'=>'received']);
    }

    private function cleanup(array $files): void { foreach ($files as $file) Storage::disk($file->disk)->delete($file->path); }
    private function fail(string $message): never { throw ValidationException::withMessages(['claim'=>$message]); }
}
