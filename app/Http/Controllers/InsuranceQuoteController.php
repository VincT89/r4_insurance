<?php
namespace App\Http\Controllers;

use App\Models\{Client,InsuranceQuoteCase,InsuranceQuote,InsuranceBranch,InsurancePolicy,SupplierCompany};
use App\Services\{InsuranceQuoteWorkflow, QuoteRequestDocuments, QuoteVehicleContext};
use App\Support\TableListing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\{DB, Storage};

class InsuranceQuoteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny',Client::class);
        $filters=$request->validate(['search'=>['nullable','string','max:255'],'status'=>['nullable','in:open,approved,closed'],'company'=>['nullable','integer'],'from'=>['nullable','date_format:Y-m-d'],'to'=>['nullable','date_format:Y-m-d'],'client'=>['nullable','integer'],'plate'=>['nullable','string','max:20'],'cu'=>['nullable','integer','between:1,18']]);
        $selectedClient=($filters['client']??null) ? Client::current()->visibleTo($request->user())->findOrFail($filters['client']) : null;
        $query=InsuranceQuoteCase::visibleTo($request->user())->with(['client','branch','subbranch','quotes.company']);
        if($filters['search']??null)$query->where(fn($q)=>$q->where('subject','like','%'.$filters['search'].'%')->orWhereHas('client',fn($q)=>$q->where('name','like','%'.$filters['search'].'%')));
        foreach(['status','client'=>'client_id'] as $key=>$column){if(is_int($key))$key=$column;if($filters[$key]??null)$query->where($column,$filters[$key]);}
        if($filters['company']??null)$query->whereHas('quotes',fn($q)=>$q->where('supplier_company_id',$filters['company']));
        if($filters['plate']??null)$query->where('vehicle_plate','like','%'.strtoupper(trim($filters['plate'])).'%');
        if($filters['cu']??null)$query->where('merit_class_cu',$filters['cu']);
        if($filters['from']??null)$query->whereDate('created_at','>=',$filters['from']);if($filters['to']??null)$query->whereDate('created_at','<=',$filters['to']);
        return view('quotes.index',['cases'=>$query->latest('id')->paginate(TableListing::perPage(25))->withQueryString(),'filters'=>$filters,'selectedClient'=>$selectedClient,'companies'=>SupplierCompany::orderBy('name')->get()]);
    }
    public function create(Request $request)
    {
        $this->authorize('create',Client::class);$request->validate(['client_id'=>['nullable','integer'],'purpose'=>['nullable',Rule::in(array_keys(InsuranceQuoteCase::PURPOSES))],'current_policy_id'=>['nullable','integer']]);
        $clientId=$request->input('client_id', $request->old('client_id'));
        $client=$clientId ? Client::current()->visibleTo($request->user())->findOrFail($clientId) : new Client;
        if($client->exists)$this->authorize('updateRegistry',$client);
        $currentPolicies=$client->exists ? $client->policies()->orderBy('number')->get() : collect();
        $source=$request->filled('current_policy_id') ? $currentPolicies->firstWhere('id',$request->integer('current_policy_id')) : null;
        abort_if($request->filled('current_policy_id') && !$source,404);
        return view('quotes.create',['client'=>$client,'currentPolicies'=>$currentPolicies,'source'=>$source,'purpose'=>$request->input('purpose','new'),'vehicles'=>$client->exists ? $client->vehicles()->orderBy('plate')->get() : collect(),'recipientGroups'=>\App\Models\QuoteRecipientGroup::where('status','active')->with(['companies'=>fn($q)=>$q->where('supplier_companies.status','active')])->orderBy('cu_from')->orderBy('name')->get(),'branches'=>InsuranceBranch::where('status','active')->with(['subbranches'=>fn($q)=>$q->where('status','active')])->orderBy('name')->get()]);
    }
    public function store(Request $request)
    {
        $data=$request->validate([
            'client_id'=>['required','integer'],
            'purpose'=>['sometimes',Rule::in(array_keys(InsuranceQuoteCase::PURPOSES))],
            'insurance_branch_id'=>['required','integer',Rule::exists('insurance_branches','id')->where('status','active')],
            'insurance_subbranch_id'=>['nullable','integer',Rule::exists('insurance_subbranches','id')->where('insurance_branch_id',$request->input('insurance_branch_id'))->where('status','active')],
            'subject'=>['required','string','max:255'],'notes'=>['nullable','string','max:5000'],
            'requester_name'=>['nullable','string','max:255'],
            'current_policy_id'=>[Rule::requiredIf(in_array($request->input('purpose'),['replacement','addition'],true)),'nullable','integer',Rule::exists('insurance_policies','id')->where('client_id',$request->input('client_id'))],
            'current_policy_reference'=>['nullable','string','max:500',Rule::prohibitedIf($request->filled('current_policy_id'))],
            'current_coverages'=>['nullable','string','max:5000'],
            'additional_coverages'=>['required_if:purpose,addition','nullable','string','max:5000'],
        ] + QuoteRequestDocuments::rules() + QuoteVehicleContext::rules());
        $client=Client::current()->findOrFail($data['client_id']);$this->authorize('updateRegistry',$client);
        $documents=app(QuoteRequestDocuments::class); $uploads=QuoteRequestDocuments::uploads($request); $documents->check($uploads); $stored=[];
        $values=\Illuminate\Support\Arr::except($data,['files','identity_files','vehicle_files','identity_file_details']);
        try {
            $case=DB::transaction(function () use ($client,$values,$request,$documents,$uploads,&$stored) {
                $client=Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
                $policy=!empty($values['current_policy_id']) ? $client->policies()->whereKey($values['current_policy_id'])->lockForUpdate()->firstOrFail() : null;
                if (in_array($values['purpose']??'new',['replacement','addition'],true)) {
                    app(\App\Services\InsurancePolicyChanges::class)->check($policy,$values['purpose']);
                    if ($values['purpose']==='addition' && (!$policy->supplier_company_id || (int)$policy->insurance_branch_id !== (int)$values['insurance_branch_id'] || (int)$policy->insurance_subbranch_id !== (int)($values['insurance_subbranch_id']??null))) throw \Illuminate\Validation\ValidationException::withMessages(['current_policy_id'=>'Per un’aggiunta conserva compagnia, ramo e sottoramo della polizza. Per un altro ramo apri una nuova polizza.']);
                }
                /** Keep the references used for the request without changing the existing contract or its coverage. */
                $snapshot=$policy ? ['number'=>$policy->number,'company'=>$policy->company,'category'=>$policy->category,'plate'=>$policy->vehicle?->plate,'expires_on'=>$policy->expires_on->toDateString()] : null;
                $context = app(QuoteVehicleContext::class)->values($client, $values, $policy, $values['purpose'] ?? 'new');
                $case=$client->quoteCases()->create(array_replace($values,$context,['created_by'=>$request->user()->id,'current_policy_snapshot'=>$snapshot]));
                $documents->store($client,$uploads,['insurance_quote_case_id'=>$case->id,'vehicle_id'=>$case->vehicle_id],$stored);
                $client->auditLogs()->create(['user_id'=>$request->user()->id,'action'=>'quote_updated','description'=>'Aperta pratica preventivi #'.$case->id.': '.$case->subject,'new_values'=>['case_id'=>$case->id,'current_policy_id'=>$policy?->id,'attachments'=>count($stored)],'created_at'=>now()]);
                return $case;
            });
        } catch (\Throwable $exception) { foreach ($stored as $document) Storage::disk($document->disk)->delete($document->path); throw $exception; }
        return $this->savedDocuments($case,$stored,'Pratica preventivi aperta.');
    }

    public function documents(Request $request, InsuranceQuoteCase $quoteCase, QuoteRequestDocuments $registry)
    {
        $this->authorize('updateRegistry',$quoteCase->client);
        $request->validate(QuoteRequestDocuments::rules());
        $uploads=QuoteRequestDocuments::uploads($request);
        if (!$uploads) throw \Illuminate\Validation\ValidationException::withMessages(['files'=>'Seleziona almeno un documento da aggiungere.']);
        $registry->check($uploads); $stored=[];
        try {
            DB::transaction(function () use ($quoteCase,$request,$registry,$uploads,&$stored) {
                $case=InsuranceQuoteCase::whereKey($quoteCase->id)->lockForUpdate()->firstOrFail();
                abort_unless($case->status==='open',409);
                $registry->store($case->client,$uploads,['insurance_quote_case_id'=>$case->id,'vehicle_id'=>$case->vehicle_id],$stored);
                $case->client->auditLogs()->create(['user_id'=>$request->user()->id,'action'=>'quote_updated','description'=>'Aggiunti '.count($stored).' documenti alla pratica preventivi #'.$case->id.'.','created_at'=>now()]);
            });
        } catch (\Throwable $exception) { foreach ($stored as $document) Storage::disk($document->disk)->delete($document->path); throw $exception; }
        return $this->savedDocuments($quoteCase,$stored,'Documenti aggiunti alla pratica.');
    }

    private function savedDocuments(InsuranceQuoteCase $case, array $documents, string $message)
    {
        $response=to_route('quotes.show',$case)->with('success',$message);
        $warnings=collect($documents)->filter(fn($document)=>$document->document_category==='identity' && (!$document->expires_on || $document->expires_on->lt(today())))->map(fn($document)=>$document->original_name.': '.$document->validity_label);
        return $warnings->isNotEmpty() ? $response->with('warning',$warnings->join(' · ')) : $response;
    }
    public function show(Request $request, InsuranceQuoteCase $quoteCase)
    {
        $this->authorize('view',$quoteCase->client);$quoteCase->load(['branch','subbranch','currentPolicy','documents.uploader','quotes.company.agencies','quotes.agency','quotes.documents','quotes.policy','quotes.policyChange']);
        $companies=$quoteCase->availableCompanies()->get();
        $emailFilters = $request->validate(['email_company'=>['nullable','integer'],'email_state'=>['nullable','in:'.implode(',',array_keys(\App\Models\InsuranceQuoteEmail::STATUSES))],'email_month'=>['nullable','date_format:Y-m']]);
        $emails = $quoteCase->emails()->with(['company','user'])->when($emailFilters['email_company']??null,fn($q,$id)=>$q->where('supplier_company_id',$id))->when($emailFilters['email_state']??null,fn($q,$state)=>$q->where('status',$state));
        if($month=$emailFilters['email_month']??null){$start=\Illuminate\Support\Carbon::createFromFormat('!Y-m',$month);$emails->where('created_at','>=',$start)->where('created_at','<',$start->copy()->addMonth());}
        return view('quotes.show',['case'=>$quoteCase,'companies'=>$companies,'emailFilters'=>$emailFilters,'emails'=>$emails->latest()->paginate(TableListing::perPage(15),['*'],'requests_page')->withQueryString()]);
    }
    public function request(Request $request,InsuranceQuoteCase $quoteCase,InsuranceQuoteWorkflow $workflow)
    {
        $this->authorize('updateRegistry',$quoteCase->client);$data=$request->validate(['supplier_company_id'=>['required','integer'],'requested_on'=>['required','date_format:Y-m-d','before_or_equal:today'],'confirmed'=>['accepted']]);
        $quote=$workflow->request($quoteCase,$data['supplier_company_id'],$data['requested_on']);return redirect()->to(route('quotes.show',$quoteCase).'#proposal-'.$quote->id)->with('success','Richiesta registrata.');
    }
    public function change(Request $request,InsuranceQuote $quote,string $action,InsuranceQuoteWorkflow $workflow)
    {
        $this->authorize('updateRegistry',$quote->quoteCase->client);
        $files=['files'=>['required','array','min:1','max:10'],'files.*'=>['file','max:10240','mimes:pdf','extensions:pdf']];
        $rules=match($action){
            'receive'=>['supplier_agency_id'=>['nullable','integer'],'reference'=>['required','string','max:100'],'product'=>['required','string','max:255'],'starts_on'=>['required','date_format:Y-m-d'],'expires_on'=>['required','date_format:Y-m-d','after:starts_on'],'valid_until'=>['required','date_format:Y-m-d','after_or_equal:received_on'],'received_on'=>['required','date_format:Y-m-d','before_or_equal:today'],'premium'=>['required','numeric',$quote->quoteCase->purpose==='addition' ? 'min:0' : 'min:0.01','max:9999999999.99','decimal:0,2'],'payment_frequency'=>['required',Rule::in(InsurancePolicy::PAYMENT_FREQUENCIES)]]+$files,
            'submit'=>['confirmed'=>['accepted']],
            'approve'=>['approved_on'=>['required','date_format:Y-m-d','before_or_equal:today'],'approval_note'=>['nullable','string','max:5000'],'confirmed'=>['accepted']]+$files,
            'reject'=>['approval_note'=>['required','string','min:5','max:5000']],default=>abort(404)};
        $data=$request->validate($rules);unset($data['files'],$data['confirmed']);$workflow->change($quote,$action,$data,$request->file('files',[]));return redirect()->to(route('quotes.show',$quote->insurance_quote_case_id).'#proposal-'.$quote->id)->with('success','Preventivo aggiornato.');
    }
}
