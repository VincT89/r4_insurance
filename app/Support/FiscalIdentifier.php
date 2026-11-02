<?php

namespace App\Support;

final class FiscalIdentifier
{
    public static function normalize(string $value, string $field): string
    {
        $value = strtoupper(preg_replace('/\s+/', '', $value));
        return $field === 'vat_number' && preg_match('/^IT[0-9]{11}$/D', $value) ? substr($value, 2) : $value;
    }

    public static function valid(string $value, string $field): bool
    {
        if ($value === '00000000000') return false;
        if ($field === 'vat_number' && !preg_match('/^[0-9]{11}$/D', $value)) return false;
        /** Il controllo formale non certifica l'esistenza del soggetto nell'Anagrafe tributaria. */
        return ImportFiscalIdentifier::valid($value);
    }
}
