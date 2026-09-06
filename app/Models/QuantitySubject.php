<?php

namespace App\Models;

use Database\Factories\QuantitySubjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuantitySubject extends Model
{
    /** @use HasFactory<QuantitySubjectFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $primaryKey = 'obligation_id';

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['obligation_id', 'name', 'total', 'unit', 'is_fractionable'];

    protected function casts(): array
    {
        return ['total' => 'decimal:4', 'is_fractionable' => 'boolean'];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class, 'obligation_id');
    }

    /** @return HasMany<QuantityReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(QuantityReturn::class, 'obligation_id', 'obligation_id');
    }
}
