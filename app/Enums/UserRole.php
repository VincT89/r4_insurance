<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';

    public function label(): string
    {
        return match($this) {
            self::Admin => 'Amministratore',
            self::Operator => 'Operatore',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Admin => 'var(--gray)',
            self::Operator => 'var(--blue)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Gestisce utenti, configurazioni, provvigioni, correzioni e registro attività. Può eliminare dati quando i vincoli di storico lo consentono.',
            self::Operator => 'Gestisce tutti i clienti, preventivi, polizze, documenti, sinistri, rate e incassi. Può annullare rate e stornare incassi con motivazione. Non può eliminare dati. Le operazioni sono registrate; le polizze si bloccano dal giorno dopo la fine lavorazione.',
        };
    }
}
