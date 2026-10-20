<?php

use App\Http\Controllers\WasteDepositController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-transactions')->group(function () {
    // No edit/update: a mistake is corrected by voiding and re-entering.
    // withTrashed(show) lets admins open voided deposits.
    Route::resource('waste-deposits', WasteDepositController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->withTrashed(['show']);

    Route::delete('waste-deposits/{waste_deposit}', [WasteDepositController::class, 'destroy'])
        ->middleware('can:correct-transactions')
        ->name('waste-deposits.destroy');
});
