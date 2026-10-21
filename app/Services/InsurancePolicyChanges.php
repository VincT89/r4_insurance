<?php

namespace App\Services;

use App\Models\{Client, InsuranceBranch, InsurancePolicy, InsurancePolicyChange, InsuranceQuote, SupplierCompany};
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Validation\ValidationException;

class InsurancePolicyChanges
{
    private function fail(string $message): never { throw ValidationException::withMessages(['change'=>$message]); }

    public function check(InsurancePolicy $policy, string $kind): void
    {
        Gate::authorize('updateRegistry', $policy->client);
        abort_unless($policy->editableBy(auth()->user()), 403);
        $allowed = $kind === 'addition' ? ['active','renewed'] : ['active','renewed','expired','suspended','ceased'];
        if (!in_array($policy->status, $allowed, true)) $this->fail('Lo stato di questa polizza non consente la variazione selezionata.');
        if ($kind === 'replacement' && $policy->changes()->where('kind','replacement')->exists()) $this->fail('Questa polizza ha già una sostitutiva collegata.');
    }

    public function fingerprint(InsurancePolicy $policy, InsuranceQuote $quote): string
    {
        $documents = $quote->documents()->orderBy('id')->get()->map(fn($file)=>[
            $file->getAttributes(), Storage::disk($file->disk)->exists($file->path) ? hash('sha256', Storage::disk($file->disk)->get($file->path)) : null,
        ]);
        return hash('sha256', json_encode([$policy->getAttributes(), $policy->client->getAttributes(), $policy->changes()->max('id'), $quote->getAttributes(), $quote->quoteCase->getAttributes(), $documents], JSON_THROW_ON_ERROR));
    }

    public function record(Client $client, InsurancePolicy $source, array $data, array $files): InsurancePolicyChange
    {
        $stored = [];
        try {
            return DB::transaction(function () use ($client, $source, $data, $files, &$stored) {
                $workflow = app(InsuranceQuoteWorkflow::class);
                $quote = $workflow->approvedQuote($client, $data['quote_id']);
                /** Follow the catalog lock order used by ordinary policy edits before locking the contract. */
                InsuranceBranch::orderBy('id')->lockForUpdate()->get();
                SupplierCompany::orderBy('id')->lockForUpdate()->get();
                $client = Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
                $source = $client->policies()->whereKey($source->id)->lockForUpdate()->firstOrFail();
                $this->check($source, $data['kind']);
                $case = $quote->quoteCase;
                if ($case->purpose !== $data['kind'] || (int)$case->current_policy_id !== $source->id) $this->fail('La proposta non appartiene a questa variazione e polizza.');
                if (InsurancePolicyChange::where('request_key',$data['request_key'])->exists()) $this->fail('La variazione è già registrata. Consulta lo storico.');
                if (!hash_equals($this->fingerprint($source, $quote), $data['fingerprint'])) $this->fail('Polizza, preventivo o allegati sono cambiati. Riapri il riepilogo e controllali.');
                if ($quote->starts_on->lt($source->starts_on)) $this->fail('La variazione non può precedere la decorrenza della polizza attuale.');
                $before = app(InsuranceForms::class)->context($client, $source);
                if ($data['kind'] === 'replacement') {
                    if ($quote->starts_on->gt(today())) $this->fail('Puoi confermare la sostituzione dalla data di decorrenza del nuovo contratto. Il preventivo resta disponibile.');
                    $number = trim($data['number']);
                    if (mb_strtoupper($number) === mb_strtoupper(trim($source->number))) $this->fail('La sostitutiva deve avere un numero diverso dalla polizza precedente.');
                    if ($client->policies()->where('company',$quote->company->name)->where('number',$number)->exists()) $this->fail('Il numero è già presente per questo cliente e compagnia.');
                    $values = array_replace($source->only(['vehicle_id','client_asset_id','insured_subject','merit_class_cu','merit_class_internal','payment_method','tacit_renewal','ad_horas']), $workflow->policyValues($quote), ['number'=>$number,'status'=>'active']);
                    $result = $client->policies()->create(app(PolicyProviders::class)->values(app(InsuranceCatalog::class)->policyValues($values)));
                    $result->forceFill(['insurance_quote_id'=>$quote->id])->save();
                    $source->forceFill(['status'=>'replaced','closed_at'=>$quote->starts_on])->save();
                } else {
                    if ((int)$source->supplier_company_id !== (int)$quote->supplier_company_id || (int)$source->supplier_agency_id !== (int)$quote->supplier_agency_id) $this->fail('L’aggiunta mantiene compagnia e agenzia della polizza.');
                    if ((int)$source->insurance_branch_id !== (int)$case->insurance_branch_id || (int)$source->insurance_subbranch_id !== (int)$case->insurance_subbranch_id) $this->fail('Per un altro ramo o sottoramo occorre una nuova polizza.');
                    if ($quote->expires_on->gt($source->expires_on)) $this->fail('La copertura aggiuntiva non può superare la scadenza della polizza.');
                    if ($source->changes()->where('kind','addition')->where('reference',$data['reference'])->exists()) $this->fail('Questo riferimento di appendice è già presente sulla polizza.');
                    /** An endorsement is a separate premium for its agreed period; existing installments are never rewritten. */
                    $result = $source;
                }
                $change = InsurancePolicyChange::create([
                    'source_policy_id'=>$source->id, 'result_policy_id'=>$result->id, 'insurance_quote_id'=>$quote->id,
                    'kind'=>$data['kind'], 'effective_on'=>$quote->starts_on, 'expires_on'=>$quote->expires_on,
                    'reference'=>$data['kind']==='replacement' ? $result->number : $data['reference'],
                    'description'=>$data['description'], 'premium_cents'=>$quote->premium_cents,
                    'snapshot'=>['before'=>$before, 'after'=>app(InsuranceForms::class)->context($client,$result),
                        'quote'=>$quote->only(['reference','product','premium_cents','payment_frequency','approved_on']),
                        'requested_coverages'=>$case->additional_coverages],
                    'request_key'=>$data['request_key'], 'created_by'=>auth()->id(),
                ]);
                $context = ['insurance_policy_id'=>$result->id, 'insurance_policy_change_id'=>$change->id];
                $quote->documents()->update($context);
                $case->documents()->update($context);
                $stored = app(PrivateAttachmentBatch::class)->upload($client, $files, $context+['document_category'=>'policy']);
                $quote->update(['status'=>'converted']);
                $case->update(['status'=>'closed']);
                app(InsuranceLedger::class)->log($source, 'policy_contract_changed', InsurancePolicyChange::KINDS[$change->kind].' #'.$change->id.', polizza '.$source->number.', contratto risultante '.$result->number.'. '.$change->description, ['policy_change_id'=>$change->id,'result_policy_id'=>$result->id]);
                if ($result->id !== $source->id) app(InsuranceLedger::class)->log($result, 'policy_contract_changed', 'Nuova sostitutiva della polizza '.$source->number.'. Storico variazione #'.$change->id.'.');
                return $change;
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $file) Storage::disk($file->disk)->delete($file->path);
            throw $exception;
        }
    }
}
