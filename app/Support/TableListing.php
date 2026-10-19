<?php

namespace App\Support;

class TableListing
{
    public static function perPage(int $normal = 20): int
    {
        // Same controller, filters and authorization; only the batch size changes.
        return request()->boolean('_table_all') && request()->header('X-Table-Export') === '1' ? 500 : $normal;
    }
}
