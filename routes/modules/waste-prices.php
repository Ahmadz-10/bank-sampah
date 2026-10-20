<?php

use App\Http\Controllers\WastePriceController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-master-data')->group(function () {
    Route::resource('waste-prices', WastePriceController::class)->except('show');
});
