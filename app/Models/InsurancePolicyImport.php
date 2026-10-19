<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsurancePolicyImport extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['source_rows' => 'array', 'source_payload' => 'array', 'issues' => 'array']; }
    public function policy(): BelongsTo { return $this->belongsTo(InsurancePolicy::class, 'insurance_policy_id'); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\MorphMany { return $this->morphMany(AuditLog::class, 'auditable'); }
    public function getStatusLabelAttribute(): string { return $this->status === 'imported' ? 'Importata' : 'In sospeso'; }
    public function getIssueLabelsAttribute(): array
    {
        $labels = [
            'numero_polizza_ripetuto' => 'Più righe con lo stesso numero: verificare rate, rinnovi e storico.',
            'stessa_polizza_intestatari_diversi' => 'Stessa compagnia e numero associati a contraenti diversi.',
            'cliente_non_collegabile' => 'Anagrafica assente, in sospeso o non più disponibile.',
            'identita_cliente_da_verificare' => 'Identificativo o nome del contraente da verificare.',
            'classificazione_da_confermare' => 'Ramo o sottoramo ancora da verificare.',
            'collegamento_catalogo_mancante' => 'Ramo o sottoramo non disponibile nel catalogo.',
            'fornitore_non_collegabile' => 'Compagnia, agenzia o relativo mandato non disponibile.',
            'record_esistente_da_confrontare' => 'Polizza già presente: nessuna sovrascrittura o unificazione automatica.',
            'record_modificato_dopo_importazione' => 'Fonte cambiata dopo la precedente importazione.',
            'scadenza_precedente_decorrenza' => 'La scadenza precede la decorrenza.',
            'premio_non_valido' => 'Premio annuale o premio rata non valido.',
            'dati_polizza_non_validi' => 'Campi obbligatori, lunghezze, date o importi non validi.',
        ];
        $registry = new RegistryImportEntry;
        return array_map(function ($code) use ($labels, $registry) {
            $registry->issues = [$code];
            return $labels[$code] ?? $registry->issue_labels[0];
        }, $this->issues ?? []);
    }
}
