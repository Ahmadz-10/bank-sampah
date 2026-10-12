<?php

use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-customers')->group(function () {
    Route::resource('customers', CustomerController::class)->except('show');
});
