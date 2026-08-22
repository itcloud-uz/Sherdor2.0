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

// Editor Mode: 'facade' (Bino Fasadi Muharriri) | 'floor_plan' (Qavat Chizmasi Muharriri)
const editorMode = ref('facade');

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
    // Remove duplicates
    const unique = [];
    const map = new Map();
    for (const item of list) {
        if (!map.has(item.id)) {
            map.set(item.id, true);
            unique.push(item);
        }
    }
    return unique.sort((a, b) => a.floor_number - b.floor_number);
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
    loadCurrentModeData();
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
const isUploading = ref(false);
const fileInputRef = ref(null);
const facadeFileInputRef = ref(null);

// Active Shapes & Selection State
const shapes = ref([]); // For Floor Plan Units
const facadeFloorShapes = ref([]); // For Facade Floor bands
const selectedShape = ref(null);
const selectedItemIndex = ref(-1);

// Polygon Drawing In-Progress State
const isDrawingPolygon = ref(false);
const polygonPoints = ref([]);
const tempLines = [];
let activePolygonPreview = null;

// Natural dimensions
const currentImageDimensions = ref({ width: 1200, height: 800 });

// Status colors
const STATUS_COLORS = {
    available: { fill: 'rgba(16, 185, 129, 0.35)', stroke: '#10b981', text: '#065f46', label: 'Sotuvda' },
    reserved: { fill: 'rgba(245, 158, 11, 0.4)', stroke: '#f59e0b', text: '#92400e', label: 'Bron qilingan' },
    sold: { fill: 'rgba(239, 68, 68, 0.4)', stroke: '#ef4444', text: '#991b1b', label: 'Sotilgan' }
};

// Safe Pointer retrieval for Fabric.js 7
const getPointerCoords = (opt) => {
    if (opt.scenePoint) {
        return { x: opt.scenePoint.x, y: opt.scenePoint.y };
    }
    if (opt.viewportPoint && fabricCanvas) {
        const vpt = fabricCanvas.viewportTransform || [1, 0, 0, 1, 0, 0];
        return {
            x: (opt.viewportPoint.x - vpt[4]) / vpt[0],
            y: (opt.viewportPoint.y - vpt[5]) / vpt[3]
        };
    }
    return { x: opt.e?.offsetX || 0, y: opt.e?.offsetY || 0 };
};

// Initialize Canvas
const initCanvas = () => {
    if (!canvasContainerRef.value || !canvasElementRef.value) return;

    if (fabricCanvas) {
        fabricCanvas.dispose();
    }

    const containerWidth = canvasContainerRef.value.clientWidth || 900;
    const containerHeight = Math.max(580, window.innerHeight - 280);

    fabricCanvas = new Canvas(canvasElementRef.value, {
        width: containerWidth,
        height: containerHeight,
        backgroundColor: '#0f172a',
        selection: true,
        preserveObjectStacking: true,
        fireRightClick: true,
        stopContextMenu: true
    });

    fabricCanvas.on('selection:created', (e) => onCanvasObjectSelected(e.selected?.[0]));
    fabricCanvas.on('selection:updated', (e) => onCanvasObjectSelected(e.selected?.[0]));
    fabricCanvas.on('selection:cleared', onCanvasSelectionCleared);

    fabricCanvas.on('object:modified', (e) => {
        if (e.target && e.target.shapeMeta) {
            syncFabricObjectToMeta(e.target);
        }
    });

    // Drawing handlers
    fabricCanvas.on('mouse:down', (opt) => {
        if (opt.e.button === 2) {
            // Right click cancels polygon
            if (isDrawingPolygon.value) {
                cancelPolygonDrawing();
            }
            return;
        }

        const pointer = getPointerCoords(opt);

        if (currentTool.value === 'rectangle') {
            createRectangleAt(pointer.x, pointer.y);
            currentTool.value = 'select';
        } else if (currentTool.value === 'polygon') {
            handlePolygonPointAdd(pointer);
        }
    });

    fabricCanvas.on('mouse:move', (opt) => {
        if (isDrawingPolygon.value && polygonPoints.value.length > 0) {
            const pointer = getPointerCoords(opt);
            updatePolygonPreview(pointer);
        }
    });

    fabricCanvas.on('mouse:dblclick', () => {
        if (isDrawingPolygon.value && polygonPoints.value.length >= 3) {
            finishPolygonDrawing();
        }
    });

    loadCurrentModeData();
};

