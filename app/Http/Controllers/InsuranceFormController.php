<?php
namespace App\Http\Controllers;

use App\Models\{Client, InsuranceFormTemplate, InsuranceFormPackage, InsuranceFormDocument, InsuranceFormSubmission, InsuranceBranch, Attachment};
use App\Services\{InsuranceDocumentation, InsuranceForms, PolicyFormReminder};
use App\Support\FormTemplateContent;
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
        $query=$client->formPackages()->with(['policy.latestRenewal','client','author','documents.latestSubmission']);
        if($policy)$query->where('insurance_policy_id',$policy->id);
        if($filters['reason']??null)$query->where('reason',$filters['reason']);
        if($filters['from']??null)$query->whereDate('created_at','>=',$filters['from']);
        if($filters['to']??null)$query->whereDate('created_at','<=',$filters['to']);
        $openSignatures = !$request->filled('from') && !$request->filled('to') && in_array($filters['reason'] ?? null,[null,'client'],true) && $request->user()->can('updateRegistry',$client)
            ? app(InsuranceDocumentation::class)->openSignatures($client,$policy?->id,($filters['reason'] ?? null) === 'client') : collect();
        return view('insurance-forms.index',['client'=>$client,'policy'=>$policy,'filters'=>$filters,'openSignatures'=>$openSignatures,'packages'=>$query->latest('id')->paginate(25)->withQueryString()]);
    }
    public function create(Request $request,Client $client,InsuranceForms $forms)
    {
        $this->authorize('updateRegistry',$client); $policy=$this->policy($request,$client); if($policy)abort_unless($policy->editableBy($request->user()),403);
        $request->validate(['change'=>['nullable','integer'], 'reminder'=>['nullable','uuid']]);
        $change=$policy?->incomingChanges()->latest('id')->first();
        $reminder = $request->input('reminder');
        $savedSelection = null;
        if ($reminder) {
            abort_unless($policy, 404);
            $pending = PolicyFormReminder::find($policy);
            if (($pending?->data['request_key'] ?? null) !== $reminder || PolicyFormReminder::key($policy,$change) !== $reminder) {
                $package = $policy->formPackages()->where('request_key',$reminder)->first();
                if ($package) return to_route('clients.forms.show', [$client,$package]);
                return to_route('clients.policies.show', [$client,$policy,'tab'=>'forms'])->with('status','Questo promemoria non è più in sospeso.');
            }
            $savedSelection = $pending->data['selected_templates'] ?? null;
        }
        abort_if($request->filled('change') && (int)$change?->id!==$request->integer('change'),404);
        return view('insurance-forms.create',['client'=>$client,'policy'=>$policy,'change'=>$change,'reminder'=>$reminder,'savedSelection'=>$savedSelection,'templates'=>$forms->available($policy, $client),'contextHash'=>$forms->contextHash($client,$policy)]);
    }
    public function store(Request $request,Client $client,InsuranceForms $forms)
    {
        $this->authorize('updateRegistry',$client); $policy=$this->policy($request,$client);
        $maxTemplates = max(1, $forms->available($policy,$client)->count());
        $data=$request->validate(['context_hash'=>['required','string','size:64'],'request_key'=>['required','uuid'],'reminder'=>['nullable','uuid'],'templates'=>['required','array','list','min:1','max:'.$maxTemplates],'templates.*'=>['required','integer','distinct'],'reason'=>['required',Rule::in(array_keys(InsuranceFormPackage::REASONS))],'note'=>['nullable','string','max:2000'],'signer_name'=>['nullable','string','max:150'],'signer_role'=>['nullable','string','max:150'],'place'=>['nullable','string','max:150'],'document_date'=>['required','date_format:Y-m-d'],'confirmed'=>['accepted']], ['templates.required'=>'Spunta almeno un modulo per generare il fascicolo.']);
        $request->validate(['change'=>['nullable','integer']]); $data['change_id']=$request->integer('change') ?: null;
        $package=$forms->createPackage($client,$policy,$data); return to_route('clients.forms.show',[$client,$package])->with('success','Moduli preparati e conservati nel fascicolo.');
    }
    public function decideReminder(Request $request, Client $client, string $decision, InsuranceForms $forms)
    {
        $this->authorize('updateRegistry',$client);
        $policy = $this->policy($request, $client);
        abort_unless($policy, 404);
        $later = $decision === 'later';
        $data = $request->validate(['reminder'=>['required','uuid'], 'context_hash'=>['required','string','size:64'],
            'templates'=>$later ? ['nullable','array','list','max:'.max(1,$forms->available($policy,$client)->count())] : ['prohibited'], 'templates.*'=>['integer','distinct']]);
        $forms->decideReminder($client,$policy,$data['reminder'],$data['context_hash'],$later ? ($data['templates'] ?? []) : null);
        if ($later) return to_route('clients.policies.show',[$client,$policy,'tab'=>'payments'])->with('success','Scelta rimandata. Le spunte sono salvate nel promemoria.');
        return to_route('clients.policies.show', [$client,$policy,'tab'=>'forms'])->with('success','Scelta registrata. Nessun modulo da generare.');
    }
    public function show(Client $client,InsuranceFormPackage $package)
    {
        $this->package($client,$package); $package->load(['policy.latestRenewal','client','author','documents.generated','documents.latestSubmission','documents.submissions.files','documents.submissions.author','documents.submissions.reviewer']);
        return view('insurance-forms.show',compact('client','package'));
    }
    public function submit(Request $request,Client $client,InsuranceFormPackage $package,InsuranceFormDocument $document,InsuranceForms $forms)
    {
        $this->package($client,$package); $this->authorize('updateRegistry',$client); abort_unless($document->insurance_form_package_id===$package->id,404);
        $data=$request->validate(['request_key'=>['required','uuid'],'previous_submission'=>['sometimes','integer','min:0'],'files'=>['required','array','min:1','max:10'],'files.*'=>['required','file','max:10240','mimes:pdf,jpg,jpeg,png','extensions:pdf,jpg,jpeg,png'],'signer_name'=>['required','string','max:150'],'signer_role'=>['nullable','string','max:150'],'signed_on'=>['required','date_format:Y-m-d','before_or_equal:today'],'note'=>['nullable','string','max:2000']]);
        $forms->submit($document,$request->file('files'),$data);
        return $this->afterSignature($request,$client,$package,$document)->with('success','Copia firmata ricevuta. Ora puoi controllarla.');
    }
    public function review(Request $request,Client $client,InsuranceFormPackage $package,InsuranceFormSubmission $submission,InsuranceForms $forms)
    {
        $this->package($client,$package); $this->authorize('updateRegistry',$client); abort_unless($submission->document->insurance_form_package_id===$package->id,404);
        $data=$request->validate(['outcome'=>['required','in:validated,rejected'],'review_note'=>['nullable','string','max:2000'],'confirmed'=>['accepted']]);
        $forms->review($submission,$data);
        return $this->afterSignature($request,$client,$package,$submission->document)->with('success',$data['outcome'] === 'validated' ? 'Firma controllata. Il modulo è completato.' : 'Correzione richiesta. Puoi caricare una nuova copia.');
    }
    private function afterSignature(Request $request, Client $client, InsuranceFormPackage $package, InsuranceFormDocument $document)
    {
        if ($request->input('signature_origin') === 'policy' && $package->insurance_policy_id) {
            return redirect()->to(route('clients.policies.show',[$client,$package->insurance_policy_id,'tab'=>'forms']).'#policy-signatures')->with('signature_document',$document->id);
        }
        return to_route('clients.forms.show',[$client,$package]);
    }
    public function templates(Request $request)
    {
        $this->authorize('forms.manage'); $data=$request->validate(['search'=>['nullable','string','max:255'],'status'=>['nullable',Rule::in(array_keys(InsuranceFormTemplate::STATUSES))],'scope'=>['nullable','in:client,policy'],'client_type'=>['nullable',Rule::in(array_keys(InsuranceFormTemplate::CLIENT_TYPES))],'history'=>['nullable','in:1']]);
        $query=InsuranceFormTemplate::with(['branch','subbranch','next']);
        if (filled($data['search']??null)) $query->whereLike('title','%'.$data['search'].'%');
        foreach (['status','scope','client_type'] as $field) if (!empty($data[$field])) $query->where($field,$data[$field]);
        if (empty($data['history']) && empty($data['status'])) $query->whereDoesntHave('next');
        return view('insurance-forms.templates.index',['templates'=>$query->orderBy('title')->orderByDesc('version')->get(),'filters'=>$data]);
    }
    public function templateForm(Request $request,?InsuranceFormTemplate $template=null)
    {
        $this->authorize('forms.manage'); $request->validate(['revise'=>['nullable','in:1']]); $revision=$request->boolean('revise'); $template??=new InsuranceFormTemplate(['scope'=>'policy','signature_required'=>true,'category'=>'other']);
        if ($revision && $template->next) return to_route('form-templates.edit',$template->next)->with('status','È già disponibile la versione successiva.');
        $editable = !$template->exists || ($template->status === 'draft' && !$template->next) || $revision;
        $body = $request->old('body', $template->plain_body);
        $initialBody = $request->hasSession() && $request->session()->hasOldInput('body') && is_string($body) ? $body : (string)$template->body;
        if (is_string($draft = $request->old('editor_content')) && $draft !== '') {
            try { $initialBody = FormTemplateContent::encode($draft); }
            catch (\Illuminate\Validation\ValidationException) { $initialBody = is_string($body) ? $body : ''; }
        }
        try { $editorDocument = FormTemplateContent::editorDocument($initialBody); }
        catch (\Illuminate\Validation\ValidationException) { $editorDocument = FormTemplateContent::editorDocument(''); }
        return view('insurance-forms.templates.form',['template'=>$template,'revision'=>$revision,'editable'=>$editable,'editorDocument'=>$editorDocument,'branches'=>InsuranceBranch::with('subbranches')->orderBy('name')->get()]);
    }
    public function templateSave(Request $request,InsuranceForms $forms,?InsuranceFormTemplate $template=null)
    {
        $this->authorize('forms.manage');
        $request->validate(['client_type'=>['sometimes',Rule::in(array_keys(InsuranceFormTemplate::CLIENT_TYPES))]]);
        $data=$request->validate(['title'=>['required','string','max:150'],'category'=>['required',Rule::in(array_keys(Attachment::DOCUMENT_CATEGORIES))],'scope'=>['required','in:client,policy'],'insurance_branch_id'=>['nullable','integer'],'insurance_subbranch_id'=>['nullable','integer'],'body'=>['required_without:editor_content','nullable','string','max:50000'],'editor_content'=>['nullable','string','max:'.FormTemplateContent::MAX_BYTES],'signature_required'=>['required','boolean'],'required'=>['required','boolean'],'revision_reason'=>['nullable','string','max:2000'],'revise'=>['nullable','in:1'],'edit_key'=>['sometimes','string','size:64']]);
        if (!empty($data['editor_content'])) $data['body'] = FormTemplateContent::encode($data['editor_content']);
        unset($data['editor_content']);
        if (!$template) unset($data['edit_key']);
        $data['client_type'] = $request->input('client_type', $template?->client_type ?? 'all');
        $revision=$request->boolean('revise'); unset($data['revise']);
        if($revision)$request->validate(['revision_reason'=>['required','string','min:10','max:2000']]);
        $result=$forms->saveTemplate($data,$template,$revision); return to_route('form-templates.edit',$result)->with('success','Bozza salvata. Controllala prima di abilitarla per l’uso.');
    }
    public function activate(Request $request,InsuranceFormTemplate $template,InsuranceForms $forms)
    {
        $this->authorize('forms.manage'); $request->validate(['confirmed'=>['accepted'],'edit_key'=>['sometimes','string','size:64']]); $forms->activate($template,$request->input('edit_key')); return to_route('form-templates.edit',$template)->with('success','Versione approvata per l’uso.');
    }
    public function retire(Request $request,InsuranceFormTemplate $template)
    {
        $this->authorize('forms.manage'); $data=$request->validate(['reason'=>['required','string','min:10','max:2000'],'confirmed'=>['accepted']]);
        \Illuminate\Support\Facades\DB::transaction(function()use($template,$data){$template=InsuranceFormTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();abort_unless($template->status==='active',409);$template->update(['status'=>'retired']);app(\App\Services\AuditLogService::class)->log('form_template_retired',$template,description:'Modello ritirato: '.$data['reason']);});
        return to_route('form-templates.edit',$template)->with('success','Modello ritirato. I fascicoli esistenti restano conservati.');
    }
    public function preview(InsuranceFormTemplate $template,InsuranceForms $forms)
    {
        $this->authorize('forms.manage');
        return $this->previewPdf($forms,$template->title,$template->body,'Modello v'.$template->version);
    }
    public function previewDraft(Request $request, InsuranceForms $forms)
    {
        $this->authorize('forms.manage');
        $data = $request->validate(['title'=>['nullable','string','max:150'],'body'=>['required_without:editor_content','nullable','string','max:50000'],'editor_content'=>['nullable','string','max:'.FormTemplateContent::MAX_BYTES]]);
        $body = !empty($data['editor_content']) ? FormTemplateContent::encode($data['editor_content']) : $data['body'];
        $forms->validateBody($body);
        return $this->previewPdf($forms,$data['title'] ?? 'Modello senza nome',$body,'Anteprima delle modifiche');
    }
    private function previewPdf(InsuranceForms $forms, string $title, string $body, string $reference)
    {
        $pdf = $forms->pdf($title,$body,$reference,true,InsuranceForms::previewFields());
        $headers = ['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff'];
        $rawPreview = request()->isMethod('GET') && !request()->boolean('viewer');
        if ($rawPreview || request()->prefers(['text/html','application/pdf']) === 'application/pdf') {
            return response($pdf,200,$headers + ['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="anteprima-modello.pdf"']);
        }
        return response()->view('insurance-forms.templates.preview',['title'=>$title,'source'=>'data:application/pdf;base64,'.base64_encode($pdf)],200,$headers);
    }
}
