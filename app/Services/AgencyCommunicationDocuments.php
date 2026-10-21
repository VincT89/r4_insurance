<?php

namespace App\Services;

use App\Domain\Core\Queries\PolicyPortfolioQuery;
use App\Models\{AgencyCommunication, AgencyCommunicationAttachment, Attachment, Client, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgencyCommunicationDocuments
{
    public const MAX_BYTES = 20 * 1024 * 1024;
    public const MAX_FILES = 50;
    private const MIME_EXTENSIONS = ['application/pdf'=>'pdf', 'image/jpeg'=>'jpg', 'image/png'=>'png'];

    public static function rules(): array
    {
        return [
            'attachment_revision'=>['required','integer','min:0'],
            'document_ids'=>['nullable','array','list','max:50'], 'document_ids.*'=>['required','integer','distinct'],
            'keep_ids'=>['nullable','array','list','max:50'], 'keep_ids.*'=>['required','integer','distinct'],
            'files'=>['nullable','array','list','max:10'],
            'files.*'=>['required','file','max:10240','mimes:pdf,jpg,jpeg,png','extensions:pdf,jpg,jpeg,png'],
        ];
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['documents'=>$message]); }

    public function available(AgencyCommunication $message, User $user): Builder
    {
        $changeIds = array_column($message->payload['changes'] ?? [], 'id');
        $basePolicyIds = $message->payload['policy_ids'] ?? array_column($message->payload['policies'], 'id');
        $policies = app(PolicyPortfolioQuery::class)->forUser($user, [])
            ->whereIn('id', array_column($message->payload['policies'], 'id'))
            ->where('supplier_agency_id', $message->supplier_agency_id)->get(['id','client_id']);
        return Attachment::query()->where('attachable_type', (new Client)->getMorphClass())
            ->whereIn('attachable_id', $policies->pluck('client_id'))->whereNull('client_material_category')
            ->whereNull('insurance_quote_email_id')->whereDoesntHave('newerVersion')
            ->whereIn('mime_type', array_keys(self::MIME_EXTENSIONS))
            ->where(fn ($q) => $q->whereNull('insurance_policy_change_id')->orWhereIn('insurance_policy_change_id', $changeIds)->orWhereIn('insurance_policy_id', $basePolicyIds))
            ->where(function ($query) use ($policies) {
                $query->whereHas('insurancePolicy', fn ($q) => $q->whereIn('id', $policies->pluck('id'))->whereColumn('client_id','attachments.attachable_id'))
                    ->orWhere(function ($general) {
                        $general->whereNull('insurance_policy_id');
                        /** Documents of another practice are not general client documents, even when their policy link is empty. */
                        foreach (['insurance_quote_id','insurance_quote_case_id','insurance_title_id','insurance_collection_id','insurance_claim_id','insurance_coverage_check_id','insurance_policy_change_id'] as $link) $general->whereNull($link);
                    });
            })->with(['attachable','insurancePolicy','policyChange'])->orderBy('attachable_id')->orderBy('id');
    }

    public function editable(AgencyCommunication $message, User $user): bool
    {
        return $user->status === 'active' && $user->canManageSystem() && (int)$message->user_id === $user->id
            && $message->status === 'review' && $message->review_expires_at->isFuture();
    }

    private function copy(AgencyCommunication $message, string $bytes, string $name, array $details, ?int $sourceId, array &$paths): AgencyCommunicationAttachment
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!isset(self::MIME_EXTENSIONS[$mime])) $this->fail('Sono ammessi soltanto PDF, JPG e PNG validi.');
        if (strlen($bytes) > 10 * 1024 * 1024) $this->fail('Ogni allegato può occupare al massimo 10 MB.');
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $name));
        if ($name === '' || mb_strlen($name) > 255) $this->fail('Il nome di ogni allegato deve contenere da 1 a 255 caratteri.');
        $extension = strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if (!in_array($extension, $mime === 'image/jpeg' ? ['jpg','jpeg'] : [self::MIME_EXTENSIONS[$mime]], true)) $name = mb_substr($name,0,245).'.'.self::MIME_EXTENSIONS[$mime];
        $path = 'agency-communications/'.$message->id.'/'.Str::uuid().'.'.self::MIME_EXTENSIONS[$mime];
        $paths[] = $path;
        if (!Storage::disk('attachments')->put($path, $bytes)) $this->fail('Impossibile salvare gli allegati. Riprova.');
        return $message->documents()->create(['source_attachment_id'=>$sourceId, 'path'=>$path, 'size'=>strlen($bytes),
            'sha256'=>hash('sha256',$bytes), 'details'=>$details + ['name'=>$name, 'mime'=>$mime]]);
    }

    public function update(AgencyCommunication $message, User $user, array $data, array $uploads): void
    {
        $paths = [];
        try {
            DB::transaction(function () use ($message, $user, $data, $uploads, &$paths) {
                $message = AgencyCommunication::whereKey($message->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->status === 'active' && $user->canManageSystem() && (int)$message->user_id === $user->id, 403);
                if (!$this->editable($message,$user)) $this->fail('Gli allegati possono essere scelti soltanto prima di confermare un riepilogo valido.');
                $this->checkRevision($message, (int)$data['attachment_revision']);
                $keepIds = $data['keep_ids'] ?? [];
                $sourceIds = $data['document_ids'] ?? [];
                $kept = $message->documents()->where('included',true)->whereKey($keepIds)->orderBy('id')->get();
                $sources = $this->available($message,$user)->whereKey($sourceIds)->get();
                if ($kept->count() !== count($keepIds) || $sources->count() !== count($sourceIds)) $this->fail('Seleziona soltanto allegati disponibili di queste polizze e dei loro clienti.');
                if ($kept->pluck('source_attachment_id')->filter()->intersect($sourceIds)->isNotEmpty()) $this->fail('Un documento è già incluso. Non selezionarlo due volte.');
                if ($kept->count() + $sources->count() + count($uploads) > self::MAX_FILES) $this->fail('Seleziona al massimo 50 allegati per email.');
                $total = $kept->sum('size');
                foreach ($sources as $source) {
                    abort_unless($user->can('download',$source),403);
                    if ($source->disk !== 'attachments' || !Storage::disk('attachments')->exists($source->path)) $this->fail('Un documento selezionato non è più disponibile.');
                    $size = Storage::disk('attachments')->size($source->path);
                    if ($size > 10 * 1024 * 1024) $this->fail('Ogni allegato può occupare al massimo 10 MB.');
                    $total += $size;
                }
                foreach ($uploads as $file) $total += $file->getSize();
                if ($total > self::MAX_BYTES) $this->fail('Gli allegati dell’email possono occupare al massimo 20 MB complessivi.');
                $message->documents()->where('included',true)->whereNotIn('id',$keepIds)->update(['included'=>false]);
                foreach ($sources as $source) {
                    $kept->push($this->copy($message,Storage::disk('attachments')->get($source->path),$source->original_name,[
                        'context'=>$source->attachable->name.($source->insurance_policy_id ? ' · Polizza '.$source->insurancePolicy->number : ' · Anagrafica cliente').($source->policyChange ? ' · Appendice '.$source->policyChange->reference : ''),
                        'category'=>Attachment::DOCUMENT_CATEGORIES[$source->document_category] ?? 'Documento',
                        'identity'=>$source->document_category === 'identity',
                        'expires_on'=>$source->expires_on?->toDateString(),
                    ],$source->id,$paths));
                }
                foreach ($uploads as $file) $kept->push($this->copy($message,$file->getContent(),$file->getClientOriginalName(),[
                    'context'=>'Caricato per questa comunicazione', 'category'=>'Allegato aggiuntivo', 'identity'=>false, 'expires_on'=>null,
                ],null,$paths));
                if ($kept->sum('size') > self::MAX_BYTES) $this->fail('Gli allegati sono cambiati e superano il limite di 20 MB.');
                $manifest = $kept->sortBy('id')->map(fn ($file) => ['id'=>$file->id, 'size'=>$file->size, 'sha256'=>$file->sha256] + $file->details)->values()->all();
                $message->update(['payload'=>array_replace($message->payload,['attachments'=>$manifest]), 'attachment_revision'=>$message->attachment_revision + 1]);
                app(AuditLogService::class)->log('agency_email_attachments',$message->agency ?? $user,description:'Aggiornati gli allegati del riepilogo '.$message->id.': '.$kept->count().' file. Nessun invio.');
            });
        } catch (\Throwable $exception) {
            foreach ($paths as $path) Storage::disk('attachments')->delete($path);
            throw $exception;
        }
    }

    public function checkRevision(AgencyCommunication $message, ?int $revision): void
    {
        if ((int)$message->attachment_revision !== ($revision ?? 0)) $this->fail('Gli allegati sono cambiati in un’altra pagina. Ricarica e controlla nuovamente il riepilogo.');
    }

    public function checkedFiles(AgencyCommunication $message): array
    {
        $expected = $message->payload['attachments'] ?? [];
        $documents = $message->documents()->where('included',true)->get()->keyBy('id');
        if ($documents->count() !== count($expected) || count($expected) > self::MAX_FILES) $this->fail('Gli allegati sono cambiati. Riapri il riepilogo.');
        $files = []; $total = 0;
        foreach ($expected as $entry) {
            $document = $documents->get($entry['id']);
            if (!$document || $document->size !== $entry['size'] || !hash_equals($document->sha256,$entry['sha256'])) $this->fail('Gli allegati sono cambiati dopo il controllo.');
            $disk = Storage::disk('attachments');
            if (!$disk->exists($document->path) || $disk->size($document->path) !== $entry['size']) $this->fail('Un allegato non è più disponibile o è cambiato dopo il controllo.');
            $total += $entry['size'];
            if ($total > self::MAX_BYTES) $this->fail('Gli allegati superano il limite di 20 MB.');
            $bytes = $disk->get($document->path);
            if (!hash_equals($entry['sha256'],hash('sha256',$bytes))) $this->fail('Un allegato è cambiato dopo il controllo.');
            $files[] = ['name'=>$entry['name'], 'mime'=>$entry['mime'], 'bytes'=>$bytes];
        }
        return $files;
    }
}