// Mode switcher handler
const switchEditorMode = (mode) => {
    editorMode.value = mode;
    currentTool.value = 'select';
    if (isDrawingPolygon.value) cancelPolygonDrawing();
    loadCurrentModeData();
};

// Load Data depending on mode
const loadCurrentModeData = () => {
    if (!fabricCanvas) return;
    if (editorMode.value === 'facade') {
        loadFacadeModeData();
    } else {
        loadFloorPlanModeData();
    }
};

// Load Background Image
const loadBackgroundImage = async (imageUrl) => {
    if (!fabricCanvas) return;

    if (!imageUrl) {
        fabricCanvas.backgroundImage = null;
        fabricCanvas.setViewportTransform([1, 0, 0, 1, 0, 0]);
        fabricCanvas.renderAll();
        return;
    }

    try {
        const img = await FabricImage.fromURL(imageUrl, { crossOrigin: 'anonymous' });
        if (!img || !fabricCanvas) return;

        const imgWidth = img.width || 1200;
        const imgHeight = img.height || 800;
        currentImageDimensions.value = { width: imgWidth, height: imgHeight };

        const containerWidth = canvasContainerRef.value?.clientWidth || 900;
        const containerHeight = Math.max(580, window.innerHeight - 280);

        fabricCanvas.setDimensions({ width: containerWidth, height: containerHeight });

        const scaleX = (containerWidth - 40) / imgWidth;
        const scaleY = (containerHeight - 40) / imgHeight;
        const scale = Math.min(scaleX, scaleY);

        const posX = (containerWidth - imgWidth * scale) / 2;
        const posY = (containerHeight - imgHeight * scale) / 2;

        img.set({
            originX: 'left',
            originY: 'top',
            left: 0,
            top: 0,
            scaleX: 1,
            scaleY: 1,
            selectable: false,
            evented: false
        });

        fabricCanvas.backgroundImage = img;
        fabricCanvas.setViewportTransform([scale, 0, 0, scale, posX, posY]);
        fabricCanvas.renderAll();
    } catch (err) {
        console.error('Error loading background image', err);
    }
};

// Parse coordinates helper
const parseCoordinates = (shapeString) => {
    if (!shapeString) return null;
    try {
        if (typeof shapeString === 'object') return shapeString;
        return JSON.parse(shapeString);
    } catch (e) {
        return null;
    }
};

// ==========================================
// 1. FACADE MODE DATA & LOGIC
// ==========================================
const loadFacadeModeData = () => {
    if (!fabricCanvas || !selectedBuilding.value) return;

    fabricCanvas.clear();
    facadeFloorShapes.value = [];
    selectedShape.value = null;
    selectedItemIndex.value = -1;

    const bgUrl = selectedBuilding.value.facade_image 
        ? `/storage/${selectedBuilding.value.facade_image}` 
        : (selectedBuilding.value.photo_path ? `/storage/${selectedBuilding.value.photo_path}` : (selectedBuilding.value.showroom_images?.[0] ? `/storage/${selectedBuilding.value.showroom_images[0]}` : null));

    loadBackgroundImage(bgUrl);

    const loadedBands = [];
    availableFloors.value.forEach((floor) => {
        const coords = parseCoordinates(floor.facade_polygon);
        const shapeMeta = {
            id: floor.id,
            floor_id: floor.id,
            floor_number: floor.floor_number,
            shape_data: coords || null,
            fabricObject: null
        };

        if (coords) {
            let fabricObj = null;
            if (coords.type === 'rect') {
                fabricObj = new Rect({
                    left: coords.left,
                    top: coords.top,
                    width: coords.width,
                    height: coords.height,
                    fill: 'rgba(59, 130, 246, 0.35)',
                    stroke: '#60a5fa',
                    strokeWidth: 2,
                    cornerColor: '#3b82f6',
                    cornerSize: 8,
                    transparentCorners: false
                });
            } else if (coords.type === 'polygon' && coords.points) {
                fabricObj = new Polygon(coords.points, {
                    fill: 'rgba(59, 130, 246, 0.35)',
                    stroke: '#60a5fa',
                    strokeWidth: 2,
                    cornerColor: '#3b82f6',
                    cornerSize: 8,
                    transparentCorners: false
                });
            }

            if (fabricObj) {
                fabricObj.shapeMeta = shapeMeta;
                shapeMeta.fabricObject = fabricObj;
                fabricCanvas.add(fabricObj);
            }
        }

        loadedBands.push(shapeMeta);
    });

    facadeFloorShapes.value = loadedBands;
    fabricCanvas.renderAll();
};

