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
            self::Admin => 'Gestisce utenti, posta, impostazioni, provvigioni e registro attività. Può correggere o eliminare dati quando lo storico lo consente.',
            self::Operator => 'Gestisce tutti i clienti, preventivi, polizze, documenti, sinistri, titoli, incassi, comunicazioni e modelli. Può annullare titoli e stornare incassi con motivazione. Non può eliminare dati. Le polizze si bloccano dal giorno dopo la fine lavorazione.',
        };
    }
}
