<?php

use App\Http\Controllers\WasteWeighingController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-weighings')->group(function () {
    Route::get('waste-weighings', [WasteWeighingController::class, 'index'])->name('waste-weighings.index');
    Route::post('waste-weighings', [WasteWeighingController::class, 'store'])->name('waste-weighings.store');
});
