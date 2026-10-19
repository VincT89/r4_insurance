<?php

namespace App\Support;

use App\Models\ClientAsset;
use App\Models\ClientConsent;
use App\Models\ClientInteraction;

class ClientRegistryFields
{
    public const SECTIONS = ['contacts' => 'Referente', 'assets' => 'Bene assicurabile', 'interactions' => 'Contatto o nota', 'consents' => 'Consenso documentato'];

    public const TABS = ['contacts' => 'network', 'assets' => 'assets', 'interactions' => 'history', 'consents' => 'consents'];

    /** Field label, input type, required, length or options. */
    public static function for(string $section): array
    {
        return match ($section) {
            'contacts' => [
                'name' => ['Nome e cognome', 'text', true, 255], 'role' => ['Ruolo', 'text', false, 255],
                'email' => ['Email', 'email', false, 255], 'phone' => ['Telefono', 'tel', false, 50],
                'mobile' => ['Cellulare', 'tel', false, 50], 'notes' => ['Note', 'textarea', false, 10000],
            ],
            'assets' => [
                'asset_type' => ['Tipo di bene', 'select', true, ClientAsset::TYPES],
                'name' => ['Nome del bene', 'text', true, 255], 'category' => ['Tipologia / descrizione', 'text', true, 150],
                'identifier' => ['Identificativo, se presente', 'text', false, 255],
                'address' => ['Indirizzo dell’immobile', 'text', false, 255], 'city' => ['Città', 'text', false, 100],
                'province' => ['Provincia', 'text', false, 5], 'postal_code' => ['CAP', 'text', false, 10],
                'country' => ['Paese', 'text', false, 100], 'notes' => ['Note', 'textarea', false, 10000],
            ],
            'interactions' => [
                'event_at' => ['Data e ora del contatto', 'datetime-local', true, null],
                'channel' => ['Canale', 'select', true, ClientInteraction::CHANNELS],
                'subject' => ['Oggetto', 'text', true, 255], 'notes' => ['Dettaglio', 'textarea', true, 10000],
                'outcome' => ['Esito', 'textarea', false, 500],
            ],
            'consents' => [
                'kind' => ['Tipo di consenso', 'select', true, ClientConsent::KINDS],
                'purpose' => ['Finalità per altro consenso', 'text', false, 200],
                'scope' => ['Ambito', 'select', true, ClientConsent::SCOPES],
                'status' => ['Esito documentato', 'select', true, ClientConsent::STATUSES],
                'event_at' => ['Data e ora della dichiarazione', 'datetime-local', true, null],
                'signer_name' => ['Nome e cognome del dichiarante', 'text', true, 255],
                'signer_role' => ['Qualità / ruolo del dichiarante', 'text', false, 255],
                'notice_reference' => ['Modulo o informativa: titolo e versione / data', 'text', true, 255],
                'evidence_reference' => ['Riferimento alla prova conservata', 'textarea', false, 10000],
                'notes' => ['Note', 'textarea', false, 10000],
            ],
            default => abort(404),
        };
    }
}
