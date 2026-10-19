<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistryImportEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['source_rows' => 'array', 'issues' => 'array', 'deferred_data' => 'array'];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'imported' => 'Importato',
            'linked' => 'Collegato a scheda esistente',
            default => 'In sospeso',
        };
    }

    public function getKindLabelAttribute(): string
    {
        return ['client' => 'Cliente', 'agency' => 'Agenzia', 'company' => 'Compagnia'][$this->kind] ?? $this->kind;
    }

    public function getIssueLabelsAttribute(): array
    {
        $labels = [
            'anagrafiche_discordanti_stesso_identificativo' => 'Righe anagrafiche discordanti: scegliere i dati corretti prima di importare.',
            'cliente_assente_in_anagrafica' => 'Presente solo nel file polizze: anagrafica da confermare.',
            'anagrafica_incompleta' => 'Scheda creata con i soli dati del contraente: recapiti e dati mancanti da completare.',
            'titolo_da_verificare' => 'Titoli diversi nella fonte: nessun titolo scelto automaticamente.',
            'identificativo_fiscale_da_verificare' => 'Identificativo fiscale mancante o formalmente non valido.',
            'distinguere_codice_fiscale_e_partita_iva' => 'Verificare se il codice numerico è un codice fiscale o una partita IVA.',
            'nome_discordante_tra_export' => 'Il nome nel file polizze è diverso: importato il nome del file Anagrafica.',
            'email_da_verificare' => 'Email non valida: conservata tra i dati originali.',
            'email_condivisa' => 'Email usata da più anagrafiche: recapito conservato, senza unificare i clienti.',
            'provincia_da_verificare' => 'Provincia non espressa come sigla: originale conservato.',
            'record_esistente_da_confrontare' => 'Possibile anagrafica già presente: nessuna unificazione automatica.',
            'denominazione_compagnia_da_confermare' => 'Nome e ruolo riportati dal vecchio archivio, da verificare.',
            'nome_agenzia_da_confermare' => 'La fonte riporta solo il nome dell’agenzia: completare e verificare i dati identificativi.',
            'record_modificato_dopo_importazione' => 'La fonte è cambiata dopo la precedente importazione: verificare le differenze.',
            'record_importato_non_disponibile' => 'La precedente anagrafica importata è stata rimossa: verificare prima di ricrearla.',
        ];
        return array_map(fn ($code) => $labels[$code] ?? ucfirst(str_replace('_', ' ', $code)), $this->issues ?? []);
    }
}
