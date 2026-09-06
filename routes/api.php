<?php

use App\Http\Controllers\Api\V1\PeopleController;
use App\Http\Controllers\Api\V1\PromiseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.token')->group(function (): void {
    Route::get('promises', [PromiseController::class, 'index'])->name('api.v1.promises.index');
    Route::post('promises', [PromiseController::class, 'store'])->name('api.v1.promises.store');
    Route::post('promises/{obligation}/movements', [PromiseController::class, 'storeMovement'])->name('api.v1.promises.movements.store');
    Route::get('people', [PeopleController::class, 'index'])->name('api.v1.people.index');
});
