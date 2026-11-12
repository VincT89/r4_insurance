<?php

namespace App\Services;

use App\Models\{ClientDocumentRequest, InsuranceFormDocument, InsurancePolicy, User};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

class PolicyWorkOverview
{
    public function forPolicies(Collection $policies, User $user): array
    {
        if ($policies->isEmpty()) return [];
        $ids = $policies->modelKeys();
        $policies->loadMissing(['client', 'contracts']);
        /** Batch pending work for the visible page without loading encrypted document bodies or files. */
        $requests = ClientDocumentRequest::whereIn('insurance_policy_id', $ids)->whereDoesntHave('documents')->whereDoesntHave('linkedDocuments')
            ->select('insurance_policy_id')->selectRaw('COUNT(*) as pending_count')->groupBy('insurance_policy_id')->get()->keyBy('insurance_policy_id');
        $signatures = InsuranceFormDocument::where('signature_required', true)
            ->whereHas('package', fn ($q) => $q->whereIn('insurance_policy_id', $ids))
            ->whereDoesntHave('latestSubmission', fn ($q) => $q->where('status', 'validated'))
            ->with(['package:id,insurance_policy_id', 'latestSubmission:id,insurance_form_document_id,version,status'])
            ->get(['id', 'insurance_form_package_id'])->groupBy(fn ($doc) => $doc->package->insurance_policy_id);
        $reminders = DatabaseNotification::where('type', PolicyFormReminder::TYPE)->whereIn('data->policy_id', $ids)->get()->keyBy(fn ($row) => $row->data['policy_id']);
        $result = [];
        foreach ($policies as $policy) {
            $tasks = [];
            $url = fn ($tab, $fragment = '') => route('clients.policies.show', [$policy->client_id, $policy, 'tab'=>$tab]).$fragment;
            $canUpdate = $user->can('updateRegistry', $policy->client);
            $editable = $canUpdate && ($user->canManageSystem() || !$policy->workLocked());
            if ($policy->status === 'review') $tasks[] = ['label'=>'Verifica lo stato', 'detail'=>'Lo stato del contratto è da chiarire.', 'url'=>$editable ? route('clients.policies.edit', [$policy->client_id, $policy]) : $url('history', '#policy-corrections')];
            if ($policy->contracts->isEmpty()) $tasks[] = ['label'=>$canUpdate ? 'Carica contratto' : 'Vedi documenti', 'detail'=>'Il contratto non è ancora allegato.', 'url'=>$canUpdate ? route('clients.documents.create', [$policy->client_id, 'policy'=>$policy->id, 'category'=>'policy']) : $url('documents')];
            if ($count = (int) ($requests->get($policy->id)?->pending_count ?? 0)) $tasks[] = ['label'=>'Ricevi documenti', 'detail'=>$count === 1 ? 'Un documento richiesto è ancora da ricevere.' : $count.' documenti richiesti sono ancora da ricevere.', 'url'=>$url('forms', '#policy-forms')];
            if ($editable && ($reminder = $reminders->get($policy->id))) $tasks[] = ['label'=>'Scegli i moduli', 'detail'=>'La scelta dei moduli della polizza è da completare.', 'url'=>\App\Support\NotificationDestination::local($reminder->data['url'])];
            $pending = $signatures->get($policy->id, collect());
            if ($pending->isNotEmpty()) {
                $review = $pending->contains(fn ($doc) => $doc->latestSubmission?->status === 'received');
                $tasks[] = ['label'=>$review ? 'Controlla le firme' : 'Apri moduli', 'detail'=>$pending->count() === 1 ? 'Un modulo ha una firma da completare o controllare.' : $pending->count().' moduli hanno firme da completare o controllare.', 'url'=>$url('forms', '#policy-signatures')];
            }
            $unsigned = $policy->contracts->first(fn ($file) => $file->mime_type === 'application/pdf' && !$file->latestTabletSignature);
            if ($unsigned && $canUpdate) $tasks[] = ['label'=>'Firma contratto', 'detail'=>'Per questo contratto non risulta una firma raccolta nel gestionale.', 'url'=>route('station-signing.create', $unsigned)];
            if (!$policy->work_closed_at && !in_array($policy->status, InsurancePolicy::CLOSED_STATUSES, true)) $tasks[] = ['label'=>'Controlla e chiudi', 'detail'=>'La lavorazione è ancora aperta.', 'url'=>$url('history', '#policy-work')];
            $result[$policy->id] = $tasks;
        }
        return $result;
    }
}
