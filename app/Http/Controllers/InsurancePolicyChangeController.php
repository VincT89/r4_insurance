<?php

namespace App\Http\Controllers;

use App\Models\{Client, InsurancePolicy, InsurancePolicyChange, InsuranceQuote};
use App\Services\InsurancePolicyChanges;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InsurancePolicyChangeController extends Controller
{
    public function create(Request $request, Client $client, InsurancePolicy $policy, InsurancePolicyChanges $workflow)
    {
        abort_unless($policy->client_id === $client->id, 404);
        $data = $request->validate(['kind'=>['required',Rule::in(array_keys(InsurancePolicyChange::KINDS))], 'quote_id'=>['nullable','integer']]);
        $kind = $data['kind'];
        $workflow->check($policy,$kind);
        $quotes = InsuranceQuote::where('status','approved')->whereDate('valid_until','>=',today())->whereDoesntHave('policyChange')->whereDoesntHave('policy')
            ->whereHas('quoteCase',fn($q)=>$q->where('client_id',$client->id)->where('current_policy_id',$policy->id)->where('purpose',$kind)->where('status','approved'))
            ->with(['quoteCase','company','agency','documents'])->latest('id')->get();
        $quoteId = $request->input('quote_id', $request->old('quote_id'));
        $quote = $quoteId ? $quotes->firstWhere('id',$quoteId) : null;
        abort_if($quoteId && !$quote, 404);
        return view('policy-changes.create',compact('client','policy','kind','quotes','quote')+['fingerprint'=>$quote ? $workflow->fingerprint($policy,$quote) : null]);
    }

    public function store(Request $request, Client $client, InsurancePolicy $policy, InsurancePolicyChanges $workflow)
    {
        abort_unless($policy->client_id === $client->id, 404);
        $this->authorize('updateRegistry',$client);
        $data = $request->validate([
            'kind'=>['required',Rule::in(array_keys(InsurancePolicyChange::KINDS))], 'quote_id'=>['required','integer'],
            'number'=>[Rule::requiredIf($request->input('kind')==='replacement'),Rule::prohibitedIf($request->input('kind')!=='replacement'),'string','max:100'],
            'reference'=>[Rule::requiredIf($request->input('kind')==='addition'),'nullable','string','max:100'],
            'description'=>['required','string','min:10','max:5000'], 'request_key'=>['required','uuid'], 'fingerprint'=>['required','string','size:64'],
            'confirmed'=>['accepted'], 'documents'=>['nullable','array','list','max:10'],
            'documents.*'=>['file','mimes:pdf,jpg,jpeg,png','extensions:pdf,jpg,jpeg,png','max:10240'],
        ]);
        if (collect($request->file('documents',[]))->sum(fn($file)=>$file->getSize()) > 50*1024*1024) throw \Illuminate\Validation\ValidationException::withMessages(['documents'=>'Il totale degli allegati non può superare 50 MB.']);
        $change = $workflow->record($client,$policy,$data,$request->file('documents',[]));
        return to_route('clients.policy-changes.show',[$client,$change])->with('success','Variazione registrata. Prepara i moduli da far firmare.');
    }

    public function show(Client $client, InsurancePolicyChange $change)
    {
        $this->authorize('view',$client);
        abort_unless($change->sourcePolicy->client_id===$client->id && $change->resultPolicy->client_id===$client->id,404);
        $change->load(['sourcePolicy','resultPolicy','author','quote.quoteCase','documents.uploader','formPackages.documents.latestSubmission']);
        return view('policy-changes.show',compact('client','change'));
    }

    public function documents(Request $request, Client $client, InsurancePolicyChange $change)
    {
        $this->authorize('updateRegistry',$client);
        abort_unless($change->sourcePolicy->client_id===$client->id && $change->resultPolicy->client_id===$client->id,404);
        $request->validate(['documents'=>['required','array','list','min:1','max:10'],'documents.*'=>['file','mimes:pdf,jpg,jpeg,png','extensions:pdf,jpg,jpeg,png','max:10240']]);
        $files=$request->file('documents');
        if (collect($files)->sum(fn($file)=>$file->getSize()) > 50*1024*1024) throw \Illuminate\Validation\ValidationException::withMessages(['documents'=>'Il totale degli allegati non può superare 50 MB.']);
        $stored=[];
        try {
            \Illuminate\Support\Facades\DB::transaction(function()use($client,$change,$files,&$stored) {
                $stored=app(\App\Services\PrivateAttachmentBatch::class)->upload($client,$files,['insurance_policy_id'=>$change->result_policy_id,'insurance_policy_change_id'=>$change->id,'document_category'=>'policy']);
                app(\App\Services\InsuranceLedger::class)->log($change->resultPolicy,'policy_change_documents','Aggiunti '.count($stored).' allegati alla variazione #'.$change->id.'.');
            });
        } catch (\Throwable $exception) {
            foreach($stored as $file) \Illuminate\Support\Facades\Storage::disk($file->disk)->delete($file->path);
            throw $exception;
        }
        return to_route('clients.policy-changes.show',[$client,$change])->with('success','Allegati aggiunti allo storico della variazione.');
    }
}
