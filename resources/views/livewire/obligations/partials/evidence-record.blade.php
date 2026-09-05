@php
    $evidenceType = $document->evidence_type ?? 'file';
    $evidenceTitle = $document->title ?: ($document->original_filename ?: ucfirst(str_replace('_', ' ', $document->category ?? 'evidence')));
    $categoryLabel = ucfirst(str_replace('_', ' ', $document->category ?? 'other'));
    $verificationLabel = ucfirst(str_replace('_', ' ', $document->verification_status ?? 'needs review'));
    $verificationTone = match ($document->verification_status ?? 'needs_review') {
        'verified' => 'success',
        'rejected', 'failed' => 'danger',
        'needs_review' => 'warning',
        default => 'neutral',
    };
    $ocrTone = match ($document->ocr_status ?? null) {
        'completed', 'complete' => 'success',
        'processing', 'queued' => 'info',
        'failed' => 'danger',
        default => 'neutral',
    };
@endphp
<div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        <div class="font-medium">{{ $evidenceTitle }}</div>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-zinc-500"><span>{{ $categoryLabel }}</span><x-status-badge :tone="$verificationTone" :label="$verificationLabel" />@if ($document->captured_on)<span>Captured {{ $document->captured_on->format('d M Y') }}</span>@endif</div>
        @if ($document->source)
            <div class="mt-1 text-xs text-zinc-500">Source: {{ $document->source }}</div>
        @endif
        @if ($evidenceType === 'note' && $document->content)
            <div class="mt-3 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $document->content }}</div>
        @endif
        @if ($evidenceType === 'file' && $document->extracted_text)
            <div class="mt-2 max-w-xl truncate text-xs text-zinc-500">{{ $document->extracted_text }}</div>
        @endif
    </div>
    <div class="shrink-0 sm:text-right">
        @if ($evidenceType === 'file')
            <a href="{{ route('documents.download', $document) }}" class="text-sm font-medium text-sky-700 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100">Download</a>
            @if ($document->ocr_status && $document->ocr_status !== 'not_applicable')
                <div class="mt-2"><x-status-badge :tone="$ocrTone" :label="'OCR '.ucfirst(str_replace('_', ' ', $document->ocr_status))" /></div>
            @endif
        @elseif ($evidenceType === 'link')
            <a href="{{ $document->external_url }}" target="_blank" rel="noreferrer" class="text-sm font-medium text-sky-700 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100">Open source</a>
        @else
            <span class="text-xs font-medium uppercase tracking-wide text-zinc-500">Written note</span>
        @endif
    </div>
</div>
