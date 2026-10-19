<?php
namespace App\Http\Controllers;

use App\Models\{Client, InsurancePolicy, InsuranceCoverageCheck};
use App\Services\{InsuranceForms, PrivateAttachmentBatch};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class InsuranceCoverageCheckController extends Controller
{
    public function index(Client $client,InsurancePolicy $policy,InsuranceForms $forms)
    {
        $this->authorize('view',$client); abort_unless($policy->client_id===$client->id,404);
        return view('insurance-forms.coverage',['client'=>$client,'policy'=>$policy,'contextHash'=>$forms->contextHash($client,$policy),'checks'=>$policy->coverageChecks()->with(['author','files'])->latest('id')->get()]);
    }
    public function store(Request $request,Client $client,InsurancePolicy $policy,InsuranceForms $forms)
    {
        $this->authorize('updateRegistry',$client); abort_unless($policy->client_id===$client->id,404);
        $data=$request->validate(['outcome'=>['required','in:covered,not_covered,unavailable'],'request_key'=>['required','uuid'],'context_hash'=>['required','string','size:64'],'note'=>['nullable','string','max:2000'],'confirmed'=>['accepted'],'files'=>['nullable','array','max:10'],'files.*'=>['file','max:10240','mimes:pdf,jpg,jpeg,png','extensions:pdf,jpg,jpeg,png']]);
        $stored=[];
        try { DB::transaction(function() use($request,$client,$policy,$forms,$data,&$stored) {
            $client=Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail(); $policy=$client->policies()->whereKey($policy->id)->lockForUpdate()->firstOrFail();
            $this->authorize('updateRegistry',$client);
            $plate=$policy->vehicle?->plate;
            if(!$plate || !hash_equals($forms->contextHash($client,$policy),$data['context_hash']))throw ValidationException::withMessages(['check'=>'Targa o dati cambiati. Riapri la pagina e ripeti il controllo.']);
            if(InsuranceCoverageCheck::where('request_key',$data['request_key'])->exists())throw ValidationException::withMessages(['check'=>'Questo esito è già stato registrato.']);
            $files=$request->file('files',[]); if(collect($files)->sum(fn($f)=>$f->getSize())>50*1024*1024)throw ValidationException::withMessages(['files'=>'Il totale dei file non può superare 50 MB.']);
            $check=$policy->coverageChecks()->create(['plate'=>$plate,'outcome'=>$data['outcome'],'checked_at'=>now(),'note'=>$data['note']??null,'context_hash'=>$data['context_hash'],'request_key'=>$data['request_key'],'created_by'=>$request->user()->id]);
            $stored=app(PrivateAttachmentBatch::class)->upload($client,$files,['type'=>'document','document_category'=>'other','insurance_policy_id'=>$policy->id,'insurance_coverage_check_id'=>$check->id,'review_status'=>'received']);
            $forms->log($client,$policy,'coverage_checked','Registrato controllo manuale RCA: '.InsuranceCoverageCheck::OUTCOMES[$check->outcome].'.');
        }); } catch(\Throwable $e) { foreach($stored as $file)Storage::disk($file->disk)->delete($file->path);throw $e; }
        return to_route('clients.policies.coverage',[$client,$policy])->with('success','Esito RCA registrato. Incassi e stato della polizza restano distinti.');
    }
}