// ==========================================
// 2. FLOOR PLAN MODE DATA & LOGIC
// ==========================================
const loadFloorPlanModeData = () => {
    if (!fabricCanvas || !selectedFloor.value) return;

    fabricCanvas.clear();
    shapes.value = [];
    selectedShape.value = null;
    selectedItemIndex.value = -1;

    const bgUrl = selectedFloor.value.blueprint_path 
        ? `/storage/${selectedFloor.value.blueprint_path}` 
        : (selectedBuilding.value?.blueprint_path ? `/storage/${selectedBuilding.value.blueprint_path}` : null);

    loadBackgroundImage(bgUrl);

    const floorUnits = selectedFloor.value.units || [];
    const loadedShapes = [];

    floorUnits.forEach((unit) => {
        const coords = parseCoordinates(unit.svg_polygon);
        const statusConfig = STATUS_COLORS[unit.status] || STATUS_COLORS.available;

        const shapeItem = {
            id: unit.id,
            unit_number: unit.unit_number,
            type: unit.type || 'residential',
            area_sqm: unit.area_sqm,
            price: unit.price,
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
                    left: coords.left,
                    top: coords.top,
                    width: coords.width,
                    height: coords.height,
                    fill: statusConfig.fill,
                    stroke: statusConfig.stroke,
                    strokeWidth: 2.5,
                    cornerColor: '#3b82f6',
                    cornerSize: 8,
                    transparentCorners: false
                });
            } else if (coords.type === 'polygon' && coords.points) {
                fabricObj = new Polygon(coords.points, {
                    fill: statusConfig.fill,
                    stroke: statusConfig.stroke,
                    strokeWidth: 2.5,
                    cornerColor: '#3b82f6',
                    cornerSize: 8,
                    transparentCorners: false
                });
            }

            if (fabricObj) {
                fabricObj.shapeMeta = shapeItem;
                shapeItem.fabricObject = fabricObj;
                fabricCanvas.add(fabricObj);
            }
        }

        loadedShapes.push(shapeItem);
    });

    shapes.value = loadedShapes;
    fabricCanvas.renderAll();
};

// Create Rectangle Helper
const createRectangleAt = (x, y) => {
    const statusConfig = STATUS_COLORS.available;
    const rectW = Math.round(currentImageDimensions.value.width * 0.12) || 100;
    const rectH = Math.round(currentImageDimensions.value.height * 0.08) || 60;

    if (editorMode.value === 'facade') {
        // Find first floor without polygon or unassigned
        const unassigned = facadeFloorShapes.value.find(f => !f.shape_data) || facadeFloorShapes.value[0];
        const floorId = unassigned ? unassigned.floor_id : availableFloors.value[0]?.id;

        const newMeta = {
            id: floorId,
            floor_id: floorId,
            floor_number: unassigned?.floor_number || 1,
            shape_data: {
                type: 'rect',
                left: Math.round(x - rectW / 2),
                top: Math.round(y - rectH / 2),
                width: rectW,
                height: rectH
            },
            fabricObject: null
        };

        const rect = new Rect({
            left: Math.round(x - rectW / 2),
            top: Math.round(y - rectH / 2),
            width: rectW,
            height: rectH,
            fill: 'rgba(59, 130, 246, 0.35)',
            stroke: '#60a5fa',
            strokeWidth: 2,
            cornerColor: '#3b82f6',
            cornerSize: 8,
            transparentCorners: false
        });

        rect.shapeMeta = newMeta;
        newMeta.fabricObject = rect;
        fabricCanvas.add(rect);
        fabricCanvas.setActiveObject(rect);
        fabricCanvas.renderAll();

        if (unassigned) {
            unassigned.shape_data = newMeta.shape_data;
            unassigned.fabricObject = rect;
            rect.shapeMeta = unassigned;
        } else {
            facadeFloorShapes.value.push(newMeta);
        }
        onCanvasObjectSelected(rect);
    } else {
        // Floor Plan Mode
        const nextNum = (shapes.value.length + 1).toString();
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
                left: Math.round(x - rectW / 2),
                top: Math.round(y - rectH / 2),
                width: rectW,
                height: rectH
            },
            fabricObject: null
        };

        const rect = new Rect({
            left: Math.round(x - rectW / 2),
            top: Math.round(y - rectH / 2),
            width: rectW,
            height: rectH,
            fill: statusConfig.fill,
            stroke: statusConfig.stroke,
            strokeWidth: 2.5,
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
    }
};

