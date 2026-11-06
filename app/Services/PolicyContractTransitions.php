<?php

namespace App\Services;

use App\Models\{Client, InsuranceBranch, InsurancePolicy, InsurancePolicyChange, InsuranceQuote, SupplierCompany};
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\{Arr, Str};
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Validation\{Rule, ValidationException};

class PolicyContractTransitions
{
    private function fail(string $message, string $field = 'transition'): never { throw ValidationException::withMessages([$field=>$message]); }

    private function changesCompany(InsurancePolicy $source, array $values): bool
    {
        return $source->supplier_company_id ? (int)$source->supplier_company_id !== (int)$values['supplier_company_id']
            : InsuranceCatalog::normalize($source->company) !== InsuranceCatalog::normalize($values['company']);
    }

    public function authorize(InsurancePolicy $policy, string $kind): void
    {
        abort_unless(in_array($kind, ['renewal','replacement'], true), 404);
        Gate::authorize('updateRegistry', $policy->client);
        Gate::authorize('ledger.manage');
    }

    public function rules(string $kind, bool $save = false, ?InsurancePolicy $policy = null): array
    {
        $rules = app(PolicyContractSchedule::class)->rules();
        unset($rules['status']);
        $rules += [
            'number'=>['required','string','max:100'], 'company'=>['required','string','max:150'],
            'supplier_agency_id'=>['nullable','integer'], 'category'=>['required','string','max:100'], 'subcategory'=>['nullable','string','max:100'],
            'product'=>['nullable','string','max:255'], 'insured_subject'=>['nullable','string','max:255'],
            'vehicle_id'=>['nullable','integer',Rule::exists('client_vehicles','id')->where('client_id',$policy?->client_id)],
            'client_asset_id'=>['nullable','integer',Rule::exists('client_assets','id')->where('client_id',$policy?->client_id)],
            'merit_class_cu'=>['nullable','integer','between:1,18'], 'merit_class_internal'=>['nullable','string','max:30'],
            'quote_id'=>['nullable','integer'], 'quote_changes_confirmed'=>['sometimes','boolean'],
            'titles_mode'=>['sometimes',Rule::in(['automatic','manual'])],
            'source_revision'=>['required','string','size:64'],
            'cancel_titles'=>['sometimes','array','list','max:120'], 'cancel_titles.*'=>['integer','distinct'],
            'cancellation_reason'=>['nullable','required_with:cancel_titles','string','min:5','max:1000'],
            'adjustment'=>['required',Rule::in($kind === 'replacement' ? ['none','charge','refund'] : ['none'])],
            'adjustment_amount'=>['nullable','required_unless:adjustment,none','numeric','min:0.01','max:9999999999.99','decimal:0,2'],
            'charge_mode'=>['nullable','required_if:adjustment,charge',Rule::in(['replace_first','additional'])],
            'adjustment_due_on'=>['nullable','required_unless:adjustment,none','date_format:Y-m-d'],
            'adjustment_reference'=>['nullable','required_unless:adjustment,none','string','min:3','max:500'],
            'adjustment_policy'=>['nullable',Rule::requiredIf(fn()=>request('adjustment')==='refund' || (request('adjustment')==='charge' && request('charge_mode')==='additional')),Rule::in(['previous','new'])],
            'description'=>['nullable','string','max:2000'],
        ];
        if ($save) $rules += [
            'contract'=>['required','file','mimes:pdf','extensions:pdf','max:10240'],
            'contract_reviewed'=>['accepted'], 'schedule_fingerprint'=>['required','string','size:64'], 'request_key'=>['required','uuid'],
        ];
        return $rules;
    }

