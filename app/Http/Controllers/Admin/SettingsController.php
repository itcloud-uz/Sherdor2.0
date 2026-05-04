<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => SystemSetting::first()
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name' => 'nullable|string|max:255',
            'primary_theme_color' => 'nullable|string|max:20',
            'neon_glow_intensity' => 'nullable|string|max:50',
            'default_currency' => 'nullable|string|max:10',
            'tax_rate' => 'nullable|numeric',
            'default_penalty_rate' => 'nullable|numeric',
            'logo' => 'nullable|image|max:2048', // Max 2MB image
        ]);

        $settings = SystemSetting::first() ?? new SystemSetting();

        $fields = ['company_name', 'primary_theme_color', 'neon_glow_intensity', 'default_currency', 'tax_rate', 'default_penalty_rate'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $settings->{$field} = $request->{$field};
            }
        }

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $path = $request->file('logo')->store('branding', 'public');
            $settings->logo_path = $path;
        }

        $settings->save();

        return back()->with('success', 'System settings updated successfully.');
    }
}
