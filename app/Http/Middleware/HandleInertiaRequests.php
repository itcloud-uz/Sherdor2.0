<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = \App\Models\SystemSetting::first() ?? new \App\Models\SystemSetting([
            'logo_path' => null,
            'primary_theme_color' => '#00F0FF', // Cyan default
            'neon_glow_intensity' => '0 0 15px',
        ]);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'branding' => [
                'logo_path' => $settings->logo_path,
                'primary_theme_color' => $request->user()?->theme_color ?? $settings->primary_theme_color,
                'neon_glow_intensity' => $settings->neon_glow_intensity,
                'default_currency' => $settings->default_currency ?? 'USD',
            ],
            'locale' => $request->user()?->language ?? session('locale', config('app.locale'))
        ];
    }
}
