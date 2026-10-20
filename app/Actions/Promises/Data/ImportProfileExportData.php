<?php

namespace App\Actions\Promises\Data;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

final readonly class ImportProfileExportData
{
    private function __construct(public UploadedFile $file) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return ['file' => ['required', 'file', 'max:102400', 'mimes:zip']];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{file: UploadedFile} $validated */
        $validated = Validator::make($input, self::rules(), [
            'file.required' => __('promises.restore_file_required'),
            'file.file' => __('promises.restore_file_invalid'),
            'file.max' => __('promises.restore_file_too_large'),
            'file.mimes' => __('promises.restore_zip_required'),
        ])->validate();

        return new self($validated['file']);
    }
}
