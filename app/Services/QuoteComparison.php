<?php

namespace App\Services;

use App\Models\{InsurancePolicy, InsuranceQuote, InsuranceQuoteCase, InsuranceQuoteReview};
use App\Support\Money;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\{Rule, ValidationException};

class QuoteComparison
{
    public const FIELDS = [
        'premium'=>['Premio lordo del periodo','decimal'], 'starts_on'=>['Decorrenza','date'], 'expires_on'=>['Scadenza copertura','date'], 'valid_until'=>['Preventivo valido fino al','date'],
        'payment_frequency'=>['Frazionamento','frequency'], 'installment_premium'=>['Importo rata','decimal'], 'fees'=>['Costi aggiuntivi dichiarati','text'],
        'reference'=>['Riferimento preventivo','text'], 'product'=>['Prodotto','text'], 'company'=>['Compagnia nel documento','text'], 'agency'=>['Agenzia nel documento','text'],
        'client.name'=>['Contraente nel documento','text'], 'client.tax_code'=>['Codice fiscale del contraente','text'], 'vehicle.plate'=>['Targa nel documento','text'], 'vehicle.vin'=>['Telaio nel documento','text'], 'risk.description'=>['Rischio descritto','text'], 'driver_conditions'=>['Conducenti e condizioni di guida','text'],
    ];
    public const COVERAGE_FIELDS = ['status'=>'Copertura','limit'=>'Massimali e limiti','insured_value'=>'Valore assicurato','deductible'=>'Franchigia','excess'=>'Scoperto','conditions'=>'Condizioni'];
    public const STATUSES = ['included'=>'Inclusa','excluded'=>'Esclusa','not_indicated'=>'Non indicata','uncertain'=>'Da chiarire'];

    public function load(InsuranceQuote $quote): InsuranceQuote
    {
        return $quote->loadMissing(['company','agency','quoteCase.client','quoteCase.vehicle','quoteCase.branch','documents','readings.document','comparisonReview.verifier']);
    }

    public function editable(InsuranceQuote $quote): bool
    {
        return $quote->quoteCase->status==='open' && in_array($quote->status,['requested','received'],true);
    }

    public function cells(InsuranceQuote $quote): array
    {
        $this->load($quote);
        $base=['premium'=>$quote->premium_cents===null ? null : Money::decimal($quote->premium_cents), 'starts_on'=>$quote->starts_on?->toDateString(), 'expires_on'=>$quote->expires_on?->toDateString(), 'valid_until'=>$quote->valid_until?->toDateString(), 'payment_frequency'=>$quote->payment_frequency, 'reference'=>$quote->reference, 'product'=>$quote->product];
        $cells=[];
        foreach(self::FIELDS as $key=>[$label,$type]) $cells[$key]=$this->cell($key,$label,'Dati del preventivo',$type,$base[$key]??null);
        $auto=$quote->quoteCase->vehicle_id || $quote->quoteCase->vehicle_plate || str_contains(mb_strtolower($quote->quoteCase->branch?->name??''),'auto');
        if($auto) foreach(['rca','cristalli','furto_incendio','assistenza'] as $code) $this->coverage($cells,$code,AiQuoteSchema::COVERAGES[$code]);
        foreach($quote->readings as $reading) {
            $document=$reading->document;
            if(!$document || !$quote->documents->contains('id',$document->id) || $document->document_category!=='quote' || (int)$document->attachable_id!==(int)$quote->quoteCase->client_id || $document->attachable_type!==(new \App\Models\Client)->getMorphClass()) continue;
            foreach($reading->extraction['fields']??[] as $field) if(isset($cells[$field['key']])) $this->evidence($cells[$field['key']],$field,$document);
            foreach($reading->extraction['coverages']??[] as $coverage) {
                $code=$coverage['code']==='other' ? 'other_'.substr(hash('sha256',mb_strtolower(trim($coverage['name']))),0,20) : $coverage['code'];
                $this->coverage($cells,$code,AiQuoteSchema::COVERAGES[$code]??$coverage['name']);
                foreach($coverage['fields'] as $field) if(isset($cells['coverage.'.$code.'.'.$field['key']])) $this->evidence($cells['coverage.'.$code.'.'.$field['key']],$field,$document,$coverage['name']);
            }
        }
        foreach($quote->comparisonReview?->overrides??[] as $key=>$override) {
            if(!isset($cells[$key]) && preg_match('/\Acoverage\.([a-z0-9_]+)\.([a-z_]+)\z/',$key,$parts) && isset(self::COVERAGE_FIELDS[$parts[2]])) $this->coverage($cells,$parts[1],$override['group']);
            if(isset($cells[$key])) { $cells[$key]['value']=$override['value']; $cells[$key]['manual']=true; $cells[$key]['corrected']=true; $cells[$key]['uncertain']=false; }
        }
        foreach($cells as &$cell) {
            $candidates=array_values(array_unique(array_map(fn($source)=>$this->comparableValue((string)$source['value'],$cell['type']),$cell['sources'])));
            if(!$cell['corrected']) {
                if($cell['value']===null && count($candidates)===1) $cell['value']=$candidates[0];
                if(count($candidates)>1 || ($cell['value']!==null && $candidates && !in_array((string)$cell['value'],$candidates,true))) $cell['uncertain']=true;
            }
            $cell['display']=$this->display($cell['value'],$cell['type']);
        }
        unset($cell);
        return $cells;
    }

