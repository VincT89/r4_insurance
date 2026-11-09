<?php

namespace App\Services;

use App\Models\{Attachment, Client, DocumentSignature, InsuranceFormDocument, InsurancePolicy, User};
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Crypt, DB, Gate, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentSigning
{
    public const CONSENT = 'Ho letto il documento e confermo di volerlo sottoscrivere con la firma nei punti indicati.';

    private function operator(?User $operator): User
    {
        $operator ??= auth()->user();
        abort_unless($operator && $operator->status === 'active' && $operator->password_changed_at && $operator->can('viewAny',Client::class),403);
        return $operator;
    }

    public function source(Attachment $source, ?User $operator = null): array
    {
        $operator = $this->operator($operator);
        Gate::forUser($operator)->authorize('download', $source);
        $client = Client::current()->findOrFail($source->attachable_id);
        Gate::forUser($operator)->authorize('updateRegistry', $client);
        $document = $source->formDocument;
        if ($document) {
            abort_unless($document->signature_required && !$source->insurance_form_submission_id && (int)$document->package->client_id === $client->id, 404);
        } else {
            abort_unless($source->document_category === 'policy' && $source->insurance_policy_id && !$source->insurance_quote_id && !$source->insurance_quote_case_id && !$source->insurance_claim_id && !$source->insurance_collection_id && !$source->insurance_title_id && !$source->client_material_category, 404);
        }
        abort_if($source->signatureResult()->exists() || $source->newerVersion()->exists(), 404);
        $policy = $source->insurance_policy_id ? $client->policies()->findOrFail($source->insurance_policy_id) : null;
        $disk = Storage::disk($source->disk);
        abort_unless($disk->exists($source->path), 404);
        if ($disk->mimeType($source->path) !== 'application/pdf' || $disk->size($source->path) > config('document_signing.max_bytes')) $this->fail('Questa modalità richiede un PDF di dimensioni non superiori a 20 MB.');
        $bytes = $disk->get($source->path);
        $hash = hash('sha256', $bytes);
        if ($document && !hash_equals((string)$document->pdf_hash, $hash)) $this->fail('Il modulo originale è cambiato. Riapri il fascicolo e verifica il documento.');
        return compact('client', 'policy', 'document', 'bytes', 'hash');
    }

    public function prepare(Attachment $source, ?User $operator = null): array
    {
        $operator = $this->operator($operator);
        $context = $this->source($source, $operator);
        $latest = $context['document']?->latestSubmission;
        $last = $source->latestTabletSignature;
        $tokens = $context['document']?->package->snapshot['tokens'] ?? [];
        $signer = $latest?->signer_name ?: ($tokens['{{firmatario}}'] ?? null);
        if (!$signer || str_contains($signer, '____')) $signer = $context['client']->client_type === 'company' ? $context['client']->reference_person : $context['client']->name;
        $role = $latest?->signer_role ?: ($tokens['{{qualifica_firmatario}}'] ?? null);
        if (!$role || str_contains($role, '____')) $role = $context['client']->client_type === 'company' ? $context['client']->reference_role : '';
        $payload = ['source'=>$source->id, 'user'=>$operator->id, 'request_key'=>(string)Str::uuid(), 'hash'=>$context['hash'], 'state'=>$this->state($source, $context['document']), 'expires'=>now()->addMinutes(config('document_signing.expires_after_minutes'))->timestamp];
        return $context + ['source'=>$source, 'token'=>Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)), 'signer'=>$signer, 'role'=>$role, 'needsNote'=>(bool)($latest || $last)];
    }

    public function preparedSource(Attachment $source, string $token, User $operator): array
    {
        $payload = $this->payload($source, $token, $this->operator($operator));
        if (($payload['expires'] ?? 0) < now()->timestamp) $this->fail('La richiesta di firma è scaduta. Chiedi all’operatore di inviarla di nuovo.');
        $context = $this->source($source,$operator);
        if (!hash_equals($payload['hash'],$context['hash']) || !hash_equals($payload['state'],$this->state($source,$context['document']))) $this->fail('Il documento è cambiato. L’operatore deve controllarlo e inviarlo di nuovo.');
        return $context;
    }

    private function payload(Attachment $source, string $token, User $operator): array
    {
        try { $payload = json_decode(Crypt::decryptString($token), true, flags:JSON_THROW_ON_ERROR); }
        catch (DecryptException|\JsonException $exception) { $this->fail('La pagina di firma non è valida. Riapri il documento.'); }
        abort_unless(is_array($payload) && ($payload['source'] ?? null) === $source->id && ($payload['user'] ?? null) === $operator->id,403);
        return $payload;
    }

    private function state(Attachment $source, ?InsuranceFormDocument $document): string
    {
        return hash('sha256', json_encode([$source->disk, $source->path, $source->original_name, $source->insurance_policy_id, $source->insurance_policy_change_id,
            $document?->pdf_hash, $document?->signature_required, $document?->submissions()->max('id'), $source->tabletSignatures()->max('id')], JSON_THROW_ON_ERROR));
    }

    public function validateInk(array $strokes): void
    {
        $total = 0; $length = 0; $xs = []; $ys = [];
        if (!$strokes || count($strokes) > 80 || !array_is_list($strokes)) $this->fail('Disegna la firma nel riquadro prima di confermare.');
        foreach ($strokes as $stroke) {
            if (!is_array($stroke) || !array_is_list($stroke) || count($stroke) < 2) $this->fail('La firma è incompleta. Cancella e riprova.');
            $previous = null;
            foreach ($stroke as $point) {
                if (!is_array($point) || !array_is_list($point) || count($point) !== 2) $this->fail('La firma non è leggibile. Cancella e riprova.');
                [$x, $y] = $point;
                if ((!is_int($x) && !is_float($x)) || (!is_int($y) && !is_float($y)) || !is_finite((float)$x) || !is_finite((float)$y) || $x < 0 || $x > 1 || $y < 0 || $y > 1 || ++$total > 3000) $this->fail('La firma supera i limiti consentiti. Cancella e riprova.');
                if ($previous) $length += hypot($x-$previous[0], $y-$previous[1]);
                $previous = $point; $xs[] = $x; $ys[] = $y;
            }
        }
        /** Coordinates describe the visible drawing only; pressure, velocity and other biometric measurements are not collected. */
        if ($total < 5 || $length < 0.12 || max($xs)-min($xs) < 0.03 || max($ys)-min($ys) < 0.02) $this->fail('Il riquadro non contiene una firma completa. Cancella e riprova.');
    }

    public function sign(Attachment $source, array $data, ?User $operator = null, ?\Closure $guard = null, ?\Closure $completed = null): DocumentSignature
    {
        $operator = $this->operator($operator);
        $this->validateInk($data['strokes']);
        $payload = $this->payload($source, $data['token'], $operator);
        $inputHash = hash('sha256', json_encode(array_intersect_key($data, array_flip(['signer_name','signer_role','strokes','placements','note'])), JSON_THROW_ON_ERROR));
        $stored = []; $temporary = null;
        try {
            return DB::transaction(function () use ($source, $data, $payload, $inputHash, $operator, $guard, $completed, &$stored, &$temporary) {
                $client = Client::current()->whereKey($source->attachable_id)->lockForUpdate()->firstOrFail();
                Gate::forUser($operator)->authorize('updateRegistry', $client);
                if ($source->insurance_policy_id) $client->policies()->whereKey($source->insurance_policy_id)->lockForUpdate()->firstOrFail();
                $source = Attachment::whereKey($source->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($operator)->authorize('download', $source);
                /** La postazione e la richiesta restano bloccate nella stessa transazione che conserva la firma. */
                $stationContext = $guard ? $guard() : null;
                $existing = DocumentSignature::where('request_key', $payload['request_key'])->first();
                if ($existing) {
                    abort_unless($existing->source_attachment_id === $source->id && $existing->created_by === $operator->id, 403);
                    if (!hash_equals($existing->input_hash, $inputHash)) $this->fail('Questa conferma è già stata utilizzata con dati diversi. Riapri la polizza.');
                    if ($completed) $completed($existing);
                    return $existing;
                }
                if (($payload['expires'] ?? 0) < now()->timestamp) $this->fail('La pagina di firma è scaduta. Riapri il documento dalla polizza.');
                $context = $this->source($source, $operator);
                $document = $context['document'];
                if ($document) $document = InsuranceFormDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
                if (!hash_equals($payload['hash'], $context['hash']) || !hash_equals($payload['state'], $this->state($source, $document))) $this->fail('Il documento o le copie firmate sono cambiati. Riapri il documento e controlla la nuova versione.');
                $latest = $document?->submissions()->first();
                if (($latest || $source->tabletSignatures()->exists()) && mb_strlen(trim($data['note'] ?? '')) < 10) $this->fail('Indica il motivo della nuova copia (almeno 10 caratteri).');
                if ($client->client_type === 'company' && blank($data['signer_role'] ?? null)) $this->fail('Indica la qualifica del firmatario dell’azienda.');
                $evidence = ['request_key'=>$payload['request_key'], 'document'=>$source->original_name, 'client'=>$client->name, 'policy'=>$context['policy']?->number,
                    'source_hash'=>$context['hash'], 'signer_name'=>$data['signer_name'], 'signer_role'=>$data['signer_role'] ?? '', 'operator'=>$operator->name,
                    'signed_at'=>now()->toIso8601String(), 'signed_at_display'=>now()->format('d/m/Y H:i:s').' · '.config('app.timezone'),
                    'consent_text'=>self::CONSENT, 'identity_confirmed'=>true, 'signature_type'=>'simple', 'placements'=>$data['placements'], 'strokes'=>$data['strokes'], 'note'=>$data['note'] ?? ''];
                if ($stationContext) $evidence['station_request'] = $stationContext;
                $bytes = app(DocumentSignaturePdf::class)->render($context['bytes'], $data, $evidence);
                if (!hash_equals($context['hash'], hash('sha256', Storage::disk($source->disk)->get($source->path)))) $this->fail('Il documento è cambiato durante la firma. Riaprilo e controlla la versione.');
                $directory = storage_path('app/signing-tmp');
                if (!is_dir($directory)) mkdir($directory, 0700, true);
                $temporary = tempnam($directory, 'signed-');
                if (!$temporary || file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes)) $this->fail('La copia firmata non è stata conservata. Riprova.');
                $name = Str::limit(pathinfo($source->original_name, PATHINFO_FILENAME), 130, '').'-firmato.pdf';
                $upload = new UploadedFile($temporary, $name, 'application/pdf', null, true);
                $submission = null;
                if ($document) {
                    $submission = app(InsuranceForms::class)->submit($document, [$upload], ['request_key'=>$payload['request_key'], 'previous_submission'=>$latest?->id ?? 0,
                        'signer_name'=>$data['signer_name'], 'signer_role'=>$data['signer_role'] ?? null, 'signed_on'=>today()->toDateString(), 'note'=>$data['note'] ?? null], $operator);
                    $files = $submission->files()->get();
                } else {
                    $files = collect(app(PrivateAttachmentBatch::class)->upload($client, [$upload], ['type'=>'document', 'document_category'=>'policy',
                        'insurance_policy_id'=>$source->insurance_policy_id, 'insurance_policy_change_id'=>$source->insurance_policy_change_id, 'review_status'=>'received', 'description'=>'Copia con firma raccolta sul tablet. Originale: '.$source->original_name], $operator));
                }
                $stored = $files->map(fn ($file) => [$file->disk,$file->path])->all();
                $record = DocumentSignature::create(['request_key'=>$payload['request_key'], 'client_id'=>$client->id, 'insurance_policy_id'=>$source->insurance_policy_id,
                    'source_attachment_id'=>$source->id, 'signed_attachment_id'=>$files->sole()->id, 'insurance_form_submission_id'=>$submission?->id,
                    'created_by'=>$operator->id, 'signer_name'=>$data['signer_name'], 'signer_role'=>$data['signer_role'] ?? null, 'source_hash'=>$context['hash'],
                    'signed_hash'=>hash('sha256',$bytes), 'input_hash'=>$inputHash, 'evidence'=>$evidence, 'signed_at'=>now()]);
                app(AuditLogService::class)->log('document_signed', $context['policy'] ?? $client, description:'Firma raccolta sul tablet per '.$source->original_name.'. Originale e copia conservati.',
                    newValues:['signature_id'=>$record->id, 'source_attachment_id'=>$source->id, 'signed_attachment_id'=>$record->signed_attachment_id, 'source_hash'=>$record->source_hash, 'signed_hash'=>$record->signed_hash], userId:$operator->id);
                if ($completed) $completed($record);
                return $record;
            });
        } catch (\Throwable $exception) {
            foreach ($stored as [$disk,$path]) Storage::disk($disk)->delete($path);
            throw $exception;
        } finally {
            if ($temporary && is_file($temporary)) unlink($temporary);
        }
    }

    public function returnUrl(Attachment $source): string
    {
        if ($source->insurance_policy_id) return route('clients.policies.show', [$source->attachable_id,$source->insurance_policy_id,'tab'=>$source->insurance_form_document_id ? 'forms' : 'documents']);
        return route('clients.forms.show', [$source->attachable_id,$source->formDocument->insurance_form_package_id]);
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['signature'=>$message]); }
}
