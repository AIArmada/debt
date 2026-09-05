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

    protected function originalFilename(): Attribute
    {
        return Attribute::get(function (): ?string {
            $media = $this->mediaFile();

            return $media?->getCustomProperty('original_filename') ?: $media?->file_name;
        });
    }

    protected function mimeType(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->mediaFile()?->mime_type);
    }

    protected function sizeBytes(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->mediaFile()?->size);
    }

    protected function checksum(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->mediaFile()?->getCustomProperty('checksum'));
    }

    protected function disk(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->mediaFile()?->disk);
    }
}
