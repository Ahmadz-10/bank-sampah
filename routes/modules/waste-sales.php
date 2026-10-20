<?php

use App\Http\Controllers\WasteSaleController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-transactions')->group(function () {
    Route::resource('waste-sales', WasteSaleController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->withTrashed(['show']);

    Route::delete('waste-sales/{waste_sale}', [WasteSaleController::class, 'destroy'])
        ->middleware('can:correct-transactions')
        ->name('waste-sales.destroy');
});
