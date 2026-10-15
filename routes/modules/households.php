<?php

use App\Http\Controllers\HouseholdController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-households')->group(function () {
    Route::resource('households', HouseholdController::class);
});