    private function cell(string $key,string $label,string $group,string $type,?string $value=null): array
    {
        return compact('key','label','group','type','value')+['sources'=>[],'manual'=>$value!==null,'corrected'=>false,'uncertain'=>false];
    }

    private function comparableValue(string $value,string $type): string
    {
        /** Le fonti restano originali; importi equivalenti non devono apparire in conflitto per i soli decimali. */
        return $type==='decimal' && preg_match('/\A\d{1,10}(?:\.\d{1,2})?\z/',$value) ? Money::decimal(Money::cents($value)) : $value;
    }

    private function coverage(array &$cells,string $code,string $name): void
    {
        foreach(self::COVERAGE_FIELDS as $key=>$label) {
            $field='coverage.'.$code.'.'.$key;
            $cells[$field]??=$this->cell($field,$label,$name,$key==='status'?'coverage_status':'text');
        }
    }

    private function evidence(array &$cell,array $field,\App\Models\Attachment $document,?string $name=null): void
    {
        $cell['uncertain']=$cell['uncertain'] || $field['certainty']==='uncertain' || $field['value']==='uncertain';
        if($field['value']===null || $field['value']==='not_indicated') return;
        $cell['sources'][]=['document_id'=>$document->id,'document_name'=>$document->original_name,'value'=>$field['value'],'page'=>$field['page'],'quote'=>$field['quote'],'certainty'=>$field['certainty'],'coverage_name'=>$name,'url'=>route('attachments.preview',[$document,'page'=>$field['page']]),'download_url'=>$document->downloadUrl()];
    }

    public function display(?string $value,string $type): string
    {
        if($value===null || $value==='') return 'Non indicata';
        if($type==='coverage_status') return self::STATUSES[$value]??'Da chiarire';
        if($type==='decimal' && preg_match('/\A\d{1,10}(?:\.\d{1,2})?\z/',$value)) return Money::format(Money::cents($value));
        if($type==='date' && preg_match('/\A\d{4}-\d{2}-\d{2}\z/',$value)) { try { return \Illuminate\Support\Carbon::createFromFormat('!Y-m-d',$value)->format('d/m/Y'); } catch(\Throwable) {} }
        return $value;
    }

    public function warnings(InsuranceQuote $quote,array $cells): array
    {
        $warnings=[];
        foreach($quote->readings as $reading) {
            $warnings=array_merge($warnings,$reading->extraction['warnings']??[]);
            if(in_array($reading->extraction['document_kind']??'', ['multiple','unknown'],true)) $warnings[]='Un PDF non identifica una singola offerta. Controlla i documenti e il rischio.';
        }
        $case=$quote->quoteCase;
        $checks=['vehicle.plate'=>$case->vehicle_plate ?: ($case->vehicle_snapshot['plate']??null), 'vehicle.vin'=>$case->vehicle_snapshot['vin']??$case->vehicle?->vin, 'client.tax_code'=>$case->client->tax_code];
        foreach($checks as $key=>$expected) if($expected && filled($cells[$key]['value']??null) && $this->normalized($expected)!==$this->normalized($cells[$key]['value'])) $warnings[]=self::FIELDS[$key][0].' diverso dai dati della pratica: controlla che il PDF riguardi lo stesso rischio.';
        foreach(['company'=>$quote->company->name,'agency'=>$quote->agency?->name] as $key=>$expected) if($expected && filled($cells[$key]['value']??null) && $this->normalized($expected)!==$this->normalized($cells[$key]['value'])) $warnings[]=self::FIELDS[$key][0].' diverso dal destinatario collegato alla proposta. Verifica il collegamento senza dedurlo dal nome.';
        if(collect($cells)->contains('uncertain',true)) $warnings[]='Sono presenti dati ambigui o diversi tra i documenti e la proposta registrata.';
        return array_values(array_unique($warnings));
    }