// Polygon Drawing Handlers
const handlePolygonPointAdd = (pointer) => {
    if (!isDrawingPolygon.value) {
        isDrawingPolygon.value = true;
        polygonPoints.value = [];
    }

    polygonPoints.value.push({ x: pointer.x, y: pointer.y });

    // Add small visual guide circle
    const pointCircle = new Circle({
        radius: 4,
        fill: '#3b82f6',
        stroke: '#ffffff',
        strokeWidth: 1.5,
        left: pointer.x,
        top: pointer.y,
        originX: 'center',
        originY: 'center',
        selectable: false,
        evented: false
    });
    tempLines.push(pointCircle);
    fabricCanvas.add(pointCircle);
    fabricCanvas.renderAll();
};

const updatePolygonPreview = (pointer) => {
    if (activePolygonPreview) {
        fabricCanvas.remove(activePolygonPreview);
    }

    const currentPoints = [...polygonPoints.value, { x: pointer.x, y: pointer.y }];
    activePolygonPreview = new Polygon(currentPoints, {
        fill: editorMode.value === 'facade' ? 'rgba(59, 130, 246, 0.25)' : 'rgba(16, 185, 129, 0.25)',
        stroke: '#3b82f6',
        strokeWidth: 1.5,
        strokeDashArray: [4, 4],
        selectable: false,
        evented: false
    });

    fabricCanvas.add(activePolygonPreview);
    fabricCanvas.renderAll();
};

const finishPolygonDrawing = () => {
    if (polygonPoints.value.length < 3) {
        cancelPolygonDrawing();
        return;
    }

    tempLines.forEach(l => fabricCanvas.remove(l));
    tempLines.length = 0;
    if (activePolygonPreview) {
        fabricCanvas.remove(activePolygonPreview);
        activePolygonPreview = null;
    }

    const pts = [...polygonPoints.value];
    isDrawingPolygon.value = false;
    polygonPoints.value = [];
    currentTool.value = 'select';

    if (editorMode.value === 'facade') {
        const unassigned = facadeFloorShapes.value.find(f => !f.shape_data) || facadeFloorShapes.value[0];
        const floorId = unassigned ? unassigned.floor_id : availableFloors.value[0]?.id;

        const newMeta = {
            id: floorId,
            floor_id: floorId,
            floor_number: unassigned?.floor_number || 1,
            shape_data: { type: 'polygon', points: pts },
            fabricObject: null
        };

        const poly = new Polygon(pts, {
            fill: 'rgba(59, 130, 246, 0.35)',
            stroke: '#60a5fa',
            strokeWidth: 2,
            cornerColor: '#3b82f6',
            cornerSize: 8,
            transparentCorners: false
        });

        poly.shapeMeta = newMeta;
        newMeta.fabricObject = poly;
        fabricCanvas.add(poly);
        fabricCanvas.setActiveObject(poly);
        fabricCanvas.renderAll();

        if (unassigned) {
            unassigned.shape_data = newMeta.shape_data;
            unassigned.fabricObject = poly;
            poly.shapeMeta = unassigned;
        } else {
            facadeFloorShapes.value.push(newMeta);
        }
        onCanvasObjectSelected(poly);
    } else {
        const statusConfig = STATUS_COLORS.available;
        const nextNum = (shapes.value.length + 1).toString();
        const newShapeMeta = {
            id: null,
            unit_number: nextNum,
            type: 'residential',
            area_sqm: 60,
            price: 500000000,
            status: 'available',
            rooms_count: 2,
            podyezd: 1,
            shape_data: { type: 'polygon', points: pts },
            fabricObject: null
        };

        const poly = new Polygon(pts, {
            fill: statusConfig.fill,
            stroke: statusConfig.stroke,
            strokeWidth: 2.5,
            cornerColor: '#3b82f6',
            cornerSize: 8,
            transparentCorners: false
        });

        poly.shapeMeta = newShapeMeta;
        newShapeMeta.fabricObject = poly;

        fabricCanvas.add(poly);
        fabricCanvas.setActiveObject(poly);
        fabricCanvas.renderAll();

        shapes.value.push(newShapeMeta);
        onCanvasObjectSelected(poly);
    }
};

const cancelPolygonDrawing = () => {
    isDrawingPolygon.value = false;
    polygonPoints.value = [];
    tempLines.forEach(l => fabricCanvas.remove(l));
    tempLines.length = 0;
    if (activePolygonPreview) {
        fabricCanvas.remove(activePolygonPreview);
        activePolygonPreview = null;
    }
    fabricCanvas.renderAll();
};

