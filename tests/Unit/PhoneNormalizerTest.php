<?php

namespace Tests\Unit;

use App\Support\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    #[DataProvider('phoneNumbers')]
    public function test_normalizes_italian_phone_numbers_without_duplicating_the_country_code(
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, (new PhoneNormalizer())->normalize($input));
    }

    public static function phoneNumbers(): array
    {
        return [
            'mobile with explicit country code' => ['+39 333 1234567', '393331234567'],
            'landline with explicit country code' => ['+39 06 987654', '3906987654'],
            'international prefix with double zero' => ['0039 06 987654', '3906987654'],
            'domestic mobile' => ['333 1234567', '393331234567'],
            'domestic landline' => ['06 987654', '3906987654'],
            'already normalized number' => ['393331234567', '393331234567'],
        ];
    }
}
