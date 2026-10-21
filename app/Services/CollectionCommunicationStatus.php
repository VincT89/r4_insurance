<?php

namespace App\Services;

use App\Models\{Client, InsuranceCollection, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CollectionCommunicationStatus
{
    public function payments(User $user): Builder
    {
        return InsuranceCollection::query()
            ->join('insurance_titles as communication_title', 'communication_title.id', '=', 'insurance_collections.insurance_title_id')
            ->join('insurance_policies as communication_policy', 'communication_policy.id', '=', 'communication_title.insurance_policy_id')
            ->leftJoin('supplier_agencies as communication_agency', 'communication_agency.id', '=', 'communication_policy.supplier_agency_id')
            ->whereIn('communication_policy.client_id', Client::current()->visibleTo($user)->select('id'))
            ->whereNull('insurance_collections.voided_at')->whereNull('communication_title.cancelled_at')
            ->select('insurance_collections.*');
    }

    private function claims(): QueryBuilder
    {
        /** A change of contact address must not silently turn an already communicated payment into a new send. */
        return DB::table('agency_communication_collections as delivery')
            ->join('agency_communications as communication', 'communication.id', '=', 'delivery.agency_communication_id')
            ->whereNotNull('delivery.delivery_key')->whereIn('communication.status', ['sending', 'sent', 'uncertain']);
    }

    private function claimedPayments(): QueryBuilder
    {
        /** Materialize claimed payments once; preserve the agency of the current contract, even after reassignment. */
        return $this->claims()
            ->join('insurance_collections as claimed_payment', 'claimed_payment.id', '=', 'delivery.insurance_collection_id')
            ->join('insurance_titles as claimed_title', 'claimed_title.id', '=', 'claimed_payment.insurance_title_id')
            ->join('insurance_policies as claimed_policy', 'claimed_policy.id', '=', 'claimed_title.insurance_policy_id')
            ->whereColumn('communication.supplier_agency_id', 'claimed_policy.supplier_agency_id')
            ->select('delivery.insurance_collection_id');
    }

    public function pending(Builder $query): Builder
    {
        return $query->where(fn ($pending) => $pending->whereNotIn('insurance_collections.id', $this->claimedPayments()->where('communication.status', 'sent'))
            ->orWhereIn('insurance_collections.id', $this->claimedPayments()->whereIn('communication.status', ['sending','uncertain'])));
    }

    public function attention(Builder $query): Builder
    {
        return $this->pending($query)->whereIn('insurance_collections.id', $this->claimedPayments()->where(fn ($q) => $q->where('communication.status', 'uncertain')
            ->orWhere(fn ($q) => $q->where('communication.status', 'sending')->where('communication.sending_at', '<=', now()->subMinutes(5)))));
    }

    public function withDeliveryState(Builder $query): Builder
    {
        /** Rank the delivery history once instead of repeating a sorted lookup for every outstanding payment. */
        $ranked = $this->claims()->select('delivery.insurance_collection_id', 'communication.supplier_agency_id', 'communication.status')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY delivery.insurance_collection_id, communication.supplier_agency_id ORDER BY communication.created_at DESC, delivery.id DESC) AS delivery_position');
        $latest = DB::query()->fromSub($ranked, 'ranked_delivery')->where('delivery_position', 1);

        return $query->leftJoinSub($latest, 'latest_delivery', fn ($join) => $join
            ->on('latest_delivery.insurance_collection_id', '=', 'insurance_collections.id')
            ->on('latest_delivery.supplier_agency_id', '=', 'communication_policy.supplier_agency_id'))
            ->addSelect('latest_delivery.status as communication_state');
    }

    public function deliveries(Collection $payments): Collection
    {
        if ($payments->isEmpty()) return collect();
        $agencies = $payments->mapWithKeys(fn ($payment) => [$payment->id => $payment->title->policy->supplier_agency_id]);
        return DB::table('agency_communication_collections as delivery')
            ->join('agency_communications as communication', 'communication.id', '=', 'delivery.agency_communication_id')
            ->whereIn('delivery.insurance_collection_id', $payments->pluck('id'))->whereNotNull('delivery.delivery_key')
            ->whereIn('communication.status', ['sending', 'sent', 'uncertain'])
            ->orderByDesc('communication.created_at')->orderByDesc('delivery.id')
            ->get(['delivery.insurance_collection_id', 'communication.id', 'communication.status', 'communication.supplier_agency_id'])
            ->filter(fn ($row) => (int) $row->supplier_agency_id === (int) $agencies->get($row->insurance_collection_id))
            ->unique('insurance_collection_id')->keyBy('insurance_collection_id');
    }
}
