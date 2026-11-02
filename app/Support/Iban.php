<?php

namespace App\Support;

final class Iban
{
    /** Formati nazionali del registro SWIFT ISO 13616, release 103: https://www.swift.com/swift-resource/9606/download?language=en */
    private const FORMATS = [
        'AD' => '2!n4!n4!n12!c',
        'AE' => '2!n3!n16!n',
        'AL' => '2!n8!n16!c',
        'AT' => '2!n5!n11!n',
        'AZ' => '2!n4!a20!c',
        'BA' => '2!n3!n3!n8!n2!n',
        'BE' => '2!n3!n7!n2!n',
        'BG' => '2!n4!a4!n2!n8!c',
        'BH' => '2!n4!a14!c',
        'BI' => '2!n5!n5!n11!n2!n',
        'BR' => '2!n8!c5!n10!n1!c1!c',
        'BY' => '2!n4!c4!n16!c',
        'CH' => '2!n5!n12!c',
        'CR' => '2!n4!n14!n',
        'CY' => '2!n3!n5!n16!c',
        'CZ' => '2!n4!n6!n10!n',
        'DE' => '2!n8!n10!n',
        'DJ' => '2!n5!n5!n11!n2!n',
        'DK' => '2!n4!n9!n1!n',
        'DO' => '2!n4!c20!n',
        'EE' => '2!n2!n14!n',
        'EG' => '2!n4!n4!n17!n',
        'ES' => '2!n4!n4!n1!n1!n10!n',
        'FI' => '2!n3!n11!n',
        'FK' => '2!n2!a12!n',
        'FO' => '2!n4!n9!n1!n',
        'FR' => '2!n5!n5!n11!c2!n',
        'GB' => '2!n4!a6!n8!n',
        'GE' => '2!n2!a16!n',
        'GI' => '2!n4!a15!c',
        'GL' => '2!n4!n9!n1!n',
        'GR' => '2!n3!n4!n16!c',
        'GT' => '2!n4!c20!c',
        'HN' => '2!n4!a20!n',
        'HR' => '2!n7!n10!n',
        'HU' => '2!n3!n4!n1!n15!n1!n',
        'IE' => '2!n4!a6!n8!n',
        'IL' => '2!n3!n3!n13!n',
        'IQ' => '2!n4!a3!n12!n',
        'IS' => '2!n4!n2!n6!n10!n',
        'IT' => '2!n1!a5!n5!n12!c',
        'JO' => '2!n4!a4!n18!c',
        'KW' => '2!n4!a22!c',
        'KZ' => '2!n3!n13!c',
        'LB' => '2!n4!n20!c',
        'LC' => '2!n4!a24!c',
        'LI' => '2!n5!n12!c',
        'LT' => '2!n5!n11!n',
        'LU' => '2!n3!n13!c',
        'LV' => '2!n4!a13!c',
        'LY' => '2!n3!n3!n15!n',
        'MC' => '2!n5!n5!n11!c2!n',
        'MD' => '2!n2!c18!c',
        'ME' => '2!n3!n13!n2!n',
        'MK' => '2!n3!n10!c2!n',
        'MN' => '2!n4!n12!n',
        'MR' => '2!n5!n5!n11!n2!n',
        'MT' => '2!n4!a5!n18!c',
        'MU' => '2!n4!a2!n2!n12!n3!n3!a',
        'NI' => '2!n4!a20!n',
        'NL' => '2!n4!a10!n',
        'NO' => '2!n4!n6!n1!n',
        'OM' => '2!n3!n16!c',
        'PK' => '2!n4!a16!c',
        'PL' => '2!n8!n16!n',
        'PS' => '2!n4!a21!c',
        'PT' => '2!n4!n4!n11!n2!n',
        'QA' => '2!n4!a21!c',
        'RO' => '2!n4!a16!c',
        'RS' => '2!n3!n13!n2!n',
        'RU' => '2!n9!n5!n15!c',
        'SA' => '2!n2!n18!c',
        'SC' => '2!n4!a2!n2!n16!n3!a',
        'SD' => '2!n2!n12!n',
        'SE' => '2!n3!n16!n1!n',
        'SI' => '2!n5!n8!n2!n',
        'SK' => '2!n4!n6!n10!n',
        'SM' => '2!n1!a5!n5!n12!c',
        'SO' => '2!n4!n3!n12!n',
        'ST' => '2!n4!n4!n11!n2!n',
        'SV' => '2!n4!a20!n',
        'TL' => '2!n3!n14!n2!n',
        'TN' => '2!n2!n3!n13!n2!n',
        'TR' => '2!n5!n1!n16!c',
        'UA' => '2!n6!n19!c',
        'VA' => '2!n3!n15!n',
        'VG' => '2!n4!a16!n',
        'XK' => '2!n4!n10!n2!n',
        'YE' => '2!n4!a4!n18!c',
    ];

    public static function normalize(string $value): string
    {
        return strtoupper(preg_replace('/\s+/u', '', $value));
    }

    public static function valid(string $value): bool
    {
        $value = self::normalize($value);
        if (strlen($value) > 34 || !preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/D', $value)) return false;
        $format = self::FORMATS[substr($value, 0, 2)] ?? null;
        if (!$format) return false;
        $pattern = preg_replace_callback('/([0-9]+)!([anc])/', fn ($part) => match ($part[2]) { 'a' => '[A-Z]', 'n' => '[0-9]', 'c' => '[A-Z0-9]' }.'{'.$part[1].'}', $format);
        if (!preg_match('/^[A-Z]{2}'.$pattern.'$/D', $value)) return false;
        if ((int) substr($value, 2, 2) < 2 || (int) substr($value, 2, 2) > 98) return false;
        $remainder = 0;
        /** Il resto viene calcolato cifra per cifra per evitare conversioni in interi troppo grandi. */
        foreach (str_split(substr($value, 4).substr($value, 0, 4)) as $character) {
            $digits = ctype_digit($character) ? $character : (string) (ord($character) - 55);
            foreach (str_split($digits) as $digit) $remainder = ($remainder * 10 + (int) $digit) % 97;
        }
        return $remainder === 1;
    }
}
