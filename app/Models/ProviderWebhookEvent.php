<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProviderWebhookEvent extends Model
{
    use HasUuids;

    protected $fillable = ['provider', 'external_event_id', 'event_type', 'status', 'payload', 'processed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
