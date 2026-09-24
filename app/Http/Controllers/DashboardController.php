<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the application dashboard using modern Vue 3 SPA.
     */
    public function index(Request $request): View
    {
        return app(SpaController::class)->index($request);
    }
}
