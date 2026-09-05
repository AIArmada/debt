<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    MediaLibraryServiceProvider::class,
];
