<?php

namespace App\Domain;

final class StringNormalizer
{
    public static function trimmed(string $value): string
    {
        return trim($value);
    }

    public static function optionalTrimmed(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }

    public static function uppercase(string $value): string
    {
        return strtoupper($value);
    }

    public static function uppercaseTrimmed(string $value): string
    {
        return strtoupper(trim($value));
    }

    public static function lowercaseTrimmed(string $value): string
    {
        return strtolower(trim($value));
    }

    public static function lowercase(string $value): string
    {
        return strtolower($value);
    }
}
