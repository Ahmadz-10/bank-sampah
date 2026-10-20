<?php

use App\Http\Controllers\WasteCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-master-data')->group(function () {
    // No destroy: categories are deactivated, not deleted (see controller).
    Route::resource('waste-categories', WasteCategoryController::class)->except(['show', 'destroy']);
});
