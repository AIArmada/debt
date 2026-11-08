<?php

use App\Features\PromiseTracker\EntryPoints\Http\Controllers\HttpApi\V1\PeopleController;
use App\Features\PromiseTracker\EntryPoints\Http\Controllers\HttpApi\V1\PromiseController;
use App\Features\PromiseTracker\EntryPoints\Http\Middleware\HandleApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', HandleApiToken::class])->prefix('api/v1')->group(function (): void {
    Route::get('promises', [PromiseController::class, 'index'])->name('api.v1.promises.index');
    Route::post('promises', [PromiseController::class, 'store'])->name('api.v1.promises.store');
    Route::post('promises/{obligation}/movements', [PromiseController::class, 'storeMovement'])->name('api.v1.promises.movements.store');
    Route::get('people', [PeopleController::class, 'index'])->name('api.v1.people.index');
});
