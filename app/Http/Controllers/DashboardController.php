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
        $filters = $request->validate(['period' => ['nullable', 'in:7,15,30,60'], 'titles_from'=>['nullable','date_format:Y-m-d'], 'titles_to'=>['nullable','date_format:Y-m-d',...($request->filled('titles_from')?['after_or_equal:titles_from']:[])]]);
        $period = $filters['period'] ?? '15';
        $titlesDates = \Illuminate\Support\Arr::only($filters,['titles_from','titles_to']);
        $all = $query->forUser($request->user());
        $upcoming = $query->forUser($request->user(), ['period' => $period])->withoutContractSuccessor()->whereIn('status', ['active', 'renewed']);
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
            ->with(['client', 'supplierAgency'])->orderByDesc('created_at')->orderByDesc('id')->paginate(\App\Support\TableListing::perPage(8), ['*'], 'today_policies_page')->withQueryString()->fragment('today-policies');
        $titleQuery=\App\Models\InsuranceTitle::visibleTo($request->user())->where('direction','receipt')->outstanding();
        if(array_filter($titlesDates)) {
            if($titlesDates['titles_from']??null)$titleQuery->where('due_on','>=',$titlesDates['titles_from']);
            if($titlesDates['titles_to']??null)$titleQuery->where('due_on','<',\Illuminate\Support\Carbon::parse($titlesDates['titles_to'])->addDay()->toDateString());
        } else $titleQuery->where('due_on','<',today()->addDays((int)$period+1)->toDateString());
        $dueTitles=$titleQuery->with('policy.client')->orderBy('due_on')->orderBy('id')->paginate(\App\Support\TableListing::perPage(8),['*'],'titles_page')->withQueryString();
        /** Use the effective payment date, including entries recorded on a later day. */
        $todayCollections=\App\Models\InsuranceCollection::whereIn('insurance_title_id',\App\Models\InsuranceTitle::visibleTo($request->user())->select('id'))
            ->whereNull('voided_at')->where('received_on','>=',today()->toDateString())->where('received_on','<',today()->addDay()->toDateString())
            ->selectRaw('COUNT(*) as count, '.\App\Models\InsuranceCollection::totalsSql())->first();
        $claimActivities=\App\Models\InsuranceClaimActivity::pending()->visibleTo($request->user())->where('due_on','<',today()->addDays(4)->toDateString())->with(['claim.client','claim.assignee'])->orderBy('due_on')->orderBy('id')->paginate(\App\Support\TableListing::perPage(8),['*'],'claims_page')->withQueryString();
        $paymentSummary = app(\App\Services\InsurancePaymentSummary::class)->forQuery(\App\Models\InsuranceTitle::visibleTo($request->user()));
        $openPractices = $query->forUser($request->user(), ['work'=>'open'])->with(['client', 'contracts'])
            ->orderByRaw("CASE WHEN status='review' THEN 0 ELSE 1 END")->orderByDesc('updated_at')->orderByDesc('id')->limit(3)->get();
        $workTasks = app(\App\Services\PolicyWorkOverview::class)->forPolicies($openPractices, $request->user());
        $pendingSignatures = \App\Models\InsuranceFormDocument::where('signature_required', true)
            ->whereHas('package', fn ($q) => $q->whereIn('client_id', Client::current()->visibleTo($request->user())->select('id')))
            ->whereDoesntHave('latestSubmission', fn ($q) => $q->where('status','validated'))
            ->with(['package.client:id,name', 'package.policy:id,number', 'latestSubmission:id,insurance_form_document_id,version,status'])
            ->orderByDesc('insurance_form_package_id')->orderBy('id')->limit(5)->get(['id','insurance_form_package_id','title']);
        $pendingCommunications = $request->user()->can('communications.manage')
            ? app(\App\Services\CollectionCommunicationStatus::class)->pending(app(\App\Services\CollectionCommunicationStatus::class)->payments($request->user()))->count() : 0;
        return view('dashboard', compact('overview', 'period', 'duePolicies', 'missingDocuments', 'todayPolicies','dueTitles','claimActivities','paymentSummary','titlesDates','todayCollections','openPractices','workTasks','pendingSignatures','pendingCommunications'));
    }
}
