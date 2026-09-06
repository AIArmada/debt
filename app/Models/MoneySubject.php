<?php

namespace App\Models;

use Database\Factories\MoneySubjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneySubject extends Model
{
    /** @use HasFactory<MoneySubjectFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $primaryKey = 'obligation_id';

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['obligation_id', 'currency'];

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class, 'obligation_id');
    }
}
