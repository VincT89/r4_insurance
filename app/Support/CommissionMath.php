<?php

namespace App\Support;

class CommissionMath
{
    public static function percentage(int $cents, int $units): int
    {
        if ($cents < 0 || $cents > 999999999999 || $units < 0 || $units > 1000000) throw new \InvalidArgumentException('Importo o aliquota non validi.');
        return intdiv($cents * $units + 500000, 1000000);
    }

    public static function proportion(int $amount, int $paid, int $total): int
    {
        if ($amount < 0 || $amount > 999999999999 || $paid < 0 || $paid > $total || $total < 1 || $total > 999999999999) throw new \InvalidArgumentException('Frazione non valida.');
        /** Long division keeps cents exact even when amount times paid would overflow a 64-bit integer. */
        $quotient = 0; $remainder = 0;
        foreach (str_split(decbin($paid)) as $bit) {
            $quotient *= 2;
            $remainder = $remainder * 2 + ($bit === '1' ? $amount : 0);
            $quotient += intdiv($remainder, $total);
            $remainder %= $total;
        }
        return $quotient + ($remainder * 2 >= $total ? 1 : 0);
    }
}
