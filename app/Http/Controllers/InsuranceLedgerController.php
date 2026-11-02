<?php
namespace App\Http\Controllers;

use App\Models\{Client,InsurancePolicy,InsuranceTitle,InsuranceCollection,User};
use App\Services\{InsuranceLedger,ClientDocumentRegistry};
use App\Support\{Money,TableListing};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InsuranceLedgerController extends Controller
{
    private function access(InsuranceTitle $title,bool $write=false): void {$this->authorize($write?'updateRegistry':'view',$title->policy->client);}
    private function filters(Request $request): array
    {
        return $request->validate(['direction'=>['nullable','in:receipt,refund'],'search'=>['nullable','string','max:255'],'month'=>['nullable','date_format:Y-m'],'from'=>['nullable','date_format:Y-m-d'],'to'=>['nullable','date_format:Y-m-d',...($request->filled('from')?['after_or_equal:from']:[])],
            'agency'=>['nullable','regex:/\A(?:[1-9]\d*|direct)\z/'],'company'=>['nullable','integer'],'client'=>['nullable','integer'],'policy'=>['nullable','integer'],'category'=>['nullable','string','max:100'],'subcategory'=>['nullable','string','max:100'],
            'consultant'=>['nullable','integer'],'payment_method'=>['nullable',Rule::in([...InsurancePolicy::PAYMENT_METHODS,...InsuranceCollection::REFUND_METHODS])],'payment_frequency'=>['nullable',Rule::in(InsurancePolicy::PAYMENT_FREQUENCIES)],'state'=>['nullable',Rule::in([...array_keys(InsuranceTitle::STATES),'recorded','voided'])],'kind'=>['nullable',Rule::in(array_keys(InsuranceTitle::KINDS))],'document_type'=>['nullable',Rule::in(array_keys(InsuranceCollection::DOCUMENT_TYPES))],'recipient'=>['nullable',Rule::in(array_keys(InsuranceCollection::RECIPIENTS))]]);
    }
    private function titleQuery(array $filters)
    {
        $query = InsuranceTitle::visibleTo(auth()->user())->when($filters['policy']??null,fn($q,$v)=>$q->where('insurance_policy_id',$v));
        foreach(['company'=>'supplier_company_id','category'=>'contract_snapshot->category','subcategory'=>'contract_snapshot->subcategory','direction'=>'direction'] as $key=>$column) if(filled($filters[$key]??null)) $query->where($column,$filters[$key]);
        if (($filters['agency']??null) === 'direct') $query->whereNull('supplier_agency_id');
        elseif (filled($filters['agency']??null)) $query->where('supplier_agency_id',$filters['agency']);
        if ($filters['client'] ?? null) $query->whereHas('policy',fn($q)=>$q->where('client_id',$filters['client']));
        if ($filters['search'] ?? null) $query->where(fn($q)=>$q->where('contract_snapshot->number','like','%'.$filters['search'].'%')->orWhereHas('policy.client',fn($c)=>$c->where('name','like','%'.$filters['search'].'%')));
        return $query;
    }
    private function dates($query,string $column,array $filters): void
    {
        if(($filters['month']??null) && !($filters['from']??null) && !($filters['to']??null)){$start=\Illuminate\Support\Carbon::createFromFormat('!Y-m',$filters['month']);$query->where($column,'>=',$start->toDateString())->where($column,'<',$start->copy()->addMonth()->toDateString());}
        if($filters['from']??null)$query->where($column,'>=',$filters['from']);if($filters['to']??null)$query->where($column,'<',\Illuminate\Support\Carbon::parse($filters['to'])->addDay()->toDateString());
    }
    public function index(Request $request)
    {
        $this->authorize('viewAny',Client::class);$filters=$this->filters($request);$query=$this->titleQuery($filters);$this->dates($query,'due_on',$filters);
        foreach(['kind','payment_method','payment_frequency'] as $key)if($filters[$key]??null)$query->where($key,$filters[$key]);
        $query->inPaymentState($filters['state']??null);
        $paymentSummary = app(\App\Services\InsurancePaymentSummary::class)->forQuery($query);
        $titles=$query->with(['policy.client','supplierAgency'])->orderBy('due_on')->orderBy('id')->paginate(TableListing::perPage(25))->withQueryString();
        return view('ledger.titles',compact('titles','filters','paymentSummary')+$this->options()+$this->context($filters));
    }
    private function context(array $filters): array
    {
        $contextParameters=array_filter(\Illuminate\Support\Arr::only($filters,['client','policy']));
        $contextClient=($filters['client']??null) ? Client::current()->visibleTo(auth()->user())->findOrFail($filters['client']) : null;
        $contextPolicy=($filters['policy']??null) ? InsurancePolicy::whereIn('client_id',Client::current()->visibleTo(auth()->user())->select('id'))->with('client')->findOrFail($filters['policy']) : null;
        abort_if($contextClient && $contextPolicy && (int)$contextClient->id !== (int)$contextPolicy->client_id,404);
        $contextClient ??= $contextPolicy?->client;
        return compact('contextParameters','contextClient','contextPolicy');
    }
    private function options(): array
    {
        return ['agencies'=>\App\Models\SupplierAgency::orderBy('name')->get(['id','name']),'companies'=>\App\Models\SupplierCompany::orderBy('name')->get(['id','name']),'consultants'=>User::where('status','active')->orderBy('name')->get(['id','name']),'branches'=>\App\Models\InsuranceBranch::with('subbranches')->orderBy('name')->get()];
    }
    public function show(InsuranceTitle $title)
    {
        $this->access($title);
        $title->load(['policy.client','supplierAgency','collections.consultant','collections.documents','documents']);
        foreach ($title->collections as $payment) $payment->setRelation('title', $title);
        $deliveries = app(\App\Services\CollectionCommunicationStatus::class)->deliveries($title->collections);
        return view('ledger.show',compact('title','deliveries')+$this->options());
    }
    public function schedule(InsurancePolicy $policy,InsuranceLedger $ledger)
    {
        $this->authorize('ledger.manage');$this->authorize('view',$policy->client);$rows=$ledger->plan($policy);$fingerprint=$ledger->previewFingerprint($policy,$rows);return view('ledger.schedule',compact('policy','rows','fingerprint'));
    }
    public function generate(Request $request,InsurancePolicy $policy,InsuranceLedger $ledger){$this->authorize('ledger.manage');$data=$request->validate(['confirmed'=>['accepted'],'fingerprint'=>['required','string','size:64']]);$ledger->generate($policy,$data['fingerprint']);return to_route('titles.index',['policy'=>$policy->id])->with('success','Titoli generati.');}
    public function create(Request $request,InsurancePolicy $policy)
    {
        $this->authorize('ledger.manage');$request->validate(['change'=>['nullable','integer']]);
        $change=$request->filled('change') ? $policy->incomingChanges()->where('kind','addition')->findOrFail($request->integer('change')) : null;
        return view('ledger.form',compact('policy','change'));
    }
    public function store(Request $request,InsurancePolicy $policy,InsuranceLedger $ledger)
    {
        $this->authorize('ledger.manage');$data=$request->validate(['kind'=>['required',Rule::in(array_keys(InsuranceTitle::KINDS))],'reference'=>['nullable','string','max:100'],'due_on'=>['required','date_format:Y-m-d'],'coverage_from'=>['required','date_format:Y-m-d'],'coverage_to'=>['required','date_format:Y-m-d','after:coverage_from'],'amount'=>['required','regex:/\A\d{1,10}([.,]\d{1,2})?\z/'],'payment_method'=>['required',Rule::in(InsurancePolicy::PAYMENT_METHODS)],'payment_frequency'=>['required',Rule::in(InsurancePolicy::PAYMENT_FREQUENCIES)],'notes'=>['nullable','string','max:5000']]);
        $request->validate(['insurance_policy_change_id'=>['nullable','integer']]);$data['insurance_policy_change_id']=$request->integer('insurance_policy_change_id') ?: null;
        $data['amount_cents']=Money::cents($data['amount']);unset($data['amount']);if($data['amount_cents']<1)throw \Illuminate\Validation\ValidationException::withMessages(['amount'=>'L’importo deve essere positivo.']);
        return to_route('titles.show',$ledger->manual($policy,$data))->with('success','Titolo registrato.');
    }
    public function cancel(Request $request,InsuranceTitle $title,InsuranceLedger $ledger){$this->authorize('ledger.manage');$data=$request->validate(['reason'=>['required','string','min:5','max:1000']]);$ledger->cancel($title,$data['reason']);return back()->with('success','Titolo annullato e conservato nello storico.');}
    public function collect(Request $request,InsuranceTitle $title,InsuranceLedger $ledger)
    {
        $this->savePayment($request,$title,$ledger);
        return to_route('titles.show',$title)->with('success',$title->direction === 'refund' ? 'Rimborso registrato.' : 'Incasso registrato.');
    }
    public function collectForPolicy(Request $request,InsurancePolicy $policy,InsuranceLedger $ledger)
    {
        $this->authorize('ledger.manage');
        $this->authorize('updateRegistry',$policy->client);
        $request->validate(['title_id'=>['required','integer']]);
        /** Resolve the selected title through this policy so another contract cannot be charged. */
        $title=$policy->titles()->findOrFail($request->integer('title_id'));
        $this->savePayment($request,$title,$ledger);
        return redirect()->to(route('clients.policies.show',[$policy->client_id,$policy,'tab'=>'payments']))->with('success','Pagamento registrato. Titolo saldato e contabilità aggiornata.');
    }
    private function savePayment(Request $request,InsuranceTitle $title,InsuranceLedger $ledger): void
    {
        $this->access($title,true);$data=$request->validate(['received_on'=>['required','date_format:Y-m-d','before_or_equal:today'],'amount'=>['required','regex:/\A\d{1,10}([.,]\d{1,2})?\z/'],'payment_method'=>['required',Rule::in($title->direction === 'refund' ? InsuranceCollection::REFUND_METHODS : InsurancePolicy::PAYMENT_METHODS)],'recipient'=>['required',Rule::in(array_keys(InsuranceCollection::RECIPIENTS))],'document_type'=>['required',Rule::in(array_keys(InsuranceCollection::DOCUMENT_TYPES))],'reference'=>['nullable','string','max:150'],'notes'=>['nullable','string','max:5000'],'consultant_id'=>['nullable','integer',Rule::exists('users','id')->where('status','active')],'idempotency_key'=>['required','uuid'],'files'=>['nullable','array','max:10'],'files.*'=>['file','max:10240','mimes:pdf','extensions:pdf']]);
        if(!auth()->user()->canManageSystem())$data['consultant_id']=auth()->id();$data['consultant_id']??=auth()->id();$data['amount_cents']=Money::cents($data['amount']);unset($data['amount'],$data['files']);
        $payment=DB::transaction(function()use($ledger,$title,$data,$request){
            /** Match the registry's client-then-policy lock order before storing payment attachments. */
            $client=Client::whereKey($title->policy->client_id)->lockForUpdate()->firstOrFail();
            abort_if($client->merged_into_id,409,'Anagrafica già unificata. Riapri la polizza prima di registrare l’incasso.');
            $this->authorize('updateRegistry',$client);
            $payment=$ledger->collect($title,$data);
            if($payment->wasRecentlyCreated && $request->hasFile('files'))app(ClientDocumentRegistry::class)->uploadMany($client,$request->file('files'),['document_category'=>'collection','insurance_policy_id'=>$title->insurance_policy_id,'insurance_title_id'=>$title->id,'insurance_collection_id'=>$payment->id]);
            return $payment;
        });
    }
    public function void(Request $request,InsuranceCollection $collection,InsuranceLedger $ledger){$this->authorize('ledger.manage');$data=$request->validate(['reason'=>['required','string','min:5','max:1000']]);$ledger->void($collection,$data['reason']);return to_route('titles.show',$collection->insurance_title_id)->with('success','Incasso stornato. Lo storico è conservato.');}
    public function document(Request $request,InsuranceTitle $title)
    {
        $this->access($title,true);$request->validate(['files'=>['required','array','min:1','max:10'],'files.*'=>['file','max:10240','mimes:pdf','extensions:pdf']]);
        app(ClientDocumentRegistry::class)->uploadMany($title->policy->client,$request->file('files'),['document_category'=>'quietance','insurance_policy_id'=>$title->insurance_policy_id,'insurance_title_id'=>$title->id]);return back()->with('success','Documenti della quietanza caricati.');
    }
    public function collections(Request $request)
    {
        $this->authorize('viewAny',Client::class);$filters=$this->filters($request);
        if(!$request->hasAny(['month','from','to']))$filters+=['from'=>today()->toDateString(),'to'=>today()->toDateString()];
        $query=InsuranceCollection::whereIn('insurance_title_id',$this->titleQuery($filters)->select('id'));$this->dates($query,'received_on',$filters);
        foreach(['payment_method','document_type','recipient'] as $key)if($filters[$key]??null)$query->where($key,$filters[$key]);if($filters['consultant']??null)$query->where('consultant_id',$filters['consultant']);
        if(($filters['state']??null)==='voided')$query->whereNotNull('voided_at');elseif(($filters['state']??null)==='recorded')$query->whereNull('voided_at');
        $totals=(clone $query)->whereNull('voided_at')->selectRaw(InsuranceCollection::totalsSql())->first();
        $summary = app(\App\Services\CollectionSummary::class);
        $groups = $summary->forQuery($query);
        $options = $this->options();
        $agencyCompanies = $summary->agencyCompanies($options['agencies']);
        $collections=$query->with(['title.policy.client','title.supplierAgency','consultant','documents'])->orderByDesc('received_on')->orderByDesc('id')->paginate(TableListing::perPage(25))->withQueryString();
        return view('ledger.collections',compact('collections','filters','totals','groups','agencyCompanies')+$options+$this->context($filters));
    }
    public function renewal(Request $request, InsurancePolicy $policy)
    {
        return app(PolicyTransitionController::class)->create($request,$policy,'renewal',app(\App\Services\PolicyContractTransitions::class));
    }
    public function renewalPreview(Request $request, InsurancePolicy $policy)
    {
        return app(PolicyTransitionController::class)->preview($request,$policy,'renewal',app(\App\Services\PolicyContractTransitions::class));
    }
    public function renew(Request $request, InsurancePolicy $policy)
    {
        return app(PolicyTransitionController::class)->store($request,$policy,'renewal',app(\App\Services\PolicyContractTransitions::class));
    }
}
