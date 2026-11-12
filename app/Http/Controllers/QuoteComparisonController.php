<?php

namespace App\Http\Controllers;

use App\Models\{Attachment, Client, InsuranceQuote, InsuranceQuoteCase, InsuranceQuoteReading, SupplierCompany};
use App\Services\{AiConfiguration, AiQuoteReader, AiQuoteSchema, ClientDocumentRegistry, QuoteComparison};
use App\Support\TableListing;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Validation\{Rule, ValidationException};

class QuoteComparisonController extends Controller
{
    private function installed(): void { abort_unless(Schema::hasTable('insurance_quote_reviews'),503,'Completa l’aggiornamento del database per attivare il confronto preventivi.'); }
    private function query(Request $request)
    {
        return InsuranceQuote::whereHas('quoteCase',fn($query)=>$query->visibleTo($request->user()));
    }
    private function relations(): array { return ['company','agency','quoteCase.client','quoteCase.vehicle','quoteCase.branch','documents','readings.document','comparisonReview.verifier']; }

    public function index(Request $request,QuoteComparison $comparison)
    {
        $this->authorize('viewAny',Client::class);
        if(!Schema::hasTable('insurance_quote_reviews')) return to_route('quotes.index');
        $filters=$request->validate(['search'=>'nullable|string|max:255','verification'=>'nullable|in:pending,verified,missing','company'=>'nullable|integer|min:1','case'=>'nullable|integer|min:1','client'=>'nullable|integer|min:1']);
        $case=isset($filters['case']) ? InsuranceQuoteCase::visibleTo($request->user())->findOrFail($filters['case']) : null;
        $query=$this->query($request)->with($this->relations())->withCount(['documents as offer_documents_count'=>fn($q)=>$q->where('document_category','quote')]);
        if($case) $query->where('insurance_quote_case_id',$case->id);
        if($filters['client']??null) { Client::current()->visibleTo($request->user())->findOrFail($filters['client']); $query->whereHas('quoteCase',fn($q)=>$q->where('client_id',$filters['client'])); }
        if($filters['company']??null) $query->where('supplier_company_id',$filters['company']);
        if($filters['search']??null) {
            $like='%'.$filters['search'].'%';
            $query->where(fn($q)=>$q->where('reference','like',$like)->orWhereHas('company',fn($q)=>$q->where('name','like',$like))->orWhereHas('agency',fn($q)=>$q->where('name','like',$like))->orWhereHas('quoteCase',fn($q)=>$q->where('subject','like',$like)->orWhere('vehicle_plate','like',$like)->orWhereHas('client',fn($q)=>$q->where('name','like',$like))));
        }
        if(($filters['verification']??'')==='verified') $query->whereHas('comparisonReview',fn($q)=>$q->whereNotNull('verified_at'));
        if(($filters['verification']??'')==='pending') $query->whereHas('documents',fn($q)=>$q->where('document_category','quote'))->whereDoesntHave('comparisonReview',fn($q)=>$q->whereNotNull('verified_at'));
        if(($filters['verification']??'')==='missing') $query->whereDoesntHave('documents',fn($q)=>$q->where('document_category','quote'));
        $offers=$query->latest('id')->paginate(TableListing::perPage(25))->withQueryString();
        return response()->view('quotes.offers',compact('offers','filters','case','comparison')+['companies'=>SupplierCompany::orderBy('name')->get()])->header('Cache-Control','private, no-store');
    }