    public function revision(InsurancePolicy $policy, ?InsuranceQuote $quote = null): string
    {
        $titles = $policy->titles()->orderBy('id')->get()->map(fn($title)=>[$title->getAttributes(), $title->collections()->orderBy('id')->get()->map->getAttributes()->all()])->all();
        return hash_hmac('sha256', json_encode([$policy->getAttributes(), $policy->client->getAttributes(), $policy->changes()->max('id'), $policy->incomingChanges()->max('id'), $titles, $quote ? app(InsurancePolicyChanges::class)->fingerprint($policy, $quote) : null], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function quote(InsurancePolicy $policy, string $kind, ?int $id): ?InsuranceQuote
    {
        if (!$id) return null;
        $quote = app(InsuranceQuoteWorkflow::class)->approvedQuote($policy->client, $id);
        if ($kind !== 'replacement' || $quote->quoteCase->purpose !== $kind || (int)$quote->quoteCase->current_policy_id !== $policy->id) $this->fail('Il preventivo non appartiene a questa sostituzione.', 'quote_id');
        return $quote;
    }

    private function values(InsurancePolicy $source, string $kind, array $data): array
    {
        $values = Arr::only($data, ['number','company','supplier_agency_id','category','subcategory','product','insured_subject','vehicle_id','client_asset_id','starts_on','expires_on','premium','installment_premium','payment_frequency','payment_method','merit_class_cu','merit_class_internal']);
        $values = array_replace($source->only(['vehicle_id','client_asset_id','insured_subject','tacit_renewal','ad_horas','merit_class_cu','merit_class_internal']), $values, ['status'=>$kind === 'renewal' ? 'renewed' : 'active']);
        $values['number'] = trim($values['number']);
        if (!empty($values['vehicle_id']) && !empty($values['client_asset_id'])) $this->fail('Seleziona il veicolo oppure il bene assicurato.', 'client_asset_id');
        $values = app(PolicyProviders::class)->values(app(InsuranceCatalog::class)->policyValues($values, $source), $source);
        if (!$values['supplier_company_id']) $this->fail('Seleziona una compagnia presente nell’anagrafica.', 'company');
        if($this->changesCompany($source,$values)) {
            $values['status']='active';
            $values['merit_class_internal']=$data['merit_class_internal']??null;
        }
        return $values;
    }

    public function preview(InsurancePolicy $source, string $kind, array $data, ?InsuranceQuote $quote = null): array
    {
        $this->authorize($source, $kind);
        $allowed = $kind === 'renewal' ? ['active','renewed','expired'] : ['active','renewed','expired','suspended','ceased'];
        if (!in_array($source->status, $allowed, true)) $this->fail('Lo stato della polizza non consente questa operazione.');
        if($source->companyChangeTo()->exists())$this->fail('È già presente una nuova polizza collegata alla scadenza. Aprila dalla scheda della polizza precedente.');
        if ($source->replacementTo()->exists() || $source->hasPendingReplacement()) $this->fail('È già presente una sostituzione collegata. Apri il cambio programmato dalla scheda della polizza.');
        if (!hash_equals($this->revision($source, $quote), $data['source_revision'])) $this->fail('La polizza o i suoi pagamenti sono cambiati. Riapri il modulo per controllare i dati aggiornati.', 'source_revision');
        if ($kind === 'renewal' && $data['starts_on'] !== $source->expires_on->toDateString()) $this->fail('Il rinnovo deve iniziare alla scadenza della polizza attuale.', 'starts_on');
        if ($kind === 'replacement' && ($data['starts_on'] < $source->starts_on->toDateString() || $data['starts_on'] >= $source->expires_on->toDateString())) $this->fail('La sostituzione deve decorrere prima della scadenza e nel periodo della vecchia polizza. Alla scadenza usa Rinnova o cambia compagnia.', 'starts_on');
        $scheduled = $kind === 'replacement' && $data['starts_on'] > today()->toDateString();
        if ($scheduled && !in_array($source->status,['active','renewed'],true)) $this->fail('Puoi programmare una sostituzione futura soltanto per una polizza attiva o rinnovata.');
        $values = $this->values($source, $kind, $data);
        $companyChanged=$kind==='renewal' && $this->changesCompany($source,$values);
        if($companyChanged && mb_strtoupper($values['number'])===mb_strtoupper(trim($source->number)))$this->fail('Indica il numero del nuovo contratto della compagnia scelta.', 'number');
        $duplicates = $source->client->policies()->where('company', $values['company'])->whereRaw('UPPER(number) = ?', [mb_strtoupper($values['number'])]);
        if ($kind === 'renewal' && !$companyChanged) $duplicates->where('id','!=',$source->id);
        if ($duplicates->exists()) $this->fail('Questo numero è già presente per il cliente e la compagnia. Controlla il contratto.', 'number');
        $next = new InsurancePolicy($values);
        $rows = ($data['titles_mode'] ?? 'automatic') === 'manual' ? [] : app(InsuranceLedger::class)->plan($next);
        $cancel = array_map('intval', $data['cancel_titles'] ?? []);
        sort($cancel);
        if ($kind !== 'replacement' && $cancel) $this->fail('Il rinnovo conserva i titoli precedenti.');
        $oldTitles = $source->titles()->whereIn('id', $cancel)->get();
        if ($oldTitles->count() !== count($cancel) || $oldTitles->contains(fn($title)=>$title->cancelled_at || $title->balance_cents < 1 || $title->direction === 'refund')) $this->fail('Seleziona soltanto residui ancora aperti della vecchia polizza.', 'cancel_titles');
        $adjustment = $data['adjustment'];
        if ($adjustment === 'charge' && $data['charge_mode'] === 'replace_first') {
            if (!$rows) $this->fail('Per sostituire il primo titolo con il conguaglio, scegli la generazione dal frazionamento.', 'titles_mode');
            $rows[0]['amount_cents'] = Money::cents($data['adjustment_amount']);
            $rows[0]['due_on'] = $data['adjustment_due_on'];
            $rows[0]['notes'] = 'Primo titolo sostituito dal conguaglio comunicato: '.$data['adjustment_reference'];
        } elseif ($adjustment !== 'none') {
            $rows[] = ['kind'=>'adjustment', 'direction'=>$adjustment === 'refund' ? 'refund' : 'receipt', 'affects_coverage'=>false,
                'amount_cents'=>Money::cents($data['adjustment_amount']), 'due_on'=>$data['adjustment_due_on'],
                'coverage_from'=>$data['starts_on'], 'coverage_to'=>$data['expires_on'], 'payment_frequency'=>'Unico', 'payment_method'=>$data['payment_method'],
                'notes'=>'Conguaglio comunicato: '.$data['adjustment_reference'], 'source_contract'=>($data['adjustment_policy'] ?? 'new') === 'previous'];
        }
        $result = ['rows'=>$rows, 'values'=>$values, 'cancel_titles'=>$cancel, 'cancelled_cents'=>$oldTitles->sum('balance_cents'), 'company_changed'=>$companyChanged, 'scheduled'=>$scheduled];
        $result['fingerprint'] = hash_hmac('sha256', json_encode([auth()->id(),$kind,$data['source_revision'],$result,Arr::only($data,['cancellation_reason','adjustment','charge_mode','adjustment_reference','quote_id'])], JSON_THROW_ON_ERROR), config('app.key'));
        return $result;
    }

    public function save(InsurancePolicy $source, string $kind, array $data, UploadedFile $contract): InsurancePolicyChange
    {
        $this->authorize($source, $kind);
        $stored = [];
        $digestValues = Arr::except($data, ['contract','contract_reviewed']); ksort($digestValues);
        $digest = hash('sha256', json_encode([$digestValues, hash_file('sha256', $contract->getRealPath())], JSON_THROW_ON_ERROR));
        try {
            return DB::transaction(function () use ($source,$kind,$data,$contract,$digest,&$stored) {
                $existing = InsurancePolicyChange::where('request_key',$data['request_key'])->first();
                if ($existing) {
                    if ($existing->source_policy_id !== $source->id || $existing->created_by !== auth()->id() || !hash_equals($existing->snapshot['request_digest'] ?? '', $digest)) $this->fail('La richiesta è già stata usata con dati diversi. Riapri il modulo.');
                    return $existing;
                }
                $quote = $this->quote($source,$kind,$data['quote_id'] ?? null);
                InsuranceBranch::orderBy('id')->lockForUpdate()->get();
                SupplierCompany::orderBy('id')->lockForUpdate()->get();
                $client = Client::current()->whereKey($source->client_id)->lockForUpdate()->firstOrFail();
                $source = $client->policies()->whereKey($source->id)->lockForUpdate()->firstOrFail();
                $source->titles()->orderBy('id')->lockForUpdate()->get();
                $preview = $this->preview($source,$kind,$data,$quote);
                if (!hash_equals($preview['fingerprint'],$data['schedule_fingerprint'])) $this->fail('Ricontrolla l’anteprima: i dati dell’operazione sono cambiati.', 'schedule_fingerprint');
                if ($quote) {
                    $approved = app(InsuranceQuoteWorkflow::class)->policyValues($quote);
                    foreach (Arr::except($approved,['merit_class_cu','merit_class_internal','installment_premium']) as $field=>$value) {
                        $same = $field === 'premium' ? Money::cents($value) === Money::cents($preview['values'][$field] ?? 0) : (string)$value === (string)($preview['values'][$field] ?? '');
                        if (!$same && empty($data['quote_changes_confirmed'])) $this->fail('Conferma le differenze tra contratto e preventivo.', 'quote_changes_confirmed');
                    }
                }
                $before = app(InsuranceForms::class)->context($client,$source);
                $work = $source->only(['work_closed_at','work_closed_by']);
                $companyChanged=$preview['company_changed'];
                $scheduled=$preview['scheduled'];
                /** A different insurer starts a linked contract; the original contract and its financial state remain untouched. */
                if ($kind === 'renewal' && !$companyChanged) {
                    if (!$source->coverage_origin_on) $source->forceFill(['coverage_origin_on'=>$source->starts_on]);
                    $source->fill($preview['values'])->forceFill(['closed_at'=>null,'work_closed_at'=>null,'work_closed_by'=>null,'last_renewed_at'=>now()])->save();
                    $result = $source;
                } else {
                    $result = $client->policies()->create($preview['values']);
                    if ($quote) $result->forceFill(['insurance_quote_id'=>$quote->id])->save();
                    if(!$companyChanged && !$scheduled)$source->forceFill(['status'=>'replaced','closed_at'=>$data['starts_on']])->save();
                }
                $description = trim($data['description'] ?? '') ?: ($companyChanged ? 'Cambio compagnia alla scadenza: nuova polizza dal contratto verificato dall’operatore.' : ($kind === 'renewal' ? 'Rinnovo dal contratto verificato dall’operatore.' : 'Sostituzione dal contratto verificato dall’operatore.'));
                $change = InsurancePolicyChange::create([
                    'source_policy_id'=>$source->id,'result_policy_id'=>$result->id,'insurance_quote_id'=>$quote?->id,'kind'=>$kind,
                    'effective_on'=>$data['starts_on'],'expires_on'=>$data['expires_on'],'reference'=>$result->number,'description'=>$description,
                    'premium_cents'=>Money::cents($result->premium),'request_key'=>$data['request_key'],'created_by'=>auth()->id(),
                    'snapshot'=>['before'=>$before,'after'=>app(InsuranceForms::class)->context($client,$result),'previous_work'=>$work,
                        'cancel_titles'=>$preview['cancel_titles'],'cancelled_cents'=>$preview['cancelled_cents'],'cancellation_reason'=>$data['cancellation_reason'] ?? null,
                        'adjustment'=>Arr::only($data,['adjustment','adjustment_amount','adjustment_due_on','adjustment_reference','charge_mode','adjustment_policy']),
                        'titles'=>$preview['rows'],'titles_mode'=>$data['titles_mode'] ?? 'automatic','request_digest'=>$digest],
                ]);
                $ledger = app(InsuranceLedger::class);
                app(ScheduledPolicyReplacements::class)->record($change);
                if (!$scheduled) foreach ($preview['cancel_titles'] as $id) $ledger->cancelResidual($source->titles()->findOrFail($id),$data['cancellation_reason']);
                $result->refresh();
                foreach ($preview['rows'] as $row) {
                    $titlePolicy = !empty($row['source_contract']) ? $source : $result;
                    unset($row['source_contract']);
                    $ledger->transitionTitle($titlePolicy,$row+['insurance_policy_change_id'=>$change->id,'affects_coverage'=>true]);
                }
                $ledger->refresh($result);
                if ($result->id !== $source->id && !$companyChanged && !$scheduled) $ledger->refresh($source);
                $context = ['insurance_policy_id'=>$result->id,'insurance_policy_change_id'=>$change->id];
                $stored = app(PrivateAttachmentBatch::class)->upload($client,[$contract],$context+['document_category'=>'policy']);
                if ($quote) {
                    $quote->documents()->update($context); $quote->quoteCase->documents()->update($context);
                    $quote->update(['status'=>'converted']); $quote->quoteCase->update(['status'=>'closed']);
                }
                $ledger->log($source,$kind === 'renewal' ? 'policy_renewed' : 'policy_contract_changed',$description,['policy_change_id'=>$change->id,'result_policy_id'=>$result->id],$before['policy'] ?? []);
                if ($result->id !== $source->id) $ledger->log($result,'policy_contract_changed',($companyChanged ? 'Prosegue alla scadenza della polizza ' : 'Sostituisce la polizza ').$source->number.'.',['policy_change_id'=>$change->id]);
                if ($kind === 'renewal') PolicyFormReminder::record($result, $change);
                else PolicyFormReminder::clear($source);
                return $change;
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $file) Storage::disk($file->disk)->delete($file->path);
            throw $exception;
        }
    }
}
