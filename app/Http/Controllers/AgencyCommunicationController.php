<?php

namespace App\Http\Controllers;

use App\Domain\Core\Queries\PolicyPortfolioQuery;
use App\Models\SupplierAgency;
use App\Models\AgencyCommunication;
use App\Services\{AgencyEmailWorkflow, AgencyMailConfiguration};
use App\Support\TableListing;
use App\Support\CommunicationSelectionScope;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AgencyCommunicationController extends Controller
{
    private function selection(Request $request, PolicyPortfolioQuery $query)
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:day,15,30,month,custom'], 'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'month' => ['nullable', 'date_format:Y-m'], 'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'agency' => ['nullable', 'integer', 'exists:supplier_agencies,id'], 'company' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(PolicyPortfolioQuery::STATES))], 'search' => ['nullable', 'string', 'max:255'],
        ]);
        $filters['period'] ??= 'day'; $filters['date'] ??= today()->toDateString(); $filters['month'] ??= today()->format('Y-m');
        $start = \Illuminate\Support\Carbon::parse($filters['date']); $end = $start->copy();
        if (in_array($filters['period'], ['15','30'], true)) { $end=today(); $start=today()->subDays((int)$filters['period']-1); }
        elseif ($filters['period']==='month') { $start=\Illuminate\Support\Carbon::createFromFormat('!Y-m',$filters['month']); $end=$start->copy()->endOfMonth(); }
        elseif ($filters['period']==='custom') { $start=\Illuminate\Support\Carbon::parse($filters['from']); $end=\Illuminate\Support\Carbon::parse($filters['to']); }
        if ($end->diffInDays($start, true)>366) throw ValidationException::withMessages(['from'=>'Scegli un intervallo massimo di un anno.']);
        $range = $start->format('d/m/Y').($start->isSameDay($end) ? '' : ' – '.$end->format('d/m/Y'));
        $baseFilters = array_intersect_key($filters,array_flip(['agency','company','status','search']));
        return [$filters, $range, $query->forUser($request->user(),$baseFilters)->whereDoesntHave('importEntry')->where('created_at','>=',$start->startOfDay())->where('created_at','<',$end->addDay()->startOfDay())];
    }

    public function index(Request $request, PolicyPortfolioQuery $query, AgencyEmailWorkflow $workflow, AgencyMailConfiguration $configuration)
    {
        $this->authorize('system.admin');
        [$filters, $range, $policies] = $this->selection($request, $query);
        $policies = $policies->with(['client','supplierAgency'])->orderBy('supplier_agency_id')->orderByDesc('ad_horas')->orderBy('id')->paginate(TableListing::perPage(25))->withQueryString();
        $options = $query->options($request->user());
        $deliveries = $workflow->existingDeliveries($policies->getCollection());
        $mailReady = $configuration->ready();
        $reviewAgencies = SupplierAgency::whereIn('id', $options['agencies']->pluck('id'))->orderBy('name')->get(['id','name','email','status']);
        $selectionScope = CommunicationSelectionScope::forFilters($filters, $range);
        return view('agency-communications.index', compact('filters','range','policies','options','deliveries','mailReady','selectionScope','reviewAgencies'));
    }

    public function review(Request $request, SupplierAgency $agency, PolicyPortfolioQuery $query, AgencyEmailWorkflow $workflow)
    {
        $this->authorize('system.admin');
        $data = $request->validate(['policies'=>['required','array','min:1','max:500'], 'policies.*'=>['required','integer','distinct']]);
        [$filters, $range, $policies] = $this->selection($request,$query);
        $selected = $policies->where('supplier_agency_id',$agency->id)->whereIn('id',$data['policies'])->with('client')->orderBy('id')->get();
        if ($selected->count() !== count($data['policies'])) throw ValidationException::withMessages(['policies'=>'La selezione contiene polizze fuori dal periodo o dall’agenzia scelta.']);
        return to_route('agency-communications.show',$workflow->prepare($request->user(),$agency,$selected,$filters,$range));
    }

    public function show(AgencyCommunication $communication, AgencyMailConfiguration $configuration)
    {
        $this->authorize('system.admin');
        return response()->view('agency-communications.show',['communication'=>$communication, 'payload'=>$communication->payload, 'mailReady'=>$configuration->ready()])->header('Cache-Control','no-store, private');
    }

    public function send(Request $request, AgencyCommunication $communication, AgencyEmailWorkflow $workflow)
    {
        $this->authorize('system.admin');
        $request->validate(['reviewed'=>['accepted']]);
        $message = $workflow->send($communication,$request->user());
        return to_route('agency-communications.show',$message)->with($message->status === 'sent' ? 'success' : 'warning',
            $message->status === 'sent' ? 'Email cumulativa accettata dal server di posta.' : 'Esito non confermato: verifica con il servizio di posta prima di ripetere l’invio.');
    }

    public function resolve(Request $request, AgencyCommunication $communication, AgencyEmailWorkflow $workflow)
    {
        $this->authorize('system.admin');
        $data = $request->validate(['checked'=>['accepted'],'outcome'=>['required','in:sent,not_sent'],'note'=>['required','string','min:5','max:500']]);
        $workflow->resolve($communication,$data['outcome']==='sent',$data['note']);
        return to_route('agency-communications.show',$communication)->with('success','Esito registrato nel registro attività.');
    }

    public function history(Request $request)
    {
        $this->authorize('system.admin');
        $filters = $request->validate(['month'=>['nullable','date_format:Y-m'],'agency'=>['nullable','integer','exists:supplier_agencies,id'],'state'=>['nullable','in:'.implode(',',array_keys(AgencyCommunication::STATUSES))]]);
        $filters += ['month'=>today()->format('Y-m')];
        $start = \Illuminate\Support\Carbon::createFromFormat('!Y-m',$filters['month']);
        $messages = AgencyCommunication::with('user')->where('created_at','>=',$start)->where('created_at','<',$start->copy()->addMonth())
            ->when($filters['agency'] ?? null,fn ($q,$agency) => $q->where('supplier_agency_id',$agency))
            ->when($filters['state'] ?? null,fn ($q,$state) => $q->where('status',$state))->latest()->orderByDesc('id')->paginate(TableListing::perPage(25))->withQueryString();
        return view('agency-communications.history',['messages'=>$messages,'filters'=>$filters,'agencies'=>SupplierAgency::orderBy('name')->get(['id','name'])]);
    }

    public function draft(Request $request, SupplierAgency $agency, PolicyPortfolioQuery $query)
    {
        $this->authorize('system.admin');
        $data = $request->validate(['policies'=>['required','array','min:1','max:500'], 'policies.*'=>['required','integer','distinct'], 'reviewed'=>['accepted']]);
        [$filters, $range, $policies] = $this->selection($request, $query);
        $policies = $policies->where('supplier_agency_id', $agency->id)->whereIn('id', $data['policies'])->with('client')->orderByDesc('ad_horas')->orderBy('number')->get();
        if ($policies->count() !== count($data['policies'])) throw ValidationException::withMessages(['policies'=>'La selezione contiene polizze di un’altra data o agenzia. Riapri il riepilogo.']);
        if (!filter_var($agency->email, FILTER_VALIDATE_EMAIL)) throw ValidationException::withMessages(['agency'=>'Completa l’indirizzo email dell’agenzia prima di preparare il messaggio.']);
        if ($policies->contains(fn ($p) => !in_array($p->status, ['active','renewed'], true) || $p->expires_on->lt(today()))) throw ValidationException::withMessages(['policies'=>'Seleziona soltanto polizze attive o rinnovate, con scadenza non trascorsa.']);
        $lines = ['Buonasera,', '', 'riepilogo delle polizze inserite nel periodo '.$range.':', ''];
        foreach ($policies as $p) {
            $lines[] = ($p->ad_horas ? 'AD HORAS - ' : '').$p->number.' | '.$p->client->name.' | '.$p->company;
            $lines[] = 'Ramo: '.$p->category.($p->subcategory ? ' / '.$p->subcategory : '').'; decorrenza: '.$p->starts_on->format('d/m/Y').'; scadenza: '.$p->expires_on->format('d/m/Y').'.';
            $lines[] = 'Frazionamento: '.($p->payment_frequency ?: 'Non indicato').'.';
            $lines[] = '';
        }
        $lines[] = config('branding.wordmark');
        $headers = new \Symfony\Component\Mime\Header\Headers;
        $headers->addMailboxListHeader('To', [new \Symfony\Component\Mime\Address($agency->email, $agency->name)]);
        $headers->addTextHeader('Subject', ($policies->contains('ad_horas', true) ? 'Ad horas - ' : '').'Riepilogo polizze '.$range.' - '.config('branding.wordmark'));
        $headers->addTextHeader('X-Unsent', '1');
        /** A draft has no invented sender: the mail application supplies the user's sending account. */
        $message = $headers->toString()."MIME-Version: 1.0\r\n".(new \Symfony\Component\Mime\Part\TextPart(implode("\n", $lines), 'utf-8'))->toString();
        app(\App\Services\AuditLogService::class)->log('agency_email_draft', $agency, description:'Preparata bozza cumulativa per '.$agency->name.': '.$policies->count().' polizze, periodo '.$range.'. Nessun invio automatico.');
        return response($message)->header('Content-Type', 'message/rfc822')->header('Content-Disposition', 'attachment; filename="riepilogo-agenzia-'.$agency->id.'-'.today()->toDateString().'.eml"')->header('Cache-Control','no-store, private');
    }
}