    public function show(Request $request,QuoteComparison $comparison)
    {
        $this->authorize('viewAny',Client::class); $this->installed();
        $input=$request->validate(['quotes'=>'required|array|min:1|max:6','quotes.*'=>'required|integer|min:1|distinct','source_quote'=>'nullable|integer|min:1','field'=>'nullable|string|max:100','document'=>'nullable|integer|min:1','page'=>'nullable|integer|between:1,1000']);
        $offers=$this->query($request)->with($this->relations())->whereIn('id',$input['quotes'])->get()->sortBy(fn($quote)=>array_search($quote->id,array_map('intval',$input['quotes']),true))->values();
        abort_unless($offers->count()===count($input['quotes']),404);
        if($offers->pluck('insurance_quote_case_id')->unique()->count()!==1) throw ValidationException::withMessages(['quotes'=>'Confronta offerte della stessa pratica e dello stesso rischio.']);
        if($offers->contains(fn($quote)=>!$quote->documents->contains(fn($doc)=>$doc->document_category==='quote'))) throw ValidationException::withMessages(['quotes'=>'Carica il PDF per ogni offerta selezionata.']);
        $case=$offers->first()->quoteCase; $columns=[];
        foreach($offers as $offer) { $cells=$comparison->cells($offer); $columns[]=['offer'=>$offer,'cells'=>$cells,'warnings'=>$comparison->warnings($offer,$cells),'editable'=>$comparison->editable($offer)]; }
        $rows=$comparison->rows($columns); $ids=$offers->modelKeys();
        $periods=collect($columns)->map(fn($column)=>[$column['cells']['starts_on']['value'],$column['cells']['expires_on']['value']]);
        $periodIncomplete=$periods->contains(fn($period)=>!$period[0]||!$period[1]); $periodDifferent=$periods->map(fn($period)=>implode('|',$period))->unique()->count()>1;
        $source=null;
        if($request->filled('source_quote')) {
            $column=collect($columns)->first(fn($column)=>$column['offer']->id===(int)$input['source_quote']);
            abort_unless($column && isset($column['cells'][$input['field']??'']),404);
            $cell=$column['cells'][$input['field']];
            $evidence=collect($cell['sources'])->first(fn($entry)=>$entry['document_id']===(int)($input['document']??0) && $entry['page']===(int)($input['page']??0));
            abort_unless($evidence,404);
            $document=$column['offer']->documents->firstWhere('id',$evidence['document_id']); $this->authorize('download',$document);
            $source=compact('cell','evidence')+['offer'=>$column['offer'],'editable'=>$column['editable']];
        }
        $data=compact('case','columns','rows','ids','periodIncomplete','periodDifferent','source','comparison')+['aiReady'=>app(AiConfiguration::class)->ready()];
        if($request->expectsJson()) { abort_unless($source,404); return response()->json(['html'=>view('quotes.comparison-source',$data)->render()])->header('Cache-Control','private, no-store'); }
        return response()->view('quotes.comparison',$data)->header('Cache-Control','private, no-store');
    }

    public function upload(Request $request)
    {
        $this->authorize('create',Client::class); $this->installed();
        $input=$request->validate(['case'=>'nullable|integer|min:1','search'=>'nullable|string|max:255']);
        $cases=InsuranceQuoteCase::visibleTo($request->user())->where('status','open')->with('client')->when($input['search']??null,fn($q,$search)=>$q->where(fn($q)=>$q->where('subject','like','%'.$search.'%')->orWhereHas('client',fn($q)=>$q->where('name','like','%'.$search.'%'))))->latest('id')->limit(150)->get();
        $case=isset($input['case']) ? InsuranceQuoteCase::visibleTo($request->user())->with(['client','quotes.company','quotes.agency','quotes.documents'])->findOrFail($input['case']) : null;
        if($case) { $this->authorize('updateRegistry',$case->client); abort_unless($case->status==='open',409); }
        return response()->view('quotes.offer-upload',compact('cases','case'))->header('Cache-Control','private, no-store');
    }

    public function store(Request $request,InsuranceQuoteCase $quoteCase,QuoteComparison $comparison,ClientDocumentRegistry $registry)
    {
        $this->authorize('updateRegistry',$quoteCase->client); $this->installed();
        $request->validate(['files'=>'required|array|min:1|max:10','files.*'=>'required|array|min:1|max:10','files.*.*'=>'required|file|max:10240|mimes:pdf|extensions:pdf']);
        $uploads=$request->file('files'); $ids=array_keys($uploads);
        if(collect($ids)->contains(fn($id)=>!ctype_digit((string)$id)) || $quoteCase->quotes()->whereIn('id',$ids)->count()!==count($ids)) throw ValidationException::withMessages(['files'=>'Seleziona solo offerte appartenenti a questa pratica.']);
        if(collect($uploads)->flatten()->sum(fn($file)=>$file->getSize())>50*1024*1024) throw ValidationException::withMessages(['files'=>'Carica al massimo 50 MB complessivi per volta.']);
        $stored=[];
        try {
            DB::transaction(function()use($quoteCase,$comparison,$registry,$uploads,&$stored) {
                foreach($uploads as $id=>$files) {
                    $offer=$comparison->locked($quoteCase->quotes()->findOrFail($id));
                    $stored=array_merge($stored,$registry->uploadMany($quoteCase->client,$files,['document_category'=>'quote','insurance_quote_id'=>$offer->id]));
                    $comparison->invalidate($offer); $comparison->log($offer,'Aggiunti i documenti dell’offerta.',['documents'=>count($files)]);
                }
            });
        } catch(\Throwable $exception) { foreach($stored as $document) Storage::disk($document->disk)->delete($document->path); throw $exception; }
        $selected=$quoteCase->quotes()->whereHas('documents',fn($q)=>$q->where('document_category','quote'))->orderBy('id')->limit(6)->pluck('id')->all();
        return to_route('quotes.compare',['quotes'=>$selected])->with('success','PDF caricati. Controlla i dati o avvia la lettura AI.');
    }

