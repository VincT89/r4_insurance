<?php
namespace App\Http\Controllers;
use App\Models\{InsuranceCollection,InsuranceTitle,SupplierAgency,SupplierCompany};
use App\Services\{AgencyCollectionEmailWorkflow,AgencyMailConfiguration,CollectionCommunicationStatus};
use App\Support\TableListing;
use App\Support\CommunicationSelectionScope;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AgencyCollectionCommunicationController extends Controller
{
    private function selection(Request $request): array
    {
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
        if ($filters['unassigned'] ?? false) $query->whereNull('communication_policy.supplier_agency_id');
        $query->whereHas('title.policy',function($q)use($filters){
            if($filters['agency']??null)$q->where('supplier_agency_id',$filters['agency']);if($filters['company']??null)$q->where('supplier_company_id',$filters['company']);
            if($filters['search']??null)$q->where(fn($q)=>$q->where('number','like','%'.$filters['search'].'%')->orWhereHas('client',fn($q)=>$q->where('name','like','%'.$filters['search'].'%')));
        })->with(['title.policy.client','title.policy.supplierAgency'])->orderBy('insurance_collections.id');
        return [$filters,$query,$range];
    }
    public function index(Request $request,AgencyCollectionEmailWorkflow $workflow,AgencyMailConfiguration $configuration)
    {
        $this->authorize('system.admin');[$filters,$query,$range]=$this->selection($request);
        $payments=$query->paginate(TableListing::perPage(25))->withQueryString();
        $deliveries=$workflow->existingDeliveries($payments->getCollection());
        $selectionScope=CommunicationSelectionScope::forFilters($filters,$range);
        return view('agency-communications.collections',['filters'=>$filters,'range'=>$range,'payments'=>$payments,'selectionScope'=>$selectionScope,'deliveries'=>$deliveries,'mailReady'=>$configuration->ready(),'agencies'=>SupplierAgency::orderBy('name')->get(),'companies'=>SupplierCompany::orderBy('name')->get()]);
    }
    public function review(Request $request,SupplierAgency $agency,AgencyCollectionEmailWorkflow $workflow)
    {
        $this->authorize('system.admin');$data=$request->validate(['collections'=>['required','array','min:1','max:500'],'collections.*'=>['required','integer','distinct']]);[$filters,$query,$range]=$this->selection($request);
        $payments=$query->whereIn('insurance_collections.id',$data['collections'])->whereHas('title.policy',fn($q)=>$q->where('supplier_agency_id',$agency->id))->get();
        if($payments->count()!==count($data['collections']))throw ValidationException::withMessages(['collections'=>'La selezione include incassi di un’altra agenzia o fuori dal periodo.']);
        if ($workflow->existingDeliveries($payments)->isNotEmpty()) throw ValidationException::withMessages(['collections'=>'La selezione contiene incassi già comunicati o con un invio da controllare. Aggiorna la selezione.']);
        if ($filters['scope'] !== 'period') $range = $payments->min('received_on')->format('d/m/Y').' – '.$payments->max('received_on')->format('d/m/Y');
        return to_route('agency-communications.show',$workflow->prepare($request->user(),$agency,$payments,$filters,$range));
    }
}
