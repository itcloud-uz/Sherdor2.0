<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserSettingsController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->user()->roles->pluck('name')->first() ?? 'admin';
        
        $context = 'admin';
        $previousUrl = url()->previous();
        if (str_contains($previousUrl, 'operator-panel')) {
            $context = 'operator';
        } elseif (str_contains($previousUrl, 'partner-panel')) {
            $context = 'partner';
        }

        return Inertia::render('Profile/DesignSettings', [
            'user' => $request->user(),
            'role' => $role,
            'context' => $context
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'language' => ['required', 'string', 'in:uz,ru,en'],
            'theme_color' => ['nullable', 'string', 'max:20'],
            'theme_style' => ['nullable', 'string', 'in:apple_glass,galaxy,cyberpunk,minimalist,gold,itcloud_cyberglass'],
            'label_font_size' => ['nullable', 'integer', 'min:8', 'max:24'],
            'label_color' => ['nullable', 'string', 'max:20'],
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['required', Password::defaults()],
            ]);
            $user->password = Hash::make($request->password);
        }

        if ($request->hasFile('avatar')) {
            $request->validate([
                'avatar' => ['image', 'max:2048'] // Max 2MB
            ]);
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->photo_path = $path;
        }

        $user->fill($validated);
        $user->save();

        return redirect()->back()->with('success', 'Sozlamalar saqlandi.');
    }
}
