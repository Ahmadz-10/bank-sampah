<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-users')->group(function () {
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
        ->name('users.toggle-active');

    // No destroy: users are deactivated, not deleted.
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
});
