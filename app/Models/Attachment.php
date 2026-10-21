<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    public const IDENTITY_TYPES = ['electronic' => 'Carta d’identità elettronica', 'paper' => 'Carta d’identità cartacea', 'driving_licence' => 'Patente'];
    public const DOCUMENT_CATEGORIES = [
        'claim'=>'Documento sinistro',
        'quote_request'=>'Allegato richiesta preventivo',
        'quote'=>'Preventivo compagnia','quote_approval'=>'Approvazione preventivo','quietance'=>'Quietanza compagnia','collection'=>'Documento di incasso',
        'identity' => 'Documento di identità', 'tax_code' => 'Codice fiscale / partita IVA',
        'vehicle' => 'Documento del veicolo', 'policy' => 'Polizza',
        'consent' => 'Consenso', 'company' => 'Documento aziendale', 'other' => 'Altro documento',
        'mup_insurance' => 'MUP per prodotti assicurativi', 'mup_investment' => 'MUP per prodotti di investimento assicurativi',
        'annex_3' => 'Allegato 3 (modello precedente)', 'annex_4' => 'Allegato 4 (modello precedente)', 'annex_4_ter' => 'Allegato 4 Ter (modello precedente)', 'annex_4_bis' => 'Allegato 4 bis (modello precedente)',
        'privacy' => 'Informativa privacy', 'mandate' => 'Mandato e condizioni di brokeraggio',
        'needs_questionnaire' => 'Questionario esigenze e adeguatezza', 'consistency' => 'Dichiarazione di coerenza',
        'delivery_receipt' => 'Consegna documenti', 'dip' => 'DIP', 'additional_dip' => 'DIP aggiuntivo',
        'contract_terms' => 'Condizioni contrattuali', 'target_market' => 'POG / target market',
    ];
    public const CLIENT_MATERIAL_CATEGORIES = [
        'logo' => 'Loghi', 'vector' => 'Vettoriali', 'guidelines' => 'Linee guida',
        'template' => 'Modelli e sorgenti', 'other' => 'Altri materiali',
    ];

    protected $fillable = [
        'insurance_policy_change_id',
        'insurance_claim_id','insurance_claim_event_id',
        'insurance_form_document_id','insurance_form_submission_id','insurance_coverage_check_id',
        'insurance_quote_email_id','insurance_quote_case_id',
        'insurance_quote_id','insurance_title_id','insurance_collection_id',
        'attachable_type',
        'attachable_id',
        'type',
        'uploaded_by',
        'disk',
        'directory',
        'path',
        'original_name',
        'stored_name',
        'mime_type',
        'extension',
        'size',
        'description',
        'client_material_category',
        'document_category', 'identity_type', 'insurance_policy_id', 'vehicle_id', 'expires_on',
        'previous_version_id', 'version_number', 'document_request_id', 'review_status', 'validated_by', 'validated_at', 'validation_note',
    ];

    protected $casts = [
        'size' => 'integer',
        'expires_on' => 'date',
        'validated_at' => 'datetime',
    ];

    public function policyChange() { return $this->belongsTo(InsurancePolicyChange::class, 'insurance_policy_change_id'); }

    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment) {
            if ($attachment->insurance_policy_change_id) throw \Illuminate\Validation\ValidationException::withMessages(['document'=>'L’allegato appartiene allo storico di una variazione contrattuale e deve essere conservato.']);
            if ($attachment->insurance_quote_case_id) throw \Illuminate\Validation\ValidationException::withMessages(['document'=>'Il documento appartiene a una pratica preventivi e deve restare nello storico.']);
            if ($attachment->insurance_claim_id) throw \Illuminate\Validation\ValidationException::withMessages(['document'=>'Il documento appartiene allo storico di un sinistro e deve essere conservato.']);
            if ($attachment->insurance_form_document_id || $attachment->insurance_coverage_check_id) throw \Illuminate\Validation\ValidationException::withMessages(['document'=>'Il documento appartiene allo storico dei moduli o dei controlli RCA e deve essere conservato.']);
            if ($attachment->insurance_quote_email_id) throw \Illuminate\Validation\ValidationException::withMessages(['document'=>'L’allegato appartiene a una richiesta di preventivo controllata e deve restare nello storico.']);
            if ($attachment->insurance_quote_id || $attachment->insurance_title_id || $attachment->insurance_collection_id) throw \Illuminate\Validation\ValidationException::withMessages(['document'=>'Il documento appartiene a un preventivo o allo storico contabile e deve essere conservato.']);
            if ($attachment->previous_version_id || $attachment->newerVersion()->exists() || $attachment->document_request_id || $attachment->checklistRequests()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['document' => 'Il file appartiene a uno storico di versioni o a una richiesta documentale e deve essere conservato.']);
            }
            if (ClientConsent::where('attachment_id', $attachment->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['document' => 'Il documento è una prova collegata allo storico dei consensi e deve essere conservato.']);
            }
            $disk = Storage::disk($attachment->disk);

            if ($disk->exists($attachment->path)) {
                $disk->delete($attachment->path);
            }
        });
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function insurancePolicy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicy::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(ClientVehicle::class, 'vehicle_id');
    }

    public function downloadUrl(): string
    {
        return route('attachments.download', $this);
    }

    public function getValidityLabelAttribute(): string
    {
        if (!$this->expires_on) return 'Da verificare, data di scadenza non leggibile';
        return $this->expires_on->lt(today()) ? 'Documento non valido — scaduto il '.$this->expires_on->format('d/m/Y') : 'Documento valido';
    }
    public function previousVersion(): BelongsTo { return $this->belongsTo(self::class, 'previous_version_id'); }
    public function formDocument(): BelongsTo { return $this->belongsTo(InsuranceFormDocument::class,'insurance_form_document_id'); }
    public function newerVersion(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(self::class, 'previous_version_id'); }
    public function documentRequest(): BelongsTo { return $this->belongsTo(ClientDocumentRequest::class, 'document_request_id'); }
    public function checklistRequests() { return $this->belongsToMany(ClientDocumentRequest::class, 'client_document_request_files'); }
    public function validator(): BelongsTo { return $this->belongsTo(User::class, 'validated_by'); }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
