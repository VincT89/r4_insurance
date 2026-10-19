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
            'responsible' => ['nullable', 'integer'], 'payment_frequency' => ['nullable', \Illuminate\Validation\Rule::in(\App\Models\InsurancePolicy::PAYMENT_FREQUENCIES)], 'search' => ['nullable', 'string', 'max:255']]);
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
            ->selectRaw("r.id as source_id, c.id as client_id, r.due_on as due_date, 'request' as kind, r.label as title, COALESCE(p.category, b.name) as category, p.company, c.name as client_name, c.commercial_user_id as responsible_id, u.name as responsible_name, NULL as review_status, p.payment_frequency");
        $titles=DB::table('insurance_titles as t')->join('insurance_policies as p','p.id','=','t.insurance_policy_id')->join('clients as c','c.id','=','p.client_id')->leftJoin('users as u','u.id','=','c.commercial_user_id')
            ->whereIn('c.id',clone $visible)->whereNull('t.cancelled_at')->whereColumn('t.collected_cents','<','t.amount_cents')
            ->selectRaw("t.id as source_id, c.id as client_id, t.due_on as due_date, 'title' as kind, p.number as title, p.category, p.company, c.name as client_name, c.commercial_user_id as responsible_id, u.name as responsible_name, NULL as review_status, t.payment_frequency");
        $all = DB::query()->fromSub($policies->unionAll($documents)->unionAll($requests)->unionAll($titles), 'deadlines');
        $categories = (clone $all)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        $companies = (clone $all)->whereNotNull('company')->distinct()->orderBy('company')->pluck('company');
        $responsibles = User::whereIn('id', Client::current()->visibleTo($request->user())->select('commercial_user_id')->whereNotNull('commercial_user_id'))->orderBy('name')->get(['id', 'name']);
        foreach (['kind', 'category', 'company', 'payment_frequency'] as $field) if (filled($filters[$field] ?? null)) $all->where($field, $filters[$field]);
        if (filled($filters['responsible'] ?? null)) $all->where('responsible_id', $filters['responsible']);
        if (filled($filters['search'] ?? null)) $all->where(fn ($q) => $q->where('title', 'like', '%'.$filters['search'].'%')->orWhere('client_name', 'like', '%'.$filters['search'].'%'));
        $period = $filters['period'] ?? '30';
        if ($period === 'overdue') $all->where(fn ($q) => $q->whereDate('due_date', '<', today()->toDateString())->orWhere(fn ($q) => $q->where('kind','policy')->where('review_status','expired')));
        elseif (ctype_digit($period)) $all->whereDate('due_date', '>=', today()->toDateString())->whereDate('due_date', '<=', today()->addDays((int)$period)->toDateString());
        elseif ($period === 'custom') {
            if ($filters['from'] ?? null) $all->whereDate('due_date', '>=', $filters['from']);
            if ($filters['to'] ?? null) $all->whereDate('due_date', '<=', $filters['to']);
        }
        $deadlines = $all->orderBy('due_date')->orderBy('client_name')->orderBy('kind')->orderBy('source_id')->paginate(\App\Support\TableListing::perPage(25))->withQueryString();
        return view('deadlines.index', compact('deadlines', 'categories', 'companies', 'responsibles', 'period'));
    }
}
