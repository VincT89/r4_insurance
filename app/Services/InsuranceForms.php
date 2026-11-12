<?php
namespace App\Services;

use App\Models\{Attachment, Client, InsurancePolicy, InsuranceFormTemplate, InsuranceFormPackage, InsuranceFormDocument, InsuranceFormSubmission};
use App\Support\FormTemplateContent;
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InsuranceForms
{
    public const TOKENS = ['contraente','codice_fiscale_piva','codice_fiscale','partita_iva','indirizzo','citta','cap','provincia','paese','nascita','luogo_nascita','data_nascita','titolo_cliente','email','telefono','firmatario','qualifica_firmatario','luogo','data_documento','compagnia','agenzia','ramo','sottoramo','numero_polizza','prodotto','bene_assicurato','decorrenza','scadenza','premio','frazionamento','premio_rata','metodo_pagamento','targa','classe_cu','classe_compagnia','operazione','riferimento_variazione','coperture_aggiuntive','decorrenza_variazione','scadenza_variazione','premio_variazione','polizza_precedente','titolo_modello','riferimento_documento'];
    public static function fieldLabels(): array
    {
        $labels = array_combine(self::TOKENS, array_map(fn ($key) => ucfirst(str_replace('_',' ',$key)),self::TOKENS));
        return array_replace($labels,['contraente'=>'Nome del contraente','codice_fiscale_piva'=>'Codice fiscale o partita IVA','partita_iva'=>'Partita IVA','citta'=>'Città','cap'=>'CAP','nascita'=>'Luogo e data di nascita','firmatario'=>'Nome del firmatario','numero_polizza'=>'Numero di polizza','data_documento'=>'Data del documento','bene_assicurato'=>'Bene o soggetto assicurato','premio_rata'=>'Premio della rata','classe_cu'=>'Classe universale CU','classe_compagnia'=>'Classe della compagnia']);
    }
    public static function previewFields(): array
    {
        $values = [];
        foreach (self::fieldLabels() as $key=>$label) $values['{{'.$key.'}}'] = '['.$label.']';
        return $values;
    }

    public function context(Client $client, ?InsurancePolicy $policy): array
    {
        /** Operational notes and receipts do not invalidate signed contractual documents. */
        $contractFields = array_diff((new InsurancePolicy)->getFillable(), ['notes','payment_status','ad_horas']);
        $context = ['client'=>$client->only(['name','client_type','title','tax_code','vat_number','address','city','postal_code','province','country','birth_date','birth_place','email','phone','reference_person','reference_role']), 'policy'=>$policy?->only($contractFields), 'plate'=>$policy?->vehicle?->plate, 'agency'=>$policy?->supplierAgency?->name];
        if ($change = $policy?->incomingChanges()->latest('id')->first()) $context['contract_change'] = $change->only(['id','kind','reference','description','effective_on','expires_on','premium_cents']);
        return $context;
    }
    public function contextHash(Client $client, ?InsurancePolicy $policy): string { return hash('sha256',json_encode($this->context($client,$policy),JSON_THROW_ON_ERROR)); }
    public function available(?InsurancePolicy $policy, ?Client $client = null)
    {
        return InsuranceFormTemplate::where('status','active')->with(['branch','subbranch'])->orderBy('title')->get()
            ->sortBy(fn ($template) => [$template->applies($policy,$client) ? 0 : 1, mb_strtolower($template->title), $template->id])->values();
    }
    private function fail(string $message, string $field='forms'): never { throw ValidationException::withMessages([$field=>$message]); }
    public function validateBody(string $body): void
    {
        $body = FormTemplateContent::allText($body);
        preg_match_all('/\{\{(.*?)\}\}/s',$body,$matches);
        foreach ($matches[1] as $token) if (!in_array($token,self::TOKENS,true)) $this->fail('Campo non riconosciuto: '.$token,'body');
        if (preg_match('/<\/?[a-z!]|<\?php/i',$body)) $this->fail('Usa testo semplice, senza codice HTML o PHP.','body');
    }
    public function saveTemplate(array $data, ?InsuranceFormTemplate $template=null, bool $revision=false): InsuranceFormTemplate
    {
        Gate::authorize('forms.manage'); $this->validateBody($data['body']);
        return DB::transaction(function() use ($data,$template,$revision) {
            $branch = !empty($data['insurance_branch_id']) ? \App\Models\InsuranceBranch::whereKey($data['insurance_branch_id'])->lockForUpdate()->firstOrFail() : null;
            $sub = !empty($data['insurance_subbranch_id']) ? \App\Models\InsuranceSubbranch::whereKey($data['insurance_subbranch_id'])->lockForUpdate()->firstOrFail() : null;
            if ($sub && (!$branch || $sub->insurance_branch_id !== $branch->id)) $this->fail('Il sottoramo non appartiene al ramo selezionato.','insurance_subbranch_id');
            if (($branch && $branch->status !== 'active') || ($sub && $sub->status !== 'active')) $this->fail('Scegli una voce attiva del catalogo.');
            if ($data['scope'] === 'client' && ($branch || $sub)) $this->fail('I modelli del cliente non dipendono dal ramo della polizza.');
            if ($template) {
                $template = InsuranceFormTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();
                if (isset($data['edit_key']) && !hash_equals($template->editKey(), $data['edit_key'])) $this->fail('Il modello è cambiato in un’altra scheda. Copia le tue modifiche e riapri la versione aggiornata.');
                unset($data['edit_key']);
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
    public function activate(InsuranceFormTemplate $template, ?string $editKey=null): void
    {
        Gate::authorize('forms.manage');
        DB::transaction(function() use ($template,$editKey) {
            $family=InsuranceFormTemplate::where('family',$template->family)->orderBy('id')->lockForUpdate()->get(); $template=$family->firstWhere('id',$template->id);
            if ($editKey !== null && !hash_equals($template->editKey(),$editKey)) $this->fail('La bozza è stata aggiornata. Controlla la versione salvata prima di approvarla.');
            if ($template->status !== 'draft' || $template->next()->exists()) $this->fail('Approva soltanto l’ultima bozza della famiglia.');
            if (str_starts_with($template->category,'annex_')) $this->fail('I precedenti allegati restano nello storico. Predisponi un modello aggiornato nella categoria corretta.');
            if ($template->previous?->status === 'legacy' && trim($template->plain_body) === trim($template->previous->plain_body)) $this->fail('Il testo del modello precedente non è stato aggiornato.');
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
        $values += ['codice_fiscale'=>$client->tax_code,'partita_iva'=>$client->vat_number,'citta'=>$client->city,'cap'=>$client->postal_code,'provincia'=>$client->province,'paese'=>$client->country,'luogo_nascita'=>$client->birth_place,'data_nascita'=>$client->birth_date?->format('d/m/Y'),
            'prodotto'=>$policy?->product,'bene_assicurato'=>$policy?->insured_subject,'frazionamento'=>$policy?->payment_frequency,'premio_rata'=>$policy?->installment_premium === null ? null : number_format((float)$policy->installment_premium,2,',','.').' EUR','metodo_pagamento'=>$policy?->payment_method,'classe_cu'=>$policy?->merit_class_cu,'classe_compagnia'=>$policy?->merit_class_internal];
        $change = !empty($data['change_id']) ? $policy?->incomingChanges()->find($data['change_id']) : null;
        $values += ['operazione'=>$change ? $change->kind_label : null,'riferimento_variazione'=>$change?->reference,'coperture_aggiuntive'=>$change?->description,'decorrenza_variazione'=>$change?->effective_on->format('d/m/Y'),'scadenza_variazione'=>$change?->expires_on->format('d/m/Y'),'premio_variazione'=>$change ? \App\Support\Money::format($change->premium_cents) : null,'polizza_precedente'=>$change?->snapshot['before']['policy']['number']??null];
        $result=[]; foreach($values as $name=>$value) $result['{{'.$name.'}}']=filled($value)?(string)$value:'________________'; return $result;
    }
    public function createPackage(Client $client, ?InsurancePolicy $policy, array $data, ?array &$stored = null): InsuranceFormPackage
    {
        Gate::authorize('updateRegistry',$client); $paths=[];
        try { return DB::transaction(function() use ($client,$policy,$data,&$paths,&$stored) {
            $client=Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('updateRegistry',$client);
            $policy=$policy ? $client->policies()->whereKey($policy->id)->lockForUpdate()->firstOrFail() : null;
            if($policy) abort_unless($policy->editableBy(auth()->user()),403);
            $change = $policy?->incomingChanges()->latest('id')->first();
            if ((int)($data['change_id']??null) !== (int)$change?->id) $this->fail('Collega i moduli all’ultima variazione della polizza e ricontrolla i dati.');
            if (!empty($data['reminder'])) {
                $this->checkReminder($policy, $change, $data['reminder']);
                $data['request_key'] = $data['reminder'];
                $data['reason'] = $change ? 'renewal' : 'initial';
                if ($change && blank($data['note'] ?? null)) $data['note'] = $change->description;
            }
            if ($change && !in_array($data['reason'],[$change->kind,'renewal'],true)) $this->fail('Indica il motivo della variazione collegata oppure il rinnovo successivo.');
            if (!$change && in_array($data['reason'],['replacement','addition'],true)) $this->fail('Registra prima la variazione contrattuale.');
            if(InsuranceFormPackage::where('request_key',$data['request_key'])->exists()) $this->fail('Il fascicolo è già stato preparato. Apri lo storico prima di ripetere.');
            if(!hash_equals($data['context_hash'],$this->contextHash($client,$policy))) $this->fail('I dati sono cambiati. Riapri il modulo e controllali nuovamente.');
            $templates=InsuranceFormTemplate::whereIn('id',$data['templates'])->orderBy('id')->lockForUpdate()->get();
            if($templates->count()!==count($data['templates'])) $this->fail('Uno dei modelli selezionati non è disponibile.');
            foreach($templates as $template) if($template->status !== 'active') $this->fail('Uno dei modelli selezionati non è più utilizzabile. Riapri il promemoria e controlla la selezione.');
            $data += ['signer_name'=>null, 'place'=>null];
            if ($templates->contains(fn ($template) => $template->needsSigner())) {
                if (blank($data['signer_name'])) $this->fail('Indica il nome del firmatario per i moduli selezionati.', 'signer_name');
                if ($client->client_type === 'company' && blank($data['signer_role'] ?? null)) $this->fail('Indica la qualifica del firmatario per l’azienda.','signer_role');
            }
            if ($templates->contains(fn ($template) => $template->needsPlace()) && blank($data['place'])) $this->fail('Indica il luogo da riportare nei moduli selezionati.', 'place');
            if(($policy && $data['reason']==='client') || (!$policy && $data['reason']!=='client')) $this->fail('Motivo non compatibile con il fascicolo.');
            if(in_array($data['reason'],['change','renewal'],true) && mb_strlen(trim($data['note']??''))<10) $this->fail('Descrivi la variazione o il rinnovo (almeno 10 caratteri).','note');
            $tokens=$this->tokens($client,$policy,$data);
            $package=InsuranceFormPackage::create(['client_id'=>$client->id,'insurance_policy_id'=>$policy?->id,'insurance_policy_change_id'=>$change?->id,'reason'=>$data['reason'],'note'=>$data['note']??null,'snapshot'=>['context'=>$this->context($client,$policy),'tokens'=>$tokens],'context_hash'=>$this->contextHash($client,$policy),'request_key'=>$data['request_key'],'created_by'=>auth()->id()]);
            foreach($templates as $template) {
                $reference = 'Modello v'.$template->version.' / Fascicolo '.$package->id.' / '.$tokens['{{data_documento}}'].($change ? ' / Variazione #'.$change->id : '');
                $documentTokens = array_replace($tokens,['{{titolo_modello}}'=>$template->title,'{{riferimento_documento}}'=>$reference]);
                $document=$package->documents()->create(['insurance_form_template_id'=>$template->id,'title'=>$template->title,'template_version'=>$template->version,'body'=>strtr(FormTemplateContent::allText($template->body),$documentTokens),'signature_required'=>$template->signature_required]);
                $pdf=$this->pdf($document->title,$template->body,$reference,tokens:$documentTokens);
                $directory='client/'.$client->id.'/forms'; $path=$directory.'/'.Str::uuid().'.pdf';
                if(!Storage::disk('attachments')->put($path,$pdf)) $this->fail('Impossibile conservare il PDF. Riprova.');
                $paths[]=$path;
                $attachment=$client->attachments()->create(['insurance_policy_id'=>$policy?->id,'insurance_policy_change_id'=>$change?->id,'insurance_form_document_id'=>$document->id,'document_category'=>$template->category,'type'=>'document','uploaded_by'=>auth()->id(),'disk'=>'attachments','directory'=>$directory,'path'=>$path,'stored_name'=>basename($path),'original_name'=>Str::slug($document->title).'-f'.$package->id.'-v'.$template->version.'.pdf','mime_type'=>'application/pdf','extension'=>'pdf','size'=>strlen($pdf),'review_status'=>'generated']);
                if($stored!==null) $stored[]=$attachment;
                $document->update(['pdf_hash'=>hash('sha256',$pdf)]);
            }
            $this->log($client,$policy,'forms_prepared','Preparato fascicolo moduli #'.$package->id.'.');
            if ($policy && ((!$change && $data['reason'] === 'initial') || ($change?->kind === 'renewal' && $data['reason'] === 'renewal'))) PolicyFormReminder::clear($policy);
            return $package;
        }); } catch(\Throwable $e) { foreach($paths as $path) Storage::disk('attachments')->delete($path); throw $e; }
    }
    private function checkReminder(?InsurancePolicy $policy, ?\App\Models\InsurancePolicyChange $change, string $key): \Illuminate\Notifications\DatabaseNotification
    {
        $pending = $policy ? PolicyFormReminder::find($policy) : null;
        if (!$pending || !hash_equals(PolicyFormReminder::key($policy, $change), $key) || ($pending->data['request_key'] ?? null) !== $key) {
            $this->fail('Questo promemoria è già stato gestito o appartiene a un altro periodo. Riapri la scheda della polizza.');
        }
        return $pending;
    }

    public function decideReminder(Client $client, InsurancePolicy $policy, string $key, string $contextHash, ?array $deferredSelection): void
    {
        DB::transaction(function () use ($client,$policy,$key,$contextHash,$deferredSelection) {
            $client = Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('updateRegistry', $client);
            $policy = $client->policies()->whereKey($policy->id)->lockForUpdate()->firstOrFail();
            abort_unless($policy->editableBy(auth()->user()), 403);
            $change = $policy->incomingChanges()->latest('id')->first();
            $pending = $this->checkReminder($policy,$change,$key);
            if (!hash_equals($this->contextHash($client,$policy),$contextHash)) $this->fail('I dati sono cambiati. Riapri il promemoria prima di confermare.');
            if ($deferredSelection !== null) {
                if (array_diff($deferredSelection, $this->available($policy,$client)->pluck('id')->all())) $this->fail('Un modello selezionato non è più disponibile per questa polizza.');
                $pending->update(['data'=>array_merge($pending->data,['selected_templates'=>array_map('intval',$deferredSelection)]), 'read_at'=>null]);
                return;
            }
            $this->log($client, $policy, 'forms_skipped', 'Nessun modulo da generare per '.($change ? 'il rinnovo #'.$change->id : 'la nuova emissione').' della polizza '.$policy->number.'.');
            PolicyFormReminder::clear($policy);
        });
    }
    public function pdf(string $title,string $body,string $reference,bool $draft=false,array $tokens=[]): string
    {
        /** Il testo viene escapato prima della stampa e il PDF non può caricare risorse esterne o eseguire codice. */
        $options=new \Dompdf\Options(['isRemoteEnabled'=>false,'isPhpEnabled'=>false,'isJavascriptEnabled'=>false,'defaultFont'=>'DejaVu Sans','chroot'=>resource_path('insurance')]);
        $layout = FormTemplateContent::layout($body);
        $complete = FormTemplateContent::isDocument($body);
        $tokens = array_replace($tokens,['{{titolo_modello}}'=>$title,'{{riferimento_documento}}'=>$reference]);
        $content = FormTemplateContent::html($body,$tokens);
        $header = FormTemplateContent::html($body,$tokens,'header');
        $footer = FormTemplateContent::html($body,$tokens,'footer');
        $imageLimits = ['header'=>$layout['header_space']-4,'footer'=>$layout['footer_space']-4];
        $heights = [];
        for ($pass=0;$pass<2;$pass++) {
            $pdf=new \Dompdf\Dompdf($options); $pdf->setPaper($layout['paper'],$layout['orientation']);
            if ($complete) $pdf->setCallbacks([['event'=>'end_frame','f'=>function ($frame) use (&$heights) {
                $part = $frame->get_node()->nodeName;
                if (in_array($part,['header','footer'],true) && $frame->get_style()->position==='fixed') {
                    $heights[$part] = max($heights[$part] ?? 0,$frame->get_margin_height()*25.4/72);
                }
            }]]);
            $pdf->loadHtml(view('insurance-forms.pdf',compact('title','content','reference','draft','layout','complete','header','footer','imageLimits'))->render(),'UTF-8'); $pdf->render();
            $adjusted = false;
            /** Lo spazio minimo scelto cresce quando il contenuto stampato richiede più altezza. */
            foreach ($heights as $part=>$height) {
                if ($layout[$part.'_space'] < ceil($height)+3) {
                    $layout[$part.'_space'] = (int)ceil($height)+3;
                    $adjusted = true;
                }
            }
            $occupied = $layout['margin_top']+$layout['margin_bottom']+($layout['repeat_header'] && $header!=='' ? $layout['header_space'] : 0)+($layout['repeat_footer'] && $footer!=='' ? $layout['footer_space'] : 0);
            if ($complete && $pdf->getCanvas()->get_height()*25.4/72-$occupied < 70) $this->fail('Intestazione e piè di pagina occupano troppo spazio. Riduci il contenuto o scegli il formato verticale.','body');
            if (!$adjusted) break;
        }
        $canvas=$pdf->getCanvas(); $canvas->page_text($canvas->get_width()-70,$canvas->get_height()-22,'{PAGE_NUM} / {PAGE_COUNT}',$pdf->getFontMetrics()->getFont('DejaVu Sans','normal'),8,[0.3,0.3,0.3]);
        return $pdf->output();
    }
    public function submit(InsuranceFormDocument $document,array $files,array $data, ?\App\Models\User $operator = null): InsuranceFormSubmission
    {
        $operator ??= auth()->user();
        $package=$document->package; Gate::forUser($operator)->authorize('updateRegistry',$package->client); $stored=[];
        try { return DB::transaction(function() use ($document,$package,$files,$data,$operator,&$stored) {
            $client=Client::current()->whereKey($package->client_id)->lockForUpdate()->firstOrFail(); Gate::forUser($operator)->authorize('updateRegistry',$client);
            $document=InsuranceFormDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if(!$document->signature_required) $this->fail('Questo modulo non richiede una firma.');
            if(InsuranceFormSubmission::where('request_key',$data['request_key'])->exists()) $this->fail('Questo caricamento è già stato registrato.');
            $last=$document->submissions()->first();
            if (isset($data['previous_submission']) && (int)$data['previous_submission'] !== (int)$last?->id) $this->fail('È già arrivata un’altra copia di questo modulo. Controllala prima di caricare una nuova versione.');
            if($last && mb_strlen(trim($data['note']??''))<10) $this->fail('Motiva la nuova versione dei documenti firmati.','note');
            if($package->client->client_type==='company' && empty($data['signer_role'])) $this->fail('Indica la qualifica del firmatario.','signer_role');
            if(collect($files)->sum(fn($file)=>$file->getSize())>50*1024*1024) $this->fail('Il totale dei file non può superare 50 MB.','files');
            $submission=$document->submissions()->create(array_intersect_key($data,array_flip(['signer_name','signer_role','signed_on','note','request_key']))+['version'=>($last?->version??0)+1,'created_by'=>$operator->id,'file_hashes'=>[]]);
            $stored=app(PrivateAttachmentBatch::class)->upload($package->client,$files,['type'=>'document','document_category'=>$document->template->category,'insurance_policy_id'=>$package->insurance_policy_id,'insurance_policy_change_id'=>$package->insurance_policy_change_id,'insurance_form_document_id'=>$document->id,'insurance_form_submission_id'=>$submission->id,'review_status'=>'received','version_number'=>$submission->version], $operator);
            $hashes=[]; foreach($stored as $file) $hashes[$file->id]=hash('sha256',Storage::disk($file->disk)->get($file->path)); $submission->update(['file_hashes'=>$hashes]);
            app(AuditLogService::class)->log('forms_received',$package->policy??$package->client,description:'Ricevuti '.count($stored).' file per '.$document->title.', versione firmata '.$submission->version.'.',userId:$operator->id);
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
