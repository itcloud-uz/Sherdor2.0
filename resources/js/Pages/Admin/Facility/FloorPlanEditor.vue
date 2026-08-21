<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Canvas, Rect, Polygon, Circle, Line, Point, util, FabricImage } from 'fabric';

const props = defineProps({
    buildings: {
        type: Array,
        default: () => []
    },
    initialBuildingId: {
        type: Number,
        default: null
    },
    initialFloorId: {
        type: Number,
        default: null
    }
});

const page = usePage();

// Selected Building and Floor
const selectedBuildingId = ref(props.initialBuildingId || (props.buildings.length > 0 ? props.buildings[0].id : null));
const selectedBuilding = computed(() => {
    return props.buildings.find(b => b.id === Number(selectedBuildingId.value)) || null;
});

// All floors of selected building
const availableFloors = computed(() => {
    if (!selectedBuilding.value) return [];
    let list = [];
    if (selectedBuilding.value.floors && selectedBuilding.value.floors.length > 0) {
        list = [...selectedBuilding.value.floors];
    } else if (selectedBuilding.value.podezes) {
        selectedBuilding.value.podezes.forEach(p => {
            if (p.floors) list.push(...p.floors);
        });
    }
    // Sort floors by floor_number
    return list.sort((a, b) => a.floor_number - b.floor_number);
});

const selectedFloorId = ref(props.initialFloorId || (availableFloors.value.length > 0 ? availableFloors.value[0].id : null));

// Ensure valid selectedFloorId when building changes
watch(selectedBuilding, (newB) => {
    if (newB && availableFloors.value.length > 0) {
        if (!availableFloors.value.some(f => f.id === selectedFloorId.value)) {
            selectedFloorId.value = availableFloors.value[0].id;
        }
    } else {
        selectedFloorId.value = null;
    }
}, { immediate: true });

const selectedFloor = computed(() => {
    return availableFloors.value.find(f => f.id === Number(selectedFloorId.value)) || null;
});

// Canvas & Editor State
const canvasContainerRef = ref(null);
const canvasElementRef = ref(null);
let fabricCanvas = null;

const currentTool = ref('select'); // 'select', 'rectangle', 'polygon'
const zoomLevel = ref(1);
const isSaving = ref(false);
const saveSuccessMessage = ref('');
const isUploadingBlueprint = ref(false);
const fileInputRef = ref(null);

// Active Shapes & Selected Unit Data
const shapes = ref([]);
const selectedShape = ref(null);
const selectedUnitIndex = ref(-1);

// Polygon Drawing In-Progress State
const isDrawingPolygon = ref(false);
const polygonPoints = ref([]);
const tempLines = [];
let activePolygonPreview = null;

// Status colors
const STATUS_COLORS = {
    available: { fill: 'rgba(16, 185, 129, 0.35)', stroke: '#10b981', text: '#065f46', label: 'Sotuvda' },
    reserved: { fill: 'rgba(245, 158, 11, 0.4)', stroke: '#f59e0b', text: '#92400e', label: 'Bron qilingan' },
    sold: { fill: 'rgba(239, 68, 68, 0.4)', stroke: '#ef4444', text: '#991b1b', label: 'Sotilgan' }
};

// Undo / Redo History
const historyStack = ref([]);
const historyIndex = ref(-1);
let isHistoryAction = false;

const pushHistory = () => {
    if (isHistoryAction) return;
    const state = JSON.stringify(shapes.value.map(s => ({
        id: s.id,
        unit_number: s.unit_number,
        type: s.type,
        area_sqm: s.area_sqm,
        price: s.price,
        status: s.status,
        rooms_count: s.rooms_count,
        podyezd: s.podyezd,
        shape_data: s.shape_data
    })));
    if (historyIndex.value < historyStack.value.length - 1) {
        historyStack.value = historyStack.value.slice(0, historyIndex.value + 1);
    }
    historyStack.value.push(state);
    historyIndex.value = historyStack.value.length - 1;
};

const undo = () => {
    if (historyIndex.value > 0) {
        historyIndex.value--;
        isHistoryAction = true;
        loadShapesFromHistory(JSON.parse(historyStack.value[historyIndex.value]));
        isHistoryAction = false;
    }
};

const redo = () => {
    if (historyIndex.value < historyStack.value.length - 1) {
        historyIndex.value++;
        isHistoryAction = true;
        loadShapesFromHistory(JSON.parse(historyStack.value[historyIndex.value]));
        isHistoryAction = false;
    }
};

