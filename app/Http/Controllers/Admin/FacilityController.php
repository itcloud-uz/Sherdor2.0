<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use Inertia\Inertia;

class FacilityController extends Controller
{
    public function index()
    {
        // Self-healing: auto-generate floors and units for any existing building/block that has 0 floors in DB
        $emptyBuildings = Building::where('total_floors', '>', 0)
            ->whereDoesntHave('floors')
            ->get();

        foreach ($emptyBuildings as $building) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($building) {
                $totalFloors = (int) $building->total_floors;
                $basementFloors = (int) ($building->basement_floors ?? 0);
                $totalApartments = (int) ($building->total_apartments ?? 0);
                $totalShops = (int) ($building->total_shops ?? 0);
                $entrancesCount = (int) ($building->entrances_count ?? 1);
                if ($entrancesCount < 1) $entrancesCount = 1;

                $floorNumbers = [];
                for ($b = -$basementFloors; $b <= -1; $b++) {
                    if ($b < 0) $floorNumbers[] = $b;
                }
                $aboveGroundCount = $totalFloors - $basementFloors;
                for ($a = 1; $a <= $aboveGroundCount; $a++) {
                    $floorNumbers[] = $a;
                }

                foreach ($floorNumbers as $idx => $f) {
                    $commercialCount = 0;
                    $residentialCount = 0;

                    if ($f === 1) {
                        $commercialCount = $totalShops;
                    }

                    if ($totalApartments > 0) {
                        if ($totalShops > 0 && $aboveGroundCount > 1) {
                            $residentialFloors = $totalFloors - 1;
                            $baseUnitsPerFloor = (int) floor($totalApartments / $residentialFloors);
                            $remainder = $totalApartments % $residentialFloors;

                            if ($f !== 1) {
                                $residentialCount = $baseUnitsPerFloor;
                                if ($idx < $remainder) {
                                    $residentialCount++;
                                }
                            }
                        } else {
                            $baseUnitsPerFloor = (int) floor($totalApartments / $totalFloors);
                            $remainder = $totalApartments % $totalFloors;
                            $residentialCount = $baseUnitsPerFloor;
                            if ($idx < $remainder) {
                                $residentialCount++;
                            }
                        }
                    }

                    $floor = \App\Models\Floor::create([
                        'building_id' => $building->id,
                        'floor_number' => $f,
                        'total_units' => $residentialCount,
                        'total_commercial_units' => $commercialCount,
                    ]);

                    // Turar joy xonadonlari (Residential) yaratish
                    $resUnitsPerEntrance = (int) ceil($residentialCount / $entrancesCount);
                    if ($resUnitsPerEntrance < 1) $resUnitsPerEntrance = 1;
                    for ($u = 1; $u <= $residentialCount; $u++) {
                        $prefix = $f < 0 ? 'B' . abs($f) : 'A' . $f;
                        $unitNumber = $prefix . str_pad($u, 2, '0', STR_PAD_LEFT);
                        $podyezd = (int) ceil($u / $resUnitsPerEntrance);
                        if ($podyezd > $entrancesCount) {
                            $podyezd = $entrancesCount;
                        }

                        \App\Models\Unit::create([
                            'floor_id' => $floor->id,
                            'unit_number' => $unitNumber,
                            'type' => 'residential',
                            'area_sqm' => 0,
                            'price' => 0,
                            'status' => 'available',
                            'podyezd' => $podyezd,
                        ]);
                    }

                    // Tijorat xonalari (Commercial) yaratish
                    $commUnitsPerEntrance = (int) ceil($commercialCount / $entrancesCount);
                    if ($commUnitsPerEntrance < 1) $commUnitsPerEntrance = 1;
                    for ($c = 1; $c <= $commercialCount; $c++) {
                        $prefix = 'D' . $f;
                        $unitNumber = $prefix . str_pad($c, 2, '0', STR_PAD_LEFT);
                        $podyezd = (int) ceil($c / $commUnitsPerEntrance);
                        if ($podyezd > $entrancesCount) {
                            $podyezd = $entrancesCount;
                        }

                        \App\Models\Unit::create([
                            'floor_id' => $floor->id,
                            'unit_number' => $unitNumber,
                            'type' => 'commercial',
                            'area_sqm' => 0,
                            'price' => 0,
                            'status' => 'available',
                            'podyezd' => $podyezd,
                        ]);
                    }
                }
            });
        }

        $buildings = Building::with(['floors.units', 'stages'])->get();
        $clients = \App\Models\Client::latest()->limit(500)->get();
        $partners = \App\Models\Partner::with('user')->get();

        return Inertia::render('Admin/Facility/Index', [
            'buildings' => $buildings,
            'clients' => $clients,
            'partners' => $partners
        ]);
    }

    public function storeBuilding(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:255', // Tuman/Shahar
            'district' => 'nullable|string|max:255', // Viloyat
            'mahalla' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'block_name' => 'nullable|string|max:255',
            'corpus_name' => 'nullable|string|max:255',
            'length_m' => 'nullable|numeric|min:0',
            'width_m' => 'nullable|numeric|min:0',
            'height_m' => 'nullable|numeric|min:0',
            'total_floors' => 'nullable|integer|min:0',
            'blocks_count' => 'nullable|integer|min:1',
            'entrances_count' => 'nullable|integer|min:1',
            'total_apartments' => 'nullable|integer|min:0',
            'total_shops' => 'nullable|integer|min:0',
            'estimated_budget' => 'nullable|numeric|min:0',
            'blueprint' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
            'model_3d' => 'nullable|file|max:51200',
            'basement_floors' => 'nullable|integer|min:0',
            'is_object_only' => 'nullable|boolean',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('photos', 'public');
        }

        $blueprintPath = null;
        if ($request->hasFile('blueprint')) {
            $blueprintPath = $request->file('blueprint')->store('blueprints', 'public');
        }

        $model3dPath = null;
        if ($request->hasFile('model_3d')) {
            $model3dPath = $request->file('model_3d')->store('models_3d', 'public');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $blueprintPath, $photoPath, $model3dPath, $request) {
            $isObjectOnly = $request->boolean('is_object_only');

            if ($isObjectOnly) {
                Building::create([
                    'name' => $validated['name'],
                    'city' => $validated['city'] ?? null,
                    'district' => $validated['district'] ?? null,
                    'mahalla' => $validated['mahalla'] ?? null,
                    'street' => $validated['street'] ?? null,
                    'total_floors' => 0,
                    'blueprint_path' => $blueprintPath,
                    'photo_path' => $photoPath,
                    'model_3d_path' => $model3dPath,
                    'status' => 'planning',
                ]);
            } else {
                // Yangi Blok Qo'shish: check if there's an existing empty container record for this object
                $container = Building::where('name', $validated['name'])
                    ->where('total_floors', 0)
                    ->first();

                $data = [
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
                    'total_floors' => $validated['total_floors'] ?? 1,
                    'blocks_count' => $validated['blocks_count'] ?? 1,
                    'entrances_count' => $validated['entrances_count'] ?? 1,
                    'total_apartments' => $validated['total_apartments'] ?? 0,
                    'total_shops' => $validated['total_shops'] ?? 0,
                    'estimated_budget' => $validated['estimated_budget'] ?? 0,
                    'basement_floors' => $validated['basement_floors'] ?? 0,
                    'status' => 'planning',
                ];

                if ($blueprintPath) {
                    $data['blueprint_path'] = $blueprintPath;
                }
                if ($photoPath) {
                    $data['photo_path'] = $photoPath;
                }
                if ($model3dPath) {
                    $data['model_3d_path'] = $model3dPath;
                }

                if ($container) {
                    // Update the existing empty object container to become the first block
                    if (!isset($data['photo_path']) && $container->photo_path) {
                        $data['photo_path'] = $container->photo_path;
                    }
                    if (!isset($data['blueprint_path']) && $container->blueprint_path) {
                        $data['blueprint_path'] = $container->blueprint_path;
                    }
                    if (!isset($data['model_3d_path']) && $container->model_3d_path) {
                        $data['model_3d_path'] = $container->model_3d_path;
                    }
                    $container->update($data);
                    $building = $container;
                } else {
                    // Create a new block record, copy photo from existing block if not provided
                    if (!isset($data['photo_path'])) {
                        $existingWithPhoto = Building::where('name', $validated['name'])
                            ->whereNotNull('photo_path')
                            ->first();
                        if ($existingWithPhoto) {
                            $data['photo_path'] = $existingWithPhoto->photo_path;
                        }
                    }
                    $building = Building::create($data);
                }

                // Avtomatik Generatsiya Mantiqi (Qavat va Xonadonlar)
                $totalFloors = (int) $building->total_floors;
                $basementFloors = (int) ($building->basement_floors ?? 0);
                $totalApartments = (int) ($building->total_apartments ?? 0);
                $totalShops = (int) ($building->total_shops ?? 0);
                $entrancesCount = (int) ($building->entrances_count ?? 1);
                if ($entrancesCount < 1) $entrancesCount = 1;

                $floorNumbers = [];
                for ($b = -$basementFloors; $b <= -1; $b++) {
                    if ($b < 0) $floorNumbers[] = $b;
                }
                $aboveGroundCount = $totalFloors - $basementFloors;
                for ($a = 1; $a <= $aboveGroundCount; $a++) {
                    $floorNumbers[] = $a;
                }

                foreach ($floorNumbers as $idx => $f) {
                    $commercialCount = 0;
                    $residentialCount = 0;

                    if ($f === 1) {
                        $commercialCount = $totalShops;
                    }

                    if ($totalApartments > 0) {
                        if ($totalShops > 0 && $aboveGroundCount > 1) {
                            $residentialFloors = $totalFloors - 1;
                            $baseUnitsPerFloor = (int) floor($totalApartments / $residentialFloors);
                            $remainder = $totalApartments % $residentialFloors;

                            if ($f !== 1) {
                                $residentialCount = $baseUnitsPerFloor;
                                if ($idx < $remainder) {
                                    $residentialCount++;
                                }
                            }
                        } else {
                            $baseUnitsPerFloor = (int) floor($totalApartments / $totalFloors);
                            $remainder = $totalApartments % $totalFloors;
                            $residentialCount = $baseUnitsPerFloor;
                            if ($idx < $remainder) {
                                $residentialCount++;
                            }
                        }
                    }

                    $floor = \App\Models\Floor::create([
                        'building_id' => $building->id,
                        'floor_number' => $f,
                        'total_units' => $residentialCount,
                        'total_commercial_units' => $commercialCount,
                    ]);

                    $resUnitsPerEntrance = (int) ceil($residentialCount / $entrancesCount);
                    if ($resUnitsPerEntrance < 1) $resUnitsPerEntrance = 1;
                    for ($u = 1; $u <= $residentialCount; $u++) {
                        $prefix = $f < 0 ? 'B' . abs($f) : 'A' . $f;
                        $unitNumber = $prefix . str_pad($u, 2, '0', STR_PAD_LEFT);
                        $podyezd = (int) ceil($u / $resUnitsPerEntrance);
                        if ($podyezd > $entrancesCount) {
                            $podyezd = $entrancesCount;
                        }

                        \App\Models\Unit::create([
                            'floor_id' => $floor->id,
                            'unit_number' => $unitNumber,
                            'type' => 'residential',
                            'area_sqm' => 0,
                            'price' => 0,
                            'status' => 'available',
                            'podyezd' => $podyezd,
                        ]);
                    }

                    $commUnitsPerEntrance = (int) ceil($commercialCount / $entrancesCount);
                    if ($commUnitsPerEntrance < 1) $commUnitsPerEntrance = 1;
                    for ($c = 1; $c <= $commercialCount; $c++) {
                        $prefix = 'D' . $f;
                        $unitNumber = $prefix . str_pad($c, 2, '0', STR_PAD_LEFT);
                        $podyezd = (int) ceil($c / $commUnitsPerEntrance);
                        if ($podyezd > $entrancesCount) {
                            $podyezd = $entrancesCount;
                        }

                        \App\Models\Unit::create([
                            'floor_id' => $floor->id,
                            'unit_number' => $unitNumber,
                            'type' => 'commercial',
                            'area_sqm' => 0,
                            'price' => 0,
                            'status' => 'available',
                            'podyezd' => $podyezd,
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
            'block_name' => 'nullable|string|max:255',
            'corpus_name' => 'nullable|string|max:255',
            'length_m' => 'nullable|numeric|min:0',
            'width_m' => 'nullable|numeric|min:0',
            'height_m' => 'nullable|numeric|min:0',
            'total_floors' => 'required|integer|min:0',
            'blocks_count' => 'nullable|integer|min:1',
            'entrances_count' => 'nullable|integer|min:1',
            'total_apartments' => 'nullable|integer|min:0',
            'total_shops' => 'nullable|integer|min:0',
            'estimated_budget' => 'nullable|numeric|min:0',
            'blueprint' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
            'model_3d' => 'nullable|file|max:51200',
            'basement_floors' => 'nullable|integer|min:0',
        ]);

        $photoPath = $building->photo_path;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('photos', 'public');
        }

        $blueprintPath = $building->blueprint_path;
        if ($request->hasFile('blueprint')) {
            $blueprintPath = $request->file('blueprint')->store('blueprints', 'public');
        }

        $model3dPath = $building->model_3d_path;
        if ($request->hasFile('model_3d')) {
            $model3dPath = $request->file('model_3d')->store('models_3d', 'public');
        }

        $totalFloors = (int) $validated['total_floors'];
        $basementFloors = (int) ($validated['basement_floors'] ?? 0);
        $totalApartments = (int) ($validated['total_apartments'] ?? 0);
        $totalShops = (int) ($validated['total_shops'] ?? 0);
        $entrancesCount = (int) ($validated['entrances_count'] ?? 1);
        if ($entrancesCount < 1) $entrancesCount = 1;

        $desiredFloorNumbers = [];
        for ($b = -$basementFloors; $b <= -1; $b++) {
            if ($b < 0) $desiredFloorNumbers[] = $b;
        }
        $aboveGroundCount = $totalFloors - $basementFloors;
        for ($a = 1; $a <= $aboveGroundCount; $a++) {
            $desiredFloorNumbers[] = $a;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($building, $validated, $desiredFloorNumbers, $totalFloors, $basementFloors, $totalApartments, $totalShops, $entrancesCount, $photoPath, $blueprintPath, $model3dPath) {
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
                'estimated_budget' => $validated['estimated_budget'] ?? 0,
                'basement_floors' => $validated['basement_floors'] ?? 0,
                'photo_path' => $photoPath,
                'blueprint_path' => $blueprintPath,
                'model_3d_path' => $model3dPath,
            ]);

            // Sync floors: delete floors that are not in the desired floor numbers
            $building->floors()->whereNotIn('floor_number', $desiredFloorNumbers)->delete();

            $existingFloorNumbers = $building->floors()->pluck('floor_number')->toArray();

            // Create new floors if they do not exist
            foreach ($desiredFloorNumbers as $idx => $f) {
                if (!in_array($f, $existingFloorNumbers)) {
                    $commercialCount = 0;
                    $residentialCount = 0;

                    if ($f === 1) {
                        $commercialCount = $totalShops;
                    }

                    $aboveGroundCount = $totalFloors - $basementFloors;
                    if ($totalApartments > 0) {
                        $residentialFloors = $totalFloors - ($totalShops > 0 && $aboveGroundCount > 1 ? 1 : 0);
                        if ($residentialFloors < 1) $residentialFloors = 1;
                        $baseUnitsPerFloor = (int) floor($totalApartments / $residentialFloors);
                        $remainder = $totalApartments % $residentialFloors;

                        if ($f !== 1 || $totalShops == 0 || $aboveGroundCount <= 1) {
                            $residentialCount = $baseUnitsPerFloor;
                            if ($idx < $remainder) {
                                $residentialCount++;
                            }
                        }
                    }

                    $floor = \App\Models\Floor::create([
                        'building_id' => $building->id,
                        'floor_number' => $f,
                        'total_units' => $residentialCount,
                        'total_commercial_units' => $commercialCount,
                    ]);

                    if ($residentialCount > 0) {
                        $resUnitsPerEntrance = (int) ceil($residentialCount / $entrancesCount);
                        if ($resUnitsPerEntrance < 1) $resUnitsPerEntrance = 1;
                        for ($u = 1; $u <= $residentialCount; $u++) {
                            $prefix = $f < 0 ? 'B' . abs($f) : 'A' . $f;
                            $unitNumber = $prefix . str_pad($u, 2, '0', STR_PAD_LEFT);
                            $podyezd = (int) ceil($u / $resUnitsPerEntrance);
                            if ($podyezd > $entrancesCount) {
                                $podyezd = $entrancesCount;
                            }

                            \App\Models\Unit::create([
                                'floor_id' => $floor->id,
                                'unit_number' => $unitNumber,
                                'type' => 'residential',
                                'area_sqm' => 0,
                                'price' => 0,
                                'status' => 'available',
                                'podyezd' => $podyezd,
                            ]);
                        }
                    }

                    if ($commercialCount > 0) {
                        $commUnitsPerEntrance = (int) ceil($commercialCount / $entrancesCount);
                        if ($commUnitsPerEntrance < 1) $commUnitsPerEntrance = 1;
                        for ($c = 1; $c <= $commercialCount; $c++) {
                            $prefix = 'D' . $f;
                            $unitNumber = $prefix . str_pad($c, 2, '0', STR_PAD_LEFT);
                            $podyezd = (int) ceil($c / $commUnitsPerEntrance);
                            if ($podyezd > $entrancesCount) {
                                $podyezd = $entrancesCount;
                            }

                            \App\Models\Unit::create([
                                'floor_id' => $floor->id,
                                'unit_number' => $unitNumber,
                                'type' => 'commercial',
                                'area_sqm' => 0,
                                'price' => 0,
                                'status' => 'available',
                                'podyezd' => $podyezd,
                            ]);
                        }
                    }
                }
            }
        });

        return redirect()->back()->with('message', 'Obyekt ma\'lumotlari yangilandi!');
    }

    public function storeStage(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'name' => 'required|string|max:255',
            'target_volume' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $validated['target_volume'] = $validated['target_volume'] ?? 0;
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

        $building = Building::find($validated['building_id']);
        $entrancesCount = $building ? (int) ($building->entrances_count ?? 1) : 1;
        if ($entrancesCount < 1) $entrancesCount = 1;

        if ($validated['total_units'] > 0) {
            $resUnitsPerEntrance = (int) ceil($validated['total_units'] / $entrancesCount);
            if ($resUnitsPerEntrance < 1) $resUnitsPerEntrance = 1;
            for ($i = 1; $i <= $validated['total_units']; $i++) {
                $prefix = $floor->floor_number < 0 ? 'B' . abs($floor->floor_number) : 'A' . $floor->floor_number;
                $unitNumber = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
                $podyezd = (int) ceil($i / $resUnitsPerEntrance);
                if ($podyezd > $entrancesCount) $podyezd = $entrancesCount;

                \App\Models\Unit::create([
                    'floor_id' => $floor->id,
                    'unit_number' => $unitNumber,
                    'type' => 'residential',
                    'area_sqm' => 0,
                    'price' => 0,
                    'status' => 'available',
                    'podyezd' => $podyezd,
                ]);
            }
        }

        if (($validated['total_commercial_units'] ?? 0) > 0) {
            $commUnitsPerEntrance = (int) ceil($validated['total_commercial_units'] / $entrancesCount);
            if ($commUnitsPerEntrance < 1) $commUnitsPerEntrance = 1;
            for ($i = 1; $i <= $validated['total_commercial_units']; $i++) {
                $prefix = 'D' . $floor->floor_number;
                $unitNumber = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
                $podyezd = (int) ceil($i / $commUnitsPerEntrance);
                if ($podyezd > $entrancesCount) $podyezd = $entrancesCount;

                \App\Models\Unit::create([
                    'floor_id' => $floor->id,
                    'unit_number' => $unitNumber,
                    'type' => 'commercial',
                    'area_sqm' => 0,
                    'price' => 0,
                    'status' => 'available',
                    'podyezd' => $podyezd,
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

        $oldFloorNumber = $floor->floor_number;

        if ($request->hasFile('blueprint')) {
            $floor->blueprint_path = $request->file('blueprint')->store('blueprints/floors', 'public');
        }

        $floor->floor_number = $validated['floor_number'];
        $floor->total_units = $validated['total_units'];
        $floor->total_commercial_units = $validated['total_commercial_units'] ?? 0;
        $floor->save();

        // If floor number changed, rename existing units
        if ($oldFloorNumber !== $floor->floor_number) {
            $oldResPrefix = $oldFloorNumber < 0 ? 'B' . abs($oldFloorNumber) : 'A' . $oldFloorNumber;
            $newResPrefix = $floor->floor_number < 0 ? 'B' . abs($floor->floor_number) : 'A' . $floor->floor_number;
            
            $oldCommPrefix = 'D' . $oldFloorNumber;
            $newCommPrefix = 'D' . $floor->floor_number;
            
            foreach ($floor->units as $unit) {
                if ($unit->type === 'residential') {
                    if (str_starts_with($unit->unit_number, $oldResPrefix)) {
                        $unit->unit_number = $newResPrefix . substr($unit->unit_number, strlen($oldResPrefix));
                        $unit->save();
                    }
                } else if ($unit->type === 'commercial') {
                    if (str_starts_with($unit->unit_number, $oldCommPrefix)) {
                        $unit->unit_number = $newCommPrefix . substr($unit->unit_number, strlen($oldCommPrefix));
                        $unit->save();
                    }
                }
            }
        }

        $building = $floor->building;
        $entrancesCount = $building ? (int) ($building->entrances_count ?? 1) : 1;
        if ($entrancesCount < 1) $entrancesCount = 1;

        $currentResidentialCount = $floor->units()->where('type', 'residential')->count();
        if ($validated['total_units'] > $currentResidentialCount) {
            $resUnitsPerEntrance = (int) ceil($validated['total_units'] / $entrancesCount);
            if ($resUnitsPerEntrance < 1) $resUnitsPerEntrance = 1;
            for ($i = $currentResidentialCount + 1; $i <= $validated['total_units']; $i++) {
                $prefix = $floor->floor_number < 0 ? 'B' . abs($floor->floor_number) : 'A' . $floor->floor_number;
                $unitNumber = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
                $originalNumber = $unitNumber;
                $counter = 1;
                while (\App\Models\Unit::where('floor_id', $floor->id)->where('unit_number', $unitNumber)->exists()) {
                    $unitNumber = $originalNumber . '-' . $counter;
                    $counter++;
                }
                $podyezd = (int) ceil($i / $resUnitsPerEntrance);
                if ($podyezd > $entrancesCount) $podyezd = $entrancesCount;

                \App\Models\Unit::create([
                    'floor_id' => $floor->id,
                    'unit_number' => $unitNumber,
                    'type' => 'residential',
                    'area_sqm' => 0,
                    'price' => 0,
                    'status' => 'available',
                    'podyezd' => $podyezd,
                ]);
            }
        } elseif ($validated['total_units'] < $currentResidentialCount) {
            $excessCount = $currentResidentialCount - $validated['total_units'];
            $excessIds = $floor->units()
                ->where('type', 'residential')
                ->orderBy('id', 'desc')
                ->limit($excessCount)
                ->pluck('id');
            \App\Models\Unit::whereIn('id', $excessIds)->delete();
        }

        $currentCommercialCount = $floor->units()->where('type', 'commercial')->count();
        $totalCommercial = $validated['total_commercial_units'] ?? 0;
        if ($totalCommercial > $currentCommercialCount) {
            $commUnitsPerEntrance = (int) ceil($totalCommercial / $entrancesCount);
            if ($commUnitsPerEntrance < 1) $commUnitsPerEntrance = 1;
            for ($i = $currentCommercialCount + 1; $i <= $totalCommercial; $i++) {
                $prefix = 'D' . $floor->floor_number;
                $unitNumber = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
                $originalNumber = $unitNumber;
                $counter = 1;
                while (\App\Models\Unit::where('floor_id', $floor->id)->where('unit_number', $unitNumber)->exists()) {
                    $unitNumber = $originalNumber . '-' . $counter;
                    $counter++;
                }
                $podyezd = (int) ceil($i / $commUnitsPerEntrance);
                if ($podyezd > $entrancesCount) $podyezd = $entrancesCount;

                \App\Models\Unit::create([
                    'floor_id' => $floor->id,
                    'unit_number' => $unitNumber,
                    'type' => 'commercial',
                    'area_sqm' => 0,
                    'price' => 0,
                    'status' => 'available',
                    'podyezd' => $podyezd,
                ]);
            }
        } elseif ($totalCommercial < $currentCommercialCount) {
            $excessCount = $currentCommercialCount - $totalCommercial;
            $excessIds = $floor->units()
                ->where('type', 'commercial')
                ->orderBy('id', 'desc')
                ->limit($excessCount)
                ->pluck('id');
            \App\Models\Unit::whereIn('id', $excessIds)->delete();
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
            'podyezd' => 'nullable|integer|min:1',
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
            'podyezd' => 'nullable|integer|min:1',
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
