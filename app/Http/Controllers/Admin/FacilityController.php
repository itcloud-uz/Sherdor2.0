<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use Inertia\Inertia;

class FacilityController extends Controller
{
    public function index()
    {
        $buildings = Building::with(['floors.units', 'stages'])->get();
        return Inertia::render('Admin/Facility/Index', [
            'buildings' => $buildings
        ]);
    }

    public function storeBuilding(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'mahalla' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'block_name' => 'nullable|string|max:255',
            'corpus_name' => 'nullable|string|max:255',
            'length_m' => 'nullable|numeric|min:0',
            'width_m' => 'nullable|numeric|min:0',
            'height_m' => 'nullable|numeric|min:0',
            'total_floors' => 'required|integer|min:1',
            'blocks_count' => 'nullable|integer|min:1',
            'entrances_count' => 'nullable|integer|min:1',
            'total_apartments' => 'nullable|integer|min:0',
            'total_shops' => 'nullable|integer|min:0',
            'estimated_budget' => 'required|numeric|min:0',
            'blueprint' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            // Ommaviy generatsiya uchun maydonlar
            'units_per_floor' => 'nullable|integer|min:0',
            'average_area' => 'nullable|numeric|min:0',
            'average_price' => 'nullable|numeric|min:0',
            'commercial_units_first_floor' => 'nullable|integer|min:0',
        ]);

        $blueprintPath = null;
        if ($request->hasFile('blueprint')) {
            $blueprintPath = $request->file('blueprint')->store('blueprints', 'public');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $blueprintPath) {
            $building = Building::create([
                'name' => $validated['name'],
                'city' => $validated['city'] ?? null,
                'district' => $validated['district'] ?? null,
                'mahalla' => $validated['mahalla'] ?? null,
                'street' => $validated['street'] ?? null,
                'block_name' => $validated['block_name'] ?? null,
                'corpus_name' => $validated['corpus_name'] ?? null,
                'length_m' => $validated['length_m'] ?? null,
                'width_m' => $validated['width_m'] ?? null,
                'height_m' => $validated['height_m'] ?? null,
                'total_floors' => $validated['total_floors'],
                'blocks_count' => $validated['blocks_count'] ?? 1,
                'entrances_count' => $validated['entrances_count'] ?? 1,
                'total_apartments' => $validated['total_apartments'] ?? 0,
                'total_shops' => $validated['total_shops'] ?? 0,
                'estimated_budget' => $validated['estimated_budget'],
                'status' => 'planning',
                'actual_expenses' => 0,
                'blueprint_path' => $blueprintPath,
            ]);

            // Avtomatik Generatsiya Mantiqi
            $totalFloors = $validated['total_floors'];
            $unitsPerFloor = $validated['units_per_floor'] ?? 0;
            $averageArea = $validated['average_area'] ?? 0;
            $averagePrice = $validated['average_price'] ?? 0;
            $commercialFirstFloor = $validated['commercial_units_first_floor'] ?? 0;

            if ($unitsPerFloor > 0 || $commercialFirstFloor > 0) {
                for ($f = 1; $f <= $totalFloors; $f++) {
                    $isFirstFloor = ($f === 1);
                    $commercialCount = $isFirstFloor ? $commercialFirstFloor : 0;
                    $residentialCount = $isFirstFloor && $commercialCount > 0 ? 0 : $unitsPerFloor;
                    
                    if ($isFirstFloor && $commercialCount > 0) {
                        $residentialCount = 0; // 1-qavat tijorat
                    } else {
                        $residentialCount = $unitsPerFloor;
                    }

                    $floor = \App\Models\Floor::create([
                        'building_id' => $building->id,
                        'floor_number' => $f,
                        'total_units' => $residentialCount,
                        'total_commercial_units' => $commercialCount,
                    ]);

                    // Turar joy (Residential) yaratish
                    for ($u = 1; $u <= $residentialCount; $u++) {
                        $prefix = 'A' . $f;
                        $unitNumber = $prefix . str_pad($u, 2, '0', STR_PAD_LEFT);
                        \App\Models\Unit::create([
                            'floor_id' => $floor->id,
                            'unit_number' => $unitNumber,
                            'type' => 'residential',
                            'area_sqm' => $averageArea,
                            'price' => $averagePrice,
                            'status' => 'available',
                        ]);
                    }

                    // Tijorat xonalari (Commercial) yaratish
                    for ($c = 1; $c <= $commercialCount; $c++) {
                        $prefix = 'D' . $f;
                        $unitNumber = $prefix . str_pad($c, 2, '0', STR_PAD_LEFT);
                        \App\Models\Unit::create([
                            'floor_id' => $floor->id,
                            'unit_number' => $unitNumber,
                            'type' => 'commercial',
                            'area_sqm' => $averageArea,
                            'price' => $averagePrice,
                            'status' => 'available',
                        ]);
                    }
                }
            }
        });

        return redirect()->back()->with('message', 'Obyekt muvaffaqiyatli qo\'shildi!');
    }
    public function updateBuilding(\Illuminate\Http\Request $request, $id)
    {
        $building = Building::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'mahalla' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'total_floors' => 'required|integer|min:1',
            'blocks_count' => 'nullable|integer|min:1',
            'entrances_count' => 'nullable|integer|min:1',
            'total_apartments' => 'nullable|integer|min:0',
            'total_shops' => 'nullable|integer|min:0',
            'estimated_budget' => 'required|numeric|min:0',
            'blueprint' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->hasFile('blueprint')) {
            $building->blueprint_path = $request->file('blueprint')->store('blueprints', 'public');
        }

        $building->update([
            'name' => $validated['name'],
            'city' => $validated['city'] ?? null,
            'district' => $validated['district'] ?? null,
            'mahalla' => $validated['mahalla'] ?? null,
            'street' => $validated['street'] ?? null,
            'block_name' => $validated['block_name'] ?? null,
            'corpus_name' => $validated['corpus_name'] ?? null,
            'length_m' => $validated['length_m'] ?? null,
            'width_m' => $validated['width_m'] ?? null,
            'height_m' => $validated['height_m'] ?? null,
            'total_floors' => $validated['total_floors'],
            'blocks_count' => $validated['blocks_count'] ?? 1,
            'entrances_count' => $validated['entrances_count'] ?? 1,
            'total_apartments' => $validated['total_apartments'] ?? 0,
            'total_shops' => $validated['total_shops'] ?? 0,
            'estimated_budget' => $validated['estimated_budget'],
        ]);

        return redirect()->back()->with('message', 'Obyekt ma\'lumotlari yangilandi!');
    }

    public function storeStage(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'name' => 'required|string|max:255',
            'target_volume' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $validated['progress_percentage'] = 0;
        $validated['current_volume'] = 0;

        \App\Models\ConstructionStage::create($validated);

        return redirect()->back();
    }

    public function storeFloor(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'floor_number' => 'required|integer',
            'total_units' => 'required|integer|min:0',
            'total_commercial_units' => 'nullable|integer|min:0',
            'blueprint' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $blueprintPath = null;
        if ($request->hasFile('blueprint')) {
            $blueprintPath = $request->file('blueprint')->store('blueprints/floors', 'public');
        }

        $floor = \App\Models\Floor::create([
            'building_id' => $validated['building_id'],
            'floor_number' => $validated['floor_number'],
            'total_units' => $validated['total_units'],
            'total_commercial_units' => $validated['total_commercial_units'] ?? 0,
            'blueprint_path' => $blueprintPath,
        ]);

        if ($validated['total_units'] > 0) {
            for ($i = 1; $i <= $validated['total_units']; $i++) {
                $prefix = $floor->floor_number < 0 ? 'B' . abs($floor->floor_number) : 'A' . $floor->floor_number;
                $unitNumber = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
                \App\Models\Unit::create([
                    'floor_id' => $floor->id,
                    'unit_number' => $unitNumber,
                    'type' => 'residential',
                    'area_sqm' => 0,
                    'price' => 0,
                    'status' => 'available',
                ]);
            }
        }

        if (($validated['total_commercial_units'] ?? 0) > 0) {
            for ($i = 1; $i <= $validated['total_commercial_units']; $i++) {
                $prefix = 'D' . $floor->floor_number;
                $unitNumber = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
                \App\Models\Unit::create([
                    'floor_id' => $floor->id,
                    'unit_number' => $unitNumber,
                    'type' => 'commercial',
                    'area_sqm' => 0,
                    'price' => 0,
                    'status' => 'available',
                ]);
            }
        }

        return redirect()->back()->with('message', 'Qavat va xonadonlar qo\'shildi!');
    }

    public function updateFloor(\Illuminate\Http\Request $request, $id)
    {
        $floor = \App\Models\Floor::findOrFail($id);
        $validated = $request->validate([
            'floor_number' => 'required|integer',
            'total_units' => 'required|integer|min:0',
            'total_commercial_units' => 'nullable|integer|min:0',
            'blueprint' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->hasFile('blueprint')) {
            $floor->blueprint_path = $request->file('blueprint')->store('blueprints/floors', 'public');
        }

        $floor->floor_number = $validated['floor_number'];
        $floor->total_units = $validated['total_units'];
        $floor->total_commercial_units = $validated['total_commercial_units'] ?? 0;
        $floor->save();

        $currentResidentialCount = $floor->units()->where('type', 'residential')->count();
        if ($validated['total_units'] > $currentResidentialCount) {
            for ($i = $currentResidentialCount + 1; $i <= $validated['total_units']; $i++) {
                $prefix = $floor->floor_number < 0 ? 'B' . abs($floor->floor_number) : 'A' . $floor->floor_number;
                $unitNumber = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
                $originalNumber = $unitNumber;
                $counter = 1;
                while (\App\Models\Unit::where('floor_id', $floor->id)->where('unit_number', $unitNumber)->exists()) {
                    $unitNumber = $originalNumber . '-' . $counter;
                    $counter++;
                }
                \App\Models\Unit::create([
                    'floor_id' => $floor->id,
                    'unit_number' => $unitNumber,
                    'type' => 'residential',
                    'area_sqm' => 0,
                    'price' => 0,
                    'status' => 'available',
                ]);
            }
        }

        $currentCommercialCount = $floor->units()->where('type', 'commercial')->count();
        $totalCommercial = $validated['total_commercial_units'] ?? 0;
        if ($totalCommercial > $currentCommercialCount) {
            for ($i = $currentCommercialCount + 1; $i <= $totalCommercial; $i++) {
                $prefix = 'D' . $floor->floor_number;
                $unitNumber = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
                $originalNumber = $unitNumber;
                $counter = 1;
                while (\App\Models\Unit::where('floor_id', $floor->id)->where('unit_number', $unitNumber)->exists()) {
                    $unitNumber = $originalNumber . '-' . $counter;
                    $counter++;
                }
                \App\Models\Unit::create([
                    'floor_id' => $floor->id,
                    'unit_number' => $unitNumber,
                    'type' => 'commercial',
                    'area_sqm' => 0,
                    'price' => 0,
                    'status' => 'available',
                ]);
            }
        }

        return redirect()->back()->with('message', 'Qavat yangilandi!');
    }

    public function destroyFloor($id)
    {
        $floor = \App\Models\Floor::findOrFail($id);
        $floor->delete();
        return redirect()->back()->with('message', 'Qavat o\'chirildi!');
    }

    public function destroyBuilding($id)
    {
        $building = Building::findOrFail($id);
        $building->delete();
        return redirect()->route('admin.facility')->with('message', 'Bino o\'chirildi!');
    }

    public function storeUnit(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'floor_id' => 'required|exists:floors,id',
            'unit_number' => 'required|string|max:50',
            'type' => 'required|in:residential,commercial',
            'area_sqm' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:available,reserved,sold',
            'svg_polygon' => 'nullable|string',
            'rooms_count' => 'nullable|integer|min:1',
            'kitchens_count' => 'nullable|integer|min:0',
            'bathrooms_count' => 'nullable|integer|min:0',
            'toilets_count' => 'nullable|integer|min:0',
            'orientation' => 'nullable|string|max:255',
        ]);

        \App\Models\Unit::create($validated);

        return redirect()->back()->with('message', 'Xonadon/Do\'kon qo\'shildi!');
    }

    public function updateUnit(\Illuminate\Http\Request $request, $id)
    {
        $unit = \App\Models\Unit::findOrFail($id);
        $validated = $request->validate([
            'unit_number' => 'required|string|max:50',
            'type' => 'required|in:residential,commercial',
            'area_sqm' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:available,reserved,sold',
            'svg_polygon' => 'nullable|string',
            'rooms_count' => 'nullable|integer|min:1',
            'kitchens_count' => 'nullable|integer|min:0',
            'bathrooms_count' => 'nullable|integer|min:0',
            'toilets_count' => 'nullable|integer|min:0',
            'orientation' => 'nullable|string|max:255',
        ]);

        $unit->update($validated);

        return redirect()->back()->with('message', 'Xonadon/Do\'kon yangilandi!');
    }

    public function destroyUnit($id)
    {
        $unit = \App\Models\Unit::findOrFail($id);
        $unit->delete();
        return redirect()->back()->with('message', 'Xonadon/Do\'kon o\'chirildi!');
    }
}
