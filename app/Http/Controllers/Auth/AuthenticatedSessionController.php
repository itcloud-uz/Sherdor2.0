<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user->hasRole('Super_Admin') || $user->hasRole('Admin')) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }
        
        // Since all roles now use the unified AdminLayout, redirect them to a page they have access to
        if ($user->hasPermissionTo('access dashboard')) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        } elseif ($user->hasPermissionTo('access clients')) {
            return redirect()->intended(route('admin.crm', absolute: false));
        } elseif ($user->hasPermissionTo('access finance') || $user->hasPermissionTo('access transactions')) {
            // Usually transactions is tab 2
            return redirect()->intended(route('admin.finance', ['tab' => 2], absolute: false));
        } elseif ($user->hasPermissionTo('access objects')) {
            return redirect()->intended(route('admin.facility', absolute: false));
        }

        return redirect()->intended(route('admin.dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
