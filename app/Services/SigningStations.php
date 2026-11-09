<?php

namespace App\Services;

use App\Models\{Attachment, Client, DocumentSignature, SigningStation, StationSigningItem, StationSigningRequest, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

class SigningStations
{
    public const COOKIE = 'r4_signing_station';

    public function transport(Request $request): void
    {
        $loopback = app()->environment(['local','testing','audit']) && in_array($request->getHost(),['127.0.0.1','localhost','::1','[::1]'],true);
        abort_unless($request->isSecure() || $loopback,426,'La postazione di firma richiede un collegamento HTTPS.');
    }

    public function identify(Request $request): ?SigningStation
    {
        $this->transport($request);
        $credential = $request->cookie(self::COOKIE);
        if (!is_string($credential) || !preg_match('/^[a-f0-9]{64}$/D',$credential)) return null;
        return SigningStation::where('credential_hash',hash('sha256',$credential))->first();
    }

    public function active(Request $request): SigningStation
    {
        $station = $this->identify($request);
        abort_unless($station?->usable(),403,'Il collegamento della postazione è scaduto o è stato revocato. Torna alla schermata di attesa.');
        return $station;
    }

    public function startPairing(Request $request): array
    {
        $this->transport($request);
        $old = $this->identify($request);
        if ($old?->usable() || ($old?->status === 'pending' && $old->pairing_expires_at->isFuture())) return [$old,null];
        $credential = bin2hex(random_bytes(32));
        do { $code = (string)random_int(10000000,99999999); }
        while (SigningStation::where('pairing_code_hash',hash('sha256',$code))->exists());
        $station = SigningStation::create(['credential_hash'=>hash('sha256',$credential),'pairing_code'=>$code,'pairing_code_hash'=>hash('sha256',$code),
            'pairing_expires_at'=>now()->addMinutes(config('document_signing.station_pairing_minutes')),'credential_expires_at'=>now()->addDays(config('document_signing.station_credential_days'))]);
        return [$station,$credential];
    }

    public function pair(User $operator, string $code, string $name): SigningStation
    {
        Gate::forUser($operator)->authorize('create',Client::class);
        return DB::transaction(function () use ($operator,$code,$name) {
            $station = SigningStation::where('pairing_code_hash',hash('sha256',$code))->lockForUpdate()->first();
            if (!$station || $station->status !== 'pending' || $station->pairing_expires_at->isPast()) $this->fail('Il codice non è valido o è scaduto. Richiedine uno nuovo sul tablet.','pairing_code');
            if (SigningStation::where('status','active')->where('name',$name)->exists()) $this->fail('Esiste già una postazione con questo nome. Scegli un nome diverso.','name');
            $station->update(['status'=>'active','name'=>$name,'paired_by'=>$operator->id,'paired_at'=>now(),'last_seen_at'=>now(),'pairing_code'=>null,'pairing_code_hash'=>null,
                'credential_expires_at'=>now()->addDays(config('document_signing.station_credential_days'))]);
            $this->audit($station,'signing_station_paired','Collegata la postazione di firma '.$name.'.',$operator->id);
            return $station;
        });
    }

    public function revoke(SigningStation $station, User $operator): void
    {
        Gate::forUser($operator)->authorize('create',Client::class);
        abort_unless($operator->isAdmin() || $station->paired_by === $operator->id,403);
        DB::transaction(function () use ($station,$operator) {
            $station = SigningStation::whereKey($station->id)->lockForUpdate()->firstOrFail();
            $station->update(['status'=>'revoked','revoked_at'=>now(),'pairing_code'=>null,'pairing_code_hash'=>null]);
            $station->requests()->where('status','pending')->where('expires_at','>',now())->update(['status'=>'cancelled','cancelled_at'=>now(),'cancelled_by'=>$operator->id]);
            $this->audit($station,'signing_station_revoked','Revocato il collegamento della postazione '.$station->name.'.',$operator->id);
        });
    }

    public function candidates(Attachment $source): \Illuminate\Support\Collection
    {
        if ($source->insurance_form_document_id) {
            $packageId = $source->formDocument->insurance_form_package_id;
            $candidates = Attachment::whereHas('formDocument',fn($q)=>$q->where('insurance_form_package_id',$packageId)->where('signature_required',true))
                ->whereNull('insurance_form_submission_id')->with(['latestTabletSignature','formDocument.latestSubmission'])->orderBy('id')->get();
        } else {
            $candidates = $source->insurancePolicy->contracts()->with('latestTabletSignature')->orderBy('id')->get();
        }
        return $candidates->filter(fn($candidate)=>$candidate->id === $source->id || ($candidate->mime_type === 'application/pdf' && !$candidate->latestTabletSignature && (!$candidate->formDocument?->latestSubmission || $candidate->formDocument->latestSubmission->status === 'rejected')))->values();
    }

    public function dispatch(Attachment $source, User $operator, array $data): StationSigningRequest
    {
        app(DocumentSigning::class)->source($source,$operator);
        return DB::transaction(function () use ($source,$operator,$data) {
            $client = Client::current()->whereKey($source->attachable_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($operator)->authorize('updateRegistry',$client);
            if ($source->insurance_policy_id) $client->policies()->whereKey($source->insurance_policy_id)->lockForUpdate()->firstOrFail();
            $ids = $data['attachment_ids']; sort($ids,SORT_NUMERIC);
            $sources = Attachment::whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();
            $allowed = $this->candidates($source)->modelKeys();
            if (count($sources) !== count($ids) || array_diff($ids,$allowed)) $this->fail('Seleziona soltanto i documenti di questa polizza o di questo fascicolo.','attachment_ids');
            $signing = app(DocumentSigning::class);
            $prepared = [];
            foreach ($sources as $file) {
                $context = $signing->prepare($file,$operator);
                if ($context['client']->id !== $client->id || (int)$file->insurance_policy_id !== (int)$source->insurance_policy_id) $this->fail('I documenti devono appartenere alla stessa pratica.','attachment_ids');
                if ($context['needsNote'] && mb_strlen(trim($data['note'] ?? '')) < 10) $this->fail('Indica il motivo della nuova copia, con almeno 10 caratteri.','note');
                /** Il lotto conserva solo i riferimenti; i PDF vengono letti uno alla volta anche con molti documenti. */
                $prepared[$file->id] = ['token'=>$context['token'],'hash'=>$context['hash']];
                unset($context);
            }
            if ($client->client_type === 'company' && blank($data['signer_role'] ?? null)) $this->fail('Indica la qualifica del firmatario dell’azienda.','signer_role');
            if (StationSigningItem::whereIn('source_attachment_id',$ids)->whereHas('request',fn($q)=>$q->where('status','pending')->where('expires_at','>',now()))->exists()) $this->fail('Uno dei documenti è già stato inviato a una postazione. Completa o annulla la richiesta precedente.','attachment_ids');
            $station = SigningStation::whereKey($data['signing_station_id'])->lockForUpdate()->first();
            if (!$station?->usable()) $this->fail('La postazione non è più collegata. Scegline un’altra o collegala di nuovo.','signing_station_id');
            if ($station->requests()->where('status','pending')->where('expires_at','>',now())->exists()) $this->fail('La postazione è occupata. Scegline un’altra oppure attendi la fine della firma.','signing_station_id');
            $request = StationSigningRequest::create(['signing_station_id'=>$station->id,'client_id'=>$client->id,'insurance_policy_id'=>$source->insurance_policy_id,
                'created_by'=>$operator->id,'signer_name'=>$data['signer_name'],'signer_role'=>$data['signer_role'] ?? null,'note'=>$data['note'] ?? null,
                'identity_confirmed_at'=>now(),'expires_at'=>now()->addMinutes(config('document_signing.station_request_minutes'))]);
            foreach ($sources->values() as $position=>$file) $request->items()->create(['source_attachment_id'=>$file->id,'position'=>$position,
                'prepared_token'=>$prepared[$file->id]['token'],'source_hash'=>$prepared[$file->id]['hash']]);
            $this->audit($request,'station_signing_requested','Inviati '.count($sources).' documenti alla postazione '.$station->name.'.',$operator->id);
            return $request;
        });
    }

    public function operatorFor(StationSigningRequest $request): User
    {
        $operator = $request->author()->first();
        abort_unless($operator && !$request->client->merged_into_id && $operator->status === 'active' && $operator->password_changed_at && $operator->can('updateRegistry',$request->client),403,'La richiesta deve essere verificata dall’operatore.');
        return $operator;
    }

    public function assigned(SigningStation $station, StationSigningRequest $request, bool $completed = false): void
    {
        abort_unless($station->usable() && $request->signing_station_id === $station->id,404);
        abort_unless($request->pending() || ($completed && $request->status === 'completed'),410,'La richiesta è scaduta o è stata annullata.');
        $this->operatorFor($request);
    }

    public function current(SigningStation $station): ?StationSigningRequest
    {
        if (!$station->usable()) return null;
        if (!$station->last_seen_at || $station->last_seen_at->lt(now()->subMinute())) $station->update(['last_seen_at'=>now()]);
        $request = $station->requests()->where('status','pending')->where('expires_at','>',now())->first();
        if (!$request) return null;
        $operator = $request->author;
        if (!$operator || $request->client->merged_into_id || $operator->status !== 'active' || !$operator->password_changed_at || !$operator->can('updateRegistry',$request->client)) {
            $this->block($request,'L’accesso dell’operatore è cambiato. La richiesta deve essere verificata.');
            return null;
        }
        return $request;
    }

    public function block(StationSigningRequest $request, string $reason): void
    {
        StationSigningRequest::whereKey($request->id)->where('status','pending')->update(['status'=>'blocked','failure_reason'=>encrypt($reason,serialize:false)]);
    }

    public function cancel(StationSigningRequest $request, User $operator): void
    {
        Gate::forUser($operator)->authorize('updateRegistry',$request->client);
        abort_unless($operator->isAdmin() || $request->created_by === $operator->id,403);
        DB::transaction(function () use ($request,$operator) {
            SigningStation::whereKey($request->signing_station_id)->lockForUpdate()->firstOrFail();
            $request = StationSigningRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') return;
            $request->update(['status'=>'cancelled','cancelled_at'=>now(),'cancelled_by'=>$operator->id]);
            $this->audit($request,'station_signing_cancelled','Annullata la richiesta alla postazione. Le firme già raccolte restano nello storico.',$operator->id);
        });
    }

    public function sign(SigningStation $station, StationSigningItem $item, array $data): DocumentSignature
    {
        $request = $item->request;
        $this->assigned($station,$request,completed:true);
        $operator = $this->operatorFor($request);
        $data = array_replace($data,['token'=>$item->prepared_token,'signer_name'=>$request->signer_name,'signer_role'=>$request->signer_role,'note'=>$request->note,'identity_confirmed'=>true]);
        return app(DocumentSigning::class)->sign($item->source,$data,$operator,guard:function () use ($station,$request,$item) {
            $lockedStation = SigningStation::whereKey($station->id)->lockForUpdate()->firstOrFail();
            $lockedRequest = StationSigningRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $lockedItem = StationSigningItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->assigned($lockedStation,$lockedRequest,completed:(bool)$lockedItem->document_signature_id);
            abort_unless($lockedItem->station_signing_request_id === $lockedRequest->id && $lockedRequest->identity_confirmed_at,403);
            $first = $lockedRequest->items()->whereNull('document_signature_id')->first();
            abort_unless($lockedItem->document_signature_id || $first?->id === $lockedItem->id,409,'Completa prima il documento precedente.');
            return ['station_id'=>$lockedStation->id,'request_id'=>$lockedRequest->id,'item_id'=>$lockedItem->id,'identity_confirmed_at'=>$lockedRequest->identity_confirmed_at->toIso8601String()];
        },completed:function (DocumentSignature $signature) use ($item,$request,$operator) {
            if ($item->fresh()->document_signature_id) return;
            $item->update(['document_signature_id'=>$signature->id,'completed_at'=>now()]);
            if (!$request->items()->whereNull('document_signature_id')->exists()) $request->update(['status'=>'completed','completed_at'=>now()]);
            $this->audit($request,'station_document_signed','Raccolta la firma del documento assegnato alla postazione.',$operator->id);
        });
    }

    private function audit($model, string $action, string $description, int $actor): void
    {
        app(AuditLogService::class)->log($action,$model,description:$description,userId:$actor);
    }

    private function fail(string $message, string $field): never { throw ValidationException::withMessages([$field=>$message]); }
}
