<?php
namespace App\Services;

use App\Models\{InsuranceQuoteCase,InsuranceQuote,SupplierCompany,InsurancePolicy,Client};
use App\Support\Money;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;

class InsuranceQuoteWorkflow
{
    private function fail(string $message): never { throw ValidationException::withMessages(['quote'=>$message]); }
    private function log(InsuranceQuoteCase $case,string $description,array $values=[]): void
    {
        $case->client->auditLogs()->create(['user_id'=>auth()->id(),'action'=>'quote_updated','description'=>'Preventivo #'.$case->id.': '.$description,'new_values'=>$values,'created_at'=>now()]);
    }
    public function request(InsuranceQuoteCase $case,int $companyId,string $requestedOn): InsuranceQuote
    {
        return DB::transaction(function()use($case,$companyId,$requestedOn){
            $case=InsuranceQuoteCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            if($case->status!=='open') $this->fail('La pratica ha già una proposta approvata o una polizza.');
            $company=SupplierCompany::whereKey($companyId)->where('status','active')->firstOrFail();
            $priorities=SupplierCompany::where('quote_priority',true)->where('status','active')->pluck('id');
            if(!$company->quote_priority && $priorities->diff($case->quotes()->pluck('supplier_company_id'))->isNotEmpty()) $this->fail('Registra prima le richieste alle compagnie prioritarie dell’agenzia.');
            if($case->quotes()->where('supplier_company_id',$company->id)->exists()) $this->fail('La richiesta a questa compagnia è già presente.');
            $quote=$case->quotes()->create(['supplier_company_id'=>$company->id,'priority'=>$company->quote_priority,'requested_at'=>$requestedOn,'status'=>'requested']);
            $this->log($case,'registrata richiesta a '.$company->name.'.',['requested_on'=>$requestedOn]); return $quote;
        });
    }
    public function change(InsuranceQuote $quote,string $action,array $data,array $files=[]): void
    {
        $stored=[];
        try {DB::transaction(function()use($quote,$action,$data,$files,&$stored){
            $case=InsuranceQuoteCase::whereKey($quote->insurance_quote_case_id)->lockForUpdate()->firstOrFail();
            $quote=InsuranceQuote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if($case->status!=='open') $this->fail('La pratica ha già una proposta approvata o una polizza.');
            if($action==='receive') {
                if($quote->status!=='requested') $this->fail('Il documento è già stato ricevuto.');
                $agency=$data['supplier_agency_id']??null;
                if($agency && !$quote->company->agencies()->where('supplier_agencies.id',$agency)->where('supplier_agencies.status','active')->exists()) $this->fail('L’agenzia non è collegata alla compagnia della proposta.');
                if($data['received_on']<$quote->requested_at->toDateString()) $this->fail('La ricezione non può precedere la richiesta.');
                $values=$data; unset($values['received_on'],$values['premium']);
                $quote->update($values+['premium_cents'=>Money::cents($data['premium']),'received_at'=>$data['received_on'],'status'=>'received']);
                $stored=app(ClientDocumentRegistry::class)->uploadMany($case->client,$files,['document_category'=>'quote','insurance_quote_id'=>$quote->id]);
            } elseif($action==='submit') {
                if($quote->status!=='received') $this->fail('Carica prima il preventivo ricevuto dalla compagnia.');
                if($quote->valid_until->lt(today())) $this->fail('Il preventivo non è più valido.');
                $quote->update(['status'=>'pending','submitted_at'=>now()]);
            } elseif($action==='approve') {
                if($quote->status!=='pending') $this->fail('La proposta deve essere in approvazione al cliente.');
                if($data['approved_on']<$quote->received_at->toDateString() || $data['approved_on']>$quote->valid_until->toDateString()) $this->fail('La data di approvazione deve rientrare tra ricezione e validità del preventivo.');
                $stored=app(ClientDocumentRegistry::class)->uploadMany($case->client,$files,['document_category'=>'quote_approval','insurance_quote_id'=>$quote->id]);
                $quote->update($data+['status'=>'approved','approved_by'=>auth()->id()]); $case->update(['status'=>'approved']);
            } elseif($action==='reject') {
                if(!in_array($quote->status,['received','pending'],true)) $this->fail('Questa proposta non può essere rifiutata.');
                $quote->update(['status'=>'rejected','approval_note'=>$data['approval_note']]);
            }
            $this->log($case,'proposta '.$quote->company->name.': '.InsuranceQuote::STATUSES[$quote->status].'.',$quote->only(['id','status','premium_cents','approved_on','approved_by']));
        });} catch(\Throwable $e){foreach($stored as $document)Storage::disk($document->disk)->delete($document->path);throw $e;}
    }
    public function policyValues(InsuranceQuote $quote): array
    {
        return ['category'=>$quote->quoteCase->branch->name,'subcategory'=>$quote->quoteCase->subbranch?->name,'company'=>$quote->company->name,'supplier_agency_id'=>$quote->supplier_agency_id,'product'=>$quote->product,'starts_on'=>$quote->starts_on->toDateString(),'expires_on'=>$quote->expires_on->toDateString(),'premium'=>Money::decimal($quote->premium_cents),'payment_frequency'=>$quote->payment_frequency,'installment_premium'=>null];
    }
    public function createPolicy(Client $client,array $values,int $quoteId): InsurancePolicy
    {
        /** Lock the case before the proposal so concurrent approvals and conversions use the same order. */
        $candidate=InsuranceQuote::whereKey($quoteId)->firstOrFail();
        $case=InsuranceQuoteCase::whereKey($candidate->insurance_quote_case_id)->lockForUpdate()->firstOrFail();
        if($case->client_id!==$client->id) $this->fail('Il preventivo appartiene a un altro cliente.');
        $quote=InsuranceQuote::whereKey($quoteId)->lockForUpdate()->firstOrFail();
        if($quote->status!=='approved' || $case->status!=='approved' || $quote->policy()->exists()) $this->fail('Seleziona un preventivo approvato, non ancora trasformato in polizza.');
        if($quote->valid_until->lt(today())) $this->fail('Il preventivo approvato è scaduto: occorre una proposta aggiornata.');
        foreach(['quote','quote_approval'] as $category) {
            $docs=$quote->documents()->where('document_category',$category)->get();
            if($docs->isEmpty() || $docs->contains(fn($doc)=>!Storage::disk($doc->disk)->exists($doc->path))) $this->fail('Manca il documento del preventivo o la prova di approvazione.');
        }
        $values=array_replace($values,$this->policyValues($quote));
        if($client->policies()->where('company',$values['company'])->where('number',$values['number'])->exists()) $this->fail('Questo numero di polizza è già presente per cliente e compagnia.');
        if(!in_array($values['status'],['active','renewed'],true)) $this->fail('Una nuova stipula deve essere attiva o rinnovata.');
        $policy=$client->policies()->create(app(PolicyProviders::class)->values(app(InsuranceCatalog::class)->policyValues($values)));
        $policy->forceFill(['insurance_quote_id'=>$quote->id])->save();
        $quote->documents()->update(['insurance_policy_id'=>$policy->id]);
        $case->documents()->update(['insurance_policy_id'=>$policy->id]);
        $quote->update(['status'=>'converted']); $case->update(['status'=>'closed']);
        $this->log($case,'creata polizza '.$policy->number.' dal preventivo approvato.',['policy_id'=>$policy->id,'quote_id'=>$quote->id]); return $policy;
    }
}
