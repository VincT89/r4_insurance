<?php

namespace App\Support;

class CommunicationSelectionScope
{
    public static function forFilters(array $filters, string $range): string
    {
        /** Empty form fields and omitted query parameters describe the same search, including when returning from review. */
        $filters = array_map('strval', array_filter($filters, fn ($value) => $value !== null && $value !== ''));
        ksort($filters);
        return hash('sha256', json_encode([$filters, $range, today()->toDateString()], JSON_THROW_ON_ERROR));
    }
}
