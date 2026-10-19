<?php

namespace App\Services;

use App\Models\{InsuranceQuoteCase, InsuranceQuoteEmail, SupplierCompany, User};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class QuoteRequestEmailWorkflow
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(private AgencyMailConfiguration $configuration, private QuoteRequestEmailTransport $transport) {}
    private function fail(string $message): never { throw ValidationException::withMessages(['email'=>$message]); }
    private function line(string $value): string { return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value)); }
    private function key(InsuranceQuoteCase $case, SupplierCompany $company): string { return hash('sha256', $case->id.'|'.$company->id); }

    public function remainingPriorities(InsuranceQuoteCase $case)
    {
        /** A documented reply also proves prior contact without forcing a duplicate request outside this system. */
        $contacted = $case->emails()->where('status', 'sent')->pluck('supplier_company_id')
            ->merge($case->quotes()->whereNotNull('received_at')->whereHas('documents', fn ($q) => $q->where('document_category', 'quote'))->pluck('supplier_company_id'));
        return SupplierCompany::where('status', 'active')->where('quote_priority', true)
            ->whereNotIn('id', $contacted)->orderBy('name')->get();
    }

    private function eligible(InsuranceQuoteCase $case, SupplierCompany $company, User $user): void
    {
        abort_unless($user->status === 'active' && $user->can('updateRegistry', $case->client), 403);
        if ($case->status !== 'open') $this->fail('La pratica ha già una proposta approvata o una polizza.');
        if ($company->status !== 'active' || !filter_var($company->email, FILTER_VALIDATE_EMAIL)) $this->fail('Completa l’indirizzo email della compagnia attiva nella sua anagrafica.');
        if (!$company->quote_priority && $this->remainingPriorities($case)->isNotEmpty()) $this->fail('Contatta prima le compagnie prioritarie: occorre un invio confermato o il preventivo ricevuto con documento allegato.');
        if ($case->quotes()->where('supplier_company_id', $company->id)->where('status', '!=', 'requested')->exists()) $this->fail('Questa compagnia ha già risposto alla richiesta. Conserva la proposta nella pratica.');
    }

    private function context(InsuranceQuoteCase $case, SupplierCompany $company): array
    {
        $caseValues = $case->getAttributes(); unset($caseValues['updated_at']); ksort($caseValues);
        $clientValues = $case->client->getAttributes(); unset($clientValues['updated_at']); ksort($clientValues);
        return [$caseValues, $clientValues, $case->branch->name, $case->subbranch?->name, $company->only(['id','name','email','status','quote_priority']), SupplierCompany::where('status','active')->where('quote_priority',true)->orderBy('id')->pluck('id')->all()];
    }

    private function fingerprint(array $payload, InsuranceQuoteCase $case, SupplierCompany $company): string
    {
        return hash('sha256', json_encode([$payload, $this->context($case, $company)], JSON_THROW_ON_ERROR));
    }

    private function audit(InsuranceQuoteEmail $message, string $action, string $description): void
    {
        $message->quoteCase->client->auditLogs()->create(['user_id'=>auth()->id(), 'action'=>$action, 'description'=>$description.' Pratica #'.$message->insurance_quote_case_id.'; invio '.$message->id.'.', 'created_at'=>now()]);
    }

    public function prepare(InsuranceQuoteCase $case, User $user, array $data, array $files): InsuranceQuoteEmail
    {
        $registry=app(QuoteRequestDocuments::class);
        $files=$registry->normalize($files);
        $stored = [];
        try {
            return DB::transaction(function () use ($case, $user, $data, $files, $registry, &$stored) {
                $case = InsuranceQuoteCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
                $company = SupplierCompany::findOrFail($data['supplier_company_id']);
                $this->eligible($case, $company, $user);
                $selected=$registry->selected($case,$data['document_ids']??[]);
                $registry->check($files,$selected);
                if (InsuranceQuoteEmail::where('delivery_key', $this->key($case, $company))->exists()) $this->fail('La richiesta è già inviata o ha un invio da verificare. Controlla lo storico.');
                $message = $case->emails()->create(['supplier_company_id'=>$company->id, 'user_id'=>$user->id, 'status'=>'review', 'payload'=>[], 'fingerprint'=>str_repeat('0',64), 'review_expires_at'=>now()->addMinutes(30)]);
                $registry->store($case->client,$files,['insurance_quote_email_id'=>$message->id],$stored);
                foreach ($selected as $document) $stored[]=$registry->copyForEmail($document,$message->id);
                $attachments = [];
                foreach ($stored as $document) {
                    $bytes = Storage::disk($document->disk)->get($document->path);
                    $name = $this->line(str_replace(['/', '\\'], '_', $document->original_name));
                    $attachments[] = ['id'=>$document->id, 'name'=>$name, 'size'=>strlen($bytes), 'sha256'=>hash('sha256',$bytes), 'category'=>$document->document_category, 'validity'=>$document->document_category==='identity' ? $document->validity_label : null, 'expired'=>(bool)$document->expires_on?->lt(today())];
                }
                $payload = ['from'=>$this->configuration->sender(), 'recipient'=>['address'=>$company->email, 'name'=>$this->line($company->name)], 'subject'=>$data['subject'], 'body'=>$data['body'], 'attachments'=>$attachments];
                $message->update(['payload'=>$payload, 'fingerprint'=>$this->fingerprint($payload,$case,$company)]);
                $this->audit($message, 'quote_email_review', 'Preparata richiesta di preventivo a '.$company->name.' per il controllo dell’operatore.');
                return $message;
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
            if (!hash_equals($file['sha256'], hash('sha256',$bytes))) $this->fail('Un allegato è cambiato dopo il controllo.');
            $files[] = ['name'=>$file['name'], 'bytes'=>$bytes];
        }
        return $files;
    }

    public function send(InsuranceQuoteEmail $message, User $user): InsuranceQuoteEmail
    {
        $configuration = $this->configuration->transport();
        $delivery = DB::transaction(function () use ($message, $user) {
            /** Lock the practice first, as approvals do; SMTP runs only after committing the unique claim. */
            $case = InsuranceQuoteCase::whereKey($message->insurance_quote_case_id)->lockForUpdate()->firstOrFail();
            $locked = InsuranceQuoteEmail::whereKey($message->id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$locked->user_id === $user->id && $user->status === 'active' && $user->can('updateRegistry',$case->client),403);
            if ($locked->status === 'sent') return null;
            if ($locked->status !== 'review') $this->fail('Invio già confermato. Controlla l’esito nello storico prima di proseguire.');
            if ($locked->review_expires_at->isPast()) $this->fail('Il controllo è scaduto. Prepara e controlla una nuova richiesta.');
            $company = SupplierCompany::whereKey($locked->supplier_company_id)->lockForUpdate()->firstOrFail();
            $this->eligible($case,$company,$user);
            $payload = array_replace($locked->payload, ['from'=>$this->configuration->sender(), 'recipient'=>['address'=>$company->email,'name'=>$this->line($company->name)]]);
            if (!hash_equals($locked->fingerprint,$this->fingerprint($payload,$case,$company))) $this->fail('Pratica, cliente, compagnia o mittente sono cambiati. Prepara e controlla una nuova richiesta.');
            $key = $this->key($case,$company);
            if (InsuranceQuoteEmail::where('delivery_key',$key)->exists()) $this->fail('Questa richiesta è già inviata o in corso. Controlla lo storico.');
            $files = $this->checkedFiles($locked);
            $locked->update(['delivery_key'=>$key,'status'=>'sending','reviewed_at'=>now(),'sending_at'=>now()]);
            $this->audit($locked,'quote_email_sending','Confermato invio richiesta a '.$company->name.'.');
            return ['message'=>$locked,'files'=>$files];
        });
        if (!$delivery) return $message->fresh();
        $previousAbort = ignore_user_abort(true);
        try {
            try {
                $messageId = $this->transport->send($delivery['message']->payload,$configuration,$delivery['files']);
                $this->recordSent($delivery['message'], $messageId);
            } catch (\Throwable $exception) {
                /** An interrupted connection or final database write cannot prove that SMTP rejected the email. */
                $message->refresh()->update(['status'=>'uncertain','error_code'=>'delivery_unconfirmed']);
                $this->audit($message,'quote_email_uncertain','Esito non confermato; ripetizione automatica bloccata.');
            }
        } finally { ignore_user_abort((bool)$previousAbort); }
        return $message->fresh();
    }

    private function recordSent(InsuranceQuoteEmail $message, ?string $messageId, bool $manual = false): void
    {
        DB::transaction(function () use ($message,$messageId,$manual) {
            $case = InsuranceQuoteCase::whereKey($message->insurance_quote_case_id)->lockForUpdate()->firstOrFail();
            $locked = InsuranceQuoteEmail::whereKey($message->id)->lockForUpdate()->firstOrFail();
            $quote = $case->quotes()->firstOrCreate(['supplier_company_id'=>$locked->supplier_company_id], ['status'=>'requested','requested_at'=>$locked->sending_at,'priority'=>(bool)$locked->company->quote_priority]);
            $locked->documents()->update(['insurance_quote_id'=>$quote->id]);
            $locked->update(['status'=>'sent','sent_at'=>$locked->sending_at,'message_id'=>$messageId ? mb_substr($messageId,0,255) : null,'error_code'=>$manual ? 'manually_confirmed' : null]);
            $this->audit($locked,'quote_email_sent',$manual ? 'Invio confermato dopo verifica dell’admin.' : 'Richiesta accettata dal server di posta.');
        });
    }

    public function resolve(InsuranceQuoteEmail $message, User $user, bool $sent, string $note): void
    {
        abort_unless($user->canManageSystem() && $user->status === 'active',403);
        DB::transaction(function () use ($message,$sent,$note) {
            InsuranceQuoteCase::whereKey($message->insurance_quote_case_id)->lockForUpdate()->firstOrFail();
            $locked = InsuranceQuoteEmail::whereKey($message->id)->lockForUpdate()->firstOrFail();
            if (!$locked->canResolve()) $this->fail('Questo invio non richiede una verifica manuale dell’esito.');
            if ($sent) $this->recordSent($locked,null,true);
            else $locked->update(['status'=>'failed','delivery_key'=>null,'error_code'=>'manually_not_sent']);
            $this->audit($locked,'quote_email_resolved',($sent ? 'Invio verificato' : 'Mancato invio verificato').': '.$note);
        });
    }
}
