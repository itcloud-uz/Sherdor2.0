<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Unit;
use App\Models\Podez;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class FacilityController extends Controller
{
    public function index(Request $request)
    {
        $buildings = Building::with([
            'podezes.floors.units.activeContract.client', 
            'podezes.floors.units.contracts.client', 
            'floors.units.activeContract.client', 
            'floors.units.contracts.client', 
            'stages'
        ])->get();
        $clients = \App\Models\Client::select('id', 'first_name', 'last_name')->latest()->limit(200)->get();
        $partners = \App\Models\Partner::with('user:id,name')->get();

        $startDate = null;
        $endDate = null;
        if ($request->filled('start_month')) {
            $startDate = \Carbon\Carbon::parse($request->input('start_month'))->startOfMonth()->toDateTimeString();
        }
        if ($request->filled('end_month')) {
            $endDate = \Carbon\Carbon::parse($request->input('end_month'))->endOfMonth()->toDateTimeString();
        }
        if ($request->filled('start_date')) {
            $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay()->toDateTimeString();
        }
        if ($request->filled('end_date')) {
            $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay()->toDateTimeString();
        }

        $reportData = $this->getReportData($buildings, $startDate, $endDate);

        $smetaItems = \App\Models\SmetaItem::with(['building:id,name', 'creator:id,name'])->latest()->get();

        $actualExpenses = \App\Models\Transaction::where('type', 'expense')
            ->where('status', 'approved')
            ->whereNotNull('building_id')
            ->with('category:id,name')
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'building_id' => $tx->building_id,
                    'category_name' => $tx->category ? $tx->category->name : ($tx->work_category ?: 'Boshqa Xarajatlar'),
                    'amount' => (float)$tx->amount,
                    'notes' => $tx->notes ?: $tx->description,
                    'transaction_date' => $tx->transaction_date ?: ($tx->created_at ? $tx->created_at->toDateTimeString() : null),
                ];
            });

        return Inertia::render('Admin/Facility/Index', [
            'buildings' => $buildings,
            'clients' => $clients,
            'partners' => $partners,
            'users' => \App\Models\User::select('id', 'name')->orderBy('name', 'asc')->get(),
            'registers' => \App\Models\CashRegister::where('is_active', true)->get(),
            'reportData' => $reportData,
            'smetaItems' => $smetaItems,
            'actualExpenses' => $actualExpenses,
            'contractTemplates' => \App\Models\ContractTemplate::with('variantGroups.variants')->latest()->get(),
            'filters' => [
                'start_month' => $request->input('start_month'),
                'end_month' => $request->input('end_month'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
            ]
        ]);
    }

    public function createBuilding(Request $request)
    {
        $objectContainer = null;
        if ($request->has('object_id')) {
            $objectContainer = Building::where('id', $request->input('object_id'))
                ->with('podezes')
                ->first();
        }

        return Inertia::render('Admin/Facility/BuildingForm', [
            'isEditing' => false,
            'objectContainer' => $objectContainer,
        ]);
    }

    public function editBuilding(Request $request, $id)
    {
        $building = Building::with(['floors.units', 'podezes.floors'])->findOrFail($id);
        $isObjectOverride = $building->total_floors == 0 || $request->query('type') === 'object';

        return Inertia::render('Admin/Facility/BuildingForm', [
            'isEditing' => true,
            'building' => $building,
            'isObjectOverride' => $isObjectOverride,
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
                'podezes' => 'nullable|array',
                'podezes.*.id' => 'nullable',
                'podezes.*.name' => 'required|string|max:255',
                'podezes.*.number' => 'required|integer|min:1',
                'podezes.*.floors' => 'required|integer|min:1|max:50',
                'podezes.*.type' => 'required|string|in:Staircase,Elevator,Corridor',
                'podezes.*.apartments_per_floor' => 'nullable|integer|min:0',
                'podezes.*.commercial_per_floor' => 'nullable|integer|min:0',
                'podezes.*.total_apartments' => 'nullable|integer|min:0',
                'podezes.*.total_commercial' => 'nullable|integer|min:0',
            ]);

            if ($request->has('podezes') && is_array($request->input('podezes')) && count($request->input('podezes')) > 0) {
                $maxFloors = collect($request->input('podezes'))->max('floors') ?? 1;
                $validated['total_floors'] = $maxFloors;
            }

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
                    $building = Building::create([
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
                if ($request->has('podezes') && is_array($request->input('podezes'))) {
                    $this->syncPodezes($building, $request->input('podezes'));
                } else {
                    $this->generateFloorsAndUnits($building, $request->input('floors_data'));
                }
            }

            return redirect()->route('admin.facility', ['object' => $building ? $building->name : $validated['name']])->with('message', 'Obyekt muvaffaqiyatli qo\'shildi!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
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
                'is_object_only' => 'nullable|boolean',
                'floors_data' => 'nullable|array',
                'floors_data.*.floor_number' => 'required|integer',
                'floors_data.*.shops_count' => 'required|integer|min:0',
                'floors_data.*.apartments_count' => 'required|integer|min:0',
                'podezes' => 'nullable|array',
                'podezes.*.id' => 'nullable',
                'podezes.*.name' => 'required|string|max:255',
                'podezes.*.number' => 'required|integer|min:1',
                'podezes.*.floors' => 'required|integer|min:1|max:50',
                'podezes.*.type' => 'required|string|in:Staircase,Elevator,Corridor',
                'podezes.*.apartments_per_floor' => 'nullable|integer|min:0',
                'podezes.*.commercial_per_floor' => 'nullable|integer|min:0',
                'podezes.*.total_apartments' => 'nullable|integer|min:0',
                'podezes.*.total_commercial' => 'nullable|integer|min:0',
            ]);

            if ($request->has('podezes') && is_array($request->input('podezes')) && count($request->input('podezes')) > 0) {
                $maxFloors = collect($request->input('podezes'))->max('floors') ?? 1;
                $validated['total_floors'] = $maxFloors;
            }

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

            $oldName = $building->name;

            if ($request->boolean('is_object_only')) {
                Building::where('name', $oldName)->update([
                    'name' => $validated['name'],
                    'city' => $validated['city'] ?? null,
                    'district' => $validated['district'] ?? null,
                    'mahalla' => $validated['mahalla'] ?? null,
                    'street' => $validated['street'] ?? null,
                    'photo_path' => $photoPath,
                ]);
                return redirect()->route('admin.facility', ['object' => $building->name])->with('message', 'Obyekt ma\'lumotlari muvaffaqiyatli yangilandi!');
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
                'total_floors' => $validated['total_floors'] ?? 1,
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

            // Sync object-level fields across all blocks belonging to the same object
            Building::where('name', $oldName)->update([
                'name' => $validated['name'],
                'city' => $validated['city'] ?? null,
                'district' => $validated['district'] ?? null,
                'mahalla' => $validated['mahalla'] ?? null,
                'street' => $validated['street'] ?? null,
                'photo_path' => $photoPath,
            ]);

            if ($request->has('podezes') && is_array($request->input('podezes'))) {
                $this->syncPodezes($building, $request->input('podezes'));
            } else {
                $this->generateFloorsAndUnits($building, $request->input('floors_data'));
            }

            return redirect()->route('admin.facility', ['object' => $building->name])->with('message', 'Obyekt ma\'lumotlari yangilandi!');
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

            // Clamp/fix podyezd for all existing units (bulk update)
            $unitIds = [];
            foreach ($existingFloors as $floor) {
                foreach ($floor->units as $unit) {
                    if ($unit->podyezd > $entrancesCount || $unit->podyezd < 1 || is_null($unit->podyezd)) {
                        $unitIds[] = $unit->id;
                    }
                }
            }
            if (!empty($unitIds)) {
                DB::table('units')->whereIn('id', $unitIds)->update(['podyezd' => $entrancesCount]);
            }

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
        $counts = $floor->units()->where('type', $type)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status != 'available' THEN 1 ELSE 0 END) as non_available")
            ->first();
        $currentTotal = (int) $counts->total;
        $nonAvailableCount = (int) $counts->non_available;

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

        if ($type === 'parking') {
            if ($floorNumber < 0) {
                return 'P-' . abs($floorNumber) . $seqStr;
            }
            return 'P' . $floorNumber . $seqStr;
        }

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

        $building = Building::with('podezes')->findOrFail($validated['building_id']);
        $podezes = $building->podezes;

        if ($podezes->count() > 0) {
            $totalPodezes = $podezes->count();
            $resUnitsPerEntrance = (int) ceil($validated['total_units'] / $totalPodezes);
            $commUnitsPerEntrance = (int) ceil(($validated['total_commercial_units'] ?? 0) / $totalPodezes);

            foreach ($podezes as $podez) {
                $floor = Floor::create([
                    'building_id' => $building->id,
                    'podez_id' => $podez->id,
                    'floor_number' => $validated['floor_number'],
                    'total_units' => $resUnitsPerEntrance,
                    'total_commercial_units' => $commUnitsPerEntrance,
                    'blueprint_path' => $blueprintPath,
                ]);

                if ($resUnitsPerEntrance > 0) {
                    for ($i = 1; $i <= $resUnitsPerEntrance; $i++) {
                        $prefix = $floor->floor_number < 0 ? 'B' . abs($floor->floor_number) : 'A' . $floor->floor_number;
                        // To keep unit numbering unique, we can include podez number
                        $unitNumber = $prefix . '-' . $podez->podez_number . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);

                        Unit::create([
                            'floor_id' => $floor->id,
                            'podez_id' => $podez->id,
                            'unit_number' => $unitNumber,
                            'type' => 'residential',
                            'area_sqm' => 0,
                            'price' => 0,
                            'status' => 'available',
                            'podyezd' => $podez->podez_number,
                        ]);
                    }
                }

                if ($commUnitsPerEntrance > 0) {
                    for ($i = 1; $i <= $commUnitsPerEntrance; $i++) {
                        $prefix = 'D' . $floor->floor_number;
                        $unitNumber = $prefix . '-' . $podez->podez_number . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);

                        Unit::create([
                            'floor_id' => $floor->id,
                            'podez_id' => $podez->id,
                            'unit_number' => $unitNumber,
                            'type' => 'commercial',
                            'area_sqm' => 0,
                            'price' => 0,
                            'status' => 'available',
                            'podyezd' => $podez->podez_number,
                        ]);
                    }
                }
            }
        } else {
            // Legacy fallback
            $floor = Floor::create([
                'building_id' => $building->id,
                'floor_number' => $validated['floor_number'],
                'total_units' => $validated['total_units'],
                'total_commercial_units' => $validated['total_commercial_units'] ?? 0,
                'blueprint_path' => $blueprintPath,
            ]);

            $entrancesCount = (int) ($building->entrances_count ?? 1);
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
        }

        return redirect()->back()->with('message', 'Qavat va xonadonlar qo\'shildi!');
    }

    public function updateFloor(Request $request, $id)
    {
        $floor = Floor::with('units')->findOrFail($id);
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

    public function destroyObject($id)
    {
        $container = Building::findOrFail($id);
        $name = $container->name;

        \DB::transaction(function () use ($name) {
            $buildings = Building::where('name', $name)->get();
            foreach ($buildings as $building) {
                $building->delete();
            }
        });

        return redirect()->route('admin.facility')->with('message', 'Obyekt to\'liq o\'chirildi!');
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
            'has_balcony' => 'nullable|boolean',
            'blueprint' => 'nullable|file|max:256000',
            'model_3d' => 'nullable|file|max:1024000',
        ]);

        $floor = Floor::findOrFail($validated['floor_id']);
        $building = $floor->building;

        if ($floor->podez_id) {
            $validated['podez_id'] = $floor->podez_id;
            if ($floor->podez) {
                $validated['podyezd'] = $floor->podez->podez_number;
            }
        } else {
            $ec = $building ? (int) ($building->entrances_count ?? 1) : 1;
            if ($ec < 1) $ec = 1;

            if (isset($validated['podyezd'])) {
                if ($validated['podyezd'] > $ec) {
                    $validated['podyezd'] = $ec;
                }
            } else {
                $validated['podyezd'] = $ec;
            }
        }

        $blueprintPath = null;
        if ($request->hasFile('blueprint')) {
            $ext = strtolower($request->file('blueprint')->getClientOriginalExtension());
            if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'dwg', 'dxf'])) {
                $blueprintPath = $request->file('blueprint')->store('blueprints/units', 'public');
            }
        }

        $model3dPath = null;
        if ($request->hasFile('model_3d')) {
            $ext = strtolower($request->file('model_3d')->getClientOriginalExtension());
            if (in_array($ext, ['gltf', 'glb', 'obj', 'fbx', 'zip', 'png', 'jpg', 'jpeg', 'pdf'])) {
                $model3dPath = $request->file('model_3d')->store('models_3d/units', 'public');
            }
        }

        $validated['blueprint_path'] = $blueprintPath;
        $validated['model_3d_path'] = $model3dPath;
        $validated['has_balcony'] = $request->boolean('has_balcony');

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
            'has_balcony' => 'nullable|boolean',
            'blueprint' => 'nullable|file|max:256000',
            'model_3d' => 'nullable|file|max:1024000',
        ]);

        $floor = $unit->floor;
        $building = $floor ? $floor->building : null;

        if ($building && $building->podezes()->count() > 0) {
            if (isset($validated['podyezd'])) {
                $podez = $building->podezes()->where('podez_number', $validated['podyezd'])->first();
                if ($podez) {
                    $validated['podez_id'] = $podez->id;
                    // Move to the corresponding floor of that podez
                    $targetFloor = $building->floors()
                        ->where('podez_id', $podez->id)
                        ->where('floor_number', $floor->floor_number)
                        ->first();
                    if ($targetFloor) {
                        $validated['floor_id'] = $targetFloor->id;
                    }
                }
            }
        } else {
            $ec = $building ? (int) ($building->entrances_count ?? 1) : 1;
            if ($ec < 1) $ec = 1;

            if (isset($validated['podyezd'])) {
                if ($validated['podyezd'] > $ec) {
                    $validated['podyezd'] = $ec;
                }
            }
        }

        $blueprintPath = $unit->blueprint_path;
        if ($request->hasFile('blueprint')) {
            $ext = strtolower($request->file('blueprint')->getClientOriginalExtension());
            if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'dwg', 'dxf'])) {
                $blueprintPath = $request->file('blueprint')->store('blueprints/units', 'public');
            }
        }

        $model3dPath = $unit->model_3d_path;
        if ($request->hasFile('model_3d')) {
            $ext = strtolower($request->file('model_3d')->getClientOriginalExtension());
            if (in_array($ext, ['gltf', 'glb', 'obj', 'fbx', 'zip', 'png', 'jpg', 'jpeg', 'pdf'])) {
                $model3dPath = $request->file('model_3d')->store('models_3d/units', 'public');
            }
        }

        $validated['blueprint_path'] = $blueprintPath;
        $validated['model_3d_path'] = $model3dPath;
        $validated['has_balcony'] = $request->boolean('has_balcony');

        $unit->update($validated);

        return redirect()->back()->with('message', 'Xonadon/Do\'kon yangilandi!');
    }

    public function destroyUnit($id)
    {
        $unit = Unit::findOrFail($id);
        $unit->delete();
        return redirect()->back()->with('message', 'Xonadon/Do\'kon o\'chirildi!');
    }

    private function getReportData($buildings = null, $startDate = null, $endDate = null)
    {
        if (is_string($buildings) || is_null($buildings)) {
            // Backwards compatibility if called with $startDate as first parameter
            $endDate = $startDate;
            $startDate = $buildings;
            $buildings = Building::with(['floors.units'])->get();
        }
        
        $allBuildingIds = $buildings->pluck('id')->toArray();
        
        // Single query to fetch all contracts for all these buildings
        $allContracts = \App\Models\Contract::whereHas('unit.floor', function($q) use ($allBuildingIds) {
            $q->whereIn('building_id', $allBuildingIds);
        })
        ->where('status', '!=', 'cancelled')
        ->with(['unit.floor'])
        ->get();

        $allContractIds = $allContracts->pluck('id')->toArray();
        $allPayments = [];
        
        if (!empty($allContractIds)) {
            $allPaymentsQuery = \App\Models\Transaction::whereIn('contract_id', $allContractIds)
                ->where('status', 'approved')
                ->where('type', 'income');
            if ($startDate && $endDate) {
                $allPaymentsQuery->whereBetween('transaction_date', [$startDate, $endDate]);
            }
            $allPayments = $allPaymentsQuery->select('contract_id', \DB::raw('SUM(amount) as total_amount'))
                ->groupBy('contract_id')
                ->pluck('total_amount', 'contract_id')
                ->toArray();
        }

        $grouped = $buildings->groupBy('name');
        $report = [];
        
        foreach ($grouped as $name => $blocks) {
            $maxApartments = 0;
            $soldApartments = 0;
            $availableApartments = 0;
            $soldApartmentsPrice = 0;
            $debtPrice = 0;
            
            $buildingIds = $blocks->pluck('id')->toArray();
            
            foreach ($blocks as $block) {
                foreach ($block->floors as $floor) {
                    $maxApartments += $floor->units->count();
                    $availableApartments += $floor->units->where('status', 'available')->count();
                }
            }
            
            $projectContracts = $allContracts->filter(function($contract) use ($buildingIds) {
                return $contract->unit && $contract->unit->floor && in_array($contract->unit->floor->building_id, $buildingIds);
            });
            
            if ($startDate && $endDate) {
                $projectContracts = $projectContracts->filter(function($contract) use ($startDate, $endDate) {
                    return $contract->created_at >= $startDate && $contract->created_at <= $endDate;
                });
            }
            
            $soldApartments = $projectContracts->count();
            $soldApartmentsPrice = $projectContracts->sum('total_amount');
            
            $approvedPayments = 0;
            foreach ($projectContracts as $contract) {
                $approvedPayments += $allPayments[$contract->id] ?? 0;
            }
            
            $debtPrice = max(0, $soldApartmentsPrice - $approvedPayments);
            
            $report[] = [
                'name' => $name,
                'max_apartments' => $maxApartments,
                'sold_apartments' => $soldApartments,
                'available_apartments' => $availableApartments,
                'sold_apartments_price' => $soldApartmentsPrice,
                'debt_price' => $debtPrice,
            ];
        }
        
        return $report;
    }

    public function exportReport(Request $request)
    {
        $startDate = null;
        $endDate = null;
        if ($request->filled('start_month')) {
            $startDate = \Carbon\Carbon::parse($request->input('start_month'))->startOfMonth()->toDateTimeString();
        }
        if ($request->filled('end_month')) {
            $endDate = \Carbon\Carbon::parse($request->input('end_month'))->endOfMonth()->toDateTimeString();
        }
        if ($request->filled('start_date')) {
            $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay()->toDateTimeString();
        }
        if ($request->filled('end_date')) {
            $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay()->toDateTimeString();
        }

        $reportData = $this->getReportData($startDate, $endDate);

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=obyektlar_hisoboti_" . date('Y-m-d_H-i') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($reportData) {
            $file = fopen('php://output', 'w');
            
            // Add BOM to fix UTF-8 in Excel
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                '№', 
                'Nomi', 
                'Xonadonlar soni', 
                'Sotilgan xonadonlar', 
                'Sotuvdagi xonadonlar', 
                'Sotilgan uylar narxi (so\'m)', 
                'Kutilayotgan to\'lovlar (so\'m)'
            ], ';');

            $i = 1;
            foreach ($reportData as $row) {
                fputcsv($file, [
                    $i++,
                    $row['name'],
                    $row['max_apartments'],
                    $row['sold_apartments'],
                    $row['available_apartments'],
                    $row['sold_apartments_price'],
                    $row['debt_price']
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function uploadShowroomImages(Request $request, $id)
    {
        $building = Building::findOrFail($id);

        $request->validate([
            'images' => 'required|array',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // Max 10MB per image
        ]);

        $currentImages = $building->showroom_images ?: [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('showroom', 'public');
                $currentImages[] = $path;
            }
        }

        $building->update([
            'showroom_images' => $currentImages,
        ]);

        // Sync to all other blocks of the same building group (with the same name)
        Building::where('name', $building->name)->update([
            'showroom_images' => json_encode($currentImages),
        ]);

        return redirect()->back()->with('message', 'Showroom rasmlari muvaffaqiyatli yuklandi!');
    }

    public function deleteShowroomImage(Request $request, $id)
    {
        $building = Building::findOrFail($id);

        $request->validate([
            'image_path' => 'required|string',
        ]);

        $imagePath = $request->input('image_path');
        $currentImages = $building->showroom_images ?: [];

        // Remove from list
        $currentImages = array_values(array_filter($currentImages, function ($img) use ($imagePath) {
            return $img !== $imagePath;
        }));

        // Delete actual file from storage
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($imagePath)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
        }

        $building->update([
            'showroom_images' => $currentImages,
        ]);

        // Sync to all other blocks of the same building group
        Building::where('name', $building->name)->update([
            'showroom_images' => json_encode($currentImages),
        ]);

        return redirect()->back()->with('message', 'Showroom rasmi o\'chirildi!');
    }

    private function syncPodezes(Building $building, ?array $podezesData): void
    {
        if (is_null($podezesData)) {
            return;
        }

        DB::transaction(function () use ($building, $podezesData) {
            $existingPodezes = $building->podezes()->get()->keyBy('id');
            $requestedIds = [];

            foreach ($podezesData as $podezItem) {
                $id = $podezItem['id'] ?? null;
                $isTemp = is_string($id) && str_starts_with($id, 'temp_');

                $data = [
                    'podez_name' => $podezItem['name'],
                    'podez_number' => (int) $podezItem['number'],
                    'podez_type' => $podezItem['type'] ?? 'Staircase',
                    'total_floors' => (int) $podezItem['floors'],
                    'total_apartments' => (int) ($podezItem['total_apartments'] ?? 0),
                    'total_commercial' => (int) ($podezItem['total_commercial'] ?? 0),
                    'total_parking' => (int) ($podezItem['total_parking'] ?? 0),
                ];

                if ($id && !$isTemp && $existingPodezes->has($id)) {
                    $podez = $existingPodezes->get($id);
                    $podez->update($data);
                    $requestedIds[] = $podez->id;
                } else {
                    $podez = $building->podezes()->create($data);
                    $requestedIds[] = $podez->id;
                }

                $this->syncFloorsForPodez($podez, $podezItem['floors_config'] ?? null);
            }

            foreach ($existingPodezes as $oldId => $oldPodez) {
                if (!in_array($oldId, $requestedIds)) {
                    $hasNonAvailableUnits = Unit::where('podez_id', $oldId)
                        ->where('status', '!=', 'available')
                        ->exists();

                    if ($hasNonAvailableUnits) {
                        throw new \Exception("Bu podezda band qilingan yoki sotilgan kvartiralar bor! Avval ularni bekor qiling.");
                    }

                    Unit::where('podez_id', $oldId)->delete();
                    Floor::where('podez_id', $oldId)->delete();
                    $oldPodez->delete();
                }
            }

            $totalApartments = Unit::whereIn('podez_id', $requestedIds)->where('type', 'residential')->count();
            $totalShops = Unit::whereIn('podez_id', $requestedIds)->where('type', 'commercial')->count();
            $totalParking = Unit::whereIn('podez_id', $requestedIds)->where('type', 'parking')->count();

            $building->update([
                'entrances_count' => count($requestedIds),
                'total_apartments' => $totalApartments,
                'total_shops' => $totalShops,
                'total_parking' => $totalParking,
            ]);
        });
    }

    private function syncFloorsForPodez(Podez $podez, ?array $floorsConfig = null): void
    {
        $totalFloors = (int) $podez->total_floors;
        $building = $podez->building;

        $existingFloors = $podez->floors()->get()->keyBy('floor_number');
        $basementFloors = (int) ($building->basement_floors ?? 0);
        $desiredFloorNumbers = [];
        for ($b = -$basementFloors; $b <= -1; $b++) { $desiredFloorNumbers[] = $b; }
        for ($a = 1; $a <= $totalFloors; $a++) { $desiredFloorNumbers[] = $a; }

        foreach ($existingFloors as $floorNum => $floor) {
            if (!in_array($floorNum, $desiredFloorNumbers)) {
                $hasNonAvailable = $floor->units()->where('status', '!=', 'available')->exists();
                if (!$hasNonAvailable) {
                    $floor->units()->delete();
                    $floor->delete();
                } else {
                    throw new \Exception("Podezda band qilingan yoki sotilgan kvartiralar bor qavatni o'chirib bo'lmaydi.");
                }
            }
        }

        $shopsPerFloor = [];
        $aptsPerFloor = [];
        $parkingPerFloor = [];

        if ($floorsConfig && is_array($floorsConfig) && count($floorsConfig) > 0) {
            // Use custom configuration
            foreach ($floorsConfig as $config) {
                $f = (int) $config['floor'];
                $shopsPerFloor[$f] = (int) ($config['shops'] ?? 0);
                $aptsPerFloor[$f] = (int) ($config['apartments'] ?? 0);
                $parkingPerFloor[$f] = (int) ($config['parking'] ?? 0);
            }
        } else {
            // Distribution Logic
            $totalApts = (int) $podez->total_apartments;
            $totalComm = (int) $podez->total_commercial;
            $totalPark = (int) ($podez->total_parking ?? 0);

            $commercialFloors = [];
            for ($b = -$basementFloors; $b <= -1; $b++) { $commercialFloors[] = $b; }
            $commercialFloors[] = 1;
            $commercialFloorCount = count($commercialFloors);

            $residentialFloors = [];
            for ($a = 2; $a <= $totalFloors; $a++) { $residentialFloors[] = $a; }
            $residentialFloorCount = count($residentialFloors);

            $baseShops = $commercialFloorCount > 0 ? (int)floor($totalComm / $commercialFloorCount) : 0;
            foreach ($commercialFloors as $f) { $shopsPerFloor[$f] = $baseShops; }
            $remShops = $commercialFloorCount > 0 ? $totalComm % $commercialFloorCount : 0;
            $commercialFloorsRev = array_reverse($commercialFloors);
            for ($i = 0; $i < $remShops; $i++) { $shopsPerFloor[$commercialFloorsRev[$i]]++; }

            // Parking distribution: put parking in basement floors or first floor
            if ($totalPark > 0) {
                $parkingFloors = [];
                for ($b = -$basementFloors; $b <= -1; $b++) { $parkingFloors[] = $b; }
                if (empty($parkingFloors)) { $parkingFloors[] = 1; }
                $parkFloorCount = count($parkingFloors);
                $basePark = (int)floor($totalPark / $parkFloorCount);
                foreach ($parkingFloors as $f) { $parkingPerFloor[$f] = $basePark; }
                $remPark = $totalPark % $parkFloorCount;
                for ($i = 0; $i < $remPark; $i++) { $parkingPerFloor[$parkingFloors[$i]]++; }
            }

            // Is it evenly divisible across ALL above-ground floors?
            if ($totalFloors > 0 && $totalApts > 0 && $totalApts % $totalFloors == 0) {
                $baseApts = (int)($totalApts / $totalFloors);
                for ($a = 1; $a <= $totalFloors; $a++) { $aptsPerFloor[$a] = $baseApts; }
            } else {
                $baseApts = $residentialFloorCount > 0 ? (int)floor($totalApts / $residentialFloorCount) : $totalApts;
                foreach ($residentialFloors as $f) { $aptsPerFloor[$f] = $baseApts; }
                $remApts = $residentialFloorCount > 0 ? $totalApts % $residentialFloorCount : 0;
                $aptsPerFloor[1] = $remApts;
            }
        }

        foreach ($desiredFloorNumbers as $fNum) {
            $targetCommercial = $shopsPerFloor[$fNum] ?? 0;
            $targetResidential = $aptsPerFloor[$fNum] ?? 0;
            $targetParking = $parkingPerFloor[$fNum] ?? 0;

            if (!$existingFloors->has($fNum)) {
                $floor = Floor::create([
                    'building_id' => $building->id,
                    'podez_id' => $podez->id,
                    'floor_number' => $fNum,
                    'total_units' => $targetResidential,
                    'total_commercial_units' => $targetCommercial,
                    'total_parking_units' => $targetParking,
                ]);

                $this->createUnitsForFloorPodez($floor, 'residential', $targetResidential, $podez);
                $this->createUnitsForFloorPodez($floor, 'commercial', $targetCommercial, $podez);
                $this->createUnitsForFloorPodez($floor, 'parking', $targetParking, $podez);
            } else {
                $existingFloor = $existingFloors->get($fNum);
                $this->syncFloorUnitsPodez($existingFloor, $targetResidential, $targetCommercial, $targetParking, $podez);
            }
        }
    }

    private function createUnitsForFloorPodez(Floor $floor, string $type, int $count, Podez $podez): void
    {
        if ($count <= 0) return;
        $f = $floor->floor_number;

        for ($i = 1; $i <= $count; $i++) {
            $unitNumber = $this->generateUnitNumber($f, $i, $type);
            // Optionally prepend podez number or logic
            Unit::create([
                'floor_id' => $floor->id,
                'unit_number' => $unitNumber,
                'type' => $type,
                'area_sqm' => 0,
                'price' => 0,
                'status' => 'available',
                'podyezd' => $podez->podez_number,
                'podez_id' => $podez->id,
            ]);
        }
    }

    private function syncFloorUnitsPodez(Floor $floor, int $targetResidential, int $targetCommercial, int $targetParking, Podez $podez): void
    {
        $floor->update([
            'total_units' => $targetResidential,
            'total_commercial_units' => $targetCommercial,
            'total_parking_units' => $targetParking,
        ]);

        $this->syncUnitsByTypePodez($floor, 'residential', $targetResidential, $podez);
        $this->syncUnitsByTypePodez($floor, 'commercial', $targetCommercial, $podez);
        $this->syncUnitsByTypePodez($floor, 'parking', $targetParking, $podez);
    }

    private function syncUnitsByTypePodez(Floor $floor, string $type, int $target, Podez $podez): void
    {
        $counts = $floor->units()->where('type', $type)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status != 'available' THEN 1 ELSE 0 END) as non_available")
            ->first();
        $currentTotal = (int) $counts->total;
        $nonAvailableCount = (int) $counts->non_available;

        if ($currentTotal < $target) {
            $toAdd = $target - $currentTotal;
            $startIndex = $currentTotal + 1;
            $f = $floor->floor_number;

            for ($i = $startIndex; $i < $startIndex + $toAdd; $i++) {
                $unitNumber = $this->generateUnitNumber($f, $i, $type);
                Unit::create([
                    'floor_id' => $floor->id,
                    'unit_number' => $unitNumber,
                    'type' => $type,
                    'area_sqm' => 0,
                    'price' => 0,
                    'status' => 'available',
                    'podyezd' => $podez->podez_number,
                    'podez_id' => $podez->id,
                ]);
            }
        } elseif ($currentTotal > $target) {
            if ($nonAvailableCount > $target) {
                // Cannot remove units below the number of non-available units
                // Just remove available units until we hit the number of non-available units
                $toRemove = $currentTotal - $nonAvailableCount;
            } else {
                $toRemove = $currentTotal - $target;
            }

            if ($toRemove > 0) {
                $excessIds = $floor->units()->where('type', $type)
                    ->where('status', 'available')
                    ->orderBy('id', 'desc')
                    ->limit($toRemove)
                    ->pluck('id');
                Unit::whereIn('id', $excessIds)->delete();
            }
        }
    }

    public function destroyPodez($id)
    {
        $podez = Podez::findOrFail($id);

        $hasNonAvailableUnits = Unit::where('podez_id', $podez->id)
            ->where('status', '!=', 'available')
            ->exists();

        if ($hasNonAvailableUnits) {
            return redirect()->back()->withErrors(['error' => 'Bu podezda band qilingan yoki sotilgan kvartiralar bor! Avval ularni bekor qiling.']);
        }

        DB::transaction(function () use ($podez) {
            Unit::where('podez_id', $podez->id)->delete();
            Floor::where('podez_id', $podez->id)->delete();
            $podez->delete();
        });

        return redirect()->back()->with('message', 'Podez o\'chirildi!');
    }

    public function storeSmetaItem(Request $request)
    {
        $validated = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'block_name' => 'nullable|string|max:255',
            'category_name' => 'required|string|max:255',
            'item_name' => 'required|string|max:255',
            'unit_type' => 'required|string|max:50',
            'planned_quantity' => 'required|numeric|min:0',
            'planned_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['planned_total'] = (float)$validated['planned_quantity'] * (float)$validated['planned_price'];
        $validated['created_by'] = auth()->id();
        if (empty($validated['block_name'])) {
            $validated['block_name'] = 'Umumiy';
        }

        \App\Models\SmetaItem::create($validated);

        return redirect()->back()->with('message', 'Smeta pozitsiyasi muvaffaqiyatli qo\'shildi!');
    }

    public function updateSmetaItem(Request $request, $id)
    {
        $item = \App\Models\SmetaItem::findOrFail($id);

        $validated = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'block_name' => 'nullable|string|max:255',
            'category_name' => 'required|string|max:255',
            'item_name' => 'required|string|max:255',
            'unit_type' => 'required|string|max:50',
            'planned_quantity' => 'required|numeric|min:0',
            'planned_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['planned_total'] = (float)$validated['planned_quantity'] * (float)$validated['planned_price'];
        if (empty($validated['block_name'])) {
            $validated['block_name'] = 'Umumiy';
        }

        $item->update($validated);

        return redirect()->back()->with('message', 'Smeta pozitsiyasi yangilandi!');
    }

    public function destroySmetaItem($id)
    {
        $item = \App\Models\SmetaItem::findOrFail($id);
        $item->delete();

        return redirect()->back()->with('message', 'Smeta pozitsiyasi o\'chirildi!');
    }
}
