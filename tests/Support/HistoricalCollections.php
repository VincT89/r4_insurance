<?php

namespace Tests\Support;

use App\Models\{InsuranceCollection, InsuranceTitle};
use App\Services\{InsuranceCommissionLedger, InsuranceLedger};
use Illuminate\Support\Str;

trait HistoricalCollections
{
    /** Existing partial payments are fixtures, never an exception in the new collection service. */
    private function historicalCollection(InsuranceTitle $title, int $cents, array $extra = []): InsuranceCollection
    {
        $title->refresh();
        $data = array_replace(['received_on'=>today()->toDateString(), 'amount_cents'=>$cents,
            'payment_method'=>'Bonifico a Intermediario', 'recipient'=>'our_agency', 'document_type'=>'transfer',
            'consultant_id'=>auth()->id(), 'created_by'=>auth()->id(), 'idempotency_key'=>(string) Str::uuid(),
            'request_fingerprint'=>hash('sha256', Str::uuid())], $extra);
        $payment = $title->collections()->create($data);
        $title->update(['collected_cents'=>$title->collected_cents + $cents]);
        app(InsuranceCommissionLedger::class)->sync($title, $payment->received_on->toDateString(), 'Incasso storico di prova.');
        app(InsuranceLedger::class)->refresh($title->policy);
        return $payment;
    }
}
