<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Placeholder. The real dashboard (statistics) is built in Phase 6.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard');
    }
}
