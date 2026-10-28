<?php
namespace App\Services;

use App\Models\{AgencyCommunication,InsuranceCollection,InsuranceTitle,SupplierAgency,User};
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgencyCollectionEmailWorkflow
{
    private function fail(string $message): never {throw ValidationException::withMessages(['email'=>$message]);}
    public function deliveryKey(InsuranceCollection $payment,string $recipient): string {return hash('sha256','collection|'.$payment->id.'|'.strtolower($recipient));}
    public function existingDeliveries(Collection $payments): Collection
    {
        return app(CollectionCommunicationStatus::class)->deliveries($payments);
    }
    private function snapshot(SupplierAgency $agency,Collection $payments,string $range): array
    {
        if($agency->status!=='active' || !filter_var($agency->email,FILTER_VALIDATE_EMAIL))$this->fail('Completa l’email di un’agenzia attiva.');
        if($payments->isEmpty() || $payments->contains(fn($p)=>$p->voided_at || $p->title->cancelled_at || (int)$p->title->policy->supplier_agency_id!==(int)$agency->id))$this->fail('Seleziona incassi non stornati, relativi a titoli della stessa agenzia.');
        $rows=$payments->sortBy('id')->map(fn($p)=>[
            'id'=>$p->id,'title_id'=>$p->insurance_title_id,'policy_id'=>$p->title->insurance_policy_id,'number'=>$p->title->policy->number,'client'=>$p->title->policy->client->name,'company'=>$p->title->policy->company,
            'kind'=>InsuranceTitle::KINDS[$p->title->kind],'date'=>$p->received_on->format('d/m/Y'),'amount'=>Money::format($p->amount_cents),'payment_method'=>$p->payment_method,'recipient'=>InsuranceCollection::RECIPIENTS[$p->recipient],
            'policy_status'=>$p->title->policy->status_label,
            'policy_premium'=>$p->title->policy->premium === null ? 'Non indicato' : Money::format(Money::cents((string)$p->title->policy->premium)),
            'document_type'=>InsuranceCollection::DOCUMENT_TYPES[$p->document_type],'reference'=>$p->reference,'frequency'=>$p->title->payment_frequency,'coverage_from'=>$p->title->coverage_from->format('d/m/Y'),'coverage_to'=>$p->title->coverage_to->format('d/m/Y'),'balance'=>Money::format($p->title->balance_cents),'ad_horas'=>(bool)$p->title->policy->ad_horas,
        ])->values()->all();
        $lines=['Buongiorno,','','riepilogo degli incassi registrati nel periodo '.$range.':',''];
        $clean=fn($v)=>trim(preg_replace('/[\r\n\t]+/u',' ',(string)$v));
        foreach($rows as $raw){$r=array_map($clean,$raw);$lines[]=($raw['ad_horas']?'AD HORAS - ':'').$r['number'].' | '.$r['client'].' | '.$r['company'];
            $lines[]=$r['kind'].' #'.$r['title_id'].'; periodo '.$r['coverage_from'].' – '.$r['coverage_to'].'; frazionamento '.$r['frequency'].'.';
            $lines[]='Premio della polizza: '.$r['policy_premium'].'.';
            $lines[]='Incasso del '.$r['date'].': '.$r['amount'].'; metodo: '.$r['payment_method'].'; destinatario: '.$r['recipient'].'.';
            $lines[]='Documento: '.$r['document_type'].($r['reference']?' / '.$r['reference']:'').'; residuo del titolo: '.$r['balance'].'.';
            $lines[]='Stato attuale della polizza: '.$r['policy_status'].'.';$lines[]='';}
        $lines[]='Il riepilogo riporta i movimenti registrati; gli allegati riservati del cliente non sono inclusi.';$lines[]='';$lines[]=config('branding.wordmark');
        $policies=$payments->map(fn($p)=>$p->title->policy)->unique('id')->map(fn($p)=>['id'=>$p->id,'client_id'=>$p->client_id,'number'=>$p->number,'client'=>$p->client->name,'company'=>$p->company,'starts_on'=>$p->starts_on->format('d/m/Y'),'expires_on'=>$p->expires_on->format('d/m/Y'),'premium'=>$p->premium === null ? 'Non indicato' : Money::format(Money::cents((string)$p->premium)),'frequency'=>$p->payment_frequency,'ad_horas'=>(bool)$p->ad_horas])->values()->all();
        $payload=['kind'=>'collections','from'=>app(AgencyMailConfiguration::class)->sender(),'recipient'=>['address'=>$agency->email,'name'=>$agency->name],'range'=>$range,'policies'=>$policies,'collections'=>$rows,'subject'=>($payments->contains(fn($p)=>$p->title->policy->ad_horas)?'Ad horas - ':'').'Riepilogo incassi '.$range.' - '.config('branding.wordmark'),'body'=>implode("\n",$lines)];
        $versions=$payments->sortBy('id')->map(fn($p)=>[$p->getAttributes(),$p->title->getAttributes(),app(AgencyEmailWorkflow::class)->policyFingerprint($p->title->policy)])->values()->all();
        return [$payload,hash('sha256',json_encode([$payload,$versions],JSON_THROW_ON_ERROR))];
    }
    public function prepare(User $user,SupplierAgency $agency,Collection $payments,array $filters,string $range): AgencyCommunication
    {
        [$payload,$fingerprint]=$this->snapshot($agency,$payments,$range);
        return DB::transaction(function()use($user,$agency,$payments,$filters,$payload,$fingerprint){
            $message=AgencyCommunication::create(['supplier_agency_id'=>$agency->id,'user_id'=>$user->id,'status'=>'review','payload'=>$payload+['filters'=>$filters],'fingerprint'=>$fingerprint,'review_expires_at'=>now()->addMinutes(30)]);
            foreach($payments as $payment)DB::table('agency_communication_collections')->insert(['agency_communication_id'=>$message->id,'insurance_collection_id'=>$payment->id]);
            app(AuditLogService::class)->log('agency_email_review',$agency,description:'Preparato controllo di '.$payments->count().' incassi. Nessun invio. Riferimento '.$message->id.'.');return $message;
        });
    }
    public function claim(AgencyCommunication $message,User $user,SupplierAgency $agency,Collection $policies): bool
    {
        $ids=array_column($message->payload['collections'],'id');
        $policies=$policies->keyBy('id');
        $titles=InsuranceTitle::whereIn('id',array_column($message->payload['collections'],'title_id'))->whereIn('insurance_policy_id',$policies->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $payments=InsuranceCollection::whereIn('id',$ids)->whereIn('insurance_title_id',$titles->keys())->orderBy('id')->lockForUpdate()->get();
        if($payments->count()!==count($ids))$this->fail('Alcuni incassi non sono più disponibili. Riapri il controllo.');
        /** Reuse the locked rows: reloading relations normally can hide a balance committed while this confirmation was waiting. */
        foreach($titles as $title)$title->setRelation('policy',$policies->get($title->insurance_policy_id));
        foreach($payments as $payment)$payment->setRelation('title',$titles->get($payment->insurance_title_id));
        [,$fingerprint]=$this->snapshot($agency,$payments,$message->payload['range']);
        if(!hash_equals($message->fingerprint,$fingerprint))$this->fail('Incassi, titoli, polizze o destinatario sono cambiati: prepara un nuovo riepilogo.');
        if ($this->existingDeliveries($payments)->isNotEmpty()) $this->fail('Un incasso è già stato comunicato a questa agenzia o ha un invio da controllare. Consulta lo storico.');
        foreach($payments as $payment){$key=$this->deliveryKey($payment,$agency->email);if(DB::table('agency_communication_collections')->where('delivery_key',$key)->exists())$this->fail('Un incasso è già stato inviato o ha un invio in corso. Controlla lo storico.');
            DB::table('agency_communication_collections')->where('agency_communication_id',$message->id)->where('insurance_collection_id',$payment->id)->update(['delivery_key'=>$key]);}
        $message->update(['status'=>'sending','reviewed_at'=>now(),'sending_at'=>now()]);
        app(AuditLogService::class)->log('agency_email_sending',$agency,description:'Confermato invio cumulativo di '.$payments->count().' incassi. Riferimento '.$message->id.'.');return true;
    }
}
