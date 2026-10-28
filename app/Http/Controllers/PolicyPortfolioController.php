<?php

namespace App\Http\Controllers;

use App\Domain\Core\Queries\{ClientQuery, PolicyPortfolioQuery};
use App\Models\Client;
use App\Support\TableListing;
use Illuminate\Http\Request;

class PolicyPortfolioController extends Controller
{
    public function index(Request $request, PolicyPortfolioQuery $query)
    {
        $this->authorize('viewAny', Client::class);
        $filters = $query->filters($request);
        $policies = $query->forUser($request->user(), $filters)->with(['client', 'supplierAgency', 'vehicle', 'asset'])
            ->orderBy('expires_on')->orderBy('id')->paginate(TableListing::perPage(25))->withQueryString();
        return view('policies.index', compact('policies', 'filters') + $query->options($request->user()));
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

    public function clientContext(Client $client)
    {
        $this->authorize('updateRegistry', $client);
        abort_if($client->merged_into_id, 404);
        return response()->json([
            'vehicles' => $client->vehicles()->orderBy('plate')->get(['id', 'plate', 'make', 'model']),
            'assets' => $client->assets()->orderBy('name')->get(['id', 'name', 'category']),
            'policies' => $client->policies()->orderBy('number')->get(['id', 'number', 'company', 'category', 'insurance_branch_id', 'insurance_subbranch_id']),
        ])->header('Cache-Control', 'no-store, private');
    }
}