const loadShapesFromHistory = (historyItems) => {
    if (!fabricCanvas) return;
    const objects = [...fabricCanvas.getObjects()];
    objects.forEach(obj => fabricCanvas.remove(obj));
    shapes.value = [];
    selectedShape.value = null;
    selectedUnitIndex.value = -1;

    historyItems.forEach(item => {
        const coords = item.shape_data;
        const statusConfig = STATUS_COLORS[item.status] || STATUS_COLORS.available;
        const shapeItem = { ...item, fabricObject: null };

        let fabricObj = null;
        if (coords) {
            if (coords.type === 'rect') {
                fabricObj = new Rect({
                    left: coords.left || 100,
                    top: coords.top || 100,
                    width: coords.width || 120,
                    height: coords.height || 100,
                    fill: statusConfig.fill,
                    stroke: statusConfig.stroke,
                    strokeWidth: 2,
                    cornerColor: '#3b82f6',
                    cornerSize: 8,
                    transparentCorners: false
                });
            } else if (coords.type === 'polygon' && coords.points && coords.points.length >= 3) {
                fabricObj = new Polygon(coords.points, {
                    left: coords.left !== undefined ? coords.left : undefined,
                    top: coords.top !== undefined ? coords.top : undefined,
                    fill: statusConfig.fill,
                    stroke: statusConfig.stroke,
                    strokeWidth: 2,
                    cornerColor: '#3b82f6',
                    cornerSize: 8,
                    transparentCorners: false
                });
            }
        }

        if (fabricObj) {
            fabricObj.shapeMeta = shapeItem;
            shapeItem.fabricObject = fabricObj;
            fabricCanvas.add(fabricObj);
        }

        shapes.value.push(shapeItem);
    });
    fabricCanvas.renderAll();
};

// Formatting helpers
const formatPrice = (val) => {
    if (!val) return '0 UZS';
    return Number(val).toLocaleString('uz-UZ') + ' UZS';
};

// Initialize Fabric Canvas
const initCanvas = () => {
    if (!canvasElementRef.value || !canvasContainerRef.value) return;

    if (fabricCanvas) {
        fabricCanvas.dispose();
    }

    const containerWidth = canvasContainerRef.value.clientWidth || 900;
    const containerHeight = Math.max(580, window.innerHeight - 280);

    fabricCanvas = new Canvas(canvasElementRef.value, {
        width: containerWidth,
        height: containerHeight,
        backgroundColor: '#1e293b',
        selection: true,
        preserveObjectStacking: true,
        fireRightClick: true,
        stopContextMenu: true
    });

    // Handle Object Selection
    fabricCanvas.on('selection:created', (e) => onCanvasObjectSelected(e.selected ? e.selected[0] : null));
    fabricCanvas.on('selection:updated', (e) => onCanvasObjectSelected(e.selected ? e.selected[0] : null));
    fabricCanvas.on('selection:cleared', () => {
        selectedShape.value = null;
        selectedUnitIndex.value = -1;
    });

    // Handle Object Modifications (move, scale, rotate)
    fabricCanvas.on('object:modified', (e) => {
        syncFabricObjectToState(e.target);
        pushHistory();
    });

    // Mouse Down (Drawings, Polygon points)
    fabricCanvas.on('mouse:down', (opt) => {
        const evt = opt.e;
        const pointer = fabricCanvas.getPointer(evt);

        if (currentTool.value === 'rectangle') {
            createRectangleAt(pointer.x, pointer.y);
            currentTool.value = 'select';
        } else if (currentTool.value === 'polygon') {
            handlePolygonClick(pointer);
        }
    });

    // Mouse Move (Polygon preview line)
    fabricCanvas.on('mouse:move', (opt) => {
        if (isDrawingPolygon.value && polygonPoints.value.length > 0) {
            const pointer = fabricCanvas.getPointer(opt.e);
            updatePolygonPreview(pointer);
        }
    });

    // Double click to finish polygon
    fabricCanvas.on('mouse:dblclick', () => {
        if (isDrawingPolygon.value && polygonPoints.value.length >= 3) {
            finishPolygonDrawing();
        }
    });

    // Load Floor Image and existing units
    loadFloorData();
};

// Load Background Image
const loadBackgroundImage = async (imageUrl) => {
    if (!fabricCanvas) return;

    if (!imageUrl) {
        fabricCanvas.backgroundImage = null;
        fabricCanvas.renderAll();
        return;
    }

    try {
        const img = await FabricImage.fromURL(imageUrl, { crossOrigin: 'anonymous' });
        if (!img || !fabricCanvas) return;

        const canvasWidth = fabricCanvas.getWidth();
        const canvasHeight = fabricCanvas.getHeight();

        const scaleX = (canvasWidth - 40) / (img.width || 1);
        const scaleY = (canvasHeight - 40) / (img.height || 1);
        const scale = Math.min(scaleX, scaleY, 1.2);

        img.set({
            originX: 'center',
            originY: 'center',
            left: canvasWidth / 2,
            top: canvasHeight / 2,
            scaleX: scale,
            scaleY: scale,
            selectable: false,
            evented: false
        });

        fabricCanvas.backgroundImage = img;
        fabricCanvas.renderAll();
    } catch (err) {
        console.error('Error loading background image', err);
    }
};

