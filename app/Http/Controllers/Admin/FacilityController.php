<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $this->generateFloorsAndUnits($building);
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

    public function storeBuilding(Request $request)
    {
        try {
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
                'shops_per_floor' => 'nullable|integer|min:0',
                'apartments_per_floor' => 'nullable|integer|min:0',
                'estimated_budget' => 'nullable|numeric|min:0',
                'blueprint' => 'nullable|file|max:256000',
                'photo' => 'nullable|file|max:102400',
                'model_3d' => 'nullable|file|max:1024000',
                'basement_floors' => 'nullable|integer|min:0',
                'is_object_only' => 'nullable|boolean',
                'floors_data' => 'nullable|array',
                'floors_data.*.floor_number' => 'required|integer',
                'floors_data.*.shops_count' => 'required|integer|min:0',
                'floors_data.*.apartments_count' => 'required|integer|min:0',
            ]);

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $ext = strtolower($request->file('photo')->getClientOriginalExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    return redirect()->back()->withErrors(['photo' => 'Rasm fayli formati noto\'g\'ri (faqat jpg, jpeg, png, webp, gif).']);
                }
                $photoPath = $request->file('photo')->store('photos', 'public');
            }

            $blueprintPath = null;
            if ($request->hasFile('blueprint')) {
                $ext = strtolower($request->file('blueprint')->getClientOriginalExtension());
                if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'dwg', 'dxf'])) {
                    return redirect()->back()->withErrors(['blueprint' => '2D chizma fayli formati noto\'g\'ri (faqat pdf, jpg, jpeg, png, dwg, dxf).']);
                }
                $blueprintPath = $request->file('blueprint')->store('blueprints', 'public');
            }

            $model3dPath = null;
            if ($request->hasFile('model_3d')) {
                $ext = strtolower($request->file('model_3d')->getClientOriginalExtension());
                if (!in_array($ext, ['gltf', 'glb', 'obj', 'fbx', 'zip', 'png', 'jpg', 'jpeg', 'pdf'])) {
                    return redirect()->back()->withErrors(['model_3d' => '3D model fayli formati noto\'g\'ri (faqat gltf, glb, obj, fbx, zip, png, jpg, jpeg, pdf).']);
                }
                $model3dPath = $request->file('model_3d')->store('models_3d', 'public');
            }

            $building = null;

            DB::transaction(function () use ($validated, $blueprintPath, $photoPath, $model3dPath, $request, &$building) {
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
                        'shops_per_floor' => $validated['shops_per_floor'] ?? 0,
                        'apartments_per_floor' => $validated['apartments_per_floor'] ?? 0,
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
                }
            });

            // Generate floors and units outside the transaction above (generateFloorsAndUnits has its own transaction)
            if ($building) {
                $this->generateFloorsAndUnits($building, $request->input('floors_data'));
            }

            return redirect()->back()->with('message', 'Obyekt muvaffaqiyatli qo\'shildi!');
        } catch (\Exception $e) {
            Log::error('Building store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);
            return redirect()->back()->withErrors(['error' => 'Serverda xatolik yuz berdi: ' . $e->getMessage()]);
        }
    }

    public function updateBuilding(Request $request, $id)
    {
        try {
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
                'shops_per_floor' => 'nullable|integer|min:0',
                'apartments_per_floor' => 'nullable|integer|min:0',
                'estimated_budget' => 'nullable|numeric|min:0',
                'blueprint' => 'nullable|file|max:256000',
                'photo' => 'nullable|file|max:102400',
                'model_3d' => 'nullable|file|max:1024000',
                'basement_floors' => 'nullable|integer|min:0',
                'floors_data' => 'nullable|array',
                'floors_data.*.floor_number' => 'required|integer',
                'floors_data.*.shops_count' => 'required|integer|min:0',
                'floors_data.*.apartments_count' => 'required|integer|min:0',
            ]);

            $photoPath = $building->photo_path;
            if ($request->hasFile('photo')) {
                $ext = strtolower($request->file('photo')->getClientOriginalExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    return redirect()->back()->withErrors(['photo' => 'Rasm fayli formati noto\'g\'ri (faqat jpg, jpeg, png, webp, gif).']);
                }
                $photoPath = $request->file('photo')->store('photos', 'public');
            }

            $blueprintPath = $building->blueprint_path;
            if ($request->hasFile('blueprint')) {
                $ext = strtolower($request->file('blueprint')->getClientOriginalExtension());
                if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'dwg', 'dxf'])) {
                    return redirect()->back()->withErrors(['blueprint' => '2D chizma fayli formati noto\'g\'ri (faqat pdf, jpg, jpeg, png, dwg, dxf).']);
                }
                $blueprintPath = $request->file('blueprint')->store('blueprints', 'public');
            }

            $model3dPath = $building->model_3d_path;
            if ($request->hasFile('model_3d')) {
                $ext = strtolower($request->file('model_3d')->getClientOriginalExtension());
                if (!in_array($ext, ['gltf', 'glb', 'obj', 'fbx', 'zip', 'png', 'jpg', 'jpeg', 'pdf'])) {
                    return redirect()->back()->withErrors(['model_3d' => '3D model fayli formati noto\'g\'ri (faqat gltf, glb, obj, fbx, zip, png, jpg, jpeg, pdf).']);
                }
                $model3dPath = $request->file('model_3d')->store('models_3d', 'public');
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
                'shops_per_floor' => $validated['shops_per_floor'] ?? 0,
                'apartments_per_floor' => $validated['apartments_per_floor'] ?? 0,
                'estimated_budget' => $validated['estimated_budget'] ?? 0,
                'basement_floors' => $validated['basement_floors'] ?? 0,
                'photo_path' => $photoPath,
                'blueprint_path' => $blueprintPath,
                'model_3d_path' => $model3dPath,
            ]);

            $this->generateFloorsAndUnits($building, $request->input('floors_data'));

            return redirect()->back()->with('message', 'Obyekt ma\'lumotlari yangilandi!');
        } catch (\Exception $e) {
            Log::error('Building update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);
            return redirect()->back()->withErrors(['error' => 'Serverda xatolik yuz berdi: ' . $e->getMessage()]);
        }
    }

    /**
     * Generate (or regenerate) all floors and units for a building based on
     * shops_per_floor, apartments_per_floor, total_floors, basement_floors, and entrances_count.
     *
     * Commercial units (shops) go on basement floors and the 1st floor.
     * Residential units (apartments) go on floors 2 and above.
     *
     * When regenerating, non-available units (reserved/sold) are preserved.
     */
    private function generateFloorsAndUnits(Building $building, ?array $floorsData = null): void
    {
        DB::transaction(function () use ($building, $floorsData) {
            $totalFloors = (int) $building->total_floors;
            $basementFloors = (int) ($building->basement_floors ?? 0);
            $shopsPerFloor = (int) ($building->shops_per_floor ?? 0);
            $apartmentsPerFloor = (int) ($building->apartments_per_floor ?? 0);
            $entrancesCount = (int) ($building->entrances_count ?? 1);
            $totalShops = (int) ($building->total_shops ?? 0);
            $totalApartments = (int) ($building->total_apartments ?? 0);

            if ($entrancesCount < 1) $entrancesCount = 1;

            // Build desired floor numbers
            $floorNumbers = [];
            for ($b = -$basementFloors; $b <= -1; $b++) {
                if ($b < 0) $floorNumbers[] = $b;
            }
            $aboveGroundCount = $totalFloors - $basementFloors;
            for ($a = 1; $a <= $aboveGroundCount; $a++) {
                $floorNumbers[] = $a;
            }

            // Count commercial floors (basement floors + floor 1)
            $commercialFloorCount = $basementFloors + 1; // basement floors + 1st floor
            if ($aboveGroundCount < 1) {
                $commercialFloorCount = $basementFloors; // no above-ground floors at all
            }

            // Count residential floors (floor 2 and above)
            $residentialFloorCount = max(0, $aboveGroundCount - 1); // floors 2..top

            // Convert floorsData to a map keyed by floor_number for quick lookup
            $floorsDataMap = [];
            if ($floorsData) {
                foreach ($floorsData as $item) {
                    $floorsDataMap[(int)$item['floor_number']] = [
                        'shops_count' => (int)$item['shops_count'],
                        'apartments_count' => (int)$item['apartments_count']
                    ];
                }
            }

            if ($floorsData) {
                // Sum up totals from floorsData Map
                $newTotalShops = 0;
                $newTotalApartments = 0;
                foreach ($floorNumbers as $f) {
                    if (isset($floorsDataMap[$f])) {
                        $newTotalShops += $floorsDataMap[$f]['shops_count'];
                        $newTotalApartments += $floorsDataMap[$f]['apartments_count'];
                    }
                }
            } else {
                // Backward compatibility: calculate per-floor values from totals if not set
                if ($shopsPerFloor == 0 && $totalShops > 0 && $commercialFloorCount > 0) {
                    $shopsPerFloor = (int) ceil($totalShops / $commercialFloorCount);
                }
                if ($apartmentsPerFloor == 0 && $totalApartments > 0 && $residentialFloorCount > 0) {
                    $apartmentsPerFloor = (int) ceil($totalApartments / $residentialFloorCount);
                }

                // Update building totals based on per-floor values
                $newTotalShops = $commercialFloorCount > 0 ? $shopsPerFloor * $commercialFloorCount : 0;
                $newTotalApartments = $residentialFloorCount > 0 ? $apartmentsPerFloor * $residentialFloorCount : 0;
            }

            $building->update([
                'shops_per_floor' => $shopsPerFloor,
                'apartments_per_floor' => $apartmentsPerFloor,
                'total_shops' => $newTotalShops,
                'total_apartments' => $newTotalApartments,
            ]);

            // Get existing floors
            $existingFloors = $building->floors()->with('units')->get()->keyBy('floor_number');

            // Remove floors that should no longer exist (only if all units are available)
            foreach ($existingFloors as $floorNumber => $floor) {
                if (!in_array($floorNumber, $floorNumbers)) {
                    $hasNonAvailable = $floor->units()->where('status', '!=', 'available')->exists();
                    if (!$hasNonAvailable) {
                        $floor->units()->delete();
                        $floor->delete();
                    }
                }
            }

            // Create or sync each desired floor
            foreach ($floorNumbers as $f) {
                if ($floorsData && isset($floorsDataMap[$f])) {
                    $targetCommercial = $floorsDataMap[$f]['shops_count'];
                    $targetResidential = $floorsDataMap[$f]['apartments_count'];
                } else {
                    // Determine target unit counts for this floor
                    $isCommercialFloor = ($f < 0 || $f === 1); // basement or 1st floor
                    $isResidentialFloor = ($f >= 2); // floor 2 and above

                    $targetCommercial = $isCommercialFloor ? $shopsPerFloor : 0;
                    $targetResidential = $isResidentialFloor ? $apartmentsPerFloor : 0;
                }

                $existingFloor = $existingFloors->get($f);

                if ($existingFloor) {
                    // Floor already exists — sync units while preserving non-available ones
                    $this->syncFloorUnits($existingFloor, $targetResidential, $targetCommercial, $entrancesCount);
                } else {
                    // Create new floor
                    $floor = Floor::create([
                        'building_id' => $building->id,
                        'floor_number' => $f,
                        'total_units' => $targetResidential,
                        'total_commercial_units' => $targetCommercial,
                    ]);

                    // Create residential units
                    $this->createUnitsForFloor($floor, 'residential', $targetResidential, $entrancesCount);

                    // Create commercial units
                    $this->createUnitsForFloor($floor, 'commercial', $targetCommercial, $entrancesCount);
                }
            }
        });
    }

    /**
     * Sync units on an existing floor, preserving non-available (reserved/sold) units.
     */
    private function syncFloorUnits(Floor $floor, int $targetResidential, int $targetCommercial, int $entrancesCount): void
    {
        // Update floor totals
        $floor->update([
            'total_units' => $targetResidential,
            'total_commercial_units' => $targetCommercial,
        ]);

        // Sync residential units
        $this->syncUnitsByType($floor, 'residential', $targetResidential, $entrancesCount);

        // Sync commercial units
        $this->syncUnitsByType($floor, 'commercial', $targetCommercial, $entrancesCount);
    }

    /**
     * Sync units of a given type on a floor, preserving non-available units.
     */
    private function syncUnitsByType(Floor $floor, string $type, int $target, int $entrancesCount): void
    {
        $currentTotal = $floor->units()->where('type', $type)->count();
        $nonAvailableCount = $floor->units()->where('type', $type)->where('status', '!=', 'available')->count();

        if ($currentTotal < $target) {
            // Need to add more units
            $toAdd = $target - $currentTotal;
            $startIndex = $currentTotal + 1;
            $f = $floor->floor_number;

            $unitsPerEntrance = (int) ceil($target / $entrancesCount);
            if ($unitsPerEntrance < 1) $unitsPerEntrance = 1;

            for ($i = $startIndex; $i < $startIndex + $toAdd; $i++) {
                $unitNumber = $this->generateUnitNumber($f, $i, $type);
                $podyezd = (int) ceil($i / $unitsPerEntrance);
                if ($podyezd > $entrancesCount) $podyezd = $entrancesCount;

                Unit::create([
                    'floor_id' => $floor->id,
                    'unit_number' => $unitNumber,
                    'type' => $type,
                    'area_sqm' => 0,
                    'price' => 0,
                    'status' => 'available',
                    'podyezd' => $podyezd,
                ]);
            }
        } elseif ($currentTotal > $target) {
            // Need to remove units — only remove available ones
            $toRemove = $currentTotal - $target;
            // Never remove more than the available count
            $availableCount = $currentTotal - $nonAvailableCount;
            $toRemove = min($toRemove, $availableCount);

            if ($toRemove > 0) {
                $excessIds = $floor->units()
                    ->where('type', $type)
                    ->where('status', 'available')
                    ->orderBy('id', 'desc')
                    ->limit($toRemove)
                    ->pluck('id');
                Unit::whereIn('id', $excessIds)->delete();
            }
        }
    }

    /**
     * Create units for a newly created floor.
     */
    private function createUnitsForFloor(Floor $floor, string $type, int $count, int $entrancesCount): void
    {
        if ($count <= 0) return;

        $f = $floor->floor_number;
        $unitsPerEntrance = (int) ceil($count / $entrancesCount);
        if ($unitsPerEntrance < 1) $unitsPerEntrance = 1;

        for ($i = 1; $i <= $count; $i++) {
            $unitNumber = $this->generateUnitNumber($f, $i, $type);
            $podyezd = (int) ceil($i / $unitsPerEntrance);
            if ($podyezd > $entrancesCount) $podyezd = $entrancesCount;

            Unit::create([
                'floor_id' => $floor->id,
                'unit_number' => $unitNumber,
                'type' => $type,
                'area_sqm' => 0,
                'price' => 0,
                'status' => 'available',
                'podyezd' => $podyezd,
            ]);
        }
    }

    /**
     * Generate a unit number string.
     *
     * Commercial: D-101, D-102 (basement), D101, D102 (floor 1)
     * Residential: A201, A202 (floor 2+)
     */
    private function generateUnitNumber(int $floorNumber, int $sequence, string $type): string
    {
        $seqStr = str_pad($sequence, 2, '0', STR_PAD_LEFT);

        if ($type === 'commercial') {
            if ($floorNumber < 0) {
                // Basement: D- prefix with absolute floor number
                return 'D-' . abs($floorNumber) . $seqStr;
            }
            // Above ground commercial (floor 1)
            return 'D' . $floorNumber . $seqStr;
        }

        // Residential
        if ($floorNumber < 0) {
            return 'B' . abs($floorNumber) . $seqStr;
        }
        return 'A' . $floorNumber . $seqStr;
    }

    public function storeStage(Request $request)
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

    public function storeFloor(Request $request)
    {
        $validated = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'floor_number' => 'required|integer',
            'total_units' => 'required|integer|min:0',
            'total_commercial_units' => 'nullable|integer|min:0',
            'blueprint' => 'nullable|file|max:256000',
        ]);

        $blueprintPath = null;
        if ($request->hasFile('blueprint')) {
            $ext = strtolower($request->file('blueprint')->getClientOriginalExtension());
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                return redirect()->back()->withErrors(['blueprint' => 'Qavat chizmasi fayli formati noto\'g\'ri (faqat pdf, jpg, jpeg, png).']);
            }
            $blueprintPath = $request->file('blueprint')->store('blueprints/floors', 'public');
        }

        $floor = Floor::create([
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

                Unit::create([
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

                Unit::create([
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

    public function updateFloor(Request $request, $id)
    {
        $floor = Floor::findOrFail($id);
        $validated = $request->validate([
            'floor_number' => 'required|integer',
            'total_units' => 'required|integer|min:0',
            'total_commercial_units' => 'nullable|integer|min:0',
            'blueprint' => 'nullable|file|max:256000',
        ]);

        $oldFloorNumber = $floor->floor_number;

        if ($request->hasFile('blueprint')) {
            $ext = strtolower($request->file('blueprint')->getClientOriginalExtension());
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                return redirect()->back()->withErrors(['blueprint' => 'Qavat chizmasi fayli formati noto\'g\'ri (faqat pdf, jpg, jpeg, png).']);
            }
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
                while (Unit::where('floor_id', $floor->id)->where('unit_number', $unitNumber)->exists()) {
                    $unitNumber = $originalNumber . '-' . $counter;
                    $counter++;
                }
                $podyezd = (int) ceil($i / $resUnitsPerEntrance);
                if ($podyezd > $entrancesCount) $podyezd = $entrancesCount;

                Unit::create([
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
            Unit::whereIn('id', $excessIds)->delete();
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
                while (Unit::where('floor_id', $floor->id)->where('unit_number', $unitNumber)->exists()) {
                    $unitNumber = $originalNumber . '-' . $counter;
                    $counter++;
                }
                $podyezd = (int) ceil($i / $commUnitsPerEntrance);
                if ($podyezd > $entrancesCount) $podyezd = $entrancesCount;

                Unit::create([
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
            Unit::whereIn('id', $excessIds)->delete();
        }

        return redirect()->back()->with('message', 'Qavat yangilandi!');
    }

    public function destroyFloor($id)
    {
        $floor = Floor::findOrFail($id);
        $floor->delete();
        return redirect()->back()->with('message', 'Qavat o\'chirildi!');
    }

    public function destroyBuilding($id)
    {
        $building = Building::findOrFail($id);
        $building->delete();
        return redirect()->route('admin.facility')->with('message', 'Bino o\'chirildi!');
    }

    public function storeUnit(Request $request)
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

        Unit::create($validated);

        return redirect()->back()->with('message', 'Xonadon/Do\'kon qo\'shildi!');
    }

    public function updateUnit(Request $request, $id)
    {
        $unit = Unit::findOrFail($id);
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
        $unit = Unit::findOrFail($id);
        $unit->delete();
        return redirect()->back()->with('message', 'Xonadon/Do\'kon o\'chirildi!');
    }
}
