<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property-read string|null $original_filename
 * @property-read string|null $mime_type
 * @property-read int|null $size_bytes
 * @property-read string|null $checksum
 * @property-read string|null $disk
 * @property Carbon|null $captured_on
 */
class Document extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia;

    public const MEDIA_COLLECTION = 'evidence-file';

    protected $fillable = [
        'profile_id', 'uploaded_by_user_id', 'status', 'evidence_type', 'title', 'source',
        'external_url', 'content', 'captured_on',
        'category', 'verification_status', 'ocr_status', 'ocr_provider', 'extracted_text',
        'ocr_error', 'ocr_completed_at',
    ];

    protected function casts(): array
    {
        return ['captured_on' => 'date', 'ocr_completed_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION)
            ->useDisk('local')
            ->singleFile();
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

    /** @return HasMany<DocumentLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class);
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

    /** @return Attribute<?string, mixed> */
    protected function checksum(): Attribute
    {
        return Attribute::make(get: fn (mixed $value, array $attributes): ?string => $this->mediaFile()?->getCustomProperty('checksum'));
    }

    /** @return Attribute<?string, mixed> */
    protected function disk(): Attribute
    {
        return Attribute::make(get: fn (mixed $value, array $attributes): ?string => $this->mediaFile()?->disk);
    }
}
