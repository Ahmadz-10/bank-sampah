<?php

use App\Http\Controllers\WasteReductionController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-monitoring')->group(function () {
    // Monthly records are created by the weighing sheet: no create/store/destroy here.
    Route::resource('waste-reductions', WasteReductionController::class)->only(['index', 'edit', 'update']);
});
