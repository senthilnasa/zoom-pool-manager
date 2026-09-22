<?php

namespace App\Http\Controllers;

use App\Domain\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['department', 'roles', 'permissions']);

        return view('dashboard', [
            'user' => $user,
        ]);
    }
}
