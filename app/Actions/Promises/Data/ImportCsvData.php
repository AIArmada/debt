<?php

namespace App\Actions\Promises\Data;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

final readonly class ImportCsvData
{
    private function __construct(public UploadedFile $file) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return ['file' => ['required', 'file', 'max:10240', 'mimes:csv,txt']];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'file.required' => __('promises.csv_file_required'),
            'file.file' => __('promises.csv_file_invalid'),
            'file.max' => __('promises.csv_file_too_large'),
            'file.mimes' => __('promises.csv_file_type_invalid'),
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules(), self::messages())->validate();

        return new self($validated['file']);
    }
}
