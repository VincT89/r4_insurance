<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const ACTIONS = [
        'registry_import_completed',
        'quote_group_updated',
        'agency_email_attachments',
        'roles_migrated',
        'claim_created','claim_updated','claim_status','claim_note','claim_activity_added','claim_activity_completed','claim_activity_rescheduled','claim_activity_cancelled',
        'form_template_saved','form_template_approved','form_template_retired','forms_prepared','forms_received','forms_reviewed','coverage_checked',
        'commission_created','commission_revised','commission_voided','commission_calculated','commission_base_recorded','commission_accrued','commission_confirmed','commission_settled','commission_reversed','commission_adjusted',
        'quote_email_review','quote_email_sending','quote_email_sent','quote_email_uncertain','quote_email_resolved',
        'policy_work_closed', 'policy_correction_requested', 'policy_correction_resolved', 'policy_corrected',
        'quote_updated','titles_generated','title_created','title_cancelled','collection_recorded','collection_voided','policy_renewed','policy_recorded',
        'created',
        'updated',
        'mandates_changed',
        'policy_import_consolidated',
        'policy_operational_details',
        'deleted',
        'status_changed',
        'assigned',
        'unassigned',
        'uploaded_attachment',
        'deleted_attachment',
        'payment_registered',
        'registered_payment',
        'deleted_payment',
        'password_reset',
        'login',
        'logout',
    ];

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'description',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getDisplayActionTextAttribute(): string
    {
        $entity = $this->displayEntityLabel();
        $status = $this->displayStatusLabel($this->new_values['status'] ?? null);

        return match ($this->action) {
            'registry_import_completed' => 'ha importato nuove anagrafiche da CSV o Excel dopo il controllo dell’anteprima.',
            'quote_group_updated' => 'ha configurato un gruppo di compagnie per le richieste di preventivo.',
            'roles_migrated' => 'ha convertito il ruolo precedente in Operatore.',
            'claim_created' => 'ha aperto un sinistro.',
            'claim_updated' => 'ha modificato i dati di un sinistro conservandone lo storico.',
            'claim_status' => 'ha cambiato lo stato di un sinistro.',
            'claim_note' => 'ha aggiunto note o documenti a un sinistro.',
            'claim_activity_added' => 'ha programmato un’attività per un sinistro.',
            'claim_activity_completed' => 'ha completato un’attività per un sinistro.',
            'claim_activity_rescheduled' => 'ha riprogrammato un’attività per un sinistro.',
            'claim_activity_cancelled' => 'ha annullato un’attività per un sinistro.',
            'form_template_saved' => 'ha salvato una bozza di modello documentale.',
            'form_template_approved' => 'ha approvato un modello documentale per l’uso.',
            'form_template_retired' => 'ha ritirato un modello conservando lo storico.',
            'forms_prepared' => 'ha preparato un fascicolo di moduli.',
            'forms_received' => 'ha caricato documenti firmati nel fascicolo.',
            'forms_reviewed' => 'ha registrato il controllo manuale delle firme.',
            'coverage_checked' => 'ha registrato un controllo della copertura RCA.',
            'commission_created' => 'ha configurato le condizioni provvigionali di un’agenzia.',
            'commission_revised' => 'ha revisionato le condizioni provvigionali conservando la versione precedente.',
            'commission_voided' => 'ha annullato una configurazione provvigionale indicando il motivo.',
            'commission_calculated' => 'ha calcolato una provvigione conservando le condizioni applicate.',
            'commission_base_recorded' => 'ha registrato la base provvigionale di una rata.',
            'commission_accrued' => 'ha aggiornato la maturazione in base ai movimenti del premio.',
            'commission_confirmed' => 'ha registrato una conferma di maturazione del mandante.',
            'commission_settled' => 'ha registrato una liquidazione provvigionale.',
            'commission_reversed' => 'ha stornato un movimento provvigionale conservandone lo storico.',
            'commission_adjusted' => 'ha aggiornato una provvigione dopo l’annullamento del titolo.',
            'quote_email_review' => 'ha preparato una richiesta di preventivo da controllare.',
            'quote_email_sending' => 'ha confermato l’invio di una richiesta di preventivo.',
            'quote_email_sent' => 'ha inviato una richiesta di preventivo al server di posta.',
            'quote_email_uncertain' => 'ha ricevuto un esito da verificare per una richiesta di preventivo.',
            'quote_email_resolved' => 'ha verificato l’esito di una richiesta di preventivo.',
            'policy_work_closed' => 'ha completato la lavorazione di una polizza.',
            'policy_correction_requested' => 'ha segnalato un errore su una polizza all’admin.',
            'policy_correction_resolved' => 'ha registrato l’esito di una segnalazione.',
            'policy_corrected' => 'ha corretto una polizza dopo la fine lavorazione, indicando il motivo.',
            'quote_updated'=>'ha aggiornato una pratica preventivi.',
            'titles_generated','title_created'=>'ha registrato titoli di polizza.',
            'title_cancelled'=>'ha annullato un titolo conservandolo nello storico.',
            'collection_recorded'=>'ha registrato un incasso.',
            'collection_voided'=>'ha stornato un incasso.',
            'policy_renewed'=>'ha rinnovato una polizza e generato i titoli.',
            'policy_recorded'=>'ha registrato una polizza già stipulata.',
            'created' => "ha creato {$entity}.",
            'deleted' => "ha eliminato {$entity}.",
            'status_changed' => $status
                ? "ha impostato {$entity} come {$status}."
                : "ha aggiornato lo stato di {$entity}.",
            'updated' => "ha aggiornato {$entity}.",
            'assigned' => "ha assegnato {$entity}.",
            'unassigned' => "ha rimosso l'assegnazione di {$entity}.",
            'payment_registered', 'registered_payment' => "ha registrato un pagamento per {$entity}.",
            'deleted_payment' => "ha eliminato un pagamento da {$entity}.",
            'uploaded_attachment' => "ha caricato un allegato per {$entity}.",
            'deleted_attachment' => "ha eliminato un allegato da {$entity}.",
            'password_reset' => "ha reimpostato la password per {$entity}.",
            'login' => "ha effettuato l'accesso.",
            'logout' => "ha terminato la sessione.",
            'policy_import_consolidated' => 'ha accorpato le righe Excel equivalenti in una polizza da verificare.',
            'policy_operational_details' => 'ha applicato gli stati polizza e i dati di pagamento confermati.',
            'agency_email_attachments' => 'ha aggiornato gli allegati di un riepilogo da controllare.',
            'agency_email_review' => 'ha preparato un riepilogo da controllare.',
            'agency_email_sending' => 'ha confermato l’invio cumulativo.',
            'agency_email_sent' => 'ha inviato il riepilogo al server di posta.',
            'agency_email_uncertain' => 'ha ricevuto un esito di invio non confermato.',
            'agency_email_resolved' => 'ha registrato la verifica dell’esito di invio.',
            'email_settings_updated' => 'ha aggiornato la configurazione della casella email.',
            default => "ha aggiornato {$entity}.",
        };
    }

    public function getDisplayActionLabelAttribute(): string
    {
        return match ($this->action) {
            'registry_import_completed' => 'Importazione anagrafiche',
            'quote_group_updated' => 'Gruppi preventivi',
            'agency_email_attachments' => 'Allegati comunicazione',
            'roles_migrated' => 'Ruoli e permessi',
            'claim_created','claim_updated','claim_status','claim_note','claim_activity_added','claim_activity_completed','claim_activity_rescheduled','claim_activity_cancelled' => 'Sinistri',
            'form_template_saved','form_template_approved','form_template_retired','forms_prepared','forms_received','forms_reviewed' => 'Moduli e firme',
            'coverage_checked' => 'Verifica RCA',
            'commission_created','commission_revised','commission_voided','commission_calculated','commission_base_recorded','commission_accrued','commission_confirmed','commission_settled','commission_reversed','commission_adjusted' => 'Provvigioni',
            'quote_email_review','quote_email_sending','quote_email_sent','quote_email_uncertain','quote_email_resolved' => 'Email preventivo',
            'policy_work_closed' => 'Fine lavorazione',
            'policy_correction_requested', 'policy_correction_resolved', 'policy_corrected' => 'Correzione polizza',
            'quote_updated'=>'Preventivo',
            'titles_generated','title_created','title_cancelled'=>'Titoli',
            'collection_recorded','collection_voided'=>'Incassi',
            'policy_renewed'=>'Rinnovo polizza',
            'policy_recorded'=>'Registrazione polizza',
            'created' => 'Creazione',
            'deleted' => 'Eliminazione',
            'status_changed' => 'Cambio stato',
            'assigned', 'unassigned' => 'Assegnazione',
            'payment_registered', 'registered_payment', 'deleted_payment' => 'Pagamento',
            'uploaded_attachment', 'deleted_attachment' => 'Allegato',
            'password_reset' => 'Sicurezza',
            'login' => 'Accesso',
            'logout' => 'Uscita',
            'policy_import_consolidated' => 'Accorpamento polizza',
            'policy_operational_details' => 'Stato e pagamento polizza',
            'agency_email_review','agency_email_sending','agency_email_sent','agency_email_uncertain','agency_email_resolved','agency_email_draft' => 'Comunicazione email',
            'email_settings_updated' => 'Configurazione email',
            default => 'Aggiornamento',
        };
    }

    private function displayEntityLabel(): string
    {
        // Historical types are data, never class names to instantiate.
        $labels = [Client::class => 'il cliente', User::class => "l'utente", Attachment::class => "l'allegato", SupplierAgency::class => "l'agenzia", SupplierCompany::class => 'la compagnia', InsurancePolicy::class => 'la polizza'];
        $prefix = $labels[$this->auditable_type] ?? 'il record storico';
        $snapshot = array_merge($this->old_values ?? [], $this->new_values ?? []);
        $name = $snapshot['name'] ?? $snapshot['title'] ?? $snapshot['original_name'] ?? $snapshot['number'] ?? null;
        if (! $name && isset($labels[$this->auditable_type])) {
            $model = $this->auditable;
            $name = $model?->name ?? $model?->original_name ?? $model?->number;
        }
        return $name ? $prefix.' "'.$name.'"' : $prefix.' #'.$this->auditable_id;
    }

    private function displayStatusLabel(mixed $status): ?string
    {
        if (! is_string($status) || $status === '') {
            return null;
        }

        return match ($status) {
            'open' => 'Aperto',
            'in_progress' => 'In lavorazione',
            'waiting' => 'In attesa',
            'resolved' => 'Risolto',
            'closed' => 'Chiuso',
            'todo' => 'Da fare',
            'review' => 'In revisione',
            'done' => 'Completata',
            'draft' => 'Bozza',
            'issued' => 'Emessa',
            'partially_paid' => 'Parzialmente saldata',
            'paid' => 'Saldata',
            'overdue' => 'Scaduta',
            'cancelled' => 'Annullata',
            'active' => 'Attivo',
            'inactive' => 'Inattivo',
            'completed' => 'Completato',
            'paused' => 'In pausa',
            'on_hold' => 'In pausa',
            'blocked' => 'Bloccato',
            'waiting_photographer' => 'In attesa del fotografo',
            'photographer_rejected' => 'Rifiutato dal fotografo',
            'waiting_client' => 'In attesa del cliente',
            'client_rejected' => 'Rifiutato dal cliente',
            'client_confirmed' => 'Confermato dal cliente',
            'scheduled' => 'Pianificato',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
