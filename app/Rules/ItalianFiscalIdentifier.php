<?php

namespace App\Rules;

use App\Support\FiscalIdentifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ItalianFiscalIdentifier implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !FiscalIdentifier::valid($value, $attribute)) {
            $fail($attribute === 'vat_number'
                ? 'Partita IVA non valida. Controlla le 11 cifre inserite.'
                : 'Codice fiscale non valido. Controlla i 16 caratteri, oppure le 11 cifre del codice numerico.');
        }
    }
}
