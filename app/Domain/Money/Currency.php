<?php

namespace App\Domain\Money;

use App\Domain\StringNormalizer;

final class Currency
{
    /** @var array<string, string> */
    private const OPTIONS = [
        'MYR' => 'Malaysian ringgit',
        'USD' => 'US dollar',
        'SGD' => 'Singapore dollar',
        'IDR' => 'Indonesian rupiah',
        'THB' => 'Thai baht',
        'BND' => 'Brunei dollar',
        'PHP' => 'Philippine peso',
        'VND' => 'Vietnamese dong',
        'INR' => 'Indian rupee',
        'CNY' => 'Chinese yuan',
        'HKD' => 'Hong Kong dollar',
        'TWD' => 'New Taiwan dollar',
        'JPY' => 'Japanese yen',
        'KRW' => 'South Korean won',
        'AUD' => 'Australian dollar',
        'NZD' => 'New Zealand dollar',
        'EUR' => 'Euro',
        'GBP' => 'British pound',
        'CHF' => 'Swiss franc',
        'CAD' => 'Canadian dollar',
        'AED' => 'UAE dirham',
        'SAR' => 'Saudi riyal',
        'QAR' => 'Qatari riyal',
        'BDT' => 'Bangladeshi taka',
        'PKR' => 'Pakistani rupee',
        'LKR' => 'Sri Lankan rupee',
        'MMK' => 'Myanmar kyat',
        'KHR' => 'Cambodian riel',
        'LAK' => 'Lao kip',
        'NGN' => 'Nigerian naira',
        'KES' => 'Kenyan shilling',
        'EGP' => 'Egyptian pound',
        'TRY' => 'Turkish lira',
        'ZAR' => 'South African rand',
        'BRL' => 'Brazilian real',
        'MXN' => 'Mexican peso',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::OPTIONS);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return self::OPTIONS;
    }

    public static function isSupported(?string $code): bool
    {
        return $code !== null && array_key_exists(StringNormalizer::uppercase($code), self::OPTIONS);
    }

    public static function label(?string $code): string
    {
        return self::OPTIONS[StringNormalizer::uppercase((string) $code)] ?? (string) $code;
    }
}
