<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

require __DIR__.'/auth.php';

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)
        ->middleware('can:view-dashboard')
        ->name('dashboard');

    // Module routes live in routes/modules/<module>.php (one file per feature branch),
    // so developers do not edit this shared file. Files inherit the auth + active
    // middleware above; add your own `can:` / `role:` middleware inside the file.
    $moduleRoutes = glob(__DIR__.'/modules/*.php') ?: [];
    sort($moduleRoutes);

    foreach ($moduleRoutes as $file) {
        require $file;
    }
});
