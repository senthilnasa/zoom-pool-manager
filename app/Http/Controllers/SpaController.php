<?php

namespace App\Http\Controllers;

use App\Domain\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpaController extends Controller
{
    /**
     * Render the single-page application shell.
     *
     * @param  array<string, mixed>|string|null  $options
     */
    public function index(Request $request, array|string|null $options = [], ?string $any = null): View
    {
        if (is_string($options)) {
            $options = ['initialRoute' => '/app/'.$options];
        } elseif (! is_array($options)) {
            $options = [];
        }
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $user->load(['department', 'roles', 'permissions']);
        }

        $userData = $user ? [
            'id' => $user->id,
            'public_id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url ?? null,
            'theme' => $user->theme ?? 'system',
            'is_admin' => $user->hasRole('Super Administrator') || $user->hasRole('Administrator'),
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ] : null;

        return view('app', [
            'user' => $user,
            'userData' => $userData,
            'demoMode' => (bool) config('app.demo', false),
            'appVersion' => (string) config('zpm.version', '1.0.0'),
            'fallbackHtml' => $options['fallbackHtml'] ?? '',
            'initialRoute' => $options['initialRoute'] ?? $request->path(),
        ]);
    }
}