// Parse shape_data / svg_polygon from DB
const parseShapeCoordinates = (shapeString) => {
    if (!shapeString) return null;
    try {
        if (typeof shapeString === 'object') return shapeString;
        const parsed = JSON.parse(shapeString);
        return parsed;
    } catch (e) {
        // Might be raw SVG points string: "10,20 30,40 50,60"
        if (typeof shapeString === 'string' && shapeString.includes(',')) {
            const rawPoints = shapeString.trim().split(/\s+/).map(pair => {
                const [x, y] = pair.split(',').map(Number);
                return { x: isNaN(x) ? 0 : x, y: isNaN(y) ? 0 : y };
            });
            return { type: 'polygon', points: rawPoints };
        }
        return null;
    }
};

// Load existing floor units into canvas
const loadFloorData = () => {
    if (!fabricCanvas || !selectedFloor.value) return;

    // Clear canvas objects
    fabricCanvas.clear();
    shapes.value = [];
    selectedShape.value = null;
    selectedUnitIndex.value = -1;

    // Background Image
    const bgUrl = selectedFloor.value.blueprint_path 
        ? `/storage/${selectedFloor.value.blueprint_path}` 
        : (selectedBuilding.value?.blueprint_path ? `/storage/${selectedBuilding.value.blueprint_path}` : null);

    loadBackgroundImage(bgUrl);

    // Existing Units
    const floorUnits = selectedFloor.value.units || [];
    const loadedShapes = [];

    floorUnits.forEach((unit, idx) => {
        const coords = parseShapeCoordinates(unit.svg_polygon);
        const statusConfig = STATUS_COLORS[unit.status] || STATUS_COLORS.available;

        const shapeItem = {
            id: unit.id,
            unit_number: unit.unit_number || `Unit-${idx + 1}`,
            type: unit.type || 'residential',
            area_sqm: Number(unit.area_sqm) || 0,
            price: Number(unit.price) || 0,
            status: unit.status || 'available',
            rooms_count: unit.rooms_count || 1,
            podyezd: unit.podyezd || 1,
            shape_data: coords,
            fabricObject: null
        };

        if (coords) {
            let fabricObj = null;
            if (coords.type === 'rect') {
                fabricObj = new Rect({
                    left: coords.left || 100,
                    top: coords.top || 100,
                    width: coords.width || 120,
                    height: coords.height || 100,
                    fill: statusConfig.fill,
                    stroke: statusConfig.stroke,
                    strokeWidth: 2,
                    cornerColor: '#3b82f6',
                    cornerSize: 8,
                    transparentCorners: false,
                    hasRotatingPoint: true
                });
            } else if (coords.type === 'polygon' && coords.points && coords.points.length >= 3) {
                fabricObj = new Polygon(coords.points, {
                    left: coords.left !== undefined ? coords.left : undefined,
                    top: coords.top !== undefined ? coords.top : undefined,
                    fill: statusConfig.fill,
                    stroke: statusConfig.stroke,
                    strokeWidth: 2,
                    cornerColor: '#3b82f6',
                    cornerSize: 8,
                    transparentCorners: false
                });
            }

            if (fabricObj) {
                fabricObj.shapeMeta = shapeItem;
                fabricCanvas.add(fabricObj);
                shapeItem.fabricObject = fabricObj;
            }
        }

        loadedShapes.push(shapeItem);
    });

    shapes.value = loadedShapes;
    pushHistory();
    fabricCanvas.renderAll();
};

// Create a new Rectangle
const createRectangleAt = (x, y) => {
    const nextNum = getNextUnitNumber();
    const statusConfig = STATUS_COLORS.available;

    const newShapeMeta = {
        id: null,
        unit_number: nextNum,
        type: 'residential',
        area_sqm: 60,
        price: 500000000,
        status: 'available',
        rooms_count: 2,
        podyezd: 1,
        shape_data: {
            type: 'rect',
            left: x - 60,
            top: y - 50,
            width: 120,
            height: 100
        },
        fabricObject: null
    };

    const rect = new Rect({
        left: x - 60,
        top: y - 50,
        width: 120,
        height: 100,
        fill: statusConfig.fill,
        stroke: statusConfig.stroke,
        strokeWidth: 2,
        cornerColor: '#3b82f6',
        cornerSize: 8,
        transparentCorners: false
    });

    rect.shapeMeta = newShapeMeta;
    newShapeMeta.fabricObject = rect;

    fabricCanvas.add(rect);
    fabricCanvas.setActiveObject(rect);
    fabricCanvas.renderAll();

    shapes.value.push(newShapeMeta);
    onCanvasObjectSelected(rect);
    pushHistory();
};

// Polygon Drawing Flow
const startPolygonDrawing = () => {
    currentTool.value = 'polygon';
    isDrawingPolygon.value = true;
    polygonPoints.value = [];
};

