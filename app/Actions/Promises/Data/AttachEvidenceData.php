<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\AttachmentCategory;
use App\Domain\StringNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class AttachEvidenceData
{
    private function __construct(
        public ?UploadedFile $file,
        public ?string $linkUrl,
        public AttachmentCategory $category,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'file' => [
                'nullable',
                'file',
                'max:10240',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp,text/plain,text/csv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'linkUrl' => ['nullable', 'url', 'max:2048'],
            'category' => ['required', Rule::enum(AttachmentCategory::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input, ?UploadedFile $file = null): self
    {
        /** @var array{file:UploadedFile|null, linkUrl:string|null, category:string} $validated */
        $validated = Validator::make(array_merge($input, ['file' => $file]), self::rules())->validate();
        $file = $validated['file'] ?? null;
        $linkUrl = StringNormalizer::optionalTrimmed($validated['linkUrl'] ?? null);

        if ($file === null && $linkUrl === null) {
            throw ValidationException::withMessages(['evidence' => 'Add a file or link.']);
        }

        if ($file !== null && $linkUrl !== null) {
            throw ValidationException::withMessages(['evidence' => 'Choose a file or link, not both.']);
        }

        return new self($file, $linkUrl, AttachmentCategory::from($validated['category']));
    }
}
