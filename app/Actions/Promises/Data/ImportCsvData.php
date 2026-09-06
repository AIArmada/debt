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

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->validate();

        return new self($validated['file']);
    }
}