const handlePolygonClick = (pointer) => {
    if (!isDrawingPolygon.value) {
        startPolygonDrawing();
    }

    const pt = { x: Math.round(pointer.x), y: Math.round(pointer.y) };

    // Check if clicked near the start point to close polygon
    if (polygonPoints.value.length >= 3) {
        const startPt = polygonPoints.value[0];
        const dist = Math.hypot(pt.x - startPt.x, pt.y - startPt.y);
        if (dist < 18) {
            finishPolygonDrawing();
            return;
        }
    }

    polygonPoints.value.push(pt);

    // Add a marker circle
    const circle = new Circle({
        radius: 4,
        fill: '#3b82f6',
        stroke: '#ffffff',
        strokeWidth: 1.5,
        left: pt.x - 4,
        top: pt.y - 4,
        selectable: false,
        evented: false
    });
    tempLines.push(circle);
    fabricCanvas.add(circle);

    // Add connecting line
    if (polygonPoints.value.length > 1) {
        const prevPt = polygonPoints.value[polygonPoints.value.length - 2];
        const line = new Line([prevPt.x, prevPt.y, pt.x, pt.y], {
            stroke: '#3b82f6',
            strokeWidth: 2,
            selectable: false,
            evented: false
        });
        tempLines.push(line);
        fabricCanvas.add(line);
    }

    fabricCanvas.renderAll();
};

const updatePolygonPreview = (pointer) => {
    if (polygonPoints.value.length === 0) return;
    const lastPt = polygonPoints.value[polygonPoints.value.length - 1];

    if (!activePolygonPreview) {
        activePolygonPreview = new Line([lastPt.x, lastPt.y, pointer.x, pointer.y], {
            stroke: '#93c5fd',
            strokeWidth: 1.5,
            strokeDashArray: [4, 4],
            selectable: false,
            evented: false
        });
        fabricCanvas.add(activePolygonPreview);
    } else {
        activePolygonPreview.set({
            x1: lastPt.x,
            y1: lastPt.y,
            x2: pointer.x,
            y2: pointer.y
        });
    }
    fabricCanvas.renderAll();
};

const finishPolygonDrawing = () => {
    if (polygonPoints.value.length < 3) {
        cancelPolygonDrawing();
        return;
    }

    // Clean up temporary lines & preview
    cleanTempDrawingItems();

    const nextNum = getNextUnitNumber();
    const statusConfig = STATUS_COLORS.available;
    const pts = [...polygonPoints.value];

    const newShapeMeta = {
        id: null,
        unit_number: nextNum,
        type: 'residential',
        area_sqm: 65,
        price: 550000000,
        status: 'available',
        rooms_count: 2,
        podyezd: 1,
        shape_data: {
            type: 'polygon',
            points: pts
        },
        fabricObject: null
    };

    const polygon = new Polygon(pts, {
        fill: statusConfig.fill,
        stroke: statusConfig.stroke,
        strokeWidth: 2,
        cornerColor: '#3b82f6',
        cornerSize: 8,
        transparentCorners: false
    });

    polygon.shapeMeta = newShapeMeta;
    newShapeMeta.fabricObject = polygon;

    fabricCanvas.add(polygon);
    fabricCanvas.setActiveObject(polygon);
    fabricCanvas.renderAll();

    shapes.value.push(newShapeMeta);
    onCanvasObjectSelected(polygon);

    isDrawingPolygon.value = false;
    polygonPoints.value = [];
    currentTool.value = 'select';
    pushHistory();
};

const cancelPolygonDrawing = () => {
    cleanTempDrawingItems();
    isDrawingPolygon.value = false;
    polygonPoints.value = [];
    currentTool.value = 'select';
    fabricCanvas?.renderAll();
};

const cleanTempDrawingItems = () => {
    if (activePolygonPreview) {
        fabricCanvas?.remove(activePolygonPreview);
        activePolygonPreview = null;
    }
    tempLines.forEach(item => fabricCanvas?.remove(item));
    tempLines.length = 0;
};

// Object selection listener
const onCanvasObjectSelected = (target) => {
    if (!target || !target.shapeMeta) {
        selectedShape.value = null;
        selectedUnitIndex.value = -1;
        return;
    }

    const foundIdx = shapes.value.findIndex(s => s === target.shapeMeta || (s.fabricObject && s.fabricObject === target));
    if (foundIdx > -1) {
        selectedUnitIndex.value = foundIdx;
        selectedShape.value = shapes.value[foundIdx];
    } else {
        selectedShape.value = target.shapeMeta;
    }
};

// Select unit from sidebar list
const selectUnitFromList = (index) => {
    selectedUnitIndex.value = index;
    const item = shapes.value[index];
    selectedShape.value = item;

    if (fabricCanvas && item.fabricObject) {
        fabricCanvas.setActiveObject(item.fabricObject);
        fabricCanvas.renderAll();
    }
};

