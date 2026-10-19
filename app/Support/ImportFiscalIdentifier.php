<?php

namespace App\Support;

final class ImportFiscalIdentifier
{
    // Only format and checksum; this does not verify an identity with a fiscal registry.
    public static function valid(string $value): bool
    {
        if (preg_match('/^[0-9]{11}$/D', $value)) {
            $sum = 0;
            for ($i = 0; $i < 10; $i++) {
                $digit = (int) $value[$i] * ($i % 2 ? 2 : 1);
                $sum += intdiv($digit, 10) + $digit % 10;
            }
            return (10 - $sum % 10) % 10 === (int) $value[10];
        }
        if (!preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/D', $value)) return false;
        $odd = array_combine(str_split('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), [1,0,5,7,9,13,15,17,19,21,1,0,5,7,9,13,15,17,19,21,2,4,18,20,11,3,6,8,12,14,16,10,22,25,24,23]);
        $sum = 0;
        for ($i = 0; $i < 15; $i++) $sum += $i % 2 === 0 ? $odd[$value[$i]] : (ctype_digit($value[$i]) ? (int) $value[$i] : ord($value[$i]) - 65);
        return chr(65 + $sum % 26) === $value[15];
    }
}
