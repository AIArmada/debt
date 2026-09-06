<?php

namespace App\Models;

use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $rate
 * @property Carbon $rated_on
 */
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['from_currency', 'to_currency', 'rate', 'rated_on', 'source'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:8', 'rated_on' => 'date'];
    }
}
