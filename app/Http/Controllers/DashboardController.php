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
        $documentRequests = ClientDocumentRequest::whereIn('client_id', Client::current()->visibleTo($request->user())->select('id'))->whereDoesntHave('documents');
        $overview = [
            'clients' => Client::current()->visibleTo($request->user())->count(),
            'policies' => (clone $all)->count(),
            'active' => $query->forUser($request->user(), ['status' => 'active'])->count(),
            'unpaid' => (clone $all)->where('payment_status', 'unpaid')->count(),
            'review' => (clone $all)->where('status', 'review')->count(),
            'due' => (clone $upcoming)->count(),
        ];
        $duePolicies = $upcoming->with(['client', 'supplierAgency'])->orderBy('expires_on')->orderBy('id')->paginate(\App\Support\TableListing::perPage(8))->withQueryString();
        $missingDocuments = $documentRequests->with('client')->orderByRaw('due_on IS NULL')->orderBy('due_on')->orderBy('id')->limit(5)->get();
        /** L'apertura è la data di inserimento, distinta dalla decorrenza della copertura. */
        $todayPolicies = $query->forUser($request->user())->whereDoesntHave('importEntry')->where('created_at', '>=', today())->where('created_at', '<', today()->addDay())
            ->whereIn('status', ['active', 'renewed'])->whereDate('expires_on', '>=', today()->toDateString())
            ->with(['client', 'supplierAgency'])->orderByDesc('created_at')->orderByDesc('id')->get();
        $dueTitles=\App\Models\InsuranceTitle::visibleTo($request->user())->whereNull('cancelled_at')->whereColumn('collected_cents','<','amount_cents')->whereDate('due_on','<=',today()->addDays((int)$period))->with('policy.client')->orderBy('due_on')->orderBy('id')->paginate(\App\Support\TableListing::perPage(8),['*'],'titles_page')->withQueryString();
        $claimActivities=\App\Models\InsuranceClaimActivity::pending()->visibleTo($request->user())->whereDate('due_on','<=',today()->addDays(3))->with(['claim.client','claim.assignee'])->orderBy('due_on')->orderBy('id')->paginate(\App\Support\TableListing::perPage(8),['*'],'claims_page')->withQueryString();
        return view('dashboard', compact('overview', 'period', 'duePolicies', 'missingDocuments', 'todayPolicies','dueTitles','claimActivities'));
    }
}
