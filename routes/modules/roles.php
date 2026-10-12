<?php

use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-users')->group(function () {
    // Roles are fixed; only the label/description can be edited.
    Route::resource('roles', RoleController::class)->only(['index', 'edit', 'update']);
});
