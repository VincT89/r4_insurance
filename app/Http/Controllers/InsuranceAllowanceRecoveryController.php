<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInsuranceAllowanceRecoveryRequest;
use App\Models\{InsuranceAllowance, InsuranceAllowanceRecovery};
use App\Services\InsuranceAllowances;
use App\Support\Money;
use Illuminate\Http\Request;

class InsuranceAllowanceRecoveryController extends Controller
{
    public function show(InsuranceAllowance $allowance)
    {
        $this->authorize('view', $allowance->title->policy->client);
        $allowance->load(['title.policy.client', 'title.supplierAgency', 'author', 'voidedBy', 'recoveries.author', 'recoveries.voidedBy']);
        return view('ledger.allowance', compact('allowance'));
    }

    public function store(StoreInsuranceAllowanceRecoveryRequest $request, InsuranceAllowance $allowance, InsuranceAllowances $allowances)
    {
        $this->authorize('updateRegistry', $allowance->title->policy->client);
        $values = $request->validated();
        $recovery = $allowances->recordRecovery($allowance, [
            'amount_cents'=>Money::cents($values['recovery_amount']), 'received_on'=>$values['recovery_on'],
            'method'=>$values['recovery_method'], 'reference'=>$values['recovery_reference'] ?? null, 'notes'=>$values['recovery_notes'] ?? null,
            'idempotency_key'=>$values['recovery_key'], 'fingerprint'=>$values['recovery_fingerprint'],
        ]);
        return to_route('allowances.show', $allowance)->with('success', $recovery->voided_at ? 'La richiesta è già nello storico e il rientro risulta stornato.' : 'Rientro registrato. Importo da recuperare aggiornato.');
    }

    public function void(Request $request, InsuranceAllowance $allowance, InsuranceAllowanceRecovery $recovery, InsuranceAllowances $allowances)
    {
        $this->authorize('ledger.manage');
        $this->authorize('updateRegistry', $allowance->title->policy->client);
        abort_unless((int)$recovery->insurance_allowance_id === (int)$allowance->id, 404);
        $values = $request->validate(['recovery_void_reason'=>['required', 'string', 'min:5', 'max:1000']]);
        $allowances->voidRecovery($recovery, $values['recovery_void_reason']);
        return to_route('allowances.show', $allowance)->with('success', 'Rientro stornato. Residuo aggiornato e storico conservato.');
    }
}
