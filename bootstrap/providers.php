<?php

use App\Features\PromiseTracker\FeatureServiceProvider;
use App\Providers\AppServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

return [
    FeatureServiceProvider::class,
    AppServiceProvider::class,
    MediaLibraryServiceProvider::class,
];
