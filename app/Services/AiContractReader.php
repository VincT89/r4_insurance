<?php

namespace App\Services;

use App\Models\{AiSetting, AiUsage, User};
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Arr;
use Illuminate\Http\Exceptions\HttpResponseException;

class AiContractReader
{
    protected function schema(): AiContractSchema { return app(AiContractSchema::class); }
    protected function feature(): string { return 'policy_contract'; }
    private function fail(string $message, bool $retryAllowed=false): never { throw new HttpResponseException(response()->json(['errors'=>['contract'=>[$message]],'ai_retry_allowed'=>$retryAllowed],422)); }

    public function recoverAbandoned(): void
    {
        DB::transaction(function() {
            AiSetting::lockForUpdate()->findOrFail(1);
            /** An interrupted worker must remain reviewable; an uncertain delivery is never retried or marked free. */
            AiUsage::whereIn('state',['running','reserved'])->where('started_at','<',now()->subMinutes(10))->update(['state'=>'uncertain','error_code'=>'worker_interrupted','completed_at'=>now()]);
            AiUsage::where('state','counting')->where('started_at','<',now()->subMinutes(10))->update(['state'=>'failed','cost_microusd'=>0,'reserved_microusd'=>0,'error_code'=>'preparation_interrupted','completed_at'=>now()]);
        });
    }