// Sync Fabric Object to Meta Data
const syncFabricObjectToMeta = (obj) => {
    if (!obj || !obj.shapeMeta) return;
    const meta = obj.shapeMeta;

    if (obj.type === 'rect') {
        const left = Math.round(obj.left);
        const top = Math.round(obj.top);
        const width = Math.round(obj.width * (obj.scaleX || 1));
        const height = Math.round(obj.height * (obj.scaleY || 1));

        meta.shape_data = { type: 'rect', left, top, width, height };
    } else if (obj.type === 'polygon') {
        const matrix = obj.calcTransformMatrix();
        const absolutePoints = (obj.points || []).map(p => {
            const pt = util.transformPoint(
                new Point(p.x - obj.pathOffset.x, p.y - obj.pathOffset.y),
                matrix
            );
            return { x: Math.round(pt.x), y: Math.round(pt.y) };
        });

        meta.shape_data = { type: 'polygon', points: absolutePoints };
    }
};

// Selection Handling
const onCanvasObjectSelected = (obj) => {
    if (!obj || !obj.shapeMeta) return;
    selectedShape.value = obj.shapeMeta;
    if (editorMode.value === 'facade') {
        selectedItemIndex.value = facadeFloorShapes.value.findIndex(s => s === obj.shapeMeta);
    } else {
        selectedItemIndex.value = shapes.value.findIndex(s => s === obj.shapeMeta);
    }
};

const onCanvasSelectionCleared = () => {
    selectedShape.value = null;
    selectedItemIndex.value = -1;
};

// Delete Selected Shape
const deleteSelectedShape = () => {
    if (!fabricCanvas || !selectedShape.value) return;
    const obj = selectedShape.value.fabricObject;
    if (obj) {
        fabricCanvas.remove(obj);
    }
    if (editorMode.value === 'facade') {
        selectedShape.value.shape_data = null;
        selectedShape.value.fabricObject = null;
    } else {
        shapes.value = shapes.value.filter(s => s !== selectedShape.value);
    }
    selectedShape.value = null;
    selectedItemIndex.value = -1;
    fabricCanvas.renderAll();
};

// Zoom Helpers
const resetZoom = () => {
    if (!fabricCanvas) return;
    zoomLevel.value = 1;
    const imgWidth = currentImageDimensions.value.width;
    const imgHeight = currentImageDimensions.value.height;
    const containerWidth = canvasContainerRef.value?.clientWidth || 900;
    const containerHeight = Math.max(580, window.innerHeight - 280);
    const scale = Math.min((containerWidth - 40) / imgWidth, (containerHeight - 40) / imgHeight);
    const posX = (containerWidth - imgWidth * scale) / 2;
    const posY = (containerHeight - imgHeight * scale) / 2;
    fabricCanvas.setViewportTransform([scale, 0, 0, scale, posX, posY]);
    fabricCanvas.renderAll();
};

const setZoom = (factor) => {
    if (!fabricCanvas) return;
    let newZoom = zoomLevel.value * factor;
    newZoom = Math.max(0.4, Math.min(4, newZoom));
    zoomLevel.value = newZoom;
    const center = fabricCanvas.getCenterPoint ? fabricCanvas.getCenterPoint() : new Point((fabricCanvas.width || 900) / 2, (fabricCanvas.height || 600) / 2);
    fabricCanvas.zoomToPoint(center, newZoom);
};

// Save Action
const saveAllData = async () => {
    if (editorMode.value === 'facade') {
        await saveFacadeData();
    } else {
        await saveFloorPlanData();
    }
};

const saveFacadeData = async () => {
    if (!selectedBuilding.value) return;
    isSaving.value = true;
    saveSuccessMessage.value = '';

    fabricCanvas.getObjects().forEach(obj => syncFabricObjectToMeta(obj));

    const payload = {
        floors: facadeFloorShapes.value.map(f => ({
            id: f.floor_id,
            facade_polygon: f.shape_data ? JSON.stringify(f.shape_data) : null
        }))
    };

    try {
        const res = await axios.post(route('admin.facility.buildings.save_facade_floors', selectedBuilding.value.id), payload);
        saveSuccessMessage.value = 'Bino fasadi va qavatlar muvaffaqiyatli saqlandi!';
        setTimeout(() => { saveSuccessMessage.value = ''; }, 3500);
    } catch (err) {
        console.error('Error saving facade', err);
        alert('Xatolik: Saqlashda muammo yuz berdi!');
    } finally {
        isSaving.value = false;
    }
};