    private function normalized(string $value): string { return preg_replace('/[^\pL\pN]/u','',mb_strtoupper($value)); }

    public function rows(array $columns): array
    {
        $rows=[];
        foreach($columns as $column) foreach($column['cells'] as $key=>$cell) $rows[$key]??=array_intersect_key($cell,array_flip(['key','label','group','type']));
        foreach($rows as &$row) {
            $row['cells']=array_map(fn($column)=>$column['cells'][$row['key']]??($this->cell($row['key'],$row['label'],$row['group'],$row['type'])+['display'=>'Non indicata']),$columns);
            $row['different']=count(array_unique(array_map(fn($cell)=>json_encode([$cell['value'],$cell['uncertain']]),$row['cells'])))>1;
        }
        unset($row);
        return collect($rows)->groupBy('group')->all();
    }

    public function locked(InsuranceQuote $quote,?int $revision=null): InsuranceQuote
    {
        /** Lo stesso ordine di blocco del flusso preventivi evita aggiornamenti concorrenti incoerenti. */
        $case=InsuranceQuoteCase::visibleTo(auth()->user())->whereKey($quote->insurance_quote_case_id)->lockForUpdate()->firstOrFail();
        $quote=InsuranceQuote::whereKey($quote->id)->lockForUpdate()->firstOrFail()->setRelation('quoteCase',$case);
        if(!$this->editable($quote)) throw ValidationException::withMessages(['quote'=>'La proposta è già stata presentata, approvata o chiusa. Il confronto resta consultabile.']);
        /** Il valore iniziale serve anche al modello appena creato, prima di una rilettura dal database. */
        $review=InsuranceQuoteReview::firstOrCreate(['insurance_quote_id'=>$quote->id],['revision'=>0]);
        if($revision!==null && $review->revision!==$revision) throw ValidationException::withMessages(['revision'=>'Il confronto è cambiato nel frattempo. Ricarica la pagina e ricontrolla i dati.']);
        return $quote->setRelation('comparisonReview',$review);
    }

    public function invalidate(InsuranceQuote $quote): void
    {
        $review=$quote->comparisonReview;
        $review->update(['revision'=>$review->revision+1,'verified_at'=>null,'verified_by'=>null]);
    }

    public function correct(InsuranceQuote $quote,array $data): void
    {
        DB::transaction(function()use($quote,$data) {
            $quote=$this->locked($quote,(int)$data['revision']); $cells=$this->cells($quote); $key=$data['field'];
            if(!isset($cells[$key])) throw ValidationException::withMessages(['field'=>'Dato non presente nel confronto.']);
            $cell=$cells[$key]; $value=$data['value']??null;
            $rules=match($cell['type']) { 'decimal'=>['nullable','numeric','min:0','max:9999999999.99','decimal:0,2'], 'date'=>['nullable','date_format:Y-m-d'], 'frequency'=>['nullable',Rule::in(InsurancePolicy::PAYMENT_FREQUENCIES)], 'coverage_status'=>['nullable',Rule::in(array_keys(self::STATUSES))], default=>['nullable','string','max:1000'] };
            validator(['value'=>$value],['value'=>$rules])->validate();
            if($cell['type']==='decimal' && $value!==null) $value=Money::decimal(Money::cents($value));
            $review=$quote->comparisonReview; $overrides=$review->overrides??[]; $history=$review->history??[];
            $history[]=['field'=>$key,'before'=>$cell['value'],'after'=>$value,'user_id'=>auth()->id(),'at'=>now()->toIso8601String()];
            $overrides[$key]=['value'=>$value,'group'=>$cell['group']];
            /** Le correzioni restano nel confronto fino alla verifica esplicita dell’intera offerta. */
            $review->update(['overrides'=>$overrides,'history'=>$history]); $this->invalidate($quote);
            $this->log($quote,'Corretto un dato del confronto.',['field'=>$key,'revision'=>$review->revision]);
        });
    }

    public function addCoverage(InsuranceQuote $quote,array $data): void
    {
        DB::transaction(function()use($quote,$data) {
            $quote=$this->locked($quote,(int)$data['revision']); $name=trim($data['name']); $code=$data['code'];
            if($code==='other') $code='other_'.substr(hash('sha256',mb_strtolower($name)),0,20);
            $key='coverage.'.$code.'.status';
            if(isset($this->cells($quote)[$key])) throw ValidationException::withMessages(['name'=>'La garanzia è già presente. Modifica i dati della riga esistente.']);
            $values=$quote->comparisonReview->overrides??[];
            $values[$key]=['value'=>'not_indicated','group'=>AiQuoteSchema::COVERAGES[$code]??$name];
            $quote->comparisonReview->update(['overrides'=>$values]); $this->invalidate($quote);
            $this->log($quote,'Aggiunta una garanzia al confronto.',['coverage'=>$code]);
        });
    }