// Synchronize properties back to Fabric Canvas Object
const updateSelectedShapeVisuals = () => {
    if (!selectedShape.value || !selectedShape.value.fabricObject) return;

    const obj = selectedShape.value.fabricObject;
    const statusCfg = STATUS_COLORS[selectedShape.value.status] || STATUS_COLORS.available;

    obj.set({
        fill: statusCfg.fill,
        stroke: statusCfg.stroke
    });

    syncFabricObjectToState(obj);
    fabricCanvas.renderAll();
    pushHistory();
};

// Sync Fabric.js geometry to state shape_data
const syncFabricObjectToState = (obj) => {
    if (!obj || !obj.shapeMeta) return;

    if (obj.type === 'rect') {
        obj.shapeMeta.shape_data = {
            type: 'rect',
            left: Math.round(obj.left),
            top: Math.round(obj.top),
            width: Math.round(obj.width * (obj.scaleX || 1)),
            height: Math.round(obj.height * (obj.scaleY || 1)),
            angle: obj.angle || 0
        };
    } else if (obj.type === 'polygon') {
        const matrix = obj.calcTransformMatrix();
        const transformedPoints = obj.points.map(p => {
            const point = new Point(p.x - (obj.pathOffset?.x || 0), p.y - (obj.pathOffset?.y || 0));
            const transformed = util.transformPoint(point, matrix);
            return { x: Math.round(transformed.x), y: Math.round(transformed.y) };
        });

        obj.shapeMeta.shape_data = {
            type: 'polygon',
            points: transformedPoints,
            left: Math.round(obj.left),
            top: Math.round(obj.top)
        };
    }
};

// Helper to auto-generate next apartment number based on floor
const getNextUnitNumber = () => {
    const floorNum = selectedFloor.value?.floor_number || 1;
    const count = shapes.value.length + 1;
    const padded = String(count).padStart(2, '0');
    return `${floorNum}${padded}`;
};

// Delete selected shape
const deleteSelectedShape = () => {
    if (!selectedShape.value) return;

    if (selectedShape.value.fabricObject && fabricCanvas) {
        fabricCanvas.remove(selectedShape.value.fabricObject);
    }

    shapes.value = shapes.value.filter(s => s !== selectedShape.value);
    selectedShape.value = null;
    selectedUnitIndex.value = -1;
    fabricCanvas?.renderAll();
    pushHistory();
};

// Zoom Helpers
const setZoom = (factor) => {
    if (!fabricCanvas) return;
    let newZoom = zoomLevel.value * factor;
    newZoom = Math.max(0.4, Math.min(3, newZoom));
    zoomLevel.value = newZoom;

    const center = fabricCanvas.getCenter();
    fabricCanvas.zoomToPoint(new Point(center.left, center.top), newZoom);
};

const resetZoom = () => {
    if (!fabricCanvas) return;
    zoomLevel.value = 1;
    fabricCanvas.setViewportTransform([1, 0, 0, 1, 0, 0]);
};

// Blueprint File Upload
const triggerBlueprintUpload = () => {
    fileInputRef.value?.click();
};

const onBlueprintFileSelected = async (e) => {
    const file = e.target.files[0];
    if (!file || !selectedFloor.value) return;

    const formData = new FormData();
    formData.append('blueprint', file);

    isUploadingBlueprint.value = true;
    try {
        const response = await fetch(`/admin/facility/floors/${selectedFloor.value.id}/upload-blueprint`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json'
            },
            body: formData
        });

        const data = await response.json();
        if (data.success && data.blueprint_url) {
            loadBackgroundImage(data.blueprint_url);
            saveSuccessMessage.value = 'Chizma muvaffaqiyatli yuklandi!';
            setTimeout(() => saveSuccessMessage.value = '', 3000);
        }
    } catch (err) {
        console.error('Blueprint upload failed', err);
        alert('Chizma yuklashda xatolik yuz berdi.');
    } finally {
        isUploadingBlueprint.value = false;
        e.target.value = '';
    }
};