const saveFloorPlanData = async () => {
    if (!selectedFloor.value) return;
    isSaving.value = true;
    saveSuccessMessage.value = '';

    fabricCanvas.getObjects().forEach(obj => syncFabricObjectToMeta(obj));

    const payload = {
        shapes: shapes.value.map(s => ({
            id: s.id,
            unit_number: s.unit_number,
            type: s.type,
            area_sqm: s.area_sqm,
            price: s.price,
            status: s.status,
            rooms_count: s.rooms_count,
            podyezd: s.podyezd,
            shape_data: s.shape_data,
            svg_polygon: s.shape_data ? JSON.stringify(s.shape_data) : null
        }))
    };

    try {
        const res = await axios.post(route('admin.facility.floors.save_shapes', selectedFloor.value.id), payload);
        saveSuccessMessage.value = 'Qavat xonadonlari muvaffaqiyatli saqlandi!';
        setTimeout(() => { saveSuccessMessage.value = ''; }, 3500);
    } catch (err) {
        console.error('Error saving floor shapes', err);
        alert('Xatolik: Saqlashda muammo yuz berdi!');
    } finally {
        isSaving.value = false;
    }
};

// Upload Blueprint for Floor
const triggerBlueprintUpload = () => {
    fileInputRef.value?.click();
};

