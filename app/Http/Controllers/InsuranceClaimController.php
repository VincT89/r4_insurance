<?php

namespace App\Http\Controllers;

use App\Models\{Client, InsuranceBranch, InsuranceClaim, InsuranceClaimActivity, InsurancePolicy, SupplierAgency, SupplierCompany, User};
use App\Services\InsuranceClaims;
use App\Support\TableListing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InsuranceClaimController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny',Client::class);
        $filters=$this->filters($request);
        $query=InsuranceClaim::visibleTo($request->user())->with(['client','policy.supplierAgency','assignee','nextActivity']);
        if (!empty($filters['q'])) $query->where(fn($q)=>$q->where('number','like','%'.$filters['q'].'%')->orWhereHas('policy',fn($p)=>$p->where('number','like','%'.$filters['q'].'%'))->orWhereHas('client',fn($c)=>$c->where('name','like','%'.$filters['q'].'%')));
        foreach (['client'=>'client_id','policy'=>'insurance_policy_id','operator'=>'assigned_to'] as $key=>$column) if (!empty($filters[$key])) $query->where($column,$filters[$key]);
        if (($filters['status']??'')==='open_only') $query->open();
        elseif (!empty($filters['status'])) $query->where('status',$filters['status']);
        $query->whereHas('policy',fn($p)=>$this->policyFilters($p,$filters));
        $basis=$filters['date_basis']??'event_on';
        if ($basis==='activity') $query->whereHas('activities',fn($a)=>$this->dates($a->where('status','pending'),'due_on',$filters));
        else $this->dates($query,$basis,$filters);
        if (!empty($filters['reminder'])) $query->open()->whereHas('activities',fn($a)=>$this->reminderFilter($a->where('status','pending'),$filters['reminder']));
        $claims=$query->orderByDesc('event_on')->orderByDesc('id')->paginate(TableListing::perPage())->withQueryString();
        return view('claims.index',['claims'=>$claims,'filters'=>$filters,'options'=>$this->options($request),'canCreate'=>$this->canCreate($request)]);
    }

    public function create(Request $request,InsuranceClaims $service)
    {
        $this->authorize('viewAny',Client::class); abort_unless($this->canCreate($request),403);
        $filters=$this->filters($request);
        $policy=null; $policies=null; $operators=collect(); $contextHash=null;
        if (!empty($filters['policy'])) {
            $policy=InsurancePolicy::whereIn('client_id',Client::current()->visibleTo($request->user())->select('id'))->with(['client','supplierAgency','vehicle'])->findOrFail($filters['policy']);
            $this->authorize('updateRegistry',$policy->client);
            $operators=$service->operators($policy->client); $contextHash=$service->context($policy);
        } else {
            $query=InsurancePolicy::whereIn('client_id',Client::current()->visibleTo($request->user())->select('id'))->with(['client','supplierAgency']);
            if (!empty($filters['client'])) $query->where('client_id',$filters['client']);
            if (!empty($filters['q'])) $query->where(fn($q)=>$q->where('number','like','%'.$filters['q'].'%')->orWhereHas('client',fn($c)=>$c->where('name','like','%'.$filters['q'].'%'))->orWhereHas('vehicle',fn($v)=>$v->where('plate','like','%'.$filters['q'].'%')));
            $this->policyFilters($query,$filters);
            $policies=$query->orderByDesc('id')->paginate(TableListing::perPage())->withQueryString();
        }
        return view('claims.form',['claim'=>null,'policy'=>$policy,'policies'=>$policies,'filters'=>$filters,'options'=>$this->options($request),'operators'=>$operators,'contextHash'=>$contextHash]);
    }

    public function store(Request $request,InsuranceClaims $service)
    {
        $this->authorize('viewAny',Client::class); abort_unless($this->canCreate($request),403);
        $data=$request->validate($this->dataRules()+$this->fileRules()+[
            'policy_id'=>['required','integer'],'context_hash'=>['required','string','size:64'],'request_key'=>['required','uuid'],
            'activity_label'=>['nullable','required_with:activity_due_on','string','max:200'],
            'activity_due_on'=>['nullable','required_with:activity_label','date_format:Y-m-d'],
        ]);
        $policy=InsurancePolicy::whereIn('client_id',Client::current()->visibleTo($request->user())->select('id'))->findOrFail($data['policy_id']);
        $claim=$service->create($request->user(),$policy,$data,$request->file('files',[]));
        return to_route('claims.show',$claim)->with('success','Sinistro aperto.');
    }

    public function show(Request $request,InsuranceClaim $claim)
    {
        $this->access($request,$claim);
        $claim->load(['client','policy.supplierAgency','policy.vehicle','assignee']);
        $activities=$claim->activities()->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderBy('due_on')->orderBy('id')->paginate(TableListing::perPage(10),['*'],'activities_page')->withQueryString();
        $events=$claim->events()->with('files')->latest('id')->paginate(TableListing::perPage(15),['*'],'history_page')->withQueryString();
        $files=$claim->files()->with('uploader')->latest('id')->paginate(TableListing::perPage(10),['*'],'files_page')->withQueryString();
        return view('claims.show',compact('claim','activities','events','files'));
    }

    public function edit(Request $request,InsuranceClaim $claim,InsuranceClaims $service)
    {
        $this->access($request,$claim); abort_unless($claim->editableBy($request->user()),403);
        return view('claims.form',['claim'=>$claim,'policy'=>$claim->policy,'operators'=>$service->operators($claim->client),'contextHash'=>null]);
    }

    public function change(Request $request,InsuranceClaim $claim,InsuranceClaims $service,string $action)
    {
        $this->access($request,$claim); abort_unless($claim->editableBy($request->user()),403);
        $rules=['request_key'=>['required','uuid'],'version'=>['required','integer','min:1']];
        $rules+=match($action) {
            'updated'=>$this->dataRules()+['reason'=>['required','string','max:2000']],
            'status'=>['status'=>['required',Rule::in(array_keys(InsuranceClaim::STATUSES))],'reason'=>['required','string','max:2000']],
            'note'=>$this->fileRules()+['note'=>['nullable','string','max:5000']],
            'activity_added'=>['label'=>['required','string','max:200'],'due_on'=>['required','date_format:Y-m-d']],
            'activity_completed','activity_cancelled'=>['activity_id'=>['required','integer'],'reason'=>['required','string','max:2000']],
            'activity_rescheduled'=>['activity_id'=>['required','integer'],'due_on'=>['required','date_format:Y-m-d'],'reason'=>['required','string','max:2000']],
        };
        $service->change($request->user(),$claim,$request->validate($rules),$action,$action==='note'?$request->file('files',[]):[]);
        return to_route('claims.show',$claim)->with('success','Pratica aggiornata e operazione registrata nello storico.');
    }

    public function activity(Request $request,InsuranceClaim $claim,InsuranceClaimActivity $activity)
    {
        $this->access($request,$claim); abort_unless($claim->editableBy($request->user()),403);
        abort_unless($activity->insurance_claim_id===$claim->id,404);
        abort_unless($activity->status==='pending' && !$claim->isClosed(),409,'Questa attività non è più aperta.');
        return view('claims.activity',compact('claim','activity'));
    }

    public function updateActivity(Request $request,InsuranceClaim $claim,InsuranceClaimActivity $activity,InsuranceClaims $service)
    {
        $this->access($request,$claim); abort_unless($activity->insurance_claim_id===$claim->id,404);
        $data=$request->validate(['operation'=>['required','in:activity_completed,activity_rescheduled,activity_cancelled']]);
        $request->merge(['activity_id'=>$activity->id]);
        return $this->change($request,$claim,$service,$data['operation']);
    }

    private function access(Request $request,InsuranceClaim $claim): void
    {
        $this->authorize('viewAny',Client::class); $this->authorize('view',$claim->client);
        abort_if($claim->client->merged_into_id,404);
    }

    private function canCreate(Request $request): bool { return $request->user()->can('create', Client::class); }

    private function dataRules(): array
    {
        return ['number'=>['nullable','string','max:100'],'event_on'=>['required','date_format:Y-m-d','before_or_equal:today'],'reported_on'=>['nullable','date_format:Y-m-d','after_or_equal:event_on','before_or_equal:today'],'description'=>['required','string','max:10000'],'assigned_to'=>['required','integer']];
    }

    private function fileRules(): array { return ['files'=>['nullable','array','max:10'],'files.*'=>['file','max:10240','mimes:pdf,jpg,jpeg,png','extensions:pdf,jpg,jpeg,png']]; }

    private function filters(Request $request): array
    {
        return $request->validate(['q'=>['nullable','string','max:150'],'client'=>['nullable','integer'],'policy'=>['nullable','integer'],'agency'=>['nullable','integer'],'company'=>['nullable','integer'],'branch'=>['nullable','integer'],'subbranch'=>['nullable','integer'],'operator'=>['nullable','integer'],'status'=>['nullable',Rule::in(array_merge(['open_only'],array_keys(InsuranceClaim::STATUSES)))],'date_basis'=>['nullable','in:event_on,reported_on,created_at,activity'],'from'=>['nullable','date_format:Y-m-d'],'to'=>array_merge(['nullable','date_format:Y-m-d'],$request->filled('from')?['after_or_equal:from']:[]),'reminder'=>['nullable','in:overdue,today,3,15,all']]);
    }

    private function policyFilters($query,array $filters): void
    {
        foreach (['agency'=>'supplier_agency_id','company'=>'supplier_company_id','branch'=>'insurance_branch_id','subbranch'=>'insurance_subbranch_id'] as $key=>$column) if (!empty($filters[$key])) $query->where($column,$filters[$key]);
    }

    private function dates($query,string $column,array $filters): void
    {
        if (!empty($filters['from'])) $query->whereDate($column,'>=',$filters['from']);
        if (!empty($filters['to'])) $query->whereDate($column,'<=',$filters['to']);
    }

    private function reminderFilter($query,string $filter): void
    {
        if ($filter==='overdue') $query->whereDate('due_on','<',today());
        elseif ($filter==='today') $query->whereDate('due_on','=',today());
        elseif (in_array($filter,['3','15'],true)) $query->whereDate('due_on','<=',today()->addDays((int)$filter));
    }

    private function options(Request $request): array
    {
        $policies=InsurancePolicy::whereIn('client_id',Client::current()->visibleTo($request->user())->select('id'));
        return ['agencies'=>SupplierAgency::whereIn('id',(clone $policies)->select('supplier_agency_id'))->orderBy('name')->get(['id','name']),
            'companies'=>SupplierCompany::whereIn('id',(clone $policies)->select('supplier_company_id'))->orderBy('name')->get(['id','name']),
            'branches'=>InsuranceBranch::whereIn('id',(clone $policies)->select('insurance_branch_id'))->with('subbranches')->orderBy('name')->get(),
            'operators'=>User::whereIn('id',InsuranceClaim::visibleTo($request->user())->select('assigned_to'))->orderBy('name')->get(['id','name'])];
    }
}
