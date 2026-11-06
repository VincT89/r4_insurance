<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class DocumentSignature extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['signer_name'=>'encrypted', 'signer_role'=>'encrypted', 'evidence'=>'encrypted:array', 'signed_at'=>'datetime'];
    }

    protected static function booted(): void
    {
        /** A correction creates a new record; the document and the evidence already confirmed are retained. */
        static::updating(fn () => throw ValidationException::withMessages(['signature'=>'Una firma registrata non può essere modificata.']));
        static::deleting(fn () => throw ValidationException::withMessages(['signature'=>'La firma appartiene allo storico e deve essere conservata.']));
    }

    public function source() { return $this->belongsTo(Attachment::class, 'source_attachment_id'); }
    public function signedFile() { return $this->belongsTo(Attachment::class, 'signed_attachment_id'); }
    public function author() { return $this->belongsTo(User::class, 'created_by'); }
    public function submission() { return $this->belongsTo(InsuranceFormSubmission::class, 'insurance_form_submission_id'); }
}
