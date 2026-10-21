<?php
namespace App\Http\Controllers;

use App\Models\{Client, InsuranceFormTemplate, InsuranceFormPackage, InsuranceFormDocument, InsuranceFormSubmission, InsuranceBranch, Attachment};
use App\Services\InsuranceForms;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InsuranceFormController extends Controller
{
    private function policy(Request $request, Client $client) { $request->validate(['policy'=>['nullable','integer']]); return $request->filled('policy') ? $client->policies()->findOrFail($request->integer('policy')) : null; }
    private function package(Client $client, InsuranceFormPackage $package): void { $this->authorize('view',$client); abort_unless($package->client_id === $client->id,404); }
    public function index(Request $request, Client $client)
    {
        $this->authorize('view',$client); $policy=$this->policy($request,$client);
        $filters=$request->validate(['reason'=>['nullable',Rule::in(array_keys(InsuranceFormPackage::REASONS))],'from'=>['nullable','date_format:Y-m-d'],'to'=>['nullable','date_format:Y-m-d','after_or_equal:from']]);
        $query=$client->formPackages()->with(['policy','client','author','documents.latestSubmission']);
        if($policy)$query->where('insurance_policy_id',$policy->id);
        if($filters['reason']??null)$query->where('reason',$filters['reason']);
        if($filters['from']??null)$query->whereDate('created_at','>=',$filters['from']);
        if($filters['to']??null)$query->whereDate('created_at','<=',$filters['to']);
        return view('insurance-forms.index',['client'=>$client,'policy'=>$policy,'filters'=>$filters,'packages'=>$query->latest('id')->paginate(25)->withQueryString()]);
    }
    public function create(Request $request,Client $client,InsuranceForms $forms)
    {
        $this->authorize('updateRegistry',$client); $policy=$this->policy($request,$client); if($policy)abort_unless($policy->editableBy($request->user()),403);
        $request->validate(['change'=>['nullable','integer']]);
        $change=$policy?->incomingChanges()->latest('id')->first();
        abort_if($request->filled('change') && (int)$change?->id!==$request->integer('change'),404);
        return view('insurance-forms.create',['client'=>$client,'policy'=>$policy,'change'=>$change,'templates'=>$forms->available($policy, $client),'contextHash'=>$forms->contextHash($client,$policy)]);
    }
    public function store(Request $request,Client $client,InsuranceForms $forms)
    {
        $this->authorize('updateRegistry',$client); $policy=$this->policy($request,$client);
        $data=$request->validate(['context_hash'=>['required','string','size:64'],'request_key'=>['required','uuid'],'templates'=>['required','array','min:1','max:12'],'templates.*'=>['required','integer','distinct'],'reason'=>['required',Rule::in(array_keys(InsuranceFormPackage::REASONS))],'note'=>['nullable','string','max:2000'],'signer_name'=>['required','string','max:150'],'signer_role'=>['nullable','string','max:150'],'place'=>['required','string','max:150'],'document_date'=>['required','date_format:Y-m-d'],'confirmed'=>['accepted']]);
        $request->validate(['change'=>['nullable','integer']]); $data['change_id']=$request->integer('change') ?: null;
        $package=$forms->createPackage($client,$policy,$data); return to_route('clients.forms.show',[$client,$package])->with('success','Moduli preparati e conservati nel fascicolo.');
    }
    public function show(Client $client,InsuranceFormPackage $package)
    {
        $this->package($client,$package); $package->load(['policy','client','author','documents.generated','documents.latestSubmission','documents.submissions.files','documents.submissions.author','documents.submissions.reviewer']);
        return view('insurance-forms.show',compact('client','package'));
    }
    public function submit(Request $request,Client $client,InsuranceFormPackage $package,InsuranceFormDocument $document,InsuranceForms $forms)
    {
        $this->package($client,$package); $this->authorize('updateRegistry',$client); abort_unless($document->insurance_form_package_id===$package->id,404);
        $data=$request->validate(['request_key'=>['required','uuid'],'files'=>['required','array','min:1','max:10'],'files.*'=>['file','max:10240','mimes:pdf,jpg,jpeg,png','extensions:pdf,jpg,jpeg,png'],'signer_name'=>['required','string','max:150'],'signer_role'=>['nullable','string','max:150'],'signed_on'=>['required','date_format:Y-m-d','before_or_equal:today'],'note'=>['nullable','string','max:2000']]);
        $forms->submit($document,$request->file('files'),$data); return to_route('clients.forms.show',[$client,$package])->with('success','Documenti firmati ricevuti. Ora controlla le firme.');
    }
    public function review(Request $request,Client $client,InsuranceFormPackage $package,InsuranceFormSubmission $submission,InsuranceForms $forms)
    {
        $this->package($client,$package); $this->authorize('updateRegistry',$client); abort_unless($submission->document->insurance_form_package_id===$package->id,404);
        $data=$request->validate(['outcome'=>['required','in:validated,rejected'],'review_note'=>['nullable','string','max:2000'],'confirmed'=>['accepted']]);
        $forms->review($submission,$data); return to_route('clients.forms.show',[$client,$package])->with('success','Esito del controllo registrato nello storico.');
    }
    public function templates(Request $request)
    {
        $this->authorize('system.admin'); $data=$request->validate(['status'=>['nullable',Rule::in(array_keys(InsuranceFormTemplate::STATUSES))],'scope'=>['nullable','in:client,policy'],'client_type'=>['nullable',Rule::in(array_keys(InsuranceFormTemplate::CLIENT_TYPES))]]);
        $query=InsuranceFormTemplate::with(['branch','subbranch']); foreach($data as $field=>$value)if($value)$query->where($field,$value);
        return view('insurance-forms.templates.index',['templates'=>$query->orderBy('title')->orderByDesc('version')->get(),'filters'=>$data]);
    }
    public function templateForm(Request $request,?InsuranceFormTemplate $template=null)
    {
        $this->authorize('system.admin'); $request->validate(['revise'=>['nullable','in:1']]); $revision=$request->boolean('revise'); $template??=new InsuranceFormTemplate(['scope'=>'policy','signature_required'=>true]);
        return view('insurance-forms.templates.form',['template'=>$template,'revision'=>$revision,'branches'=>InsuranceBranch::with('subbranches')->orderBy('name')->get()]);
    }
    public function templateSave(Request $request,InsuranceForms $forms,?InsuranceFormTemplate $template=null)
    {
        $this->authorize('system.admin');
        $request->validate(['client_type'=>['sometimes',Rule::in(array_keys(InsuranceFormTemplate::CLIENT_TYPES))]]);
        $data=$request->validate(['title'=>['required','string','max:150'],'category'=>['required',Rule::in(array_keys(Attachment::DOCUMENT_CATEGORIES))],'scope'=>['required','in:client,policy'],'insurance_branch_id'=>['nullable','integer'],'insurance_subbranch_id'=>['nullable','integer'],'body'=>['required','string','max:50000'],'signature_required'=>['required','boolean'],'required'=>['required','boolean'],'revision_reason'=>['nullable','string','max:2000'],'revise'=>['nullable','in:1']]);
        $data['client_type'] = $request->input('client_type', $template?->client_type ?? 'all');
        $revision=$request->boolean('revise'); unset($data['revise']);
        if($revision)$request->validate(['revision_reason'=>['required','string','min:10','max:2000']]);
        $result=$forms->saveTemplate($data,$template,$revision); return to_route('form-templates.edit',$result)->with('success','Bozza salvata. Controllala prima di abilitarla per l’uso.');
    }
    public function activate(Request $request,InsuranceFormTemplate $template,InsuranceForms $forms)
    {
        $this->authorize('system.admin'); $request->validate(['confirmed'=>['accepted']]); $forms->activate($template); return to_route('form-templates.edit',$template)->with('success','Versione approvata per l’uso.');
    }
    public function retire(Request $request,InsuranceFormTemplate $template)
    {
        $this->authorize('system.admin'); $data=$request->validate(['reason'=>['required','string','min:10','max:2000'],'confirmed'=>['accepted']]);
        \Illuminate\Support\Facades\DB::transaction(function()use($template,$data){$template=InsuranceFormTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();abort_unless($template->status==='active',409);$template->update(['status'=>'retired']);app(\App\Services\AuditLogService::class)->log('form_template_retired',$template,description:'Modello ritirato: '.$data['reason']);});
        return to_route('form-templates.edit',$template)->with('success','Modello ritirato. I fascicoli esistenti restano conservati.');
    }
    public function preview(InsuranceFormTemplate $template,InsuranceForms $forms)
    {
        $this->authorize('system.admin'); return response($forms->pdf($template->title,$template->body,'Modello v'.$template->version,true),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="anteprima-modello.pdf"','Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
