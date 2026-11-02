<?php

namespace App\Rules;

use App\Support\Iban;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BankIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !Iban::valid($value)) $fail('IBAN non valido. Controlla il paese, la lunghezza e i caratteri inseriti.');
    }
}
