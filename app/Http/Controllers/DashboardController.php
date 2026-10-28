<?php
namespace App\Http\Controllers;

use App\Domain\Core\Queries\PolicyPortfolioQuery;
use App\Models\{Client, ClientDocumentRequest};
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PolicyPortfolioQuery $query): View
    {
        if (!$request->user()->can('viewAny', Client::class)) return view('dashboard', ['overview' => null]);
        $period = $request->validate(['period' => ['nullable', 'in:7,15,30,60']])['period'] ?? '15';
        $all = $query->forUser($request->user());
        $upcoming = $query->forUser($request->user(), ['period' => $period])->whereIn('status', ['active', 'renewed']);
        $documentRequests = ClientDocumentRequest::whereIn('client_id', Client::current()->visibleTo($request->user())->select('id'))->whereDoesntHave('documents')->whereDoesntHave('linkedDocuments');
        $totals = (clone $all)->selectRaw("COUNT(*) AS policies,
            COALESCE(SUM(CASE WHEN status IN ('active','renewed') AND
                (CASE WHEN status='renewed' THEN COALESCE(coverage_origin_on,starts_on) ELSE starts_on END) < ?
                AND expires_on >= ? THEN 1 ELSE 0 END),0) AS active,
            COALESCE(SUM(CASE WHEN payment_status='unpaid' THEN 1 ELSE 0 END),0) AS unpaid,
            COALESCE(SUM(CASE WHEN status='review' THEN 1 ELSE 0 END),0) AS review",
            [today()->addDay()->toDateString(), today()->toDateString()])->first();
        $duePolicies = $upcoming->with(['client', 'supplierAgency'])->orderBy('expires_on')->orderBy('id')->paginate(\App\Support\TableListing::perPage(8))->withQueryString();
        /** One portfolio scan supplies the counters; pagination already knows the number of upcoming contracts. */
        $overview = [
            'clients' => Client::current()->visibleTo($request->user())->count(),
            'policies' => (int) $totals->policies,
            'active' => (int) $totals->active,
            'unpaid' => (int) $totals->unpaid,
            'review' => (int) $totals->review,
            'due' => $duePolicies->total(),
        ];
        $missingDocuments = $documentRequests->with('client')->orderByRaw('due_on IS NULL')->orderBy('due_on')->orderBy('id')->limit(5)->get();
        /** L'apertura è la data di inserimento, distinta dalla decorrenza della copertura. */
        $todayPolicies = $query->forUser($request->user())->whereDoesntHave('importEntry')->where('created_at', '>=', today())->where('created_at', '<', today()->addDay())
            ->whereIn('status', ['active', 'renewed'])->where('expires_on', '>=', today()->toDateString())
            ->with(['client', 'supplierAgency'])->orderByDesc('created_at')->orderByDesc('id')->get();
        $dueTitles=\App\Models\InsuranceTitle::visibleTo($request->user())->whereNull('cancelled_at')->whereColumn('collected_cents','<','amount_cents')->where('due_on','<',today()->addDays((int)$period+1)->toDateString())->with('policy.client')->orderBy('due_on')->orderBy('id')->paginate(\App\Support\TableListing::perPage(8),['*'],'titles_page')->withQueryString();
        $claimActivities=\App\Models\InsuranceClaimActivity::pending()->visibleTo($request->user())->where('due_on','<',today()->addDays(4)->toDateString())->with(['claim.client','claim.assignee'])->orderBy('due_on')->orderBy('id')->paginate(\App\Support\TableListing::perPage(8),['*'],'claims_page')->withQueryString();
        $paymentSummary = app(\App\Services\InsurancePaymentSummary::class)->forQuery(\App\Models\InsuranceTitle::visibleTo($request->user()));
        return view('dashboard', compact('overview', 'period', 'duePolicies', 'missingDocuments', 'todayPolicies','dueTitles','claimActivities','paymentSummary'));
    }
}
