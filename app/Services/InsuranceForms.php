<?php
namespace App\Services;

use App\Models\{Attachment, Client, InsurancePolicy, InsuranceFormTemplate, InsuranceFormPackage, InsuranceFormDocument, InsuranceFormSubmission};
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InsuranceForms
{
    public const TOKENS = ['contraente','codice_fiscale_piva','indirizzo','nascita','titolo_cliente','email','telefono','firmatario','qualifica_firmatario','luogo','data_documento','compagnia','agenzia','ramo','sottoramo','numero_polizza','decorrenza','scadenza','premio','targa'];

    public function context(Client $client, ?InsurancePolicy $policy): array
    {
        /** Operational notes and receipts do not invalidate signed contractual documents. */
        $contractFields = array_diff((new InsurancePolicy)->getFillable(), ['notes','payment_status','ad_horas']);
        return ['client'=>$client->only(['name','client_type','title','tax_code','vat_number','address','city','postal_code','province','country','birth_date','birth_place','email','phone','reference_person','reference_role']), 'policy'=>$policy?->only($contractFields), 'plate'=>$policy?->vehicle?->plate, 'agency'=>$policy?->supplierAgency?->name];
    }
    public function contextHash(Client $client, ?InsurancePolicy $policy): string { return hash('sha256',json_encode($this->context($client,$policy),JSON_THROW_ON_ERROR)); }
    public function available(?InsurancePolicy $policy)
    {
        return InsuranceFormTemplate::where('status','active')->with(['branch','subbranch'])->orderBy('title')->get()->filter(fn($t)=>$t->applies($policy));
    }
    private function fail(string $message, string $field='forms'): never { throw ValidationException::withMessages([$field=>$message]); }
    public function validateBody(string $body): void
    {
        preg_match_all('/\{\{(.*?)\}\}/s',$body,$matches);
        foreach ($matches[1] as $token) if (!in_array($token,self::TOKENS,true)) $this->fail('Campo non riconosciuto: '.$token,'body');
        if (preg_match('/<\/?[a-z!]|<\?php/i',$body)) $this->fail('Usa testo semplice, senza codice HTML o PHP.','body');
    }
    public function saveTemplate(array $data, ?InsuranceFormTemplate $template=null, bool $revision=false): InsuranceFormTemplate
    {
        Gate::authorize('system.admin'); $this->validateBody($data['body']);
        return DB::transaction(function() use ($data,$template,$revision) {
            $branch = !empty($data['insurance_branch_id']) ? \App\Models\InsuranceBranch::whereKey($data['insurance_branch_id'])->lockForUpdate()->firstOrFail() : null;
            $sub = !empty($data['insurance_subbranch_id']) ? \App\Models\InsuranceSubbranch::whereKey($data['insurance_subbranch_id'])->lockForUpdate()->firstOrFail() : null;
            if ($sub && (!$branch || $sub->insurance_branch_id !== $branch->id)) $this->fail('Il sottoramo non appartiene al ramo selezionato.','insurance_subbranch_id');
            if (($branch && $branch->status !== 'active') || ($sub && $sub->status !== 'active')) $this->fail('Scegli una voce attiva del catalogo.');
            if ($data['scope'] === 'client' && ($branch || $sub)) $this->fail('I modelli del cliente non dipendono dal ramo della polizza.');
            if ($template) {
                $template = InsuranceFormTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();
                if ($revision) {
                    if ($template->next()->exists()) $this->fail('Esiste già una revisione successiva. Aprila dallo storico.');
                    $next = new InsuranceFormTemplate(['family'=>$template->family,'version'=>$template->version+1,'previous_id'=>$template->id,'source_sheet'=>$template->source_sheet,'source_hash'=>$template->source_hash,'source_warning'=>$template->source_warning]);
                } else {
                    if ($template->status !== 'draft' || $template->next()->exists()) $this->fail('Questo modello è conservato nello storico. Apri l’ultima versione.');
                    $next = $template;
                }
            } else $next = new InsuranceFormTemplate(['family'=>(string)Str::uuid(),'version'=>1]);
            $next->fill($data); $next->status='draft'; $next->source_reviewed=false; $next->created_by=auth()->id(); $next->save();
            app(AuditLogService::class)->log('form_template_saved',$next,description:'Salvata la bozza del modello '.$next->title.', versione '.$next->version.'.');
            return $next;
        });
    }
    public function activate(InsuranceFormTemplate $template): void
    {
        Gate::authorize('system.admin');
        DB::transaction(function() use ($template) {
            $family=InsuranceFormTemplate::where('family',$template->family)->orderBy('id')->lockForUpdate()->get(); $template=$family->firstWhere('id',$template->id);
            if ($template->status !== 'draft' || $template->next()->exists()) $this->fail('Approva soltanto l’ultima bozza della famiglia.');
            if (str_starts_with($template->category,'annex_')) $this->fail('I precedenti allegati restano nello storico. Predisponi un modello aggiornato nella categoria corretta.');
            if ($template->previous?->status === 'legacy' && $template->body === $template->previous->body) $this->fail('Il testo del modello precedente non è stato aggiornato.');
            $this->validateBody($template->body);
            if (($template->branch && $template->branch->status !== 'active') || ($template->subbranch && $template->subbranch->status !== 'active')) $this->fail('Il ramo o sottoramo del modello non è più attivo.');
            foreach ($family as $row) if ($row->status === 'active') $row->update(['status'=>'retired']);
            $template->update(['status'=>'active','approved_by'=>auth()->id(),'approved_at'=>now(),'source_reviewed'=>true]);
            app(AuditLogService::class)->log('form_template_approved',$template,description:'Approvato per l’uso il modello '.$template->title.', versione '.$template->version.'.');
        });
    }
    public function tokens(Client $client, ?InsurancePolicy $policy, array $data): array
    {
        $values = ['contraente'=>$client->name,'codice_fiscale_piva'=>implode(' / ',array_filter([$client->tax_code,$client->vat_number])),'indirizzo'=>implode(', ',array_filter([$client->address,$client->postal_code,$client->city,$client->province,$client->country])), 'nascita'=>trim(($client->birth_place??'').' '.($client->birth_date?->format('d/m/Y')??'')),'titolo_cliente'=>$client->title,'email'=>$client->email,'telefono'=>$client->phone,
            'firmatario'=>$data['signer_name'],'qualifica_firmatario'=>$data['signer_role']??null,'luogo'=>$data['place'],'data_documento'=>\Carbon\Carbon::parse($data['document_date'])->format('d/m/Y'),'compagnia'=>$policy?->company,'agenzia'=>$policy?->supplierAgency?->name,'ramo'=>$policy?->category,'sottoramo'=>$policy?->subcategory,'numero_polizza'=>$policy?->number,'decorrenza'=>$policy?->starts_on?->format('d/m/Y'),'scadenza'=>$policy?->expires_on?->format('d/m/Y'),'premio'=>$policy?->premium === null ? null : number_format((float)$policy->premium,2,',','.').' EUR','targa'=>$policy?->vehicle?->plate];
        $result=[]; foreach($values as $name=>$value) $result['{{'.$name.'}}']=filled($value)?(string)$value:'________________'; return $result;
    }
    public function createPackage(Client $client, ?InsurancePolicy $policy, array $data): InsuranceFormPackage
    {
        Gate::authorize('updateRegistry',$client); $paths=[];
        try { return DB::transaction(function() use ($client,$policy,$data,&$paths) {
            $client=Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('updateRegistry',$client);
            $policy=$policy ? $client->policies()->whereKey($policy->id)->lockForUpdate()->firstOrFail() : null;
            if($policy) abort_unless($policy->editableBy(auth()->user()),403);
            if(InsuranceFormPackage::where('request_key',$data['request_key'])->exists()) $this->fail('Il fascicolo è già stato preparato. Apri lo storico prima di ripetere.');
            if(!hash_equals($data['context_hash'],$this->contextHash($client,$policy))) $this->fail('I dati sono cambiati. Riapri il modulo e controllali nuovamente.');
            if($client->client_type === 'company' && empty($data['signer_role'])) $this->fail('Indica la qualifica del firmatario per l’azienda.','signer_role');
            $templates=InsuranceFormTemplate::whereIn('id',$data['templates'])->orderBy('id')->lockForUpdate()->get();
            if($templates->count()!==count($data['templates'])) $this->fail('Uno dei modelli selezionati non è disponibile.');
            foreach($templates as $template) if($template->status !== 'active' || !$template->applies($policy)) $this->fail('Seleziona modelli approvati e compatibili con questo cliente o polizza.');
            foreach($this->available($policy) as $template) if($template->required && !$templates->contains('id',$template->id)) $this->fail('Il modello '.$template->title.' è richiesto dalla configurazione.');
            if(($policy && $data['reason']==='client') || (!$policy && $data['reason']!=='client')) $this->fail('Motivo non compatibile con il fascicolo.');
            if(in_array($data['reason'],['change','renewal'],true) && mb_strlen(trim($data['note']??''))<10) $this->fail('Descrivi la variazione o il rinnovo (almeno 10 caratteri).','note');
            $tokens=$this->tokens($client,$policy,$data);
            $package=InsuranceFormPackage::create(['client_id'=>$client->id,'insurance_policy_id'=>$policy?->id,'reason'=>$data['reason'],'note'=>$data['note']??null,'snapshot'=>['context'=>$this->context($client,$policy),'tokens'=>$tokens],'context_hash'=>$this->contextHash($client,$policy),'request_key'=>$data['request_key'],'created_by'=>auth()->id()]);
            foreach($templates as $template) {
                $document=$package->documents()->create(['insurance_form_template_id'=>$template->id,'title'=>$template->title,'template_version'=>$template->version,'body'=>strtr($template->body,$tokens),'signature_required'=>$template->signature_required]);
                $pdf=$this->pdf($document->title,$document->body,'Modello v'.$template->version.' / Fascicolo '.$package->id.' / '.$tokens['{{data_documento}}']);
                $directory='client/'.$client->id.'/forms'; $path=$directory.'/'.Str::uuid().'.pdf';
                if(!Storage::disk('attachments')->put($path,$pdf)) $this->fail('Impossibile conservare il PDF. Riprova.');
                $paths[]=$path;
                $client->attachments()->create(['insurance_policy_id'=>$policy?->id,'insurance_form_document_id'=>$document->id,'document_category'=>$template->category,'type'=>'document','uploaded_by'=>auth()->id(),'disk'=>'attachments','directory'=>$directory,'path'=>$path,'stored_name'=>basename($path),'original_name'=>Str::slug($document->title).'-f'.$package->id.'-v'.$template->version.'.pdf','mime_type'=>'application/pdf','extension'=>'pdf','size'=>strlen($pdf),'review_status'=>'generated']);
                $document->update(['pdf_hash'=>hash('sha256',$pdf)]);
            }
            $this->log($client,$policy,'forms_prepared','Preparato fascicolo moduli #'.$package->id.'.');
            return $package;
        }); } catch(\Throwable $e) { foreach($paths as $path) Storage::disk('attachments')->delete($path); throw $e; }
    }
    public function pdf(string $title,string $body,string $reference,bool $draft=false): string
    {
        /** Templates are plain text; all values are escaped and the renderer cannot fetch remote resources or execute code. */
        $options=new \Dompdf\Options(['isRemoteEnabled'=>false,'isPhpEnabled'=>false,'isJavascriptEnabled'=>false,'defaultFont'=>'DejaVu Sans','chroot'=>resource_path('insurance')]);
        $pdf=new \Dompdf\Dompdf($options); $pdf->setPaper('A4');
        $pdf->loadHtml(view('insurance-forms.pdf',compact('title','body','reference','draft'))->render(),'UTF-8'); $pdf->render();
        $canvas=$pdf->getCanvas(); $canvas->page_text(470,812,'{PAGE_NUM} / {PAGE_COUNT}',$pdf->getFontMetrics()->getFont('DejaVu Sans','normal'),8,[0.3,0.3,0.3]);
        return $pdf->output();
    }
    public function submit(InsuranceFormDocument $document,array $files,array $data): InsuranceFormSubmission
    {
        $package=$document->package; Gate::authorize('updateRegistry',$package->client); $stored=[];
        try { return DB::transaction(function() use ($document,$package,$files,$data,&$stored) {
            $client=Client::current()->whereKey($package->client_id)->lockForUpdate()->firstOrFail(); Gate::authorize('updateRegistry',$client);
            $document=InsuranceFormDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if(!$document->signature_required) $this->fail('Questo modulo non richiede una firma.');
            if(InsuranceFormSubmission::where('request_key',$data['request_key'])->exists()) $this->fail('Questo caricamento è già stato registrato.');
            $last=$document->submissions()->first();
            if($last && mb_strlen(trim($data['note']??''))<10) $this->fail('Motiva la nuova versione dei documenti firmati.','note');
            if($package->client->client_type==='company' && empty($data['signer_role'])) $this->fail('Indica la qualifica del firmatario.','signer_role');
            if(collect($files)->sum(fn($file)=>$file->getSize())>50*1024*1024) $this->fail('Il totale dei file non può superare 50 MB.','files');
            $submission=$document->submissions()->create(array_intersect_key($data,array_flip(['signer_name','signer_role','signed_on','note','request_key']))+['version'=>($last?->version??0)+1,'created_by'=>auth()->id(),'file_hashes'=>[]]);
            $stored=app(PrivateAttachmentBatch::class)->upload($package->client,$files,['type'=>'document','document_category'=>$document->template->category,'insurance_policy_id'=>$package->insurance_policy_id,'insurance_form_document_id'=>$document->id,'insurance_form_submission_id'=>$submission->id,'review_status'=>'received','version_number'=>$submission->version]);
            $hashes=[]; foreach($stored as $file) $hashes[$file->id]=hash('sha256',Storage::disk($file->disk)->get($file->path)); $submission->update(['file_hashes'=>$hashes]);
            $this->log($package->client,$package->policy,'forms_received','Ricevuti '.count($stored).' file per '.$document->title.', versione firmata '.$submission->version.'.');
            return $submission;
        }); } catch(\Throwable $e) { foreach($stored as $file) Storage::disk($file->disk)->delete($file->path); throw $e; }
    }
    public function review(InsuranceFormSubmission $submission,array $data): void
    {
        $package=$submission->document->package; Gate::authorize('updateRegistry',$package->client);
        DB::transaction(function() use ($submission,$package,$data) {
            $client=Client::current()->whereKey($package->client_id)->lockForUpdate()->firstOrFail(); Gate::authorize('updateRegistry',$client);
            InsuranceFormDocument::whereKey($submission->insurance_form_document_id)->lockForUpdate()->firstOrFail();
            $submission=InsuranceFormSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if($submission->status!=='received' || (int)$submission->document->submissions()->max('version')!==(int)$submission->version) $this->fail('Controlla soltanto l’ultima versione ancora da verificare.');
            if($submission->files->isEmpty()) $this->fail('Mancano i documenti firmati. Carica una nuova versione.');
            foreach($submission->files as $file) if(!Storage::disk($file->disk)->exists($file->path) || !hash_equals($submission->file_hashes[$file->id]??'',hash('sha256',Storage::disk($file->disk)->get($file->path)))) $this->fail('Un allegato manca o è cambiato. Carica una nuova versione.');
            if($data['outcome']==='rejected' && empty($data['review_note'])) $this->fail('Indica cosa deve essere corretto.','review_note');
            $submission->update(['status'=>$data['outcome'],'review_note'=>$data['review_note']??null,'reviewed_by'=>auth()->id(),'reviewed_at'=>now()]);
            $submission->files()->update(['review_status'=>$data['outcome'],'validated_by'=>auth()->id(),'validated_at'=>now(),'validation_note'=>$data['review_note']??null]);
            $this->log($package->client,$package->policy,'forms_reviewed','Registrato controllo manuale delle firme del fascicolo #'.$package->id.': '.$data['outcome'].'.');
        });
    }
    public function log(Client $client,?InsurancePolicy $policy,string $action,string $message): void
    {
        app(AuditLogService::class)->log($action,$policy??$client,description:$message);
    }
}
