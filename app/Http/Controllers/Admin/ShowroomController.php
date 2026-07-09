<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShowroomController extends Controller
{
    public function index(Request $request)
    {
        $buildings = Building::with(['podezes.floors.units', 'floors.units'])->get();
        
        return Inertia::render('Admin/Crm/Showroom/Index', [
            'buildings' => $buildings,
        ]);
    }
}
