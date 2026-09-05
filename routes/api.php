<?php

use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PartyController;
use App\Http\Controllers\Api\V1\RecordController;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::middleware([StartSession::class, 'auth', 'verified', 'throttle:api'])->prefix('v1')->group(function (): void {
    Route::get('records', [RecordController::class, 'index'])->name('api.v1.records.index');
    Route::post('records', [RecordController::class, 'store'])->name('api.v1.records.store');
    Route::get('records/{record}', [RecordController::class, 'show'])->name('api.v1.records.show');
    Route::get('parties', [PartyController::class, 'index'])->name('api.v1.parties.index');
    Route::post('parties', [PartyController::class, 'store'])->name('api.v1.parties.store');
    Route::get('parties/{party}', [PartyController::class, 'show'])->name('api.v1.parties.show');
    Route::match(['put', 'patch'], 'parties/{party}', [PartyController::class, 'update'])->name('api.v1.parties.update');
    Route::post('parties/{party}/contact-routes', [PartyController::class, 'addContactRoute'])->name('api.v1.parties.contact-routes.store');
    Route::post('parties/{party}/payment-destinations', [PartyController::class, 'addPaymentDestination'])->name('api.v1.parties.payment-destinations.store');
    Route::post('records/{record}/obligations', [RecordController::class, 'addObligation'])->name('api.v1.records.obligations.store');
    Route::post('records/{record}/parties', [RecordController::class, 'addParty'])->name('api.v1.records.parties.store');
    Route::delete('records/{record}/parties/{recordParty}', [RecordController::class, 'removeParty'])->name('api.v1.records.parties.destroy');
    Route::post('records/{record}/obligations/{obligation}/parties', [RecordController::class, 'addObligationParty'])->name('api.v1.records.obligation-parties.store');
    Route::post('records/{record}/obligations/{obligation}/transactions', [RecordController::class, 'transaction'])->name('api.v1.records.transactions.store');
    Route::post('records/{record}/obligations/{obligation}/collection-schedules', [RecordController::class, 'collectionSchedule'])->name('api.v1.records.collection-schedules.store');
    Route::post('records/{record}/obligations/{obligation}/delivery-instructions', [RecordController::class, 'deliveryInstruction'])->name('api.v1.records.delivery-instructions.store');
    Route::match(['put', 'patch'], 'records/{record}/obligations/{obligation}/transactions/{transaction}', [RecordController::class, 'updateTransaction'])->name('api.v1.records.transactions.update');
    Route::post('records/{record}/obligations/{obligation}/events', [RecordController::class, 'event'])->name('api.v1.records.events.store');
    Route::get('notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('api.v1.notifications.unread-count');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('api.v1.notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.v1.notifications.read-all');
});
