<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceFormDocument extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['body'=>'encrypted','signature_required'=>'boolean']; }
    public function package() { return $this->belongsTo(InsuranceFormPackage::class,'insurance_form_package_id'); }
    public function template() { return $this->belongsTo(InsuranceFormTemplate::class,'insurance_form_template_id'); }
    public function generated() { return $this->hasOne(Attachment::class)->whereNull('insurance_form_submission_id'); }
    public function submissions() { return $this->hasMany(InsuranceFormSubmission::class)->orderByDesc('version'); }
    public function latestSubmission() { return $this->hasOne(InsuranceFormSubmission::class)->ofMany('version','max'); }
    public function getStateLabelAttribute(): string
    {
        if (!$this->signature_required) return 'Non richiede firma';
        return ['received'=>'Firma da controllare','validated'=>'Firma controllata','rejected'=>'Da correggere'][$this->latestSubmission?->status] ?? 'Firma mancante';
    }
}
