<?php

namespace App\Http\Controllers;

use App\Models\InsurancePolicy;
use App\Services\{InsuranceQuoteWorkflow, PolicyContractTransitions, PolicyFormReminder};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PolicyTransitionController extends Controller
{
    public function create(Request $request, InsurancePolicy $policy, string $kind, PolicyContractTransitions $workflow)
    {
        $workflow->authorize($policy,$kind);
        $request->validate(['quote_id'=>['nullable','integer']]);
        $selectedQuote = $workflow->quote($policy,$kind,$request->integer('quote_id') ?: null);
        $source = $policy;
        $data = app(InsurancePolicyController::class)->form($source->client,$source)->getData();
        $policy = clone $source;
        $policy->fill(['starts_on'=>null,'expires_on'=>null,'premium'=>null,'installment_premium'=>null]);
        if ($kind === 'renewal') $policy->starts_on = $source->expires_on;
        else $policy->number = '';
        if ($selectedQuote) $policy->fill(app(InsuranceQuoteWorkflow::class)->policyValues($selectedQuote));
        $openTitles = $source->titles()->where('direction','receipt')->outstanding()->orderBy('due_on')->get();
        $approvedQuotes = $kind === 'replacement' ? \App\Models\InsuranceQuote::where('status','approved')->whereDate('valid_until','>=',today())->whereDoesntHave('policyChange')->whereDoesntHave('policy')
            ->whereHas('quoteCase',fn($q)=>$q->where('client_id',$source->client_id)->where('current_policy_id',$source->id)->where('purpose','replacement')->where('status','approved'))
            ->with(['quoteCase.client','company'])->latest('id')->get() : collect();
        return view('policy-transitions.create',array_replace($data, compact('source','policy','kind','selectedQuote','openTitles'),[
            'approvedQuotes'=>$approvedQuotes,'sourceRevision'=>$workflow->revision($source,$selectedQuote),'requestKey'=>(string)Str::uuid(),
        ]));
    }

    public function preview(Request $request, InsurancePolicy $policy, string $kind, PolicyContractTransitions $workflow)
    {
        $workflow->authorize($policy,$kind);
        $data = $request->validate($workflow->rules($kind,false,$policy));
        $quote = $workflow->quote($policy,$kind,$data['quote_id'] ?? null);
        $preview = $workflow->preview($policy,$kind,$data,$quote);
        return response()->json(\Illuminate\Support\Arr::only($preview,['rows','fingerprint','cancelled_cents','company_changed','scheduled']));
    }

    public function store(Request $request, InsurancePolicy $policy, string $kind, PolicyContractTransitions $workflow)
    {
        $workflow->authorize($policy,$kind);
        $data = $request->validate($workflow->rules($kind,true,$policy));
        $change = $workflow->save($policy,$kind,$data,$request->file('contract'));
        $url = $kind === 'renewal' ? PolicyFormReminder::url($change->resultPolicy, $change) : route('clients.policy-changes.show',[$policy->client_id,$change]);
        $message = $change->isCompanyChange() ? 'Nuova polizza creata e collegata alla precedente. Seleziona i moduli da preparare.' : ($kind === 'renewal' ? 'Rinnovo registrato. Il contratto è salvato.' : 'Sostituzione registrata con contratto e titoli. Seleziona i moduli da preparare.');
        if ($change->replacementSchedule?->state === 'pending') $message = 'Sostituzione programmata dal '.$change->effective_on->format('d/m/Y').'. La polizza precedente resta operativa fino al cambio. Seleziona i moduli da preparare.';
        if ($request->expectsJson()) { session()->flash('success',$message); return response()->json(['url'=>$url]); }
        return redirect($url)->with('success',$message);
    }

    public function cancelSchedule(Request $request, \App\Models\Client $client, \App\Models\InsurancePolicyChange $change, \App\Services\ScheduledPolicyReplacements $replacements)
    {
        $this->authorize('updateRegistry',$client);
        abort_unless((int)$change->sourcePolicy->client_id === (int)$client->id && (int)$change->resultPolicy->client_id === (int)$client->id,404);
        $data = $request->validate(['reason'=>['required','string','min:10','max:2000']]);
        $replacements->cancel($change->replacementSchedule()->firstOrFail(),$data['reason']);
        return redirect()->route('clients.policies.show',[$client,$change->sourcePolicy])->with('success','Programmazione annullata. La polizza precedente resta operativa e lo storico è conservato.');
    }
}
