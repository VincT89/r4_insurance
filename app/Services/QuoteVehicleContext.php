<?php

namespace App\Services;

use App\Models\{Client, InsurancePolicy, InsuranceQuoteCase, QuoteRecipientGroup};
use Illuminate\Validation\ValidationException;

class QuoteVehicleContext
{
    public static function rules(): array
    {
        return [
            'vehicle_id' => ['nullable', 'integer'],
            'merit_class_cu' => ['nullable', 'integer', 'between:1,18'],
            'merit_class_internal' => ['nullable', 'string', 'max:30'],
            'quote_recipient_group_id' => ['nullable', 'integer'],
            'recipient_selection_present' => ['sometimes', 'accepted'],
            'recipient_keys' => ['sometimes', 'array', 'list', 'max:'.QuoteRecipients::MAX_RECIPIENTS],
            'recipient_keys.*' => QuoteRecipients::rules()['recipient_keys.*'],
        ];
    }

    public function values(Client $client, array $data, ?InsurancePolicy $policy, string $purpose): array
    {
        $vehicleId = $data['vehicle_id'] ?? $policy?->vehicle_id;
        if ($purpose === 'addition' && (int)$vehicleId !== (int)$policy?->vehicle_id) {
            throw ValidationException::withMessages(['vehicle_id' => 'L’aggiunta conserva il veicolo della polizza attuale.']);
        }
        $vehicle = $vehicleId ? $client->vehicles()->whereKey($vehicleId)->lockForUpdate()->first() : null;
        if ($vehicleId && !$vehicle) throw ValidationException::withMessages(['vehicle_id' => 'Seleziona un veicolo di questo cliente.']);
        $cu = $data['merit_class_cu'] ?? (($policy && (int)$policy->vehicle_id === (int)$vehicleId) ? $policy->merit_class_cu : $vehicle?->merit_class_cu);
        $internal = $data['merit_class_internal'] ?? (($policy && (int)$policy->vehicle_id === (int)$vehicleId) ? $policy->merit_class_internal : $vehicle?->merit_class_internal);
        if (!$vehicle && ($cu || filled($internal))) throw ValidationException::withMessages(['vehicle_id' => 'Collega il veicolo a cui si riferisce la classe di merito.']);
        $snapshot = $vehicle?->only(['plate', 'make', 'model', 'vin']);
        if ($snapshot) {
            $snapshot['registered_on'] = $vehicle->registered_on?->toDateString();
            $snapshot['class_company_id'] = $policy && (int)$policy->vehicle_id === (int)$vehicleId ? $policy->supplier_company_id : $vehicle->merit_class_company_id;
            $snapshot['class_company'] = $snapshot['class_company_id'] ? \App\Models\SupplierCompany::find($snapshot['class_company_id'])?->name : null;
        }
        $groupId = $data['quote_recipient_group_id'] ?? null;
        $groupSnapshot = null;
        $branchId = (int)($data['insurance_branch_id'] ?? 0);
        $subbranchId = !empty($data['insurance_subbranch_id']) ? (int)$data['insurance_subbranch_id'] : null;
        if ($purpose === 'addition') {
            if ($groupId) throw ValidationException::withMessages(['quote_recipient_group_id' => 'Per le aggiunte si usa la compagnia della polizza attuale.']);
        } elseif ($groupId) {
            $group = QuoteRecipientGroup::forRequest($branchId,$subbranchId,$cu ? (int)$cu : null)->whereKey($groupId)->lockForUpdate()->first();
            if (!$group) throw ValidationException::withMessages(['quote_recipient_group_id' => 'Seleziona un gruppo attivo compatibile con ramo, sottoramo e classe CU della richiesta.']);
            $companies = $group->companies()->orderBy('supplier_companies.id')->get();
            /** Il gruppo conserva il suggerimento originale anche quando l'operatore modifica le spunte. */
            $groupSnapshot = ['name' => $group->name, 'branch' => $group->branch?->name, 'subbranch' => $group->subbranch?->name, 'cu_from' => $group->cu_from, 'cu_to' => $group->cu_to, 'company_ids' => $companies->modelKeys(), 'companies' => $companies->map(fn ($company) => $company->only(['id', 'name']))->all(), 'recipient_keys' => $group->recipientKeys()];
        }
        if (isset($data['recipient_selection_present']) || array_key_exists('recipient_keys', $data)) {
            $keys = $data['recipient_keys'] ?? [];
            $case = (new InsuranceQuoteCase(['purpose'=>$purpose]))->setRelation('currentPolicy', $policy);
            $targets = $keys ? app(QuoteRecipients::class)->resolve($keys, case:$case) : collect();
            $companies = $targets->pluck('company')->unique('id')->values();
            $groupSnapshot = array_replace($groupSnapshot ?? ['name'=>'Scelta manuale'], [
                'source'=>'manual', 'group_recipient_keys'=>$groupSnapshot['recipient_keys'] ?? [],
                'recipient_keys'=>$keys, 'company_ids'=>$companies->pluck('id')->all(),
                'companies'=>$companies->map(fn ($company) => $company->only(['id','name']))->all(),
                'recipients'=>$targets->map(fn ($target) => app(QuoteRecipients::class)->snapshot($target))->all(),
            ]);
        }
        return ['vehicle_id' => $vehicle?->id, 'vehicle_plate' => $vehicle?->plate, 'merit_class_cu' => $cu, 'merit_class_internal' => $internal, 'vehicle_snapshot' => $snapshot, 'quote_recipient_group_id' => $groupId, 'recipient_group_snapshot' => $groupSnapshot];
    }
}