const onBlueprintFileSelected = async (e) => {
    const file = e.target.files[0];
    if (!file || !selectedFloor.value) return;

    const formData = new FormData();
    formData.append('blueprint', file);

    isUploading.value = true;
    try {
        const res = await axios.post(route('admin.facility.floors.upload_blueprint', selectedFloor.value.id), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        if (res.data?.blueprint_url) {
            await loadBackgroundImage(res.data.blueprint_url);
            saveSuccessMessage.value = 'Qavat chizmasi yuklandi!';
            setTimeout(() => { saveSuccessMessage.value = ''; }, 3000);
        }
    } catch (err) {
        alert('Fayl yuklashda xatolik yuz berdi!');
    } finally {
        isUploading.value = false;
    }
};

// Upload Facade Image for Building
const triggerFacadeUpload = () => {
    facadeFileInputRef.value?.click();
};

const onFacadeFileSelected = async (e) => {
    const file = e.target.files[0];
    if (!file || !selectedBuilding.value) return;

    const formData = new FormData();
    formData.append('facade', file);

    isUploading.value = true;
    try {
        const res = await axios.post(route('admin.facility.buildings.upload_facade', selectedBuilding.value.id), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        if (res.data?.facade_url) {
            await loadBackgroundImage(res.data.facade_url);
            saveSuccessMessage.value = 'Bino fasadi surati yuklandi!';
            setTimeout(() => { saveSuccessMessage.value = ''; }, 3000);
        }
    } catch (err) {
        alert('Fasad yuklashda xatolik yuz berdi!');
    } finally {
        isUploading.value = false;
    }
};

onMounted(() => {
    nextTick(() => {
        initCanvas();
    });
});

onUnmounted(() => {
    if (fabricCanvas) {
        fabricCanvas.dispose();
    }
});
</script>

<template>
    <Head title="3D Maket va Fasad Muharriri" />
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-4">
                    <span class="font-extrabold text-xl text-gray-800 tracking-tight">📐 3D Maket / Fasad Muharriri</span>
                    
                    <!-- Mode Switcher -->
                    <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200">
                        <button 
                            @click="switchEditorMode('facade')"
                            class="px-4 py-1.5 rounded-lg text-xs font-black transition-all flex items-center gap-1.5"
                            :class="editorMode === 'facade' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                        >
                            <span>🏢</span> Bino Fasadi Muharriri
                        </button>
                        <button 
                            @click="switchEditorMode('floor_plan')"
                            class="px-4 py-1.5 rounded-lg text-xs font-black transition-all flex items-center gap-1.5"
                            :class="editorMode === 'floor_plan' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                        >
                            <span>📐</span> Qavat Chizmasi Muharriri
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Link :href="route('admin.crm.showroom')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5">
                        <span>👁️</span> Shou-roomda ko'rish
                    </Link>
                </div>
            </div>
        </template>

        <div class="flex flex-col gap-4 h-[calc(100vh-140px)]">
            <!-- Top Controls Bar -->
            <div class="flex flex-wrap items-center justify-between gap-4 bg-white p-3.5 rounded-2xl shadow-sm border border-gray-100">
                <!-- Building & Floor Selector -->
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-gray-500 uppercase">Bino:</label>
                        <select v-model="selectedBuildingId" class="border-gray-200 rounded-xl text-xs font-bold text-gray-800 p-2 min-w-[180px]">
                            <option v-for="b in buildings" :key="b.id" :value="b.id">
                                {{ b.name }} ({{ b.block_name || 'Asosiy' }})
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2" v-if="editorMode === 'floor_plan'">
                        <label class="text-xs font-bold text-gray-500 uppercase">Qavat:</label>
                        <select v-model="selectedFloorId" @change="loadFloorPlanModeData" class="border-gray-200 rounded-xl text-xs font-bold text-gray-800 p-2 min-w-[140px]">
                            <option v-for="f in availableFloors" :key="f.id" :value="f.id">
                                {{ f.floor_number < 0 ? `${f.floor_number}-yerto'la` : `${f.floor_number}-qavat` }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Drawing Tools -->
                <div class="flex items-center gap-2 bg-gray-50 p-1.5 rounded-xl border border-gray-200">
                    <button
                        @click="currentTool = 'select'"
                        :class="currentTool === 'select' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                    >
                        <span>👆</span> Tanlash (Select)
                    </button>
                    <button
                        @click="currentTool = 'rectangle'"
                        :class="currentTool === 'rectangle' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                    >
                        <span>⬛</span> To'rtburchak (Rect)
                    </button>
                    <button
                        @click="currentTool = 'polygon'"
                        :class="currentTool === 'polygon' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5"
                    >
                        <span>⬡</span> Ko'pburchak (Polygon)
                    </button>

                    <div class="w-px h-5 bg-gray-200 mx-1"></div>

                    <!-- Zoom Controls -->
                    <button @click="setZoom(1.2)" class="px-2.5 py-1 text-gray-700 hover:bg-gray-200 rounded-lg text-xs font-bold">🔍+</button>
                    <button @click="setZoom(0.8)" class="px-2.5 py-1 text-gray-700 hover:bg-gray-200 rounded-lg text-xs font-bold">🔍-</button>
                    <button @click="resetZoom" class="px-2.5 py-1 text-gray-700 hover:bg-gray-200 rounded-lg text-xs font-bold">100%</button>
                </div>

                <!-- Upload Image & Save Buttons -->
                <div class="flex items-center gap-3">
                    <input ref="fileInputRef" type="file" accept="image/*" class="hidden" @change="onBlueprintFileSelected" />
                    <input ref="facadeFileInputRef" type="file" accept="image/*" class="hidden" @change="onFacadeFileSelected" />

                    <button
                        v-if="editorMode === 'facade'"
                        @click="triggerFacadeUpload"
                        :disabled="isUploading"
                        class="px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs rounded-xl border border-indigo-200 transition-all flex items-center gap-1.5"
                    >
                        <span>🖼️</span> Fasad Surati Yuklash
                    </button>
                    <button
                        v-else
                        @click="triggerBlueprintUpload"
                        :disabled="isUploading"
                        class="px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs rounded-xl border border-indigo-200 transition-all flex items-center gap-1.5"
                    >
                        <span>🗺️</span> Qavat Chizmasi Yuklash
                    </button>

                    <button
                        @click="saveAllData"
                        :disabled="isSaving"
                        class="px-5 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-extrabold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2"
                    >
                        <span v-if="isSaving">⏳</span>
                        <span v-else>💾</span>
                        <span>Barchasini Saqlash</span>
                    </button>
                </div>
            </div>

            <!-- Success Alert -->
            <div v-if="saveSuccessMessage" class="bg-emerald-500 text-white text-xs font-bold px-4 py-2 rounded-xl shadow-md flex items-center justify-between animate-in fade-in duration-200">
                <span>✅ {{ saveSuccessMessage }}</span>
                <button @click="saveSuccessMessage = ''" class="text-white/80 hover:text-white">✕</button>
            </div>

            <!-- Main Canvas Area & Properties Sidebar -->
            <div class="flex-1 flex gap-4 min-h-0">
                <!-- Canvas Viewport -->
                <div ref="canvasContainerRef" class="flex-1 bg-slate-900 rounded-3xl border border-slate-800 overflow-hidden relative flex items-center justify-center shadow-inner">
                    <canvas ref="canvasElementRef"></canvas>

                    <!-- Hint Overlay for Polygon -->
                    <div v-if="isDrawingPolygon" class="absolute bottom-4 left-4 bg-black/75 backdrop-blur-md px-4 py-2 rounded-xl border border-white/20 text-white text-xs font-semibold flex items-center gap-2">
                        <span>💡</span> Nuqtalarni belgilang. Yakunlash uchun oxirgi nuqtani <b>ikki marta bosing (Double-Click)</b>. Bekor qilish uchun <b>sichqoncha o'ng tugmasi</b>.
                    </div>
                </div>

                <!-- Properties Sidebar -->
                <div class="w-80 bg-white rounded-3xl border border-gray-100 p-5 overflow-y-auto flex flex-col gap-4 shadow-sm text-gray-800">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <h3 class="font-extrabold text-sm text-gray-900 tracking-tight">
                            {{ editorMode === 'facade' ? 'Qavat Fasad Koordinatasi' : 'Xonadon Parametrlari' }}
                        </h3>
                        <button 
                            v-if="selectedShape"
                            @click="deleteSelectedShape" 
                            class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold rounded-lg transition-all"
                        >
                            O'chirish
                        </button>
                    </div>

                    <!-- Facade Mode Form -->
                    <div v-if="editorMode === 'facade'" class="space-y-4">
                        <div v-if="selectedShape">
                            <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Ushbu chiziq tegishli qavat:</label>
                            <select v-model="selectedShape.floor_id" class="w-full text-xs font-bold rounded-xl border-gray-200 p-2.5 focus:ring-blue-500">
                                <option v-for="f in availableFloors" :key="f.id" :value="f.id">
                                    {{ f.floor_number < 0 ? `${f.floor_number}-yerto'la` : `${f.floor_number}-qavat` }}
                                </option>
                            </select>
                        </div>
                        <div v-else class="text-xs text-gray-400 font-semibold p-4 bg-gray-50 rounded-2xl border border-dashed border-gray-200 text-center">
                            Fasadda qavatni belgilash uchun yuqoridan To'rtburchak yoki Ko'pburchak vositasini tanlab chizing.
                        </div>

                        <!-- All Floors Facade Status List -->
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <h4 class="text-xs font-extrabold text-gray-700 uppercase tracking-wider mb-2">Qavatlar ro'yxati:</h4>
                            <div class="space-y-1.5 max-h-72 overflow-y-auto">
                                <div 
                                    v-for="item in facadeFloorShapes" 
                                    :key="'fl-item-' + item.floor_id"
                                    class="p-2.5 rounded-xl border flex items-center justify-between text-xs font-bold transition-all"
                                    :class="item.shape_data ? 'bg-emerald-50/60 border-emerald-200 text-emerald-800' : 'bg-gray-50 border-gray-200 text-gray-500'"
                                >
                                    <span>{{ item.floor_number < 0 ? `${item.floor_number}-yerto'la` : `${item.floor_number}-qavat` }}</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full" :class="item.shape_data ? 'bg-emerald-200 text-emerald-900' : 'bg-gray-200 text-gray-600'">
                                        {{ item.shape_data ? 'Chizilgan ✓' : 'Chizilmagan' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Floor Plan Mode Form -->
                    <div v-else-if="editorMode === 'floor_plan'" class="space-y-3">
                        <div v-if="selectedShape" class="space-y-3">
                            <div>
                                <label class="text-xs font-bold text-gray-500 block mb-1">Xonadon raqami:</label>
                                <input v-model="selectedShape.unit_number" class="w-full text-xs font-bold rounded-xl border-gray-200 p-2.5 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 block mb-1">Maydoni (m²):</label>
                                <input v-model.number="selectedShape.area_sqm" type="number" step="0.1" class="w-full text-xs font-bold rounded-xl border-gray-200 p-2.5 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 block mb-1">Narxi (UZS):</label>
                                <input v-model.number="selectedShape.price" type="number" class="w-full text-xs font-bold rounded-xl border-gray-200 p-2.5 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 block mb-1">Xonalar soni:</label>
                                <input v-model.number="selectedShape.rooms_count" type="number" min="1" class="w-full text-xs font-bold rounded-xl border-gray-200 p-2.5 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 block mb-1">Holati:</label>
                                <select v-model="selectedShape.status" class="w-full text-xs font-bold rounded-xl border-gray-200 p-2.5 focus:ring-blue-500">
                                    <option value="available">Sotuvda</option>
                                    <option value="reserved">Bron qilingan</option>
                                    <option value="sold">Sotilgan</option>
                                </select>
                            </div>
                        </div>
                        <div v-else class="text-xs text-gray-400 font-semibold p-4 bg-gray-50 rounded-2xl border border-dashed border-gray-200 text-center">
                            Chizilgan xonadon ustiga bosing yoki yangi shakl chizing.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
