<?php

namespace App\Http\Controllers;

use App\Models\{Client, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeadlineController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Client::class);
        $filters = $request->validate(['period' => ['nullable', 'in:all,overdue,7,15,30,45,60,custom'], 'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'kind' => ['nullable', 'in:policy,document,request,title'], 'category' => ['nullable', 'string', 'max:100'], 'company' => ['nullable', 'string', 'max:150'],
            'agency' => ['nullable','integer','exists:supplier_agencies,id'], 'responsible' => ['nullable', 'integer'], 'payment_frequency' => ['nullable', \Illuminate\Validation\Rule::in(\App\Models\InsurancePolicy::PAYMENT_FREQUENCIES)], 'search' => ['nullable', 'string', 'max:255']]);
        $visible = Client::current()->visibleTo($request->user())->select('clients.id');
        $policies = DB::table('insurance_policies as p')->join('clients as c', 'c.id', '=', 'p.client_id')->leftJoin('users as u', 'u.id', '=', 'c.commercial_user_id')
            ->whereIn('c.id', clone $visible)->whereNotIn('p.status', \App\Models\InsurancePolicy::CLOSED_STATUSES)
            ->selectRaw("p.id as source_id, c.id as client_id, p.expires_on as due_date, 'policy' as kind, p.number as title, p.category, p.company, c.name as client_name, c.commercial_user_id as responsible_id, u.name as responsible_name, p.status as review_status, p.payment_frequency");
        $documents = DB::table('attachments as a')->join('clients as c', 'c.id', '=', 'a.attachable_id')->leftJoin('users as u', 'u.id', '=', 'c.commercial_user_id')
            ->leftJoin('insurance_policies as p', fn ($join) => $join->on('p.id', '=', 'a.insurance_policy_id')->on('p.client_id', '=', 'c.id'))
            ->leftJoin('attachments as newer', 'newer.previous_version_id', '=', 'a.id')
            ->whereIn('c.id', clone $visible)->where('a.attachable_type', (new Client)->getMorphClass())->whereNotNull('a.document_category')->whereNotNull('a.expires_on')->whereNull('newer.id')
            ->selectRaw("a.id as source_id, c.id as client_id, a.expires_on as due_date, 'document' as kind, a.original_name as title, p.category, p.company, c.name as client_name, c.commercial_user_id as responsible_id, u.name as responsible_name, a.review_status, p.payment_frequency");
        $requests = DB::table('client_document_requests as r')->join('clients as c', 'c.id', '=', 'r.client_id')->leftJoin('users as u', 'u.id', '=', 'c.commercial_user_id')
            ->leftJoin('insurance_policies as p', fn ($join) => $join->on('p.id', '=', 'r.insurance_policy_id')->on('p.client_id', '=', 'c.id'))
            ->leftJoin('insurance_branches as b', 'b.id', '=', 'r.insurance_branch_id')->leftJoin('attachments as received', 'received.document_request_id', '=', 'r.id')
            ->whereIn('c.id', clone $visible)->whereNotNull('r.due_on')->whereNull('received.id')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('client_document_request_files as linked')->whereColumn('linked.client_document_request_id', 'r.id'))
            ->selectRaw("r.id as source_id, c.id as client_id, r.due_on as due_date, 'request' as kind, r.label as title, COALESCE(p.category, b.name) as category, p.company, c.name as client_name, c.commercial_user_id as responsible_id, u.name as responsible_name, NULL as review_status, p.payment_frequency");
        $titles=DB::table('insurance_titles as t')->join('insurance_policies as p','p.id','=','t.insurance_policy_id')->join('clients as c','c.id','=','p.client_id')->leftJoin('users as u','u.id','=','c.commercial_user_id')
            ->whereIn('c.id',clone $visible)->whereNull('t.cancelled_at')->whereColumn('t.collected_cents','<','t.amount_cents')
            ->selectRaw("t.id as source_id, c.id as client_id, t.due_on as due_date, 'title' as kind, p.number as title, p.category, p.company, c.name as client_name, c.commercial_user_id as responsible_id, u.name as responsible_name, NULL as review_status, t.payment_frequency");
        foreach ([$policies,$documents,$requests,$titles] as $source) {
            $source->leftJoin('supplier_agencies as agency','agency.id','=','p.supplier_agency_id')->addSelect('p.supplier_agency_id as agency_id','agency.name as agency_name');
        }
        foreach ([$policies,$documents,$requests] as $source) $source->selectRaw('NULL as balance_cents');
        $titles->selectRaw('t.amount_cents - t.collected_cents as balance_cents');
        $sources = ['policy'=>$policies, 'document'=>$documents, 'request'=>$requests, 'title'=>$titles];
        [$categories, $companies] = $this->options($visible);
        $responsibles = User::whereIn('id', Client::current()->visibleTo($request->user())->select('commercial_user_id')->whereNotNull('commercial_user_id'))->orderBy('name')->get(['id', 'name']);
        $period = $filters['period'] ?? '15';
        $combined = null;
        /** Filter each source before the union, so date indexes work and unselected document types are not scanned. */
        foreach ($sources as $kind=>$source) {
            if (filled($filters['kind'] ?? null) && $filters['kind'] !== $kind) continue;
            $date = ['policy'=>'p.expires_on', 'document'=>'a.expires_on', 'request'=>'r.due_on', 'title'=>'t.due_on'][$kind];
            $title = ['policy'=>'p.number', 'document'=>'a.original_name', 'request'=>'r.label', 'title'=>'p.number'][$kind];
            foreach (['category','company','payment_frequency'] as $field) {
                $column = $field === 'category' && $kind === 'request' ? DB::raw('COALESCE(p.category, b.name)') : (($field === 'payment_frequency' && $kind === 'title' ? 't.' : 'p.').$field);
                if (filled($filters[$field] ?? null)) $source->where($column, $filters[$field]);
            }
            if (filled($filters['responsible'] ?? null)) $source->where('c.commercial_user_id', $filters['responsible']);
            if (filled($filters['agency'] ?? null)) $source->where('p.supplier_agency_id', $filters['agency']);
            if (filled($filters['search'] ?? null)) $source->where(fn ($q) => $q->where($title, 'like', '%'.$filters['search'].'%')->orWhere('c.name', 'like', '%'.$filters['search'].'%'));
            if ($period === 'overdue') $source->where(function ($q) use ($date, $kind) {
                $q->where($date, '<', today()->toDateString());
                if ($kind === 'policy') $q->orWhere('p.status', 'expired');
            });
            elseif (ctype_digit($period)) $source->where($date, '>=', today()->toDateString())->where($date, '<', today()->addDays((int)$period+1)->toDateString());
            elseif ($period === 'custom') {
                if ($filters['from'] ?? null) $source->where($date, '>=', $filters['from']);
                if ($filters['to'] ?? null) $source->where($date, '<', \Illuminate\Support\Carbon::parse($filters['to'])->addDay()->toDateString());
            }
            $combined = $combined ? $combined->unionAll($source) : $source;
        }
        $all = DB::query()->fromSub($combined, 'deadlines');
        $deadlines = $all->orderBy('due_date')->orderBy('client_name')->orderBy('kind')->orderBy('source_id')->paginate(\App\Support\TableListing::perPage(25))->withQueryString();
        $agencies = \App\Models\SupplierAgency::orderBy('name')->get(['id','name']);
        $lastCheck = $request->user()->can('system.admin') ? \App\Models\SystemHeartbeat::where('name','insurance-deadline-reminders')->value('last_seen_at') : null;
        return view('deadlines.index', compact('deadlines', 'categories', 'companies', 'responsibles', 'period', 'agencies', 'lastCheck'));
    }

    private function options($visible): array
    {
        /** A policy supplies each option once, even when many receipts or document versions refer to it. */
        $outstandingRequests = fn () => DB::table('client_document_requests as r')->whereNotNull('r.due_on')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('attachments as received')->whereColumn('received.document_request_id', 'r.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('client_document_request_files as linked')->whereColumn('linked.client_document_request_id', 'r.id'));
        $eligible = DB::table('insurance_policies as p')->whereIn('p.client_id', clone $visible)->where(function ($q) use ($outstandingRequests) {
            $q->whereNotIn('p.status', \App\Models\InsurancePolicy::CLOSED_STATUSES)
                ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('insurance_titles as t')->whereColumn('t.insurance_policy_id', 'p.id')
                    ->whereNull('t.cancelled_at')->whereColumn('t.collected_cents', '<', 't.amount_cents'))
                ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('attachments as a')->whereColumn('a.insurance_policy_id', 'p.id')->whereColumn('a.attachable_id', 'p.client_id')
                    ->where('a.attachable_type', (new Client)->getMorphClass())->whereNotNull('a.document_category')->whereNotNull('a.expires_on')
                    ->whereNotExists(fn ($newer) => $newer->selectRaw('1')->from('attachments as newer')->whereColumn('newer.previous_version_id', 'a.id')))
                ->orWhereExists($outstandingRequests()->selectRaw('1')->whereColumn('r.insurance_policy_id', 'p.id')->whereColumn('r.client_id', 'p.client_id'));
        });
        /** Materialize the eligible category/company pairs once, then deduplicate each dropdown in the database's collation. */
        $fields = DB::query()->selectRaw("'category' as kind")->unionAll(DB::query()->selectRaw("'company' as kind"));
        $names = DB::query()->fromSub($eligible->select('p.category', 'p.company')->distinct(), 'eligible')
            ->crossJoinSub($fields, 'fields')->selectRaw("fields.kind, CASE WHEN fields.kind='category' THEN eligible.category ELSE eligible.company END as value")->distinct();
        /** Requests without a matching policy still contribute their branch, with the same client ownership checks as the table. */
        $requestBranches = $outstandingRequests()->whereIn('r.client_id', clone $visible)
            ->leftJoin('insurance_policies as p', fn ($join) => $join->on('p.id', '=', 'r.insurance_policy_id')->on('p.client_id', '=', 'r.client_id'))
            ->join('insurance_branches as b', 'b.id', '=', 'r.insurance_branch_id')->whereNull('p.id')->selectRaw("'category' as kind, b.name as value")->distinct();
        $choices = DB::query()->fromSub($names->union($requestBranches), 'choices')->whereNotNull('value')->orderBy('value')->get();
        return [
            $choices->where('kind', 'category')->pluck('value'),
            $choices->where('kind', 'company')->pluck('value'),
        ];
    }
}
