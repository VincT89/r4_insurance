<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceFormSubmission extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['signed_on'=>'date','reviewed_at'=>'datetime','file_hashes'=>'array']; }
    public function document() { return $this->belongsTo(InsuranceFormDocument::class,'insurance_form_document_id'); }
    public function files() { return $this->hasMany(Attachment::class); }
    public function author() { return $this->belongsTo(User::class,'created_by'); }
    public function reviewer() { return $this->belongsTo(User::class,'reviewed_by'); }
}
