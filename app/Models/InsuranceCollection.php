<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InsuranceCollection extends Model
{
    public const REFUND_METHODS=['Bonifico','Assegno','Contanti','Carta di credito'];
    public static function totalsSql(string $table = ''): string
    {
        $prefix = $table === '' ? '' : $table.'.';
        $amount = $prefix.'amount_cents'; $direction = $prefix.'direction'; $recipient = $prefix.'recipient';
        return "COALESCE(SUM(CASE WHEN $direction='refund' THEN -$amount ELSE $amount END),0) as total, COALESCE(SUM(CASE WHEN $direction='receipt' THEN $amount ELSE 0 END),0) as receipts, COALESCE(SUM(CASE WHEN $direction='refund' THEN $amount ELSE 0 END),0) as refunds, COALESCE(SUM(CASE WHEN $recipient='our_agency' THEN CASE WHEN $direction='refund' THEN -$amount ELSE $amount END ELSE 0 END),0) as ours";
    }
    public const RECIPIENTS=['our_agency'=>'Nostra agenzia','agency'=>'Agenzia intermediaria','company'=>'Compagnia'];
    public const DOCUMENT_TYPES=['record'=>'Registrazione del pagamento','receipt'=>'Ricevuta','transfer'=>'Contabile bonifico','card'=>'Ricevuta carta','cash'=>'Registrazione contanti','other'=>'Altro documento'];
    protected $guarded=['id'];
    protected function casts(): array {return ['received_on'=>'date','voided_at'=>'datetime','amount_cents'=>'integer'];}
    public function title(){return $this->belongsTo(InsuranceTitle::class,'insurance_title_id');}
    public function consultant(){return $this->belongsTo(User::class,'consultant_id');}
    public function documents(){return $this->hasMany(Attachment::class);}
}
