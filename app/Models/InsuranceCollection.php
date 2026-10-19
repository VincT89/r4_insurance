<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InsuranceCollection extends Model
{
    public const RECIPIENTS=['our_agency'=>'Nostra agenzia','agency'=>'Agenzia intermediaria','company'=>'Compagnia'];
    public const DOCUMENT_TYPES=['receipt'=>'Ricevuta','transfer'=>'Contabile bonifico','card'=>'Ricevuta carta','cash'=>'Registrazione contanti','other'=>'Altro documento'];
    protected $guarded=['id'];
    protected function casts(): array {return ['received_on'=>'date','voided_at'=>'datetime','amount_cents'=>'integer'];}
    public function title(){return $this->belongsTo(InsuranceTitle::class,'insurance_title_id');}
    public function consultant(){return $this->belongsTo(User::class,'consultant_id');}
    public function documents(){return $this->hasMany(Attachment::class);}
}