    public function read(UploadedFile $file, User $user, bool $retry=false, array $context=[]): array
    {
        $setting=app(AiConfiguration::class)->settings();
        if(!$setting?->enabled || !filled($setting->api_key)) $this->fail('La lettura AI non è attiva. Puoi usare la lettura locale o chiedere all’amministratore di configurarla.');
        $this->recoverAbandoned();
        if(!app()->runningUnitTests() && function_exists('set_time_limit')) set_time_limit(150);
        $hash=hash_file('sha256',$file->getRealPath());
        $schema=$this->schema(); $feature=$this->feature();
        /** Conserva le chiavi dei contratti già letti e separa preventivi e pratiche per evitare riusi impropri. */
        $namespace=$feature==='policy_contract' ? '' : $feature.'|'.($context['insurance_quote_id']??'').'|';
        $key=hash('sha256',$namespace.$user->id.'|'.$setting->revision.'|'.$schema::VERSION.'|'.$hash);
        $usage=DB::transaction(function()use($setting,$user,$hash,$key,$retry,$feature,$context) {
            AiSetting::lockForUpdate()->findOrFail(1);
            if($existing=AiUsage::where('request_key',$key)->first()) {
                if(!$retry || !in_array($existing->state,['failed','rejected'],true) || $existing->reserved_microusd) return $existing;
                /** A deliberate retry gets a separate billing record; concurrent clicks still find the new active request. */
                $existing->update(['request_key'=>hash('sha256',$key.'|closed|'.$existing->id)]);
            }
            $rates=AiPricing::rates($setting->model,0);
            return AiUsage::create(['user_id'=>$user->id,'feature'=>$feature,'model'=>$setting->model,'state'=>'counting','request_key'=>$key,'document_hash'=>$hash,'input_rate'=>$rates['input'],'cached_rate'=>$rates['cached'],'output_rate'=>$rates['output'],'started_at'=>now()]+Arr::only($context,['insurance_quote_id','attachment_id']));
        });
        if(!$usage->wasRecentlyCreated) {
            if($usage->state==='complete' && $usage->result) return $this->result($usage,true);
            $this->fail(in_array($usage->state,['counting','reserved','running','uncertain'],true) ? 'Per questo PDF esiste già una richiesta in corso o da verificare. Non è stata avviata una seconda elaborazione.' : 'La precedente lettura di questo PDF non è riuscita. Usa la lettura locale oppure verifica configurazione e limiti nella sezione AI e consumi.',in_array($usage->state,['failed','rejected'],true));
        }
        $payload=$schema->payload($setting->model,$file);
        $client=Http::acceptJson()->asJson()->withToken($setting->api_key)->connectTimeout(8)->withOptions(['allow_redirects'=>false]);
        try {
            $count=$client->timeout(25)->post('https://api.openai.com/v1/responses/input_tokens',Arr::only($payload,['model','input','instructions','text','reasoning']));
            if(!$count->successful() || !is_int($input=$count->json('input_tokens')) || $input<1 || $input>1000000) {
                $usage->update(['state'=>'failed','cost_microusd'=>0,'error_code'=>'token_count_failed','completed_at'=>now()]);
                $this->fail('Impossibile preparare la lettura AI. Controlla chiave, modello e disponibilità dell’API. Nessuna elaborazione del modello è stata avviata.',true);
            }
        } catch(ConnectionException) {
            $usage->update(['state'=>'failed','cost_microusd'=>0,'error_code'=>'token_count_connection','completed_at'=>now()]);
            $this->fail('La preparazione AI non ha risposto. Nessuna elaborazione del modello è stata avviata. Puoi usare la lettura locale.',true);
        }
        $reservation=AiPricing::cost(AiPricing::rates($setting->model,$input+1024),$input+1024,AiContractSchema::MAX_OUTPUT);
        $denied=DB::transaction(function()use($usage,$setting,$reservation,$input) {
            $current=AiSetting::lockForUpdate()->findOrFail(1);
            if(!$current->enabled || $current->revision!==$setting->revision) return 'La configurazione AI è cambiata. Riapri la pagina prima di riprovare.';
            $spent=(int)AiUsage::where('started_at','>=',now()->startOfMonth())->sum(DB::raw('COALESCE(cost_microusd,0)+reserved_microusd'));
            if($reservation>$current->document_budget_microusd) return 'Il costo massimo stimato supera il limite per documento. Verifica i limiti in AI e consumi o usa la lettura locale.';
            if($spent+$reservation>$current->monthly_budget_microusd) return 'Il limite mensile AI non consente questa lettura. Le richieste in corso o senza conferma mantengono riservato il loro importo.';
            $rates=AiPricing::rates($setting->model,$input);
            $usage->update(['state'=>'running','reserved_microusd'=>$reservation,'started_at'=>now(),'input_rate'=>$rates['input'],'cached_rate'=>$rates['cached'],'output_rate'=>$rates['output']]);
            return null;
        });
        if($denied) { $usage->update(['state'=>'rejected','cost_microusd'=>0,'error_code'=>'budget_or_configuration','completed_at'=>now()]); $this->fail($denied,true); }
        /** Keep a reservation after an uncertain delivery; repeating a timeout could create a second charge. */
        try {
            $response=$client->timeout(90)->post('https://api.openai.com/v1/responses',$payload);
        } catch(ConnectionException) {
            $usage->update(['state'=>'uncertain','error_code'=>'connection_uncertain','completed_at'=>now()]);
            $this->fail('L’API non ha restituito la conferma. Il consumo è da verificare in AI e consumi; non ripetere subito la richiesta. Puoi usare la lettura locale.');
        }
        $data=$response->json();
        if(!$response->successful()) {
            $uncertain=$response->status()>=500 || $response->status()>=300 && $response->status()<400;
            $usage->update(['state'=>$uncertain ? 'uncertain' : 'failed','cost_microusd'=>$uncertain ? null : 0,'reserved_microusd'=>$uncertain ? $reservation : 0,'error_code'=>'api_'.$response->status(),'completed_at'=>now()]);
            $this->fail('La lettura AI non è disponibile. L’esito è registrato in AI e consumi. Puoi proseguire con la lettura locale.',!$uncertain);
        }
        if(!is_array($data)) { $usage->update(['state'=>'uncertain','error_code'=>'invalid_response','completed_at'=>now()]); $this->fail('Risposta AI non leggibile. Il consumo resta da verificare e non verrà ripetuto automaticamente.'); }
        $reported=$data['usage'] ?? [];
        $known=is_int($reported['input_tokens'] ?? null) && is_int($reported['output_tokens'] ?? null) && $reported['input_tokens']>=0 && $reported['output_tokens']>=0 && $reported['input_tokens']<=2000000 && $reported['output_tokens']<=AiContractSchema::MAX_OUTPUT;
        $writeDetails=$reported['input_tokens_details']['cache_write_tokens_details'] ?? [];
        $unexpectedWrites=(int)($reported['input_tokens_details']['cache_write_tokens'] ?? 0)>0 || (int)($writeDetails['ttl_30m_tokens'] ?? 0)>0 || (int)($writeDetails['ttl_12h_tokens'] ?? 0)>0;
        $knownCost=$known && !$unexpectedWrites;
        $updates=['state'=>'uncertain','completed_at'=>now()];
        if(is_string($data['id'] ?? null) && preg_match('/^resp_[a-z0-9_-]{1,90}$/i',$data['id'])) $updates['response_id']=$data['id'];
        if($known) {
            $rates=AiPricing::rates($usage->model,$reported['input_tokens']);
            $cached=max(0,min($reported['input_tokens'],(int)($reported['input_tokens_details']['cached_tokens'] ?? 0)));
            $updates+=['input_tokens'=>$reported['input_tokens'],'output_tokens'=>$reported['output_tokens'],'cached_input_tokens'=>$cached,'reasoning_tokens'=>max(0,min($reported['output_tokens'],(int)($reported['output_tokens_details']['reasoning_tokens'] ?? 0))),'total_tokens'=>$reported['input_tokens']+$reported['output_tokens'],'input_rate'=>$rates['input'],'cached_rate'=>$rates['cached'],'output_rate'=>$rates['output'],'cost_microusd'=>$knownCost ? AiPricing::cost($rates,$reported['input_tokens'],$reported['output_tokens'],$cached) : null,'reserved_microusd'=>$knownCost ? 0 : $reservation];
        }
        $usage->update($updates);
        try {
            if(($data['status'] ?? '')!=='completed') throw new \UnexpectedValueException;
            $texts=[];
            foreach($data['output'] ?? [] as $output) foreach($output['content'] ?? [] as $content) if(($content['type'] ?? '')==='output_text') $texts[]=$content['text'];
            $extraction=$schema->validate(json_decode(implode('', $texts),true,512,JSON_THROW_ON_ERROR));
            $usage->update(['result'=>$extraction,'state'=>$knownCost ? 'complete' : 'uncertain','error_code'=>$knownCost ? null : ($unexpectedWrites ? 'usage_unexpected_cache' : 'usage_missing')]);
        } catch(\Throwable) {
            $usage->update(['state'=>$knownCost ? 'failed' : 'uncertain','error_code'=>'extraction_invalid']);
            $this->fail('L’AI non ha restituito dati completi e verificabili. Il consumo ricevuto è registrato; puoi usare la lettura locale.',$knownCost);
        }
        return $this->result($usage,false);
    }

    private function result(AiUsage $usage, bool $cached): array
    {
        return ['extraction'=>$usage->result,'usage'=>['id'=>$usage->id,'tokens'=>$usage->total_tokens,'cost'=>AiPricing::format($usage->cost_microusd),'cached'=>$cached,'uncertain'=>$usage->state==='uncertain']];
    }
}
