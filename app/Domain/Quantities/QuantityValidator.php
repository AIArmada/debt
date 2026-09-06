<?php

namespace App\Domain\Quantities;

use App\Models\QuantitySubject;
use Illuminate\Validation\ValidationException;

final class QuantityValidator
{
    public static function isZero(string $quantity): bool
    {
        return preg_match('/^0+(?:\.0{1,4})?$/', $quantity) === 1;
    }

    /**
     * @param  numeric-string  $remaining
     * @return numeric-string
     */
    public function assertCanReturn(QuantitySubject $subject, string $quantity, string $remaining): string
    {
        if (! is_numeric($quantity)) {
            throw ValidationException::withMessages(['quantity' => 'Enter a valid quantity.']);
        }

        if (self::isZero($quantity)) {
            throw ValidationException::withMessages(['quantity' => 'Enter a positive quantity.']);
        }

        if (! $subject->is_fractionable && preg_match('/\.\d*[1-9]/', $quantity) === 1) {
            throw ValidationException::withMessages(['quantity' => 'This promise only accepts whole quantities.']);
        }

        if (bccomp($quantity, $remaining, 4) > 0) {
            throw ValidationException::withMessages(['quantity' => 'The return exceeds the outstanding quantity.']);
        }

        return $quantity;
    }
}
