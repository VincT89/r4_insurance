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
    public function signatureActionLabel(): string
    {
        return match ($this->latestSubmission?->status) {
            'received' => 'Controlla copia ricevuta',
            'rejected' => 'Correggi la firma',
            default => 'Firma o carica copia',
        };
    }
    public function signatureActionUrl(): string
    {
        $package = $this->package;
        return $package->insurance_policy_id
            ? route('clients.policies.show',[$package->client_id,$package->insurance_policy_id,'tab'=>'forms']).'#policy-signature-'.$this->id
            : route('clients.forms.show',[$package->client_id,$package]).'#form-document-'.$this->id;
    }
    public function getStateLabelAttribute(): string
    {
        if (!$this->signature_required) return 'Non richiede firma';
        return ['received'=>'Firma da controllare','validated'=>'Firma controllata','rejected'=>'Da correggere'][$this->latestSubmission?->status] ?? 'Firma mancante';
    }
}
