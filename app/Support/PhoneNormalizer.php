<?php

namespace App\Support;

class PhoneNormalizer
{
    public function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $phone = trim($phone);
        $normalized = preg_replace('/[^0-9]/', '', $phone);

        if (! $normalized) {
            return null;
        }

        // Un prefisso esplicito va rispettato anche per i numeri fissi, che
        // mantengono lo zero del distretto dopo il codice paese (+39 06...).
        if (str_starts_with($phone, '+')) {
            return $normalized;
        }

        if (str_starts_with($normalized, '00')) {
            return substr($normalized, 2);
        }

        if (str_starts_with($normalized, '39') && strlen($normalized) >= 11) {
            return $normalized;
        }

        if (str_starts_with($normalized, '3') || str_starts_with($normalized, '0')) {
            return '39' . $normalized;
        }

        return $normalized;
    }
}
