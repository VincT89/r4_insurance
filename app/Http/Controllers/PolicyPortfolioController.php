<?php

namespace App\Http\Controllers;

use App\Domain\Core\Queries\{ClientQuery, PolicyPortfolioQuery};
use App\Models\{Client, InsuranceTitle};
use App\Support\TableListing;
use Illuminate\Http\Request;

class PolicyPortfolioController extends Controller
{
    public function index(Request $request, PolicyPortfolioQuery $query)
    {
        $this->authorize('viewAny', Client::class);
        $filters = $query->filters($request);
        $policies = $query->forUser($request->user(), $filters)->with(['client', 'supplierAgency', 'vehicle', 'asset', 'contracts'])
            ->orderBy('expires_on')->orderBy('id')->paginate(TableListing::perPage(25))->withQueryString();
        $page = $policies->getCollection();
        if ($page->isNotEmpty()) {
            /** Calcola i saldi dopo la paginazione, raggruppando i titoli delle sole polizze visibili. */
            $balance = InsuranceTitle::balanceSql('insurance_titles');
            $openTitle = "cancelled_at IS NULL AND $balance > 0";
            $totals = InsuranceTitle::query()
                ->whereIn('insurance_policy_id', $page->modelKeys())
                ->select('insurance_policy_id')
                ->selectRaw("SUM(CASE WHEN $openTitle AND direction = ? THEN $balance END) AS receipt_due_cents", ['receipt'])
                ->selectRaw("SUM(CASE WHEN $openTitle AND direction = ? THEN $balance END) AS refund_due_cents", ['refund'])
                ->groupBy('insurance_policy_id')
                ->toBase()->get()->keyBy('insurance_policy_id');

            foreach ($page as $policy) {
                $total = $totals->get($policy->id);
                $policy->titles_exists = $total !== null;
                $policy->receipt_due_cents = $total?->receipt_due_cents;
                $policy->refund_due_cents = $total?->refund_due_cents;
            }
        }
        $workTasks = ($filters['work'] ?? null) === 'open' ? app(\App\Services\PolicyWorkOverview::class)->forPolicies($page, $request->user()) : [];
        return view('policies.index', compact('policies', 'filters', 'workTasks') + $query->options($request->user()));
    }

    public function create(Request $request, ClientQuery $query)
    {
        $this->authorize('create', Client::class);
        $data = $request->validate(['client_id' => ['nullable', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:255']]);
        $client = null;
        $clientId = $data['client_id'] ?? $request->old('client_id');
        if ($clientId) {
            $client = Client::current()->visibleTo($request->user())->findOrFail($clientId);
            $this->authorize('updateRegistry', $client);
        }
        return app(InsurancePolicyController::class)->form($client ?? new Client, new \App\Models\InsurancePolicy(['status' => 'active', 'tacit_renewal' => true]), true);
    }

    public function store(\App\Http\Requests\SaveInsurancePolicyRequest $request)
    {
        return app(InsurancePolicyController::class)->store($request, $request->policyClient());
    }

    public function contractSchedule(Request $request)
    {
        $this->authorize('create', Client::class);
        $this->authorize('ledger.manage');
        $schedule = app(\App\Services\PolicyContractSchedule::class);
        $values = $request->validate($schedule->rules());
        $rows = app(\App\Services\InsuranceLedger::class)->plan(new \App\Models\InsurancePolicy($values), $schedule->firstInstallment($values));
        return response()->json(['rows' => $rows, 'fingerprint' => $schedule->fingerprint($rows)])->header('Cache-Control', 'no-store, private');
    }

    public function clientContext(Client $client)
    {
        $this->authorize('updateRegistry', $client);
        abort_if($client->merged_into_id, 404);
        return response()->json([
            'client' => app(\App\Services\PolicyContractPreparation::class)->clientContext($client),
            'vehicles' => $client->vehicles()->orderBy('plate')->get(['id', 'plate', 'make', 'model', 'merit_class_cu', 'merit_class_internal', 'merit_class_company_id']),
            'assets' => $client->assets()->orderBy('name')->get(['id', 'name', 'category']),
            'policies' => $client->policies()->orderBy('number')->get(['id', 'number', 'company', 'supplier_company_id', 'category', 'insurance_branch_id', 'insurance_subbranch_id', 'vehicle_id', 'merit_class_cu', 'merit_class_internal']),
        ])->header('Cache-Control', 'no-store, private');
    }
}
