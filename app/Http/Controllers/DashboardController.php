<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The numbers come from DashboardService, which decides per role what may be shown.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        return view('dashboard', ['dashboard' => $dashboard->forUser($request->user())]);
    }
}
