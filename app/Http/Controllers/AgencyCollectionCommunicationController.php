<?php
namespace App\Http\Controllers;
use App\Models\{InsuranceCollection,InsuranceTitle,SupplierAgency,SupplierCompany};
use App\Services\{AgencyCollectionEmailWorkflow,AgencyMailConfiguration,CollectionCommunicationStatus};
use App\Support\TableListing;
use App\Support\CommunicationSelectionScope;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AgencyCollectionCommunicationController extends Controller
{
    private function selection(Request $request): array
    {
        if ($request->filled('view')) {
            $view=$request->validate(['view'=>['required','string','in:today,yesterday,last15,month,custom,pending,attention']])['view'];
            if (in_array($view,['pending','attention'],true)) $request->merge(['scope'=>$view,'from'=>null,'to'=>null]);
            elseif ($view==='custom') {
                $request->validate(['from'=>['required','date_format:Y-m-d'],'to'=>['required','date_format:Y-m-d']]);
                $request->merge(['scope'=>'period']);
            } else {
                $day=today()->toDateString();
                [$from,$to]=match($view) {
                    'yesterday'=>[today()->subDay()->toDateString(),today()->subDay()->toDateString()],
                    'last15'=>[today()->subDays(14)->toDateString(),$day],
                    'month'=>[today()->startOfMonth()->toDateString(),$day],
                    default=>[$day,$day],
                };
                $request->merge(['scope'=>'period','from'=>$from,'to'=>$to]);
            }
        }
        $filters=$request->validate(['scope'=>['nullable','in:period,pending,attention'],'unassigned'=>['nullable','boolean'],'from'=>['nullable','date_format:Y-m-d','before_or_equal:today'],'to'=>['nullable','date_format:Y-m-d','before_or_equal:today',...($request->filled('from')?['after_or_equal:from']:[])],'agency'=>['nullable','integer','exists:supplier_agencies,id'],'company'=>['nullable','integer','exists:supplier_companies,id'],'search'=>['nullable','string','max:255']]);
        $filters['scope'] ??= 'period';
        $status = app(CollectionCommunicationStatus::class);
        $query = $status->payments($request->user());
        if ($filters['scope'] === 'period') {
            $filters['from']??=today()->toDateString();$filters['to']??=today()->toDateString();
            if ($filters['to'] < $filters['from']) throw ValidationException::withMessages(['to'=>'La data finale non può precedere quella iniziale.']);
            if(\Carbon\Carbon::parse($filters['from'])->diffInDays(\Carbon\Carbon::parse($filters['to']),true)>366)throw ValidationException::withMessages(['from'=>'Seleziona al massimo un anno.']);
            $query->whereDate('insurance_collections.received_on','>=',$filters['from'])->whereDate('insurance_collections.received_on','<=',$filters['to']);
            $range = \Carbon\Carbon::parse($filters['from'])->format('d/m/Y').' – '.\Carbon\Carbon::parse($filters['to'])->format('d/m/Y');
        } else {
            $filters['from'] = $filters['to'] = null;
            $filters['scope'] === 'attention' ? $status->attention($query) : $status->pending($query);
            $range = $filters['scope'] === 'attention' ? 'Invii con esito da controllare, tutte le date' : 'Incassi da completare, tutte le date';
        }
        if ($filters['unassigned'] ?? false) $query->whereNull('communication_title.supplier_agency_id');
        foreach (['agency'=>'supplier_agency_id','company'=>'supplier_company_id'] as $key=>$column) if ($filters[$key] ?? null) $query->where('communication_title.'.$column,$filters[$key]);
        if ($filters['search'] ?? null) $query->where(fn($q)=>$q->where('communication_title.contract_snapshot->number','like','%'.$filters['search'].'%')->orWhereHas('title.policy.client',fn($client)=>$client->where('name','like','%'.$filters['search'].'%')));
        $query->with(['title.policy.client'])->orderBy('insurance_collections.id');
        return [$filters,$query,$range];
    }
    public function index(Request $request,AgencyCollectionEmailWorkflow $workflow,AgencyMailConfiguration $configuration)
    {
        $this->authorize('communications.manage');[$filters,$query,$range]=$this->selection($request);
        $payments=(clone $query)->paginate(TableListing::perPage(25))->withQueryString();
        $deliveries=$workflow->existingDeliveries($payments->getCollection());
        $selectionScope=CommunicationSelectionScope::forFilters($filters,$range);
        $agencies=SupplierAgency::orderBy('name')->get();
        $batchCounts=app(CollectionCommunicationStatus::class)->unclaimed(clone $query)->where('communication_agency.status','active')
            ->reorder()->select('communication_title.supplier_agency_id')->selectRaw('COUNT(*) AS item_count')->groupBy('communication_title.supplier_agency_id')->pluck('item_count','supplier_agency_id');
        $batches=$agencies->whereIn('id',$batchCounts->keys())->map(fn($agency)=>['agency'=>$agency,'count'=>(int)$batchCounts[$agency->id]]);
        return view('agency-communications.collections',['filters'=>$filters,'range'=>$range,'payments'=>$payments,'selectionScope'=>$selectionScope,'deliveries'=>$deliveries,'mailReady'=>$configuration->ready(),'agencies'=>$agencies,'batches'=>$batches,'companies'=>SupplierCompany::orderBy('name')->get()]);
    }
    public function review(Request $request,SupplierAgency $agency,AgencyCollectionEmailWorkflow $workflow)
    {
        $this->authorize('communications.manage');
        $all=$request->boolean('all_matching');
        if($all && $request->filled('_table_filters'))throw ValidationException::withMessages(['collections'=>'Con un filtro sulle singole righe, prepara la selezione dall’elenco.']);
        $data=$request->validate(['all_matching'=>['sometimes','accepted'],'collections'=>[Rule::requiredIf(!$all),Rule::prohibitedIf($all),'array','min:1','max:500'],'collections.*'=>['required','integer','distinct']]);
        [$filters,$query,$range]=$this->selection($request);
        $query->where('communication_title.supplier_agency_id',$agency->id);
        if($all) app(CollectionCommunicationStatus::class)->unclaimed($query);
        else $query->whereIn('insurance_collections.id',$data['collections']);
        /** The direct action is bounded and still produces a review; it never sends a message. */
        $payments=$query->limit(501)->get();
        if($payments->count()>500)throw ValidationException::withMessages(['collections'=>'Ci sono più di 500 incassi: restringi il periodo o scegli le singole righe.']);
        if($all && $payments->isEmpty())throw ValidationException::withMessages(['collections'=>'Non ci sono incassi da comunicare per questa agenzia con i filtri scelti.']);
        if(!$all && $payments->count()!==count($data['collections']))throw ValidationException::withMessages(['collections'=>'La selezione include incassi di un’altra agenzia o fuori dal periodo.']);
        if ($workflow->existingDeliveries($payments)->isNotEmpty()) throw ValidationException::withMessages(['collections'=>'La selezione contiene incassi già comunicati o con un invio da controllare. Aggiorna la selezione.']);
        if ($filters['scope'] !== 'period') $range = $payments->min('received_on')->format('d/m/Y').' – '.$payments->max('received_on')->format('d/m/Y');
        return to_route('agency-communications.show',$workflow->prepare($request->user(),$agency,$payments,$filters,$range));
    }
}
