<?php

namespace App\Domain\Core\Actions;

use App\Models\Client;
use App\Models\ClientMerge;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeClientsAction
{
    public const FIELDS = [
        'name' => 'Contraente', 'title' => 'Titolo', 'company_name' => 'Denominazione aziendale',
        'tax_code' => 'Codice fiscale', 'vat_number' => 'Partita IVA', 'email' => 'Email', 'phone' => 'Telefono',
        'mobile' => 'Cellulare', 'fax' => 'FAX', 'birth_date' => 'Data di nascita', 'birth_place' => 'Luogo di nascita',
        'address' => 'Indirizzo', 'city' => 'Città', 'postal_code' => 'CAP', 'province' => 'Provincia', 'country' => 'Paese', 'country_code' => 'Codice Stato',
        'reference_person' => 'Referente', 'reference_email' => 'Email referente', 'reference_phone' => 'Telefono referente', 'reference_role' => 'Ruolo referente',
        'target' => 'Target', 'office' => 'Ufficio', 'consultant' => 'Consulente', 'collaborator' => 'Collaboratore', 'operator' => 'Operatore',
        'salient_notes' => 'Notizie salienti', 'notes' => 'Note interne', 'commercial_notes' => 'Note commerciali', 'activity_description' => 'Attività',
        'preferred_contact_channel' => 'Canale preferito', 'preferred_contact_time' => 'Fascia oraria', 'contact_preferences' => 'Indicazioni di contatto',
        'pec' => 'PEC', 'billing_email' => 'Email amministrativa', 'sdi_code' => 'Codice destinatario', 'logo_path' => 'Logo', 'status' => 'Stato',
    ];

    public const RELATIONS = ['vehicles', 'assets', 'policies', 'attachments', 'documentRequests', 'contacts', 'interactions', 'consents','quoteCases','formPackages','claims'];

    public function preview(Client $target, Client $source): array
    {
        $snapshot = ['target' => $this->snapshot($target), 'source' => $this->snapshot($source)];
        $conflicts = $fill = [];
        foreach (self::FIELDS as $field => $label) {
            $a = $target->getRawOriginal($field);
            $b = $source->getRawOriginal($field);
            if (filled($a) && filled($b) && (string) $a !== (string) $b) {
                $conflicts[$field] = ['label' => $label, 'target' => $a, 'source' => $b];
            } elseif (blank($a) && filled($b)) {
                $fill[$field] = ['label' => $label, 'source' => $b];
            }
        }

        return ['snapshot' => $snapshot, 'token' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'conflicts' => $conflicts, 'fill' => $fill, 'blockers' => $this->blockers($target, $source)];
    }

    public function execute(Client $target, Client $source, string $token, array $choices): Client
    {
        abort_unless(auth()->user()?->canManageSystem(), 403);

        return DB::transaction(function () use ($target, $source, $token, $choices) {
            $locked = Client::whereIn('id', [$target->id, $source->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $target = $locked->get($target->id);
            $source = $locked->get($source->id);
            abort_unless($target && $source, 404);
            DB::table('client_user')->whereIn('client_id', [$target->id, $source->id])->lockForUpdate()->get();
            $preview = $this->preview($target, $source);
            if (! hash_equals($preview['token'], $token)) {
                $this->fail('I dati sono cambiati dopo l’anteprima. Riapri il confronto prima di confermare.');
            }
            if ($preview['blockers']) {
                $this->fail(implode(' ', $preview['blockers']));
            }
            $changes = [];
            foreach ($preview['fill'] as $field => $entry) {
                $changes[$field] = $entry['source'];
            }
            foreach ($preview['conflicts'] as $field => $entry) {
                if (! in_array($choices[$field] ?? null, ['source', 'target'], true)) {
                    $this->fail('Scegli il valore da conservare per ogni campo diverso.');
                }
                $changes[$field] = $entry[$choices[$field]];
            }
            foreach (['email', 'tax_code', 'vat_number'] as $field) {
                $value = $changes[$field] ?? $target->$field;
                if (filled($value) && Client::current()->whereNotIn('id', [$target->id, $source->id])->where($field, $value)->exists()) {
                    $this->fail('Un identificativo scelto è già usato da una terza anagrafica. Verificalo prima dell’unificazione.');
                }
            }
            // Keep the original records and snapshots. Only ownership of their related data changes.
            foreach (self::RELATIONS as $relation) {
                $source->$relation()->update([$relation === 'attachments' ? 'attachable_id' : 'client_id' => $target->id]);
            }
            $this->moveNetwork($target, $source);
            $source->mergedSources()->update(['merged_into_id' => $target->id]);
            $source->forceFill(['merged_into_id' => $target->id, 'merged_at' => now(), 'status' => 'inactive'])->save();
            $target->fill($changes)->save();
            ClientMerge::create(['source_id' => $source->id, 'target_id' => $target->id, 'created_by' => auth()->id(), 'snapshot' => $preview['snapshot'], 'choices' => $choices]);
            foreach ([$source, $target] as $client) {
                $client->auditLogs()->create(['user_id' => auth()->id(), 'action' => 'updated', 'description' => 'Anagrafiche unificate: #'.$source->id.' in #'.$target->id.'. Dati originali conservati nello storico.', 'old_values' => ['source_id' => $source->id], 'new_values' => ['target_id' => $target->id, 'choices' => $choices], 'created_at' => now()]);
            }

            return $target;
        }, 3);
    }

    private function snapshot(Client $client): array
    {
        $data = ['client' => $client->getAttributes(), 'authorized_users' => $client->authorizedUsers()->orderBy('users.id')->pluck('users.id')->all()];
        foreach (self::RELATIONS as $relation) {
            $data[$relation] = $client->$relation()->orderBy('id')->get()->map(fn ($row) => $row->getAttributes())->all();
        }
        $data['relationships'] = $client->relationships()->orderBy('id')->get()->toArray();
        $data['households'] = $client->households()->with(['members' => fn ($q) => $q->orderBy('clients.id')])->orderBy('client_households.id')->get()->toArray();
        $data['aliases'] = $client->mergedSources()->orderBy('id')->pluck('id')->all();

        return $data;
    }

    private function blockers(Client $target, Client $source): array
    {
        $errors = [];
        if ($target->is($source) || $target->merged_into_id || $source->merged_into_id) {
            $errors[] = 'Seleziona due anagrafiche distinte non ancora unificate.';
        }
        if (! $target->client_type || $target->client_type !== $source->client_type) {
            $errors[] = 'Prima classifica entrambe le schede nello stesso tipo: persona oppure azienda.';
        }
        if ($target->commercial_user_id !== $source->commercial_user_id || $target->authorizedUsers()->orderBy('users.id')->pluck('users.id')->all() !== $source->authorizedUsers()->orderBy('users.id')->pluck('users.id')->all()) {
            $errors[] = 'Le assegnazioni o gli utenti autorizzati sono diversi. Uniformali prima dell’unificazione.';
        }
        if ($target->vehicles()->whereIn('plate', $source->vehicles()->select('plate'))->exists()) {
            $errors[] = 'Una targa è presente in entrambe le schede. Verifica prima il veicolo e i suoi collegamenti.';
        }
        foreach ($source->policies()->get() as $policy) {
            if ($target->policies()->where('company', $policy->company)->where('number', $policy->number)->exists()) {
                $errors[] = 'Una polizza con la stessa compagnia e numero è già presente: '.$policy->number.'.';
            }
        }
        foreach ($source->relationships()->get() as $relation) {
            $other = $relation->other($source);
            if ($other->is($target)) {
                continue;
            }
            $existing = $target->relationships()->get()->first(fn ($row) => $row->other($target)->is($other));
            if ($existing && ($existing->relationship_type !== $relation->relationship_type || $existing->roleFor($target) !== $relation->roleFor($source) || $existing->roleFor($other) !== $relation->roleFor($other) || $existing->notes !== $relation->notes)) {
                $errors[] = 'Esistono relazioni discordanti con '.$other->name.'. Verificale prima di unificare.';
            }
        }
        $targetHouseholds = $target->households()->get()->keyBy('id');
        foreach ($source->households()->get() as $group) {
            $existing = $targetHouseholds->get($group->id);
            if ($existing && filled($existing->pivot->role) && filled($group->pivot->role) && $existing->pivot->role !== $group->pivot->role) {
                $errors[] = 'Il ruolo nel nucleo '.$group->name.' è diverso. Verificalo prima di unificare.';
            }
        }

        return array_unique($errors);
    }

    private function moveNetwork(Client $target, Client $source): void
    {
        foreach ($source->relationships()->get() as $relation) {
            $other = $relation->other($source);
            $alreadyLinked = $target->relationships()->get()->contains(fn ($row) => $row->other($target)->is($other));
            if ($other->is($target) || $alreadyLinked) {
                $relation->delete();

                continue;
            }
            $ownRole = $relation->roleFor($source);
            $otherRole = $relation->roleFor($other);
            $first = min($target->id, $other->id);
            $relation->update(['client_id' => $first, 'related_client_id' => max($target->id, $other->id), 'client_role' => $first === $target->id ? $ownRole : $otherRole, 'related_role' => $first === $target->id ? $otherRole : $ownRole]);
        }
        foreach ($source->households()->get() as $group) {
            $existing = $group->members()->where('clients.id', $target->id)->first();
            if (! $existing) {
                $group->members()->attach($target->id, ['role' => $group->pivot->role]);
            } elseif (blank($existing->pivot->role) && filled($group->pivot->role)) {
                $group->members()->updateExistingPivot($target->id, ['role' => $group->pivot->role]);
            }
            $group->members()->detach($source->id);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['merge' => $message]);
    }
}
