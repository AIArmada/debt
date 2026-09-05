<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class SafeUpload implements ValidationRule
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = [
        'csv', 'doc', 'docx', 'jpeg', 'jpg', 'm4a', 'mp3', 'mp4', 'mov',
        'pdf', 'png', 'rtf', 'txt', 'wav', 'webp', 'xls', 'xlsx', 'zip',
    ];

    /** @var list<string> */
    private const DENIED_EXTENSIONS = [
        'apk', 'app', 'appimage', 'bat', 'bash', 'bin', 'cmd', 'com', 'command',
        'cpl', 'crt', 'dll', 'dmg', 'docm', 'dotm', 'exe', 'gadget', 'hta', 'htm',
        'html', 'ipa', 'jar', 'js', 'jse', 'lnk', 'mjs', 'msi', 'msp', 'ocx',
        'paf', 'php', 'phar', 'phtml', 'pl', 'pptm', 'potm', 'ppam', 'ppsm',
        'ps1', 'ps1xml', 'psd1', 'psm1', 'py', 'pyc', 'rb', 'reg', 'scr', 'sh',
        'shtml', 'svg', 'sys', 'url', 'vbe', 'vbs', 'vxd', 'wasm', 'wsc', 'wsf',
        'wsh', 'xhtml', 'xlam', 'xlsm', 'xltm',
    ];

    /** @var list<string> */
    private const DENIED_MIME_TYPES = [
        'application/ecmascript',
        'application/javascript',
        'application/vnd.microsoft.portable-executable',
        'application/x-dosexec',
        'application/x-executable',
        'application/x-java-archive',
        'application/x-javascript',
        'application/x-msdownload',
        'application/x-msdos-program',
        'application/x-perl',
        'application/x-php',
        'application/x-powershell',
        'application/x-sh',
        'application/x-shellscript',
        'application/x-ruby',
        'application/wasm',
        'image/svg+xml',
        'text/html',
        'text/javascript',
        'text/x-perl',
        'text/x-php',
        'text/x-python',
        'text/x-ruby',
        'text/x-shellscript',
    ];

    public static function allowedExtensions(): string
    {
        return implode(',', self::ALLOWED_EXTENSIONS);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $filename = strtolower(basename(str_replace('\\', '/', $value->getClientOriginalName())));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === '' || ! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $fail('Only approved document and media file extensions can be uploaded.');

            return;
        }

        $filenameParts = explode('.', $filename);
        $nestedExtensions = array_slice($filenameParts, 0, -1);
        if (array_intersect($nestedExtensions, self::DENIED_EXTENSIONS) !== []) {
            $fail('Executable and active-content files cannot be uploaded. Archive the material if you need to preserve it.');

            return;
        }

        $mimeType = strtolower((string) ($value->getMimeType() ?: $value->getClientMimeType()));
        if (in_array($mimeType, self::DENIED_MIME_TYPES, true)) {
            $fail('This file type can execute code or active content and cannot be uploaded.');
        }
    }

    public static function sanitizedFilename(UploadedFile $file): string
    {
        $filename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $filename = preg_replace('/[\x00-\x1F\x7F]+/', '_', $filename) ?: 'uploaded-file';

        return trim($filename, ' .') ?: 'uploaded-file';
    }
}
