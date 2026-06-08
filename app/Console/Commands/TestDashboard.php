<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class TestDashboard extends Command
{
    protected $signature = 'test:dashboard';
    protected $description = 'Test the dashboard route';

    public function handle()
    {
        $user = User::first();
        if ($user) {
            Auth::login($user);
        }

        $postData = [
            'name' => 'Sherdor TJM',
            'block_name' => '6-blok',
            'city' => 'Tashkent',
            'total_floors' => 10,
            'blocks_count' => 1,
            'entrances_count' => 1,
            'total_apartments' => 50,
            'total_shops' => 5,
            'estimated_budget' => 1000000,
        ];

        $request = Request::create('/admin/facility/buildings', 'POST', $postData);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        try {
            $controller = app(\App\Http\Controllers\Admin\FacilityController::class);
            $response = $controller->storeBuilding($request);
            $this->info("Controller returned status: " . $response->getStatusCode());
        } catch (\Exception $e) {
            $this->error("EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}
