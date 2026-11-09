<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Concerns\HasUuids, Model};

class StationSigningItem extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected $hidden = ['prepared_token'];
    protected function casts(): array { return ['prepared_token'=>'encrypted','completed_at'=>'datetime']; }
    public function request() { return $this->belongsTo(StationSigningRequest::class,'station_signing_request_id'); }
    public function source() { return $this->belongsTo(Attachment::class,'source_attachment_id'); }
    public function signature() { return $this->belongsTo(DocumentSignature::class,'document_signature_id'); }
}