    public function read(Request $request,InsuranceQuote $quote,QuoteComparison $comparison,AiQuoteReader $reader)
    {
        $this->authorize('updateRegistry',$quote->quoteCase->client); $this->installed();
        $input=$request->validate(['document_id'=>'required|integer|min:1','ai_processing'=>'accepted','retry'=>'sometimes|boolean']);
        $quote=DB::transaction(fn()=>$comparison->locked($quote));
        $document=$quote->documents()->where('document_category','quote')->findOrFail($input['document_id']); $this->authorize('download',$document);
        abort_unless($document->attachable_type===(new Client)->getMorphClass() && (int)$document->attachable_id===(int)$quote->quoteCase->client_id,404);
        $disk=Storage::disk($document->disk); abort_unless($disk->exists($document->path),404);
        abort_unless($disk->mimeType($document->path)==='application/pdf' && $disk->size($document->path)<=10*1024*1024,422);
        if($existing=InsuranceQuoteReading::with('usage')->where('attachment_id',$document->id)->first()) return response()->json(['cached'=>true,'message'=>'Lettura già disponibile, nessun nuovo consumo.','usage_id'=>$existing->ai_usage_id])->header('Cache-Control','private, no-store');
        $file=new UploadedFile($disk->path($document->path),'preventivo.pdf','application/pdf',null,true);
        $result=$reader->read($file,$request->user(),$request->boolean('retry'),['insurance_quote_id'=>$quote->id,'attachment_id'=>$document->id]);
        DB::transaction(function()use($quote,$document,$comparison,$result) {
            $quote=$comparison->locked($quote);
            $reading=InsuranceQuoteReading::firstOrCreate(['attachment_id'=>$document->id],['insurance_quote_id'=>$quote->id,'ai_usage_id'=>$result['usage']['id'],'extraction'=>$result['extraction']]);
            if($reading->wasRecentlyCreated) { $comparison->invalidate($quote); $comparison->log($quote,'Preparata la lettura AI del preventivo.',['ai_usage_id'=>$result['usage']['id'],'attachment_id'=>$document->id]); }
        });
        return response()->json(['message'=>'Lettura disponibile. I dati richiedono il controllo dell’operatore.','usage'=>$result['usage']])->header('Cache-Control','private, no-store');
    }

    public function update(Request $request,InsuranceQuote $quote,string $action,QuoteComparison $comparison)
    {
        $this->authorize('updateRegistry',$quote->quoteCase->client); $this->installed();
        $rules=match($action) {
            'correct'=>['revision'=>'required|integer|min:0','field'=>'required|string|max:100','value'=>'nullable|string|max:1000'],
            'verify'=>['revision'=>'required|integer|min:0','confirmed'=>'accepted','verification_note'=>'nullable|string|max:2000'],
            'coverage'=>['revision'=>'required|integer|min:0','code'=>['required',Rule::in(array_keys(AiQuoteSchema::COVERAGES))],'name'=>'required|string|max:150'],
            'prefer'=>['selected'=>'required|boolean'], default=>abort(404),
        };
        $data=$request->validate($rules+['comparison_ids'=>'sometimes|array|max:6','comparison_ids.*'=>'integer|min:1|distinct']);
        match($action) { 'correct'=>$comparison->correct($quote,$data), 'verify'=>$comparison->verify($quote,$data), 'coverage'=>$comparison->addCoverage($quote,$data), 'prefer'=>$comparison->prefer($quote,$request->boolean('selected')) };
        $requested=array_values(array_unique(array_map('intval',[...($data['comparison_ids']??[]),$quote->id])));
        /** Mantiene l’ordine scelto dall’operatore anche dopo una correzione o una conferma. */
        $ids=$quote->quoteCase->quotes()->whereHas('documents',fn($q)=>$q->where('document_category','quote'))->whereIn('id',$requested)->pluck('id')->sortBy(fn($id)=>array_search((int)$id,$requested,true))->take(6)->values()->all();
        return to_route('quotes.compare',['quotes'=>$ids])->with('success',match($action) {'correct'=>'Dato corretto. Ricontrolla l’offerta prima di confermare la verifica.','verify'=>'Dati verificati. L’approvazione del cliente resta distinta.','coverage'=>'Garanzia aggiunta al confronto.','prefer'=>'Preferenza dell’operatore aggiornata.'});
    }
}
