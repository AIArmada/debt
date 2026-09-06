<?php

use Illuminate\Support\Facades\File;

test('promise actions stay independent from the livewire delivery layer', function () {
    foreach (File::allFiles(app_path('Actions/Promises')) as $file) {
        expect(File::get($file->getPathname()))
            ->not->toContain('App\\Livewire');
    }
});

test('promise views do not write to the database directly', function () {
    foreach (File::allFiles(resource_path('views/livewire')) as $file) {
        expect(File::get($file->getPathname()))
            ->not->toContain('::query(')
            ->not->toContain('->create(')
            ->not->toContain('->save(');
    }
});

test('update actions cannot write ledger identity or amount fields', function () {
    foreach (File::allFiles(app_path('Actions/Promises')) as $file) {
        if (! str_starts_with($file->getFilename(), 'Update')) {
            continue;
        }

        expect(File::get($file->getPathname()))
            ->not->toContain("'direction'")
            ->not->toContain("'amount'")
            ->not->toContain("'currency'");
    }
});

test('the old top-level promise support namespaces contain no php sources', function () {
    foreach (['Data', 'Enums', 'Queries'] as $directory) {
        $files = File::exists(app_path($directory))
            ? File::files(app_path($directory))
            : [];

        expect(collect($files)->filter(fn ($file): bool => $file->getExtension() === 'php'))->toBeEmpty();
    }
});
