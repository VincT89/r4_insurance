<?php

namespace App\Domain\Core\Queries;

use App\Models\Client;
use Illuminate\Support\Collection;

class ClientDeadlineQuery
{
    public function forClient(Client $client, array $filters): Collection
    {
        $policies = $client->policies()->whereNotIn('status', \App\Models\InsurancePolicy::CLOSED_STATUSES)->with(['vehicle', 'asset'])->get()
            ->map(fn ($policy) => [
                'date' => $policy->expires_on, 'kind' => 'Polizza',
                'title' => implode(' / ', array_filter([$policy->category, $policy->subcategory, $policy->company, $policy->number], fn ($value) => filled($value))),
                'detail' => $policy->vehicle?->plate ?? $policy->asset?->name, 'policy_review' => $policy->status === 'review',
                'frequency' => $policy->payment_frequency, 'expired' => $policy->status === 'expired',
                'url' => route('clients.policies.show', [$client, $policy]),
            ]);
        $documents = $client->attachments()->whereNotNull('document_category')->whereNotNull('expires_on')->whereDoesntHave('newerVersion')
            ->with(['insurancePolicy', 'vehicle'])->get()->map(fn ($document) => [
                'date' => $document->expires_on, 'kind' => 'Documento', 'title' => $document->original_name,
                'detail' => $document->insurancePolicy?->number ?? $document->vehicle?->plate,
                'url' => $document->downloadUrl(),
            ]);
        $requests = $client->documentRequests()->whereNotNull('due_on')->whereDoesntHave('documents')->get()->map(fn ($item) => [
            'date' => $item->due_on, 'kind' => 'Documento da ricevere', 'title' => $item->label, 'detail' => $item->notes,
            'url' => route('clients.show', [$client, 'tab' => 'documents']).'#document-request-'.$item->id,
        ]);
        $period = $filters['period'] ?? 'all';
        $titles=\App\Models\InsuranceTitle::whereIn('insurance_policy_id',$client->policies()->select('id'))->whereNull('cancelled_at')->whereColumn('collected_cents','<','amount_cents')->with('policy')->get()->map(fn($t)=>[
            'date'=>$t->due_on,'kind'=>'Titolo','title'=>$t->policy->number.' / '.\App\Models\InsuranceTitle::KINDS[$t->kind],'detail'=>'Residuo '.\App\Support\Money::format($t->balance_cents),'frequency'=>$t->payment_frequency,'url'=>route('titles.show',$t)]);
        return $policies->concat($documents)->concat($requests)->concat($titles)->filter(function (array $item) use ($filters, $period) {
            $date = $item['date'];
            if ($period === 'overdue') {
                return ($item['expired'] ?? false) || $date->lt(today());
            }
            if (ctype_digit($period)) {
                return $date->betweenIncluded(today(), today()->addDays((int) $period));
            }
            if ($period === 'custom') {
                return (! ($filters['from'] ?? null) || $date->toDateString() >= $filters['from'])
                    && (! ($filters['to'] ?? null) || $date->toDateString() <= $filters['to']);
            }
            return true;
        })->sortBy(fn ($item) => $item['date']->toDateString())->values();
    }
}
