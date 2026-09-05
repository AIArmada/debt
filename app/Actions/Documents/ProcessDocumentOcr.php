<?php

namespace App\Actions\Documents;

use App\Models\Document;

class ProcessDocumentOcr
{
    public function handle(Document $document): Document
    {
        $media = $document->mediaFile();

        if ($document->evidence_type !== 'file' || $media === null || $media->mime_type === null) {
            return $document->update(['ocr_status' => 'not_applicable', 'ocr_provider' => null, 'ocr_error' => null])
                ? $document->refresh()
                : $document;
        }

        $isPdf = $media->mime_type === 'application/pdf';
        $isImage = str_starts_with($media->mime_type, 'image/');

        if (! $isPdf && ! $isImage) {
            $document->update(['ocr_status' => 'not_applicable', 'ocr_provider' => null, 'ocr_error' => null]);

            return $document->refresh();
        }

        $document->update(['ocr_status' => 'processing', 'ocr_error' => null]);
        $binary = $this->binaryFor($isPdf ? 'pdftotext' : 'tesseract');

        if ($binary === null) {
            $document->update(['ocr_status' => 'unavailable', 'ocr_provider' => 'local', 'ocr_error' => 'Install pdftotext or tesseract on the application server to enable local OCR.']);

            return $document->refresh();
        }

        $path = $media->getPath();
        $command = $isPdf
            ? escapeshellarg($binary).' '.escapeshellarg($path).' -'
            : escapeshellarg($binary).' '.escapeshellarg($path).' stdout';
        [$output, $error, $exitCode] = $this->run($command);

        if ($exitCode !== 0) {
            $document->update(['ocr_status' => 'failed', 'ocr_provider' => 'local', 'ocr_error' => trim($error) ?: 'The local OCR process did not complete.']);

            return $document->fresh();
        }

        $document->update(['ocr_status' => 'completed', 'ocr_provider' => $isPdf ? 'local-pdftotext' : 'local-tesseract', 'extracted_text' => trim($output), 'ocr_completed_at' => now()]);

        return $document->fresh();
    }

    private function binaryFor(string $name): ?string
    {
        foreach (['/usr/local/bin/'.$name, '/opt/homebrew/bin/'.$name, '/usr/bin/'.$name] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @return array{0: string, 1: string, 2: int} */
    private function run(string $command): array
    {
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            return ['', 'Unable to start OCR.', 1];
        }
        $output = stream_get_contents($pipes[1]) ?: '';
        $error = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$output, $error, $exitCode];
    }
}
