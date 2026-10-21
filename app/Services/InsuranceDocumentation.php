<?php

namespace App\Services;

use App\Models\{Attachment, Client, ClientDocumentRequest, InsuranceBranch, InsurancePolicy, InsuranceSubbranch};
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Validation\ValidationException;

class InsuranceDocumentation
{
    public function categories(Client $client, ?InsuranceBranch $branch, ?InsuranceSubbranch $sub = null): array
    {
        if (!$branch || ($sub && $sub->insurance_branch_id !== $branch->id)) return [];
        $field = $client->client_type === 'company' ? 'company_document_categories' : 'person_document_categories';
        return array_values(array_unique(array_merge($branch->document_categories ?? [], $branch->$field ?? [], $sub?->document_categories ?? [], $sub?->$field ?? [])));
    }

    public function candidates(ClientDocumentRequest $request)
    {
        return $request->client->attachments()->where('document_category', $request->document_category)
            ->whereDoesntHave('newerVersion')->whereNull('insurance_claim_id')->whereNull('insurance_form_document_id')
            ->whereNull('insurance_coverage_check_id')->whereNull('insurance_quote_email_id')
            ->whereNull('insurance_title_id')->whereNull('insurance_collection_id')->whereNull('client_material_category')
            ->where(function ($query) use ($request) {
                $query->where(fn ($q) => $q->whereNull('insurance_policy_id')->whereNull('insurance_quote_case_id')->whereNull('insurance_quote_id'));
                if ($request->insurance_policy_id) $query->orWhere('insurance_policy_id', $request->insurance_policy_id);
            });
    }

    public function link(Client $client, ClientDocumentRequest $request, array $ids): void
    {
        Gate::authorize('updateRegistry', $client);
        DB::transaction(function () use ($client, $request, $ids) {
            $client = Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('updateRegistry', $client);
            $request = $client->documentRequests()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $files = $this->candidates($request)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($files->count() !== count($ids)) throw ValidationException::withMessages(['documents'=>'Uno dei documenti non appartiene al cliente, alla categoria o alla polizza indicata, oppure è stato sostituito. Ricarica la pagina.']);
            foreach ($files as $file) {
                Gate::authorize('download', $file);
                if (!Storage::disk($file->disk)->exists($file->path)) throw ValidationException::withMessages(['documents'=>'Il file '.$file->original_name.' non è disponibile.']);
            }
            $existing = $request->documents->pluck('id')->merge($request->linkedDocuments->pluck('id'));
            $new = $files->reject(fn ($file) => $existing->contains($file->id));
            foreach ($new as $file) $request->linkedDocuments()->attach($file->id, ['linked_by'=>auth()->id(), 'created_at'=>now()]);
            if ($new->isNotEmpty()) app(AuditLogService::class)->log('document_requested', $client, description:'Collegati '.$new->count().' documenti esistenti alla richiesta '.$request->label.'.', newValues:['request_id'=>$request->id, 'attachment_ids'=>$new->modelKeys()]);
        });
    }

    public function summary(Client $client, ?InsurancePolicy $policy = null): array
    {
        $requests = $client->documentRequests()->where('insurance_policy_id', $policy?->id)
            ->with(['documents.newerVersion', 'linkedDocuments.newerVersion', 'insurancePolicy'])->orderBy('label')->get();
        $categories = $policy ? $this->categories($client, $policy->branch, $policy->subbranch) : [];
        $missing = array_values(array_diff($categories, $requests->pluck('document_category')->all()));
        return compact('requests', 'categories', 'missing');
    }

    public function forms(Client $client, ?InsurancePolicy $policy = null): array
    {
        $package = $client->formPackages()->where('insurance_policy_id', $policy?->id)
            ->with(['client', 'policy', 'documents.latestSubmission'])->latest('id')->first();
        $templates = app(InsuranceForms::class)->available($policy, $client);
        return compact('package', 'templates');
    }
}
