<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\{Client, InsurancePolicy, InsurancePolicyCorrection, User};
use App\Services\InsuranceLedger;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InsurancePolicyWorkflowController extends Controller
{
    private function authorizePolicy(Client $client, InsurancePolicy $policy): void
    {
        abort_unless((int) $policy->client_id === (int) $client->id, 404);
        $this->authorize('updateRegistry', $client);
    }

    public function close(Request $request, Client $client, InsurancePolicy $policy): RedirectResponse
    {
        $this->authorizePolicy($client, $policy);
        $data = $request->validate(['confirmed' => ['accepted'], 'fingerprint' => ['required', 'string', 'size:64']]);
        DB::transaction(function () use ($policy, $data) {
            $locked = InsurancePolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail();
            $this->authorize('updateRegistry', $locked->client);
            if ($locked->work_closed_at) return;
            if (!hash_equals($locked->workFingerprint(), $data['fingerprint'])) {
                throw ValidationException::withMessages(['workflow' => 'La polizza è cambiata. Ricarica la scheda e ricontrollala prima di chiudere la lavorazione.']);
            }
            $locked->forceFill(['work_closed_at' => now(), 'work_closed_by' => auth()->id()])->save();
            app(InsuranceLedger::class)->log($locked, 'policy_work_closed', 'lavorazione completata; modifiche riservate all’admin dal giorno successivo.', ['work_closed_at' => $locked->work_closed_at->toIso8601String()]);
        });
        return redirect()->route('clients.policies.show', [$client, $policy])->with('success', 'Lavorazione chiusa. Dal giorno successivo solo l’admin potrà modificare la polizza.');
    }

    public function report(Request $request, Client $client, InsurancePolicy $policy): RedirectResponse
    {
        $this->authorizePolicy($client, $policy);
        $data = $request->validate(['message' => ['required', 'string', 'min:10', 'max:3000'], 'request_key' => ['required', 'uuid']]);
        DB::transaction(function () use ($policy, $data) {
            $locked = InsurancePolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail();
            $this->authorize('updateRegistry', $locked->client);
            $existing = InsurancePolicyCorrection::where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless((int) $existing->insurance_policy_id === (int) $locked->id && (int) $existing->requested_by === (int) auth()->id() && $existing->message === $data['message'], 409);
                return;
            }
            $correction = $locked->corrections()->create($data + ['requested_by' => auth()->id(), 'status' => 'open']);
            app(InsuranceLedger::class)->log($locked, 'policy_correction_requested', 'segnalato un errore all’admin.', ['correction_id' => $correction->id, 'message' => $correction->message]);
            foreach (User::where('role', UserRole::Admin->value)->where('status', 'active')->get() as $admin) {
                $this->notify($admin, $locked, $correction, 'Errore segnalato su una polizza', 'Da esaminare: polizza '.$locked->number.'.');
            }
        });
        return redirect()->to(route('clients.policies.show', [$client, $policy]).'#policy-corrections')->with('success', 'Segnalazione registrata e notificata all’admin.');
    }

    public function resolve(Request $request, Client $client, InsurancePolicy $policy, InsurancePolicyCorrection $correction): RedirectResponse
    {
        $this->authorize('system.admin');
        $this->authorizePolicy($client, $policy);
        abort_unless((int) $correction->insurance_policy_id === (int) $policy->id, 404);
        $data = $request->validate(['status' => ['required', 'in:corrected,no_change'], 'resolution' => ['required', 'string', 'min:10', 'max:3000']]);
        DB::transaction(function () use ($policy, $correction, $data) {
            $locked = InsurancePolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail();
            $entry = InsurancePolicyCorrection::whereKey($correction->id)->lockForUpdate()->firstOrFail();
            if ($entry->resolved_at) {
                if ($entry->status === $data['status'] && $entry->resolution === $data['resolution']) return;
                throw ValidationException::withMessages(['workflow' => 'L’esito è già registrato e resta nello storico.']);
            }
            $entry->update($data + ['resolved_at' => now(), 'resolved_by' => auth()->id()]);
            app(InsuranceLedger::class)->log($locked, 'policy_correction_resolved', 'registrato l’esito della segnalazione.', ['correction_id' => $entry->id] + $data);
            $requester = $entry->requester;
            if ($requester && $requester->status === 'active' && $requester->can('view', $locked->client)) {
                $this->notify($requester, $locked, $entry, 'Esito della segnalazione', 'Polizza '.$locked->number.': '.InsurancePolicyCorrection::STATUSES[$entry->status].'.');
            }
        });
        return redirect()->to(route('clients.policies.show', [$client, $policy]).'#policy-corrections')->with('success', 'Esito registrato nello storico della polizza.');
    }

    private function notify(User $recipient, InsurancePolicy $policy, InsurancePolicyCorrection $correction, string $title, string $message): void
    {
        $recipient->notifications()->create(['id' => (string) Str::uuid(), 'type' => InsurancePolicyCorrection::NOTIFICATION_TYPE, 'data' => [
            'type' => InsurancePolicyCorrection::NOTIFICATION_TYPE, 'title' => $title, 'message' => $message, 'client_id' => $policy->client_id,
            'policy_id' => $policy->id, 'correction_id' => $correction->id, 'url' => route('clients.policies.show', [$policy->client_id, $policy->id], false).'#policy-corrections',
        ]]);
    }
}
