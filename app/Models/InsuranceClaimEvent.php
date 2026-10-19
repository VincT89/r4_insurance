<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceClaimEvent extends Model
{
    public const KINDS = ['created'=>'Apertura sinistro','updated'=>'Dati aggiornati','status'=>'Cambio stato','note'=>'Nota e allegati','activity_added'=>'Attività programmata','activity_completed'=>'Attività completata','activity_rescheduled'=>'Attività riprogrammata','activity_cancelled'=>'Attività annullata'];
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array { return ['details'=>'encrypted:array','created_at'=>'datetime']; }
    public function files(): HasMany { return $this->hasMany(Attachment::class); }
    protected static function booted(): void
    {
        foreach (['updating','deleting'] as $event) static::$event(fn () => throw \Illuminate\Validation\ValidationException::withMessages(['claim'=>'Lo storico del sinistro non può essere modificato. Aggiungi una nuova annotazione.']));
    }
}
