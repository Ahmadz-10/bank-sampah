<?php

use App\Http\Controllers\RwController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-master-data')->group(function () {
    // No destroy: an RW is deactivated, not deleted.
    Route::resource('rws', RwController::class)->except(['show', 'destroy']);
});
