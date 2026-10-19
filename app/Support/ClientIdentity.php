<?php

namespace App\Support;

use Illuminate\Support\Str;

class ClientIdentity
{
    public static function nameKey(?string $name): ?string
    {
        $key = Str::lower(Str::ascii(Str::squish($name ?? '')));

        return $key === '' ? null : $key;
    }
}
