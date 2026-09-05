<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property-read string|null $original_filename
 * @property-read string|null $mime_type
 * @property-read int|null $size_bytes
 */
class BankImport extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia;

    public const MEDIA_COLLECTION = 'bank-statement';

    protected $fillable = ['profile_id', 'uploaded_by_user_id', 'format', 'currency', 'status', 'row_count', 'matched_count', 'error_message'];

    protected function casts(): array
    {
        return ['row_count' => 'integer', 'matched_count' => 'integer'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION)
            ->useDisk('local')
            ->singleFile();
    }

    public function mediaFile(): ?Media
    {
        return $this->getFirstMedia(self::MEDIA_COLLECTION);
    }

    /** @return Attribute<?string, mixed> */
    protected function originalFilename(): Attribute
    {
        return Attribute::make(get: function (mixed $value, array $attributes): ?string {
            $media = $this->mediaFile();

            return $media?->getCustomProperty('original_filename') ?: $media?->file_name;
        });
    }

    /** @return Attribute<?string, mixed> */
    protected function mimeType(): Attribute
    {
        return Attribute::make(get: fn (mixed $value, array $attributes): ?string => $this->mediaFile()?->mime_type);
    }

    /** @return Attribute<?int, mixed> */
    protected function sizeBytes(): Attribute
    {
        return Attribute::make(get: fn (mixed $value, array $attributes): ?int => $this->mediaFile()?->size);
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /** @return HasMany<BankImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(BankImportRow::class);
    }
}
