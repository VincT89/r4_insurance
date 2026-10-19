<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientConsent;
use App\Support\ClientRegistryFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientRecordController extends Controller
{
    private function relation(Client $client, string $section)
    {
        abort_unless(array_key_exists($section, ClientRegistryFields::SECTIONS), 404);

        return $client->$section();
    }

    public function create(Client $client, string $section)
    {
        $this->authorize('updateRegistry', $client);

        return $this->form($client, $section, $this->relation($client, $section)->make());
    }

    public function store(Request $request, Client $client, string $section)
    {
        $this->authorize('updateRegistry', $client);
        $relation = $this->relation($client, $section);
        $relation->create($this->validated($request, $client, $section));

        return $this->backToClient($client, $section, 'Registrazione aggiunta.');
    }

    public function edit(Client $client, string $section, int $record)
    {
        $this->authorize('updateRegistry', $client);
        abort_if($section === 'consents', 405);

        return $this->form($client, $section, $this->relation($client, $section)->findOrFail($record));
    }

    public function update(Request $request, Client $client, string $section, int $record)
    {
        $this->authorize('updateRegistry', $client);
        abort_if($section === 'consents', 405);
        $entry = $this->relation($client, $section)->lockForUpdate()->findOrFail($record);
        $data = $this->validated($request, $client, $section);
        unset($data['created_by']);
        $entry->update($data);

        return $this->backToClient($client, $section, 'Registrazione aggiornata.');
    }

    public function destroy(Client $client, string $section, int $record)
    {
        $this->authorize('system.admin');
        $this->authorize('updateRegistry', $client);
        abort_if($section === 'consents', 405);
        $entry = $this->relation($client, $section)->lockForUpdate()->findOrFail($record);
        if ($section === 'assets' && $entry->policies()->exists()) {
            throw ValidationException::withMessages(['asset' => 'Il bene è collegato a una polizza e deve essere conservato.']);
        }
        $entry->delete();

        return $this->backToClient($client, $section, 'Registrazione eliminata.');
    }

    private function validated(Request $request, Client $client, string $section): array
    {
        $rules = [];
        foreach (ClientRegistryFields::for($section) as $name => [$label, $type, $required, $limit]) {
            $rules[$name] = [$required ? 'required' : 'nullable'];
            if ($type === 'select') {
                $rules[$name][] = Rule::in(array_keys($limit));
            } elseif ($type === 'datetime-local') {
                array_push($rules[$name], 'date_format:Y-m-d\TH:i', 'before_or_equal:now');
            } else {
                array_push($rules[$name], 'string', 'max:'.$limit);
                if ($type === 'email') {
                    $rules[$name][] = 'email';
                }
            }
        }
        if ($section === 'assets') {
            $rules['address'][] = 'required_if:asset_type,property';
        }
        if ($section === 'consents') {
            $rules['purpose'][] = 'required_if:kind,other';
            if ($client->client_type === 'company') {
                $rules['signer_role'][] = 'required';
            }
            $rules['insurance_policy_id'] = ['nullable', 'required_if:scope,policy', 'prohibited_if:scope,all', 'integer', Rule::exists('insurance_policies', 'id')->where('client_id', $client->id)];
            $rules['attachment_id'] = ['nullable', 'required_without:evidence_reference', 'integer', Rule::exists('attachments', 'id')->where('attachable_type', $client->getMorphClass())->where('attachable_id', $client->id)->where('disk', 'attachments')->whereNotNull('document_category')->whereNull('client_material_category')];
            $rules['evidence_reference'][] = 'required_without:attachment_id';
        }
        $data = $request->validate($rules, [
            'attachment_id.exists' => 'Seleziona un documento privato di questo cliente.',
            'insurance_policy_id.exists' => 'Seleziona una polizza di questo cliente.',
            'attachment_id.required_without' => 'Collega il documento oppure indica dove è conservata la prova.',
            'evidence_reference.required_without' => 'Collega il documento oppure indica dove è conservata la prova.',
            'event_at.before_or_equal' => 'La registrazione deve riguardare una dichiarazione o un contatto già avvenuti.',
            'signer_role.required' => 'Per un’azienda indica in quale qualità agisce il dichiarante.',
        ]);
        if ($section === 'consents') {
            if ($data['kind'] === 'electronic_delivery') {
                $data['purpose'] = ClientConsent::KINDS['electronic_delivery'];
            }
            $data['created_by'] = $request->user()->id;
            // Lock the evidence before saving so a concurrent deletion cannot remove it.
            if (! empty($data['attachment_id'])) {
                $client->attachments()->whereKey($data['attachment_id'])->lockForUpdate()->firstOrFail();
            }
        }
        if ($section === 'interactions') {
            $data['created_by'] = $data['updated_by'] = $request->user()->id;
        }
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        return $data;
    }

    private function form(Client $client, string $section, Model $record)
    {
        $fields = ClientRegistryFields::for($section);
        $documents = $section === 'consents' ? $client->attachments()->where('disk', 'attachments')->whereNotNull('document_category')->whereNull('client_material_category')->latest()->get() : collect();
        $policies = $section === 'consents' ? $client->policies()->orderBy('category')->orderBy('number')->get() : collect();

        return view('clients.records.form', compact('client', 'section', 'record', 'fields', 'documents', 'policies'));
    }

    private function backToClient(Client $client, string $section, string $message)
    {
        return redirect()->route('clients.show', [$client, 'tab' => ClientRegistryFields::TABS[$section]])->with('success', $message);
    }
}
