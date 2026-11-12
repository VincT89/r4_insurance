<?php

namespace App\Http\Controllers;

use App\Models\{AiSetting, AiUsage, Client};
use App\Services\{AiConfiguration, AiPricing, AuditLogService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Validation\{Rule, ValidationException};

class AiController extends Controller
{
    public function index(Request $request, AiConfiguration $configuration)
    {
        $this->authorize('system.admin');
        $filters=$request->validate(['from'=>['nullable','date_format:Y-m-d'],'to'=>['nullable','date_format:Y-m-d',...($request->filled('from') ? ['after_or_equal:from'] : [])]]);
        $from=$filters['from'] ?? today()->startOfMonth()->toDateString();
        $to=$filters['to'] ?? today()->toDateString();
        $installed=$configuration->settings()!==null;
        if($installed) app(\App\Services\AiContractReader::class)->recoverAbandoned();
        $query=AiUsage::query()->where('started_at','>=',$from)->where('started_at','<',Carbon::parse($to)->addDay()->toDateString());
        $aggregate="COUNT(*) AS requests, COALESCE(SUM(input_tokens),0) AS input_tokens, COALESCE(SUM(cached_input_tokens),0) AS cached_tokens, COALESCE(SUM(output_tokens),0) AS output_tokens, COALESCE(SUM(total_tokens),0) AS total_tokens, COALESCE(SUM(cost_microusd),0) AS cost, COALESCE(SUM(reserved_microusd),0) AS reserved, COALESCE(SUM(CASE WHEN state='uncertain' THEN 1 ELSE 0 END),0) AS uncertain";
        $totals=$installed ? (clone $query)->selectRaw($aggregate)->first() : (object)array_fill_keys(['requests','input_tokens','cached_tokens','output_tokens','total_tokens','cost','reserved','uncertain'],0);
        $groups=$installed ? (clone $query)->select(['feature','model'])->selectRaw($aggregate)->groupBy('feature','model')->orderBy('feature')->get() : collect();
        $usageColumns=['id','user_id','feature','model','state','input_tokens','cached_input_tokens','output_tokens','reasoning_tokens','total_tokens','cost_microusd','reserved_microusd','started_at','error_code','reconciled_at'];
        if($installed && Schema::hasColumn('ai_usages','insurance_quote_id')) $usageColumns[]='insurance_quote_id';
        $usages=$installed ? (clone $query)->with('user:id,name')->orderByDesc('id')->paginate(25,$usageColumns)->withQueryString() : null;
        return response()->view('ai.index',compact('installed','totals','groups','usages','from','to')+['settings'=>$configuration->publicValues(),'ready'=>$configuration->ready()])->header('Cache-Control','no-store, private');
    }

    public function update(Request $request, AiConfiguration $configuration)
    {
        $this->authorize('system.admin');
        abort_unless($configuration->settings(),503,'Completa prima l’aggiornamento del database per la sezione AI.');
        $data=$request->validate(['enabled'=>['required','boolean'],'replace_api_key'=>['sometimes','boolean'],'ai_api_key'=>[Rule::requiredIf($request->boolean('replace_api_key')),'nullable','string','min:20','max:2048','not_regex:/[\x00-\x20\x7f]/'],'model'=>['required',Rule::in(array_keys(AiPricing::MODELS))],'monthly_budget'=>['required','numeric','decimal:0,2','min:0.01','max:10000'],'document_budget'=>['required','numeric','decimal:0,2','min:0.01','lte:monthly_budget']],['ai_api_key.required'=>'Inserisci la nuova chiave oppure premi Annulla modifica per conservare quella salvata.']);
        DB::transaction(function()use($request,$data) {
            $setting=AiSetting::lockForUpdate()->findOrFail(1);
            $key=filled($data['ai_api_key'] ?? null) ? $data['ai_api_key'] : $setting->api_key;
            if($data['enabled'] && !$key) throw ValidationException::withMessages(['ai_api_key'=>'Inserisci la chiave API OpenAI prima di abilitare la lettura.']);
            $revision=$key!==$setting->api_key || $data['model']!==$setting->model ? (string)Str::uuid() : $setting->revision;
            $setting->fill(['api_key'=>$key,'enabled'=>(bool)$data['enabled'],'model'=>$data['model'],'monthly_budget_microusd'=>\App\Support\Money::cents($data['monthly_budget'])*10000,'document_budget_microusd'=>\App\Support\Money::cents($data['document_budget'])*10000,'revision'=>$revision,'updated_by'=>$request->user()->id])->save();
            app(AuditLogService::class)->log('ai_settings_updated',$setting,description:'Aggiornata la configurazione AI e i limiti di spesa.');
        });
        return to_route('ai.index')->with('success','Configurazione AI salvata. Salvare la chiave non esegue letture a pagamento.');
    }

    public function checkConnection(Request $request, AiConfiguration $configuration, \App\Services\AiConnectionChecker $checker)
    {
        $this->authorize('system.admin');
        $data = $request->validate(['revision'=>['required','uuid']]);
        $setting = $configuration->settings();
        $changed = ['state'=>'error','code'=>'configuration_changed','message'=>'La configurazione è cambiata. Ricarica la pagina e ripeti la verifica.'];
        if (!$setting) {
            $result = ['state'=>'error','code'=>'not_installed','message'=>'La configurazione AI non è ancora disponibile in questo archivio.'];
        } elseif ($setting->revision !== $data['revision']) {
            $result = $changed;
        } else {
            $result = $checker->check($setting);
            /** Un esito riferito a una chiave o a un modello sostituiti nel frattempo non conferma la configurazione corrente. */
            if ($configuration->settings()?->revision !== $setting->revision) $result = $changed;
        }
        $result += ['revision'=>$data['revision'],'checked_at'=>now()->format('d/m/Y H:i')];
        if ($request->expectsJson()) return response()->json($result)->header('Cache-Control','no-store, private');
        return redirect()->to(route('ai.index').'#ai-configuration')->with('ai_connection_check',$result);
    }

    public function extract(Request $request, \App\Services\AiContractReader $reader)
    {
        $this->authorize('create',Client::class);
        $request->validate(['contract'=>['required','file','max:10240','mimes:pdf','extensions:pdf'],'ai_processing'=>['accepted'],'retry'=>['sometimes','boolean']]);
        $result=$reader->read($request->file('contract'),$request->user(),$request->boolean('retry'));
        return response()->json($result)->header('Cache-Control','no-store, private');
    }

    public function reconcile(Request $request, AiUsage $usage)
    {
        $this->authorize('system.admin');
        $data=$request->validate(['input_tokens'=>['required','integer','between:0,2000000'],'output_tokens'=>['required','integer','between:0,6000'],'cached_input_tokens'=>['required','integer','min:0','lte:input_tokens'],'reported_cost_usd'=>['required','numeric','decimal:0,6','between:0,10000'],'reconciliation_note'=>['required','string','min:10','max:1000']]);
        DB::transaction(function()use($usage,$request,$data) {
            AiSetting::lockForUpdate()->findOrFail(1);
            $usage=AiUsage::whereKey($usage->id)->lockForUpdate()->firstOrFail();
            if($usage->state!=='uncertain') throw ValidationException::withMessages(['reconciliation_note'=>'Questo consumo è già stato verificato oppure è ancora in corso.']);
            $usage->update(\Illuminate\Support\Arr::except($data,['reported_cost_usd'])+['total_tokens'=>$data['input_tokens']+$data['output_tokens'],'cost_microusd'=>AiPricing::dollars((string)$data['reported_cost_usd']),'reserved_microusd'=>0,'state'=>$usage->result ? 'complete' : 'failed','reconciled_at'=>now(),'reconciled_by'=>$request->user()->id]);
            app(AuditLogService::class)->log('ai_usage_reconciled',$usage,description:'Verificati manualmente i consumi AI della richiesta #'.$usage->id.' sulla piattaforma del fornitore.');
        });
        return to_route('ai.index')->with('success','Consumo verificato e importo riservato liberato. Lo storico della richiesta è conservato.');
    }
}
