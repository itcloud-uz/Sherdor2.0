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
        $settings = \Illuminate\Support\Facades\Cache::rememberForever('system_settings_v2', function () {
            $dbSettings = \App\Models\SystemSetting::first();
            if ($dbSettings) {
                return [
                    'logo_path' => $dbSettings->logo_path,
                    'background_image_path' => $dbSettings->background_image_path,
                    'primary_theme_color' => $dbSettings->primary_theme_color,
                    'neon_glow_intensity' => $dbSettings->neon_glow_intensity,
                    'default_currency' => $dbSettings->default_currency,
                    'app_name' => $dbSettings->app_name,
                ];
            }
            return [
                'logo_path' => null,
                'background_image_path' => null,
                'primary_theme_color' => '#00F0FF',
                'neon_glow_intensity' => '0 0 15px',
                'default_currency' => 'USD',
                'app_name' => 'Sher-Dor ERP',
            ];
        });

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? array_merge($request->user()->toArray(), [
                    'permissions' => $request->user()->getAllPermissions()->pluck('name')->toArray(),
                    'roles' => $request->user()->getRoleNames()->toArray(),
                ]) : null,
            ],
            'branding' => [
                'logo_path' => $settings['logo_path'] ?? null,
                'background_image_path' => $settings['background_image_path'] ?? null,
                'primary_theme_color' => $request->user()?->theme_color ?? $settings['primary_theme_color'] ?? '#00F0FF',
                'neon_glow_intensity' => $settings['neon_glow_intensity'] ?? '0 0 15px',
                'default_currency' => $settings['default_currency'] ?? 'USD',
                'app_name' => $settings['app_name'] ?? 'Sher-Dor ERP',
            ],
            'locale' => $request->user()?->language ?? session('locale', config('app.locale')),
            'facilitiesMenu' => $request->user() ? \Illuminate\Support\Facades\Cache::remember('facilities_menu_tree_v2', 60, function () {
                $res = [];
                $buildings = \App\Models\Building::orderBy('name')->orderBy('block_name')->orderBy('corpus_name')->get();
                $grouped = $buildings->groupBy('name');
                foreach ($grouped as $facilityName => $facilityBuildings) {
                    $blocks = [];
                    foreach ($facilityBuildings as $b) {
                        $podyezds = \App\Models\Unit::whereHas('floor', function($q) use ($b) {
                            $q->where('building_id', $b->id);
                        })->select('podyezd')->distinct()->orderBy('podyezd')->pluck('podyezd')->toArray();
                        
                        $blocks[] = [
                            'id' => $b->id,
                            'name' => $b->block_name ?? $b->corpus_name ?? 'Asosiy Blok',
                            'podyezds' => array_values(array_filter($podyezds, fn($val) => !is_null($val) && $val !== ''))
                        ];
                    }
                    $res[] = [
                        'name' => $facilityName,
                        'blocks' => $blocks
                    ];
                }
                return $res;
            }) : []
        ];
    }
}