    public function verify(InsuranceQuote $quote,array $data): void
    {
        DB::transaction(function()use($quote,$data) {
            $quote=$this->locked($quote,(int)$data['revision']); $cells=$this->cells($quote);
            $documents=$quote->documents->where('document_category','quote');
            if($documents->isEmpty() || $documents->contains(fn($doc)=>!Storage::disk($doc->disk)->exists($doc->path))) throw ValidationException::withMessages(['quote'=>'Il PDF dell’offerta deve essere presente e disponibile prima della verifica.']);
            $values=[];
            foreach(['premium','reference','product','starts_on','expires_on','valid_until','payment_frequency'] as $key) $values[$key]=$cells[$key]['value'];
            validator($values,['premium'=>['required','numeric',$quote->quoteCase->purpose==='addition'?'min:0':'min:0.01','max:9999999999.99','decimal:0,2'],'reference'=>'required|string|max:100','product'=>'required|string|max:255','starts_on'=>'required|date_format:Y-m-d','expires_on'=>'required|date_format:Y-m-d|after:starts_on','valid_until'=>'required|date_format:Y-m-d','payment_frequency'=>['required',Rule::in(InsurancePolicy::PAYMENT_FREQUENCIES)]])->validate();
            $missing=collect($cells)->contains(fn($cell)=>$cell['type']==='coverage_status' && in_array($cell['value'],[null,'not_indicated','uncertain'],true));
            if(($this->warnings($quote,$cells) || $missing) && mb_strlen(trim($data['verification_note']??''))<5) throw ValidationException::withMessages(['verification_note'=>'Annota come hai verificato il rischio e quali informazioni restano mancanti o da chiarire.']);
            if($quote->status==='requested') {
                $values['received_on']=today()->toDateString(); $values['supplier_agency_id']=$quote->supplier_agency_id;
                if($values['valid_until']<$values['received_on']) throw ValidationException::withMessages(['valid_until'=>'La proposta è già scaduta. Richiedi una proposta aggiornata prima di registrarla.']);
                app(InsuranceQuoteWorkflow::class)->change($quote,'receive',$values);
            } else {
                if($quote->quoteCase->purpose==='addition') {
                    $policy=$quote->quoteCase->currentPolicy;
                    if(!$policy || $values['starts_on']<$policy->starts_on->toDateString() || $values['expires_on']>$policy->expires_on->toDateString()) throw ValidationException::withMessages(['starts_on'=>'Il periodo aggiuntivo deve rientrare nella polizza attuale.']);
                }
                $values['premium_cents']=Money::cents($values['premium']); unset($values['premium']); $quote->update($values);
            }
            $quote->comparisonReview->update(['verified_at'=>now(),'verified_by'=>auth()->id(),'verification_note'=>$data['verification_note']??null,'revision'=>$quote->comparisonReview->revision+1]);
            $this->log($quote,'Confermati dall’operatore i dati del confronto.');
        });
    }

    public function prefer(InsuranceQuote $quote,bool $selected): void
    {
        DB::transaction(function()use($quote,$selected) {
            $case=InsuranceQuoteCase::visibleTo(auth()->user())->whereKey($quote->insurance_quote_case_id)->lockForUpdate()->firstOrFail();
            $quote=$case->quotes()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if($case->status!=='open' || in_array($quote->status,['approved','converted','rejected'],true) || !$quote->documents()->where('document_category','quote')->exists()) throw ValidationException::withMessages(['quote'=>'Questa proposta non può essere indicata come preferita.']);
            if(!$selected && (int)$case->preferred_quote_id!==$quote->id) return;
            $case->update(['preferred_quote_id'=>$selected?$quote->id:null,'preferred_by'=>$selected?auth()->id():null,'preferred_at'=>$selected?now():null]);
            $this->log($quote,$selected?'Indicata la preferenza interna dell’operatore.':'Rimossa la preferenza interna dell’operatore.');
        });
    }

    public function log(InsuranceQuote $quote,string $message,array $values=[]): void
    {
        $quote->quoteCase->client->auditLogs()->create(['user_id'=>auth()->id(),'action'=>'quote_updated','description'=>'Preventivo #'.$quote->insurance_quote_case_id.': '.$message,'new_values'=>['quote_id'=>$quote->id]+$values,'created_at'=>now()]);
    }
}
