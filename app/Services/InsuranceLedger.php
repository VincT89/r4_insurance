<?php
namespace App\Services;

use App\Models\{InsurancePolicy,InsuranceTitle,InsuranceCollection};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class InsuranceLedger
{
    public const MONTHS=['Annuale'=>12,'Semestrale'=>6,'Quadrimestrale'=>4,'Trimestrale'=>3,'Mensile'=>1];
    private function fail(string $message): never {throw ValidationException::withMessages(['ledger'=>$message]);}
    public function log(InsurancePolicy $policy,string $action,string $description,array $values=[],array $before=[]): void
    {
        unset($values['idempotency_key']);
        $policy->client->auditLogs()->create(['user_id'=>auth()->id(),'action'=>$action,'description'=>'Polizza '.$policy->number.': '.$description,'old_values'=>$before ?: null,'new_values'=>['insurance_policy_id'=>$policy->id]+$values,'created_at'=>now()]);
    }
    public function previewFingerprint(InsurancePolicy $policy, array $rows): string
    {
        /** Identical instalments on another contract must not reuse an operator's reviewed preview. */
        return hash('sha256', json_encode([$policy->editFingerprint(), $policy->client->getRawOriginal(), $rows], JSON_THROW_ON_ERROR));
    }
    public function plan(InsurancePolicy $policy): array
    {
        if (!in_array($policy->status,['active','renewed'],true)) $this->fail('Genera i titoli soltanto per una polizza attiva o rinnovata.');
        if ($policy->premium===null || Money::cents($policy->premium)<=0 || !in_array($policy->payment_frequency,InsurancePolicy::PAYMENT_FREQUENCIES,true)) $this->fail('Completa premio e frazionamento della polizza prima di generare i titoli.');
        if (!$policy->payment_method) $this->fail('Indica il metodo di pagamento della polizza, poi verifica quello di ciascun titolo.');
        $start=$policy->starts_on->copy(); $end=$policy->expires_on->copy(); $rows=[];
        if (!$start->lt($end)) $this->fail('La copertura deve terminare dopo la decorrenza.');
        if ($policy->payment_frequency==='Unico') $rows[]=['coverage_from'=>$start->toDateString(),'coverage_to'=>$end->toDateString(),'due_on'=>$start->toDateString()];
        else {
            $months=self::MONTHS[$policy->payment_frequency];
            for($i=0;$i<120;$i++) {
                $from=$start->copy()->addMonthsNoOverflow($i*$months); if($from->gte($end)) break;
                $to=$start->copy()->addMonthsNoOverflow(($i+1)*$months);
                if($to->gt($end)) $this->fail('Le date non coincidono con periodi completi del frazionamento. Inserisci i titoli manualmente con gli importi della compagnia.');
                $rows[]=['coverage_from'=>$from->toDateString(),'coverage_to'=>$to->toDateString(),'due_on'=>$from->toDateString()];
            }
            if (!$rows || end($rows)['coverage_to']!==$end->toDateString()) $this->fail('Intervallo troppo esteso: inserisci i titoli per singolo periodo.');
        }
        $total=Money::cents($policy->premium); $count=count($rows); $amount=intdiv($total,$count);
        if($amount<1) $this->fail('Premio insufficiente per il numero di rate.');
        if($policy->installment_premium!==null && Money::cents($policy->installment_premium)*$count!==$total) $this->fail('Premio rata e premio totale non coincidono. Correggi gli importi oppure inserisci i titoli manualmente.');
        foreach($rows as $index=>&$row) $row+=['amount_cents'=>$amount+($index===$count-1 ? $total%$count : 0),'kind'=>$index===0 ? ($policy->status==='renewed' ? 'renewal' : 'emission') : 'quietance','payment_frequency'=>$policy->payment_frequency,'payment_method'=>$policy->payment_method];
        return $rows;
    }
    public function generate(InsurancePolicy $policy,string $fingerprint): void
    {
        \Illuminate\Support\Facades\Gate::authorize('ledger.manage');
        DB::transaction(function()use($policy,$fingerprint){
            $policy=InsurancePolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail(); $rows=$this->plan($policy);
            if(!hash_equals($this->previewFingerprint($policy,$rows),$fingerprint)) $this->fail('I dati sono cambiati: ricontrolla l’anteprima.');
            foreach($rows as $row) $this->insert($policy,$row);
            $this->refresh($policy); $this->log($policy,'titles_generated','generati '.count($rows).' titoli.', ['titles'=>$rows]);
        });
    }
    private function insert(InsurancePolicy $policy,array $data): InsuranceTitle
    {
        if (!$policy->coverage_origin_on) $policy->forceFill(['coverage_origin_on'=>$policy->starts_on])->save();
        if($policy->titles()->where('insurance_policy_change_id',$data['insurance_policy_change_id']??null)->whereNull('cancelled_at')->whereDate('coverage_from','<',$data['coverage_to'])->whereDate('coverage_to','>',$data['coverage_from'])->exists()) $this->fail('Esiste già un titolo per questo periodo. Controlla i titoli presenti prima di procedere.');
        $title = $policy->titles()->create($data+['created_by'=>auth()->id()]);
        app(InsuranceCommissionLedger::class)->sync($title);
        return $title;
    }
    public function manual(InsurancePolicy $policy,array $data): InsuranceTitle
    {
        \Illuminate\Support\Facades\Gate::authorize('ledger.manage');
        return DB::transaction(function()use($policy,$data){
            $policy=InsurancePolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail();
            if(in_array($policy->status,InsurancePolicy::CLOSED_STATUSES,true)) $this->fail('La polizza è chiusa: non può ricevere nuovi titoli.');
            if($data['coverage_from']<$policy->starts_on->toDateString() || $data['coverage_to']>$policy->expires_on->toDateString()) $this->fail('Il periodo del titolo deve appartenere al periodo contrattuale.');
            if (!empty($data['insurance_policy_change_id'])) {
                $change=$policy->incomingChanges()->where('kind','addition')->findOrFail($data['insurance_policy_change_id']);
                if($data['coverage_from']<$change->effective_on->toDateString() || $data['coverage_to']>$change->expires_on->toDateString()) $this->fail('Il titolo deve rientrare nel periodo della copertura aggiuntiva.');
                if($data['amount_cents']+$change->titles()->whereNull('cancelled_at')->sum('amount_cents')>$change->premium_cents) $this->fail('Gli importi dei titoli superano il premio aggiuntivo approvato.');
            }
            $title=$this->insert($policy,$data); $this->refresh($policy); $this->log($policy,'title_created','registrato il titolo #'.$title->id.'.',['insurance_title_id'=>$title->id]+$data); return $title;
        });
    }
    public function cancel(InsuranceTitle $title,string $reason): void
    {
        \Illuminate\Support\Facades\Gate::authorize('ledger.manage');
        DB::transaction(function()use($title,$reason){
            $policy=InsurancePolicy::whereKey($title->insurance_policy_id)->lockForUpdate()->firstOrFail(); $title=InsuranceTitle::whereKey($title->id)->lockForUpdate()->firstOrFail();
            if($title->cancelled_at) return;
            if($title->collected_cents>0) $this->fail('Il titolo ha incassi: storna prima i movimenti errati, senza cancellarli.');
            $before=$title->only(['cancelled_at','cancellation_reason']);
            $title->update(['cancelled_at'=>now(),'cancellation_reason'=>$reason]);
            app(InsuranceCommissionLedger::class)->sync($title);
            $this->refresh($policy);$this->log($policy,'title_cancelled','annullato il titolo #'.$title->id.': '.$reason,['insurance_title_id'=>$title->id]+$title->only(['amount_cents','due_on','cancelled_at','cancellation_reason']),$before);
        });
    }
    public function collect(InsuranceTitle $title,array $data): InsuranceCollection
    {
        \Illuminate\Support\Facades\Gate::authorize('ledger.manage');
        return DB::transaction(function()use($title,$data){
            $policy=InsurancePolicy::whereKey($title->insurance_policy_id)->lockForUpdate()->firstOrFail(); $title=InsuranceTitle::whereKey($title->id)->lockForUpdate()->firstOrFail();
            $fingerprint=hash('sha256',json_encode([$title->id,auth()->id(),$data]));
            if($existing=InsuranceCollection::where('idempotency_key',$data['idempotency_key'])->first()) {
                if(!hash_equals($existing->request_fingerprint,$fingerprint)) $this->fail('La richiesta è già stata usata con dati diversi. Riapri il modulo.');
                return $existing;
            }
            if($title->cancelled_at || $data['amount_cents']<1 || $data['amount_cents']>$title->balance_cents) $this->fail('Importo non valido: il titolo è annullato, già incassato o l’importo supera il residuo.');
            if ((str_contains($data['payment_method'],'a Compagnia') && $data['recipient']!=='company') || (str_contains($data['payment_method'],'a Intermediario') && $data['recipient']==='company')) $this->fail('Metodo e destinatario del pagamento non coincidono.');
            if ($data['recipient']==='agency' && !$policy->supplier_agency_id) $this->fail('Collega l’agenzia intermediaria alla polizza prima di registrare questo pagamento.');
            $before=['collected_cents'=>$title->collected_cents];
            $payment=$title->collections()->create($data+['created_by'=>auth()->id(),'request_fingerprint'=>$fingerprint]);
            $title->update(['collected_cents'=>$title->collected_cents+$data['amount_cents']]);
            app(InsuranceCommissionLedger::class)->sync($title, $payment->received_on->toDateString(), 'Maturazione sull’incasso #'.$payment->id.'.');
            $this->refresh($policy);$this->log($policy,'collection_recorded','registrato incasso #'.$payment->id.' di '.Money::format($payment->amount_cents).' sul titolo #'.$title->id.'.',['insurance_collection_id'=>$payment->id,'insurance_title_id'=>$title->id,'collected_cents'=>$title->collected_cents]+$data,$before);return $payment;
        });
    }
    public function void(InsuranceCollection $payment,string $reason): void
    {
        \Illuminate\Support\Facades\Gate::authorize('ledger.manage');
        DB::transaction(function()use($payment,$reason){
            $policy=InsurancePolicy::whereKey($payment->title->insurance_policy_id)->lockForUpdate()->firstOrFail();
            $title=InsuranceTitle::whereKey($payment->insurance_title_id)->lockForUpdate()->firstOrFail(); $payment=InsuranceCollection::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if($payment->voided_at) return;
            $before=['collected_cents'=>$title->collected_cents]+$payment->only(['voided_at','void_reason']);
            $payment->update(['voided_at'=>now(),'voided_by'=>auth()->id(),'void_reason'=>$reason]);
            $title->update(['collected_cents'=>$title->collected_cents-$payment->amount_cents]);
            app(InsuranceCommissionLedger::class)->sync($title, today()->toDateString(), 'Rettifica sullo storno dell’incasso #'.$payment->id.': '.$reason);
            app(DeadlineReminders::class)->reopenTitle($title);
            app(CollectionCommunicationReminders::class)->recordReversal($payment);
            $this->refresh($policy);$this->log($policy,'collection_voided','stornato incasso #'.$payment->id.' sul titolo #'.$title->id.' di '.Money::format($payment->amount_cents).': '.$reason,['insurance_collection_id'=>$payment->id,'insurance_title_id'=>$title->id,'collected_cents'=>$title->collected_cents]+$payment->only(['amount_cents','payment_method','recipient','received_on','voided_at','void_reason']),$before);
        });
    }
    public function renewalPlan(InsurancePolicy $policy,array $data): array
    {
        if(!in_array($policy->status,['active','renewed','expired'],true))$this->fail('Non puoi rinnovare una polizza sospesa, annullata, sostituita o da verificare.');
        if($data['starts_on']!==$policy->expires_on->toDateString())$this->fail('Il nuovo periodo deve partire dalla scadenza corrente; controlla le date.');
        $next=clone $policy;$next->fill($data+['status'=>'renewed','installment_premium'=>null]);return $this->plan($next);
    }
    public function renew(InsurancePolicy $policy,array $data,string $fingerprint): void
    {
        \Illuminate\Support\Facades\Gate::authorize('ledger.manage');
        DB::transaction(function()use($policy,$data,$fingerprint){
            $policy=InsurancePolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail();
            /** A renewal changes the contract and cannot bypass the next-day work lock. */
            abort_unless($policy->editableBy(auth()->user()),403,'La lavorazione è chiusa: richiedi la correzione all’amministratore.');
            $rows=$this->renewalPlan($policy,$data);
            if(!hash_equals($this->previewFingerprint($policy,$rows),$fingerprint))$this->fail('I dati del rinnovo sono cambiati: ricontrolla l’anteprima.');
            $old=$policy->only(['starts_on','expires_on','premium','payment_frequency','payment_method','status','last_renewed_at']);
            if(!$policy->coverage_origin_on)$policy->forceFill(['coverage_origin_on'=>$policy->starts_on])->save();
            $policy->update($data+['status'=>'renewed','installment_premium'=>null]);
            $policy->forceFill(['closed_at'=>null,'last_renewed_at'=>now()])->save();
            foreach($rows as $row)$this->insert($policy,$row);
            $this->refresh($policy);$this->log($policy,'policy_renewed','rinnovo registrato con i relativi titoli.',$data+['last_renewed_at'=>$policy->last_renewed_at->toIso8601String()],$old);
        });
    }
    public function refresh(InsurancePolicy $policy): void
    {
        $titles=$policy->titles()->whereNull('cancelled_at')->orderBy('coverage_from')->orderBy('id')->get();
        $covered=null; $cursor=$policy->coverage_origin_on ?? $policy->starts_on;
        /** A later paid instalment never bridges a gap left by an unpaid earlier title. */
        foreach($titles->whereNull('insurance_policy_change_id') as $title){if($title->balance_cents>0 || !$title->coverage_from->equalTo($cursor))break; $covered=$title->coverage_to; $cursor=$covered;}
        $policy->forceFill(['coverage_until'=>$covered,'payment_status'=>$titles->isEmpty() ? null : ($titles->every(fn($t)=>$t->balance_cents===0) ? 'paid' : 'unpaid')])->save();
    }
}
