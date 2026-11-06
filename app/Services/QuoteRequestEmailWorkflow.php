<?php

namespace App\Services;

use App\Models\{InsuranceQuoteCase, InsuranceQuoteEmail, QuoteRecipientGroup, SupplierCompany, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuoteRequestEmailWorkflow
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(private AgencyMailConfiguration $configuration, private QuoteRequestEmailTransport $transport) {}
    private function fail(string $message): never { throw ValidationException::withMessages(['email' => $message]); }
    private function line(string $value): string { return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value)); }

    private function key(InsuranceQuoteCase $case, int $companyId, ?int $agencyId): string
    {
        /** Conserva le chiavi degli invii storici diretti per impedirne la ripetizione dopo l'aggiornamento. */
        return hash('sha256', $case->id.'|'.$companyId.($agencyId ? '|agency:'.$agencyId : ''));
    }

    public function remainingPriorities(InsuranceQuoteCase $case): Collection
    {
        return collect();
    }

    public function editableDraft(InsuranceQuoteCase $case, User $user, string $id, bool $lock = false): Collection
    {
        abort_unless($user->status === 'active' && $user->can('updateRegistry', $case->client), 403);
        $source = $case->emails()->whereKey($id)->firstOrFail();
        $query = $case->emails()->when($source->review_batch_id, fn($query, $batch) => $query->where('review_batch_id', $batch), fn($query) => $query->whereKey($source->id));
        $messages = $query->when($lock, fn($query) => $query->lockForUpdate())->orderBy('created_at')->orderBy('id')->get();
        abort_unless($messages->every(fn($message) => (int) $message->user_id === (int) $user->id), 403);
        if ($case->status !== 'open' || $messages->contains(fn($message) => $message->status !== 'review' || $message->delivery_key !== null)) {
            $this->fail('Questa anteprima è già stata aggiornata o confermata. Apri la richiesta più recente nello storico.');
        }
        return $messages;
    }

    public function unavailableKeys(InsuranceQuoteCase $case): Collection
    {
        /** Recipient keys are strings, including when an Eloquent result starts empty. */
        $claimed = $case->emails()->whereNotNull('delivery_key')->get(['supplier_company_id', 'supplier_agency_id'])->toBase()
            ->map(fn ($message) => QuoteRecipients::key($message->supplier_company_id, $message->supplier_agency_id));
        $answered = $case->quotes()->where('status', '!=', 'requested')->get()
            ->map(fn ($quote) => QuoteRecipients::key($quote->supplier_company_id, str_starts_with($quote->recipient_key, 'agency:') ? (int) substr($quote->recipient_key, 7) : null));
        return $claimed->merge($answered)->unique()->values();
    }

    private function eligible(InsuranceQuoteCase $case, int $companyId, ?int $agencyId, User $user): array
    {
        abort_unless($user->status === 'active' && $user->can('updateRegistry', $case->client), 403);
        if ($case->status !== 'open') $this->fail('La pratica ha già una proposta approvata o una polizza.');
        try {
            $target = app(QuoteRecipients::class)->resolve([QuoteRecipients::key($companyId, $agencyId)], email: true, case: $case)->first();
        } catch (ValidationException $exception) {
            $this->fail(collect($exception->errors())->flatten()->first());
        }
        if ($case->quotes()->where('supplier_company_id', $companyId)->where('recipient_key', QuoteRecipients::quoteKey($agencyId))->where('status', '!=', 'requested')->exists()) {
            $this->fail('Questo destinatario ha già risposto alla richiesta. Conserva la proposta nella pratica.');
        }
        return $target;
    }

    private function context(InsuranceQuoteCase $case, array $target): array
    {
        $caseValues = $case->getAttributes(); unset($caseValues['updated_at']); ksort($caseValues);
        $clientValues = $case->client->getAttributes(); unset($clientValues['updated_at']); ksort($clientValues);
        return [$caseValues, $clientValues, $case->branch->name, $case->subbranch?->name, $target['company']->only(['id', 'name', 'email', 'status']), $target['agency']?->only(['id', 'name', 'email', 'status'])];
    }

    private function fingerprint(array $payload, InsuranceQuoteCase $case, array $target): string
    {
        return hash('sha256', json_encode([$payload, $this->context($case, $target)], JSON_THROW_ON_ERROR));
    }

    private function recipient(array $target): array
    {
        return ['address' => $target['email'], 'name' => $this->line($target['name'])];
    }

    private function audit(InsuranceQuoteEmail $message, string $action, string $description): void
    {
        $message->quoteCase->client->auditLogs()->create(['user_id' => auth()->id(), 'action' => $action, 'description' => $description.' Pratica #'.$message->insurance_quote_case_id.'; invio '.$message->id.'.', 'created_at' => now()]);
    }

    public function prepare(InsuranceQuoteCase $case, User $user, array $data, array $files): InsuranceQuoteEmail
    {
        $registry = app(QuoteRequestDocuments::class);
        $files = $registry->normalize($files);
        $stored = [];
        try {
            return DB::transaction(function () use ($case, $user, $data, $files, $registry, &$stored) {
                $case = InsuranceQuoteCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
                $agencyId = !empty($data['supplier_agency_id']) ? (int) $data['supplier_agency_id'] : null;
                $target = $this->eligible($case, (int) $data['supplier_company_id'], $agencyId, $user);
                $draft = !empty($data['draft_id']) ? $this->editableDraft($case, $user, $data['draft_id'], true)->first() : null;
                $selected = $registry->selected($case, $data['document_ids'] ?? [], $draft);
                $registry->check($files, $selected);
                if (InsuranceQuoteEmail::where('delivery_key', $this->key($case, $target['company_id'], $agencyId))->exists()) $this->fail('La richiesta è già inviata o ha un invio da verificare. Controlla lo storico.');
                $message = $case->emails()->create(['supplier_company_id' => $target['company_id'], 'supplier_agency_id' => $agencyId, 'review_batch_id' => $data['review_batch_id'] ?? null, 'user_id' => $user->id, 'status' => 'review', 'payload' => [], 'fingerprint' => str_repeat('0', 64), 'review_expires_at' => now()->addMinutes(30)]);
                $registry->store($case->client, $files, ['insurance_quote_email_id' => $message->id, 'vehicle_id' => $case->vehicle_id], $stored);
                foreach ($selected as $document) $stored[] = $registry->copyForEmail($document, $message->id);
                $attachments = [];
                foreach ($stored as $document) {
                    $bytes = Storage::disk($document->disk)->get($document->path);
                    $name = $this->line(str_replace(['/', '\\'], '_', $document->original_name));
                    $attachments[] = ['id' => $document->id, 'name' => $name, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'category' => $document->document_category, 'validity' => $document->document_category === 'identity' ? $document->validity_label : null, 'expired' => (bool) $document->expires_on?->lt(today())];
                }
                $body = $agencyId ? 'Compagnia richiesta: '.$this->line($target['company']->name)."\n\n".$data['body'] : $data['body'];
                $payload = ['from' => $this->configuration->sender(), 'recipient' => $this->recipient($target), 'target' => app(QuoteRecipients::class)->snapshot($target), 'subject' => $data['subject'], 'body' => $body, 'request_body' => $data['body'], 'attachments' => $attachments];
                if (isset($data['recipient_group'])) $payload['recipient_group'] = $data['recipient_group'];
                $message->update(['payload' => $payload, 'fingerprint' => $this->fingerprint($payload, $case, $target)]);
                $this->audit($message, 'quote_email_review', 'Preparata richiesta a '.$target['name'].' per '.$target['company']->name.'.');
                return $message;
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $document) Storage::disk($document->disk)->delete($document->path);
            throw $exception;
        }
    }

    public function prepareBatch(InsuranceQuoteCase $case, User $user, array $data, array $files): Collection
    {
        $stored = [];
        try {
            return DB::transaction(function () use ($case, $user, $data, $files, &$stored) {
                $case = InsuranceQuoteCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->can('updateRegistry', $case->client), 403);
                $previous = !empty($data['draft_id']) ? $this->editableDraft($case, $user, $data['draft_id'], true) : collect();
                $targets = app(QuoteRecipients::class)->resolve($data['recipient_keys'], email: true, case: $case);
                if (!empty($data['recipient_group_choice'])) {
                    $group = QuoteRecipientGroup::forRequest($case->insurance_branch_id, $case->insurance_subbranch_id, $case->merit_class_cu)
                        ->with('companies')->find($data['recipient_group_choice']);
                    if (!$group || $case->purpose === 'addition') throw ValidationException::withMessages(['recipient_group_choice'=>'Il gruppo non è più compatibile con la richiesta. Controlla le spunte e scegli un altro gruppo o la selezione manuale.']);
                    /** Il gruppo serve a riaprire la bozza. Solo i destinatari effettivamente spuntati generano email. */
                    $data['recipient_group'] = ['id'=>$group->id, 'name'=>$group->name, 'recipient_keys'=>$group->recipientKeys()];
                }
                $batch = (string) Str::uuid();
                $messages = collect();
                foreach ($targets as $target) {
                    $message = $this->prepare($case, $user, array_replace($data, ['supplier_company_id' => $target['company_id'], 'supplier_agency_id' => $target['agency_id'], 'review_batch_id' => $batch]), $files);
                    foreach ($message->documents as $document) $stored[] = $document;
                    $messages->push($message);
                }
                /** Le vecchie anteprime restano nello storico ma non possono inviare una versione superata. */
                foreach ($previous as $message) {
                    $message->update(['status'=>'superseded']);
                    $this->audit($message, 'quote_email_review', 'Anteprima sostituita dopo la modifica della richiesta.');
                }
                return $messages;
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $document) Storage::disk($document->disk)->delete($document->path);
            throw $exception;
        }
    }

    private function checkedFiles(InsuranceQuoteEmail $message): array
    {
        $documents = $message->documents()->orderBy('id')->get()->keyBy('id');
        $expected = $message->payload['attachments'];
        if ($documents->count() !== count($expected)) $this->fail('Gli allegati sono cambiati. Prepara una nuova richiesta.');
        $files = []; $total = 0;
        foreach ($expected as $file) {
            $document = $documents->get($file['id']);
            if (!$document || !Storage::disk($document->disk)->exists($document->path)) $this->fail('Un allegato non è più disponibile. Prepara una nuova richiesta.');
            if (Storage::disk($document->disk)->size($document->path) !== $file['size']) $this->fail('Un allegato è cambiato dopo il controllo.');
            $total += $file['size'];
            if ($total > self::MAX_BYTES) $this->fail('Allegati oltre il limite consentito.');
            $bytes = Storage::disk($document->disk)->get($document->path);
            if (!hash_equals($file['sha256'], hash('sha256', $bytes))) $this->fail('Un allegato è cambiato dopo il controllo.');
            $files[] = ['name' => $file['name'], 'bytes' => $bytes, 'mime' => $document->mime_type];
        }
        return $files;
    }

    private function claim(InsuranceQuoteEmail $message, User $user): ?InsuranceQuoteEmail
    {
        return DB::transaction(function () use ($message, $user) {
            $case = InsuranceQuoteCase::whereKey($message->insurance_quote_case_id)->lockForUpdate()->firstOrFail();
            $locked = InsuranceQuoteEmail::whereKey($message->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === $user->id && $user->status === 'active' && $user->can('updateRegistry', $case->client), 403);
            if ($locked->status === 'sent') return null;
            if ($locked->status !== 'review') $this->fail('Invio già confermato. Controlla l’esito nello storico prima di proseguire.');
            if ($locked->review_expires_at->isPast()) $this->fail('Il controllo è scaduto. Prepara e controlla una nuova richiesta.');
            $target = $this->eligible($case, $locked->supplier_company_id, $locked->supplier_agency_id, $user);
            $payload = array_replace($locked->payload, ['from' => $this->configuration->sender(), 'recipient' => $this->recipient($target)]);
            if (!hash_equals($locked->fingerprint, $this->fingerprint($payload, $case, $target))) $this->fail('Pratica, cliente, destinatario o mittente sono cambiati. Prepara e controlla una nuova richiesta.');
            $key = $this->key($case, $target['company_id'], $target['agency_id']);
            if (InsuranceQuoteEmail::where('delivery_key', $key)->exists()) $this->fail('Questa richiesta è già inviata o in corso. Controlla lo storico.');
            $this->checkedFiles($locked);
            $locked->update(['delivery_key' => $key, 'status' => 'sending', 'reviewed_at' => now(), 'sending_at' => now()]);
            $this->audit($locked, 'quote_email_sending', 'Confermato invio richiesta a '.$target['name'].' per '.$target['company']->name.'.');
            return $locked;
        });
    }

    private function deliver(InsuranceQuoteEmail $message, array $configuration): void
    {
        try {
            $files = $this->checkedFiles($message);
            $messageId = $this->transport->send($message->payload, $configuration, $files);
            $this->recordSent($message, $messageId);
        } catch (\Throwable $exception) {
            /** Un'interruzione non prova il mancato invio: la chiave resta occupata fino alla verifica dell'admin. */
            $message->refresh()->update(['status' => 'uncertain', 'error_code' => 'delivery_unconfirmed']);
            $this->audit($message, 'quote_email_uncertain', 'Esito non confermato; ripetizione automatica bloccata.');
        }
    }

    public function send(InsuranceQuoteEmail $message, User $user): InsuranceQuoteEmail
    {
        $configuration = $this->configuration->transport();
        $delivery = $this->claim($message, $user);
        if (!$delivery) return $message->fresh();
        $previousAbort = ignore_user_abort(true);
        try { $this->deliver($delivery, $configuration); }
        finally { ignore_user_abort((bool) $previousAbort); }
        return $message->fresh();
    }

    public function sendBatch(Collection $messages, User $user): Collection
    {
        abort_unless($messages->isNotEmpty() && $messages->count() <= QuoteRecipients::MAX_RECIPIENTS && $messages->pluck('insurance_quote_case_id')->unique()->count() === 1, 422);
        $configuration = $this->configuration->transport();
        /** Verifica tutti i destinatari prima del primo invio; le prenotazioni diventano effettive insieme. */
        $deliveries = DB::transaction(fn () => $messages->map(fn ($message) => $this->claim($message, $user))->filter());
        $previousAbort = ignore_user_abort(true);
        try {
            foreach ($deliveries as $message) $this->deliver($message, $configuration);
        } finally { ignore_user_abort((bool) $previousAbort); }
        return $messages->map(fn ($message) => $message->fresh());
    }

    private function recordSent(InsuranceQuoteEmail $message, ?string $messageId, bool $manual = false): void
    {
        DB::transaction(function () use ($message, $messageId, $manual) {
            $case = InsuranceQuoteCase::whereKey($message->insurance_quote_case_id)->lockForUpdate()->firstOrFail();
            $locked = InsuranceQuoteEmail::whereKey($message->id)->lockForUpdate()->firstOrFail();
            $quote = $case->quotes()->firstOrCreate(['supplier_company_id' => $locked->supplier_company_id, 'recipient_key' => QuoteRecipients::quoteKey($locked->supplier_agency_id)], ['supplier_agency_id' => $locked->supplier_agency_id, 'request_recipient' => $locked->payload['target'] ?? null, 'status' => 'requested', 'requested_at' => $locked->sending_at, 'priority' => (bool) $locked->company->quote_priority]);
            $locked->documents()->update(['insurance_quote_id' => $quote->id]);
            $locked->update(['status' => 'sent', 'sent_at' => $locked->sending_at, 'message_id' => $messageId ? mb_substr($messageId, 0, 255) : null, 'error_code' => $manual ? 'manually_confirmed' : null]);
            $this->audit($locked, 'quote_email_sent', $manual ? 'Invio confermato dopo verifica dell’admin.' : 'Richiesta accettata dal server di posta.');
        });
    }

    public function resolve(InsuranceQuoteEmail $message, User $user, bool $sent, string $note): void
    {
        abort_unless($user->canManageSystem() && $user->status === 'active', 403);
        DB::transaction(function () use ($message, $sent, $note) {
            InsuranceQuoteCase::whereKey($message->insurance_quote_case_id)->lockForUpdate()->firstOrFail();
            $locked = InsuranceQuoteEmail::whereKey($message->id)->lockForUpdate()->firstOrFail();
            if (!$locked->canResolve()) $this->fail('Questo invio non richiede una verifica manuale dell’esito.');
            if ($sent) $this->recordSent($locked, null, true);
            else $locked->update(['status' => 'failed', 'delivery_key' => null, 'error_code' => 'manually_not_sent']);
            $this->audit($locked, 'quote_email_resolved', ($sent ? 'Invio verificato' : 'Mancato invio verificato').': '.$note);
        });
    }
}