// Save All Shapes to Server
const saveAllShapes = async () => {
    if (!selectedFloor.value) return;

    // Ensure all fabric objects have their latest geometry synced
    shapes.value.forEach(s => {
        if (s.fabricObject) {
            syncFabricObjectToState(s.fabricObject);
        }
    });

    const payload = {
        shapes: shapes.value.map(s => ({
            id: s.id,
            unit_number: s.unit_number,
            type: s.type,
            area_sqm: Number(s.area_sqm) || 0,
            price: Number(s.price) || 0,
            status: s.status,
            rooms_count: Number(s.rooms_count) || 1,
            podyezd: Number(s.podyezd) || 1,
            shape_data: s.shape_data
        }))
    };

    isSaving.value = true;
    saveSuccessMessage.value = '';

    try {
        const response = await fetch(`/admin/facility/floors/${selectedFloor.value.id}/save-shapes`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        if (data.success) {
            saveSuccessMessage.value = 'Barcha xonadonlar va chizma koordinatalari muvaffaqiyatli saqlandi!';
            setTimeout(() => saveSuccessMessage.value = '', 4000);
        }
    } catch (err) {
        console.error('Error saving floor shapes', err);
        alert('Ma\'lumotlarni saqlashda xatolik yuz berdi.');
    } finally {
        isSaving.value = false;
    }
};

// Watch floor selection changes
watch(selectedFloorId, () => {
    nextTick(() => {
        loadFloorData();
    });
});

// Mounted Lifecycle
onMounted(() => {
    nextTick(() => {
        initCanvas();
    });

    const handleResize = () => {
        if (fabricCanvas && canvasContainerRef.value) {
            const w = canvasContainerRef.value.clientWidth;
            const h = Math.max(580, window.innerHeight - 280);
            fabricCanvas.setWidth(w);
            fabricCanvas.setHeight(h);
            fabricCanvas.renderAll();
        }
    };

    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    if (fabricCanvas) {
        fabricCanvas.dispose();
    }
});
</script>

<template>
    <Head title="3D Maket / Qavat Chizmasi Muharriri" />
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.facility')" class="text-gray-400 hover:text-gray-700 transition-colors">
                    Obyektlar boshqaruvi
                </Link>
                <span class="text-gray-300">/</span>
                <span class="text-gray-800 font-bold">3D Maket & Qavat Chizmasi Muharriri</span>
            </div>
        </template>

        <div class="space-y-4 pb-12">
            <!-- Top Controls Bar -->
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-wrap items-center justify-between gap-4">
                <!-- Left: Building & Floor Selector -->
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Obyekt (Bino):</label>
                        <select v-model="selectedBuildingId" class="bg-gray-50 border border-gray-200 text-gray-800 text-sm font-bold rounded-xl px-3 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[200px]">
                            <option v-for="b in buildings" :key="b.id" :value="b.id">
                                {{ b.name }} {{ b.block_name ? `(${b.block_name})` : '' }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2" v-if="availableFloors.length > 0">
                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Qavat:</label>
                        <select v-model="selectedFloorId" class="bg-gray-50 border border-gray-200 text-gray-800 text-sm font-bold rounded-xl px-3 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[140px]">
                            <option v-for="f in availableFloors" :key="f.id" :value="f.id">
                                {{ f.floor_number < 0 ? `${f.floor_number}-yerto'la` : `${f.floor_number}-qavat` }} ({{ f.units?.length || 0 }} joy)
                            </option>
                        </select>
                    </div>

                    <!-- Quick Blueprint Upload Button -->
                    <button 
                        @click="triggerBlueprintUpload" 
                        :disabled="isUploadingBlueprint"
                        class="px-3 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-bold rounded-xl border border-blue-200 transition-all flex items-center gap-1.5 shadow-sm"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        {{ isUploadingBlueprint ? 'Yuklanmoqda...' : 'Chizma rasmini yuklash' }}
                    </button>
                    <input ref="fileInputRef" type="file" accept="image/*,.svg,.pdf" class="hidden" @change="onBlueprintFileSelected" />
                </div>

                <!-- Right: Save & Showroom Buttons -->
                <div class="flex items-center gap-3">
                    <span v-if="saveSuccessMessage" class="text-xs font-bold text-emerald-600 animate-pulse bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200">
                        {{ saveSuccessMessage }}
                    </span>

                    <Link :href="route('admin.crm.showroom')" class="px-4 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs font-bold rounded-xl border border-purple-200 transition-all flex items-center gap-1.5 shadow-sm">
                        <span>🏢</span> CRM Shou-roomda ko'rish
                    </Link>

                    <button 
                        @click="saveAllShapes" 
                        :disabled="isSaving"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center gap-2 disabled:opacity-50"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        {{ isSaving ? 'Saqlanmoqda...' : 'Barchasini Saqlash' }}
                    </button>
                </div>
            </div>

            <!-- Main Editor Workspace -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <!-- Left/Center: Canvas Area (8 cols) -->
                <div class="lg:col-span-8 flex flex-col gap-3">
                    <!-- Toolbar -->
                    <div class="bg-white p-3 rounded-2xl shadow-sm border border-gray-100 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-1.5">
                            <button 
                                @click="currentTool = 'select'; if(isDrawingPolygon) cancelPolygonDrawing();"
                                :class="currentTool === 'select' ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                                title="Tanlash va o'lchamini o'zgartirish"
                            >
                                <span>🖐️</span> Tanlash
                            </button>

                            <button 
                                @click="currentTool = 'rectangle'; if(isDrawingPolygon) cancelPolygonDrawing();"
                                :class="currentTool === 'rectangle' ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                                title="To'rtburchak xonadon chizish"
                            >
                                <span>🔲</span> To'rtburchak
                            </button>

                            <button 
                                @click="startPolygonDrawing"
                                :class="isDrawingPolygon || currentTool === 'polygon' ? 'bg-blue-600 text-white shadow-sm animate-pulse' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                                title="Murakkab ko'pburchak xonadon chizish (nuqtalarni bosing)"
                            >
                                <span>🔷</span> Ko'pburchak (Polygon)
                            </button>

                            <button 
                                v-if="selectedShape"
                                @click="deleteSelectedShape"
                                class="px-3 py-2 rounded-xl text-xs font-bold bg-red-50 text-red-600 hover:bg-red-600 hover:text-white border border-red-200 transition-all flex items-center gap-1"
                                title="Tanlangan shaklni o'chirish"
                            >
                                <span>🗑️</span> O'chirish
                            </button>
                        </div>

                        <!-- Zoom & Undo/Redo -->
                        <div class="flex items-center gap-2">
                            <button @click="undo" :disabled="historyIndex <= 0" class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 disabled:opacity-40" title="Undo (Bekor qilish)">
                                ↩️
                            </button>
                            <button @click="redo" :disabled="historyIndex >= historyStack.length - 1" class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 disabled:opacity-40" title="Redo (Qaytarish)">
                                ↪️
                            </button>
                            <div class="h-5 w-px bg-gray-200"></div>
                            <button @click="setZoom(1.2)" class="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold" title="Kattalashtirish">
                                🔍 +
                            </button>
                            <button @click="setZoom(0.8)" class="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold" title="Kichiklashtirish">
                                🔍 -
                            </button>
                            <button @click="resetZoom" class="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold" title="100% o'lcham">
                                100%
                            </button>
                        </div>
                    </div>

                    <!-- Polygon Instruction Banner -->
                    <div v-if="isDrawingPolygon" class="bg-blue-50 border border-blue-200 px-4 py-2.5 rounded-xl flex items-center justify-between text-xs text-blue-800 font-semibold shadow-sm animate-fade-in">
                        <div class="flex items-center gap-2">
                            <span class="animate-spin text-base">💠</span>
                            <span>Chizmada nuqtalarni bosing. Shaklni yakunlash uchun boshlang'ich nuqtani bosing yoki 2 marta bosing.</span>
                        </div>
                        <div class="flex gap-2">
                            <button @click="finishPolygonDrawing" :disabled="polygonPoints.length < 3" class="px-3 py-1 bg-blue-600 text-white rounded-lg font-bold disabled:opacity-50">
                                Tugatish
                            </button>
                            <button @click="cancelPolygonDrawing" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-lg font-bold hover:bg-gray-300">
                                Bekor qilish
                            </button>
                        </div>
                    </div>

                    <!-- Canvas Container -->
                    <div ref="canvasContainerRef" class="w-full bg-slate-900 rounded-2xl overflow-hidden shadow-inner relative border-2 border-slate-700 min-h-[580px] flex items-center justify-center">
                        <canvas ref="canvasElementRef"></canvas>

                        <!-- Canvas Watermark / Hint -->
                        <div class="absolute bottom-3 left-3 bg-slate-800/80 backdrop-blur-md px-3 py-1.5 rounded-lg text-[11px] text-slate-300 pointer-events-none border border-slate-700">
                            💡 Xonadon qo'shish uchun yuqoridagi <b>To'rtburchak</b> yoki <b>Ko'pburchak</b> asbobini tanlang va chizmada chizing.
                        </div>
                    </div>
                </div>

                <!-- Right: Properties & Shapes List (4 cols) -->
                <div class="lg:col-span-4 flex flex-col gap-4">
                    <!-- Unit Properties Card -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <h3 class="text-sm font-extrabold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                                <span>📐</span> Xonadon Parametrlari
                            </h3>
                            <span v-if="selectedShape" class="text-xs font-bold px-2.5 py-1 rounded-full bg-blue-50 text-blue-600 border border-blue-200">
                                № {{ selectedShape.unit_number }}
                            </span>
                        </div>

                        <div v-if="selectedShape" class="space-y-3.5">
                            <!-- Unit Number -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Xonadon / Joy raqami</label>
                                <input 
                                    type="text" 
                                    v-model="selectedShape.unit_number" 
                                    @input="updateSelectedShapeVisuals"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm font-bold text-gray-800 focus:ring-blue-500 focus:border-blue-500" 
                                    placeholder="401, 402, D-101..." 
                                />
                            </div>

                            <!-- Joy Turi & Xonalar Soni -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Joy turi</label>
                                    <select v-model="selectedShape.type" @change="updateSelectedShapeVisuals" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 focus:ring-blue-500">
                                        <option value="residential">Turar-joy (Uy)</option>
                                        <option value="commercial">Do'kon</option>
                                        <option value="parking">Parkovka</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Xonalar soni</label>
                                    <input 
                                        type="number" 
                                        v-model="selectedShape.rooms_count" 
                                        min="1" 
                                        max="20"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 focus:ring-blue-500" 
                                    />
                                </div>
                            </div>

                            <!-- Maydoni (m2) & Narxi -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Maydoni (m²)</label>
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        v-model="selectedShape.area_sqm" 
                                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 focus:ring-blue-500" 
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 mb-1">Podezd</label>
                                    <input 
                                        type="number" 
                                        min="1" 
                                        v-model="selectedShape.podyezd" 
                                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 focus:ring-blue-500" 
                                    />
                                </div>
                            </div>

                            <!-- Narxi -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Umumiy narxi (UZS)</label>
                                <input 
                                    type="number" 
                                    v-model="selectedShape.price" 
                                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm font-extrabold text-emerald-600 focus:ring-blue-500" 
                                />
                                <div class="text-[11px] text-gray-400 font-semibold mt-1 flex justify-between">
                                    <span>Format:</span>
                                    <span class="text-gray-700 font-bold">{{ formatPrice(selectedShape.price) }}</span>
                                </div>
                            </div>

                            <!-- Holati (Status) -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Sotuv holati</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button 
                                        type="button"
                                        @click="selectedShape.status = 'available'; updateSelectedShapeVisuals();"
                                        :class="selectedShape.status === 'available' ? 'bg-emerald-500 text-white font-bold ring-2 ring-emerald-300' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                        class="py-2 rounded-xl text-xs transition-all text-center"
                                    >
                                        Sotuvda
                                    </button>
                                    <button 
                                        type="button"
                                        @click="selectedShape.status = 'reserved'; updateSelectedShapeVisuals();"
                                        :class="selectedShape.status === 'reserved' ? 'bg-amber-500 text-white font-bold ring-2 ring-amber-300' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                        class="py-2 rounded-xl text-xs transition-all text-center"
                                    >
                                        Band
                                    </button>
                                    <button 
                                        type="button"
                                        @click="selectedShape.status = 'sold'; updateSelectedShapeVisuals();"
                                        :class="selectedShape.status === 'sold' ? 'bg-red-500 text-white font-bold ring-2 ring-red-300' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                        class="py-2 rounded-xl text-xs transition-all text-center"
                                    >
                                        Sotilgan
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-else class="py-8 text-center text-gray-400 font-semibold text-xs bg-gray-50 rounded-xl border border-dashed border-gray-200">
                            Chizilgan xonadonni tahrirlash uchun canvasdagi shaklni tanlang.
                        </div>
                    </div>

                    <!-- Shapes List on this Floor -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex-1 flex flex-col min-h-[300px]">
                        <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-3">
                            <h4 class="text-xs font-extrabold text-gray-700 uppercase tracking-wider">
                                Qavatdagi Shakllar Ro'yxati ({{ shapes.length }})
                            </h4>
                            <span class="text-xs font-bold text-gray-400">
                                {{ shapes.reduce((sum, s) => sum + (Number(s.area_sqm) || 0), 0).toFixed(1) }} m²
                            </span>
                        </div>

                        <div class="flex-1 overflow-y-auto space-y-2 max-h-[380px] pr-1">
                            <div 
                                v-for="(shape, idx) in shapes" 
                                :key="'shape-item-' + idx"
                                @click="selectUnitFromList(idx)"
                                :class="selectedUnitIndex === idx ? 'bg-blue-50 border-blue-400 ring-2 ring-blue-100' : 'bg-gray-50 hover:bg-gray-100 border-gray-200'"
                                class="p-3 rounded-xl border transition-all cursor-pointer flex items-center justify-between"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span 
                                        class="w-3 h-3 rounded-full shrink-0 shadow-sm"
                                        :class="{
                                            'bg-emerald-500': shape.status === 'available',
                                            'bg-amber-500': shape.status === 'reserved',
                                            'bg-red-500': shape.status === 'sold'
                                        }"
                                    ></span>
                                    <div>
                                        <div class="text-xs font-bold text-gray-900">№ {{ shape.unit_number }}</div>
                                        <div class="text-[10px] text-gray-500 font-semibold">
                                            {{ shape.rooms_count }} xona • {{ shape.area_sqm }} m² • {{ shape.podyezd }}-podezd
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <div class="text-xs font-extrabold text-emerald-600">{{ Number(shape.price).toLocaleString() }} UZS</div>
                                    <div class="text-[9px] uppercase font-bold text-gray-400">{{ shape.type }}</div>
                                </div>
                            </div>

                            <div v-if="shapes.length === 0" class="text-center py-6 text-xs text-gray-400 font-medium">
                                Hozircha ushbu qavatda chizilgan xonadonlar yo'q.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
