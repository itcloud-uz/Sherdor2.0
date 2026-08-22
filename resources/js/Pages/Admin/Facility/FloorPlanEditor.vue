<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

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

// Selected Building
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

// Canvas & Editor State
const canvasRef = useRefCanvas();
const fileInputRef = ref(null);

function useRefCanvas() {
    return ref(null);
}

const canvasElement = ref(null);
const ctx = ref(null);
const facadeImageObj = ref(null);
const facadeImageUrl = ref(null);

const shapes = ref([]);
const selectedFloor = ref(null);
const drawingMode = ref('rect'); // 'select', 'rect', 'polygon'
const isDrawing = ref(false);
const startPos = ref(null);
const zoom = ref(100);
const message = ref('');
const selectedShapeId = ref(null);
const isSaving = ref(false);

// Polygon in-progress drawing points
const polygonPoints = ref([]);

// Image natural size & render size
const imgRenderBounds = ref({ x: 50, y: 50, w: 800, h: 600 });

// Parse coordinates
const parseCoordinates = (raw) => {
    if (!raw) return null;
    if (typeof raw === 'object') return raw;
    try {
        return JSON.parse(raw);
    } catch(e) {
        return null;
    }
};

// Initialize building data and floors
const loadBuildingData = () => {
    if (!selectedBuilding.value) return;

    shapes.value = [];
    selectedShapeId.value = null;

    // Set initial selected floor
    if (availableFloors.value.length > 0 && !selectedFloor.value) {
        selectedFloor.value = availableFloors.value[0].floor_number;
    }

    // Set facade image
    const bgUrl = selectedBuilding.value.facade_image 
        ? `/storage/${selectedBuilding.value.facade_image}` 
        : (selectedBuilding.value.photo_path ? `/storage/${selectedBuilding.value.photo_path}` : '/images/clean-building-facade.jpg');

    loadImage(bgUrl);

    // Load existing floor shapes from DB
    const loaded = [];
    availableFloors.value.forEach((floor) => {
        const coords = parseCoordinates(floor.facade_polygon);
        if (coords) {
            if (coords.type === 'rect') {
                loaded.push({
                    id: floor.id,
                    floorId: floor.id,
                    floor: floor.floor_number,
                    floorLabel: floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat`,
                    type: 'rect',
                    x: coords.left || coords.x || 100,
                    y: coords.top || coords.y || 100,
                    w: coords.width || coords.w || 200,
                    h: coords.height || coords.h || 60
                });
            } else if (coords.type === 'polygon' && coords.points) {
                loaded.push({
                    id: floor.id,
                    floorId: floor.id,
                    floor: floor.floor_number,
                    floorLabel: floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat`,
                    type: 'polygon',
                    points: coords.points
                });
            }
        }
    });

    shapes.value = loaded;
    nextTick(() => redrawCanvas());
};

watch(selectedBuilding, () => {
    loadBuildingData();
}, { immediate: true });

// Load Image into Canvas
const loadImage = (url) => {
    if (!url) {
        facadeImageObj.value = null;
        facadeImageUrl.value = null;
        redrawCanvas();
        return;
    }

    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
        facadeImageObj.value = img;
        facadeImageUrl.value = url;

        const cw = canvasElement.value?.width || 900;
        const ch = canvasElement.value?.height || 700;

        const scaleX = (cw - 80) / img.width;
        const scaleY = (ch - 80) / img.height;
        const scale = Math.min(scaleX, scaleY, 1);

        const rw = img.width * scale;
        const rh = img.height * scale;
        const rx = (cw - rw) / 2;
        const ry = (ch - rh) / 2;

        imgRenderBounds.value = { x: rx, y: ry, w: rw, h: rh };
        redrawCanvas();
    };
    img.src = url;
};

// Canvas drawing functions
const redrawCanvas = () => {
    const canvas = canvasElement.value;
    if (!canvas) return;
    const context = ctx.value || canvas.getContext('2d');
    if (!context) return;
    ctx.value = context;

    // Background
    context.fillStyle = '#0f172a';
    context.fillRect(0, 0, canvas.width, canvas.height);

    // Draw facade image if exists
    if (facadeImageObj.value) {
        const b = imgRenderBounds.value;
        context.drawImage(facadeImageObj.value, b.x, b.y, b.w, b.h);
        context.strokeStyle = 'rgba(255, 255, 255, 0.1)';
        context.lineWidth = 1;
        context.strokeRect(b.x, b.y, b.w, b.h);
    } else {
        context.fillStyle = '#1e293b';
        context.fillRect(50, 50, canvas.width - 100, canvas.height - 100);
        context.fillStyle = '#94a3b8';
        context.font = 'bold 16px Arial';
        context.textAlign = 'center';
        context.fillText('Fasad rasmini yuklash uchun yuqoridagi tugmani bosing', canvas.width / 2, canvas.height / 2);
    }

    // Draw all shapes
    drawShapes(context, shapes.value);

    // Draw polygon preview if in-progress
    if (drawingMode.value === 'polygon' && polygonPoints.value.length > 0) {
        context.strokeStyle = '#38bdf8';
        context.fillStyle = 'rgba(56, 189, 248, 0.2)';
        context.lineWidth = 2;
        context.setLineDash([4, 4]);

        context.beginPath();
        context.moveTo(polygonPoints.value[0].x, polygonPoints.value[0].y);
        for (let i = 1; i < polygonPoints.value.length; i++) {
            context.lineTo(polygonPoints.value[i].x, polygonPoints.value[i].y);
        }
        context.stroke();
        context.setLineDash([]);

        polygonPoints.value.forEach(p => {
            context.fillStyle = '#38bdf8';
            context.beginPath();
            context.arc(p.x, p.y, 4, 0, Math.PI * 2);
            context.fill();
        });
    }
};

// Draw all shapes with handles & labels
const drawShapes = (context, currentShapes) => {
    currentShapes.forEach((shape) => {
        const isSelected = shape.id === selectedShapeId.value;

        if (shape.type === 'rect') {
            context.strokeStyle = isSelected ? '#FFD700' : '#22C55E';
            context.fillStyle = isSelected ? 'rgba(255, 215, 0, 0.28)' : 'rgba(34, 197, 94, 0.22)';
            context.lineWidth = isSelected ? 3 : 2;

            context.fillRect(shape.x, shape.y, shape.w, shape.h);
            context.strokeRect(shape.x, shape.y, shape.w, shape.h);

            // Label
            context.fillStyle = '#FFFFFF';
            context.font = 'bold 13px sans-serif';
            context.textAlign = 'left';
            context.fillText(shape.floorLabel || `${shape.floor}-qavat`, shape.x + 8, shape.y + 20);

            // Selection handles
            if (isSelected) {
                drawResizeHandles(context, shape);
            }
        } else if (shape.type === 'polygon') {
            context.strokeStyle = isSelected ? '#FFD700' : '#10B981';
            context.fillStyle = isSelected ? 'rgba(255, 215, 0, 0.28)' : 'rgba(16, 185, 129, 0.22)';
            context.lineWidth = isSelected ? 3 : 2;

            if (shape.points && shape.points.length > 0) {
                context.beginPath();
                context.moveTo(shape.points[0].x || shape.points[0][0], shape.points[0].y || shape.points[0][1]);
                for (let i = 1; i < shape.points.length; i++) {
                    const pt = shape.points[i];
                    context.lineTo(pt.x || pt[0], pt.y || pt[1]);
                }
                context.closePath();
                context.fill();
                context.stroke();

                // Centroid Label
                const centroid = getCentroid(shape.points);
                context.fillStyle = '#FFFFFF';
                context.font = 'bold 13px sans-serif';
                context.textAlign = 'center';
                context.fillText(shape.floorLabel || `${shape.floor}-qavat`, centroid.x, centroid.y);
            }
        }
    });
};

const drawResizeHandles = (context, shape) => {
    const handles = [
        [shape.x, shape.y],
        [shape.x + shape.w, shape.y],
        [shape.x + shape.w, shape.y + shape.h],
        [shape.x, shape.y + shape.h],
        [shape.x + shape.w / 2, shape.y],
        [shape.x + shape.w / 2, shape.y + shape.h],
        [shape.x, shape.y + shape.h / 2],
        [shape.x + shape.w, shape.y + shape.h / 2]
    ];

    context.fillStyle = '#FFD700';
    context.strokeStyle = '#000000';
    context.lineWidth = 1;
    handles.forEach((handle) => {
        context.fillRect(handle[0] - 4, handle[1] - 4, 8, 8);
        context.strokeRect(handle[0] - 4, handle[1] - 4, 8, 8);
    });
};

const getCentroid = (points) => {
    let sx = 0, sy = 0;
    points.forEach(p => {
        sx += (p.x || p[0]);
        sy += (p.y || p[1]);
    });
    return { x: sx / points.length, y: sy / points.length };
};

// Image Upload Handler
const handleImageUpload = (e) => {
    const file = e.target.files?.[0];
    if (!file || !selectedBuilding.value) return;

    const reader = new FileReader();
    reader.onload = (event) => {
        const imageData = event.target?.result;
        loadImage(imageData);
        message.value = "✓ Fasad rasmi yuklandi!";
        setTimeout(() => message.value = '', 3000);
    };
    reader.readAsDataURL(file);

    // Also upload to server
    const formData = new FormData();
    formData.append('facade', file);
    axios.post(route('admin.facility.buildings.upload_facade', selectedBuilding.value.id), formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    }).catch(() => {});
};

// Canvas Mouse Events
const handleCanvasMouseDown = (e) => {
    if (!canvasElement.value) return;

    if (!selectedFloor.value) {
        message.value = '⚠️ Avval chap tarafdan qavatni tanlang!';
        setTimeout(() => message.value = '', 3000);
        return;
    }

    const rect = canvasElement.value.getBoundingClientRect();
    const x = (e.clientX - rect.left) / (zoom.value / 100);
    const y = (e.clientY - rect.top) / (zoom.value / 100);

    if (drawingMode.value === 'select') {
        const clickedShape = shapes.value.find(
            (s) => s.type === 'rect' && x >= s.x && x <= s.x + s.w && y >= s.y && y <= s.y + s.h
        );
        selectedShapeId.value = clickedShape?.id || null;
        if (clickedShape) {
            selectedFloor.value = clickedShape.floor;
        }
        redrawCanvas();
    } else if (drawingMode.value === 'rect') {
        isDrawing.value = true;
        startPos.value = [x, y];
    } else if (drawingMode.value === 'polygon') {
        polygonPoints.value.push({ x, y });
        redrawCanvas();
    }
};

const handleCanvasMouseMove = (e) => {
    if (!canvasElement.value) return;
    const rect = canvasElement.value.getBoundingClientRect();
    const x = (e.clientX - rect.left) / (zoom.value / 100);
    const y = (e.clientY - rect.top) / (zoom.value / 100);

    if (isDrawing.value && startPos.value && drawingMode.value === 'rect') {
        redrawCanvas();

        const context = ctx.value;
        if (!context) return;
        context.strokeStyle = '#22C55E';
        context.fillStyle = 'rgba(34, 197, 94, 0.25)';
        context.lineWidth = 2;
        context.setLineDash([4, 4]);

        const rx = Math.min(startPos.value[0], x);
        const ry = Math.min(startPos.value[1], y);
        const rw = Math.abs(x - startPos.value[0]);
        const rh = Math.abs(y - startPos.value[1]);

        context.fillRect(rx, ry, rw, rh);
        context.strokeRect(rx, ry, rw, rh);
        context.setLineDash([]);
    }
};

const handleCanvasMouseUp = (e) => {
    if (!canvasElement.value) return;
    if (!isDrawing.value || !startPos.value) {
        isDrawing.value = false;
        return;
    }

    const rect = canvasElement.value.getBoundingClientRect();
    const x = (e.clientX - rect.left) / (zoom.value / 100);
    const y = (e.clientY - rect.top) / (zoom.value / 100);

    if (drawingMode.value === 'rect') {
        const width = Math.abs(x - startPos.value[0]);
        const height = Math.abs(y - startPos.value[1]);
        const left = Math.min(startPos.value[0], x);
        const top = Math.min(startPos.value[1], y);

        if (width > 10 && height > 10) {
            // Find target floor object
            const floorObj = availableFloors.value.find(f => f.floor_number === selectedFloor.value);
            const floorId = floorObj ? floorObj.id : Date.now();
            const floorLabel = floorObj ? (floorObj.floor_number < 0 ? `${floorObj.floor_number}-yerto'la` : `${floorObj.floor_number}-qavat`) : `${selectedFloor.value}-qavat`;

            // Remove existing shape for this floor if replacing
            const filtered = shapes.value.filter(s => s.floor !== selectedFloor.value);

            const newShape = {
                id: floorId,
                floorId: floorId,
                floor: selectedFloor.value,
                floorLabel: floorLabel,
                type: 'rect',
                x: Math.round(left),
                y: Math.round(top),
                w: Math.round(width),
                h: Math.round(height)
            };

            shapes.value = [...filtered, newShape];
            selectedShapeId.value = newShape.id;
            redrawCanvas();

            message.value = `✓ ${floorLabel} chizildi!`;
            setTimeout(() => message.value = '', 2500);
        }
    }

    isDrawing.value = false;
    startPos.value = null;
};

// Finish Polygon with Double Click
const handleCanvasDoubleClick = () => {
    if (drawingMode.value === 'polygon' && polygonPoints.value.length >= 3) {
        const floorObj = availableFloors.value.find(f => f.floor_number === selectedFloor.value);
        const floorId = floorObj ? floorObj.id : Date.now();
        const floorLabel = floorObj ? (floorObj.floor_number < 0 ? `${floorObj.floor_number}-yerto'la` : `${floorObj.floor_number}-qavat`) : `${selectedFloor.value}-qavat`;

        const filtered = shapes.value.filter(s => s.floor !== selectedFloor.value);

        const newShape = {
            id: floorId,
            floorId: floorId,
            floor: selectedFloor.value,
            floorLabel: floorLabel,
            type: 'polygon',
            points: [...polygonPoints.value]
        };

        shapes.value = [...filtered, newShape];
        selectedShapeId.value = newShape.id;
        polygonPoints.value = [];
        redrawCanvas();

        message.value = `✓ ${floorLabel} ko'pburchak chizildi!`;
        setTimeout(() => message.value = '', 2500);
    }
};

// Select floor from left list
const handleSelectFloor = (floorNum) => {
    selectedFloor.value = floorNum;
    const existing = shapes.value.find(s => s.floor === floorNum);
    selectedShapeId.value = existing?.id || null;
    redrawCanvas();
};

// Delete selected shape
const handleDeleteShape = () => {
    if (!selectedShapeId.value) return;
    const filtered = shapes.value.filter(s => s.id !== selectedShapeId.value);
    shapes.value = filtered;
    selectedShapeId.value = null;
    redrawCanvas();
    message.value = "🗑️ Shakl o'chirildi!";
    setTimeout(() => message.value = '', 2000);
};

// Save shapes to database
const handleSave = async () => {
    if (!selectedBuilding.value) return;
    if (shapes.value.length === 0) {
        message.value = '⚠️ Hech qanday shakl chizilmagan!';
        setTimeout(() => message.value = '', 3000);
        return;
    }

    try {
        isSaving.value = true;
        message.value = '💾 Saqlash jarayonida...';

        const floorCoords = shapes.value.map(s => {
            const floorObj = availableFloors.value.find(f => f.floor_number === s.floor);
            let shapeJson = null;
            if (s.type === 'rect') {
                shapeJson = { type: 'rect', left: Math.round(s.x), top: Math.round(s.y), width: Math.round(s.w), height: Math.round(s.h) };
            } else if (s.type === 'polygon') {
                shapeJson = { type: 'polygon', points: s.points };
            }
            return {
                id: floorObj?.id || s.floorId || s.id,
                floor_id: floorObj?.id || s.floorId || s.id,
                facade_polygon: shapeJson,
                polygon: shapeJson
            };
        });

        const targetUrl = route('admin.facility.buildings.save_facade_floors', selectedBuilding.value.id);
        const res = await axios.post(targetUrl, {
            floors: floorCoords,
            facade_data: { shapes: shapes.value }
        });

        if (res.data?.success) {
            message.value = '✅ Barcha qavat koordinatalari muvaffaqiyatli saqlandi!';
        } else {
            message.value = '✅ Saqlandi ✓';
        }
        setTimeout(() => message.value = '', 3500);
    } catch(err) {
        console.error('Save error:', err);
        message.value = '❌ Saqlashda xatolik: ' + (err.response?.data?.message || err.message || 'Xatolik');
        setTimeout(() => message.value = '', 4000);
    } finally {
        isSaving.value = false;
    }
};

const drawnFloors = computed(() => new Set(shapes.value.map(s => s.floor)));
const selectedShapeObj = computed(() => shapes.value.find(s => s.id === selectedShapeId.value) || null);

onMounted(() => {
    nextTick(() => {
        if (canvasElement.value) {
            ctx.value = canvasElement.value.getContext('2d');
            loadBuildingData();
        }
    });
});
</script>

<template>
    <Head title="3D Maket / Fasad Muharriri" />

    <!-- 100% Full-Screen Dark Standalone Editor Matching User Code Exactly -->
    <div class="fixed inset-0 z-50 flex flex-col w-screen h-screen overflow-hidden bg-gray-900 font-sans text-gray-100 select-none">
        
        <!-- Header Toolbar -->
        <div class="bg-gray-800 border-b border-gray-700 px-6 py-3.5 flex items-center justify-between shadow-lg">
            <!-- Left: Building Dropdown -->
            <div class="flex items-center gap-4">
                <span class="text-white font-extrabold text-base flex items-center gap-2">
                    <span>📐</span> 3D Maket / Fasad Muharriri
                </span>

                <select 
                    v-model="selectedBuildingId" 
                    class="bg-gray-700 hover:bg-gray-600 text-white font-bold text-xs px-4 py-2 rounded-xl border border-gray-600 focus:ring-blue-500 cursor-pointer"
                >
                    <option v-for="b in buildings" :key="b.id" :value="b.id">
                        {{ b.name }} ({{ b.block_name || 'Asosiy' }})
                    </option>
                </select>
            </div>

            <!-- Right: Drawing & Action Tools -->
            <div class="flex items-center gap-2">
                <!-- Tool buttons -->
                <button
                    @click="drawingMode = 'select'"
                    :class="drawingMode === 'select' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                    title="Tanlash (Select)"
                >
                    <span>👆</span> Tanlash (Select)
                </button>

                <button
                    @click="drawingMode = 'rect'"
                    :class="drawingMode === 'rect' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                    title="To'rtburchak (Rectangle)"
                >
                    <span>⬛</span> To'rtburchak (Rect)
                </button>

                <button
                    @click="drawingMode = 'polygon'"
                    :class="drawingMode === 'polygon' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                    title="Ko'pburchak (Polygon)"
                >
                    <span>⬡</span> Ko'pburchak (Polygon)
                </button>

                <div class="border-l border-gray-700 h-6 mx-2"></div>

                <!-- Zoom controls -->
                <button
                    @click="zoom = Math.min(zoom + 10, 200); redrawCanvas();"
                    class="px-3 py-2 bg-gray-700 text-gray-200 hover:bg-gray-600 rounded-xl text-xs font-bold transition-all"
                    title="Zoom in"
                >
                    🔍+
                </button>

                <span class="text-white font-mono font-bold text-xs w-12 text-center">{{ zoom }}%</span>

                <button
                    @click="zoom = Math.max(zoom - 10, 50); redrawCanvas();"
                    class="px-3 py-2 bg-gray-700 text-gray-200 hover:bg-gray-600 rounded-xl text-xs font-bold transition-all"
                    title="Zoom out"
                >
                    🔍-
                </button>

                <button
                    @click="zoom = 100; redrawCanvas();"
                    class="px-2.5 py-2 bg-gray-700 text-gray-300 hover:bg-gray-600 rounded-xl text-xs font-bold"
                    title="Reset Zoom"
                >
                    100%
                </button>

                <div class="border-l border-gray-700 h-6 mx-2"></div>

                <!-- Upload button -->
                <label class="px-3.5 py-2 bg-indigo-600 text-white hover:bg-indigo-700 rounded-xl font-bold text-xs cursor-pointer flex items-center gap-1.5 shadow transition-all">
                    <span>🖼️</span> Fasad Surati Yuklash
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept="image/*"
                        @change="handleImageUpload"
                        class="hidden"
                    />
                </label>

                <!-- Delete button -->
                <button
                    v-if="selectedShapeId"
                    @click="handleDeleteShape"
                    class="px-3.5 py-2 bg-red-600 text-white hover:bg-red-700 rounded-xl font-bold text-xs transition-all flex items-center gap-1 shadow"
                >
                    <span>🗑️</span> O'chirish
                </button>

                <!-- Save button -->
                <button
                    @click="handleSave"
                    :disabled="isSaving"
                    class="px-5 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white rounded-xl font-black text-xs flex items-center gap-1.5 shadow-lg transition-all"
                >
                    <span>{{ isSaving ? '⏳' : '💾' }}</span>
                    <span>Barchasini Saqlash</span>
                </button>

                <!-- View in Showroom -->
                <Link
                    :href="route('admin.crm.showroom')"
                    class="px-3.5 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded-xl font-bold text-xs transition-all flex items-center gap-1.5 ml-2"
                >
                    <span>👁️</span> Shou-room
                </Link>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="flex flex-1 overflow-hidden">
            
            <!-- Sidebar: Floors List (LEFT) -->
            <div class="w-64 bg-gray-800 border-r border-gray-700 p-4 overflow-y-auto flex flex-col gap-3">
                <div class="flex items-center justify-between pb-2 border-b border-gray-700">
                    <h3 class="text-white font-extrabold text-sm uppercase tracking-wide">Qavatlar Ro'yxati</h3>
                    <span class="text-xs text-gray-400 font-mono">{{ drawnFloors.size }}/{{ availableFloors.length }}</span>
                </div>

                <div class="space-y-1.5 flex-1 overflow-y-auto pr-1">
                    <button
                        v-for="floor in availableFloors"
                        :key="'fl-btn-' + floor.id"
                        @click="handleSelectFloor(floor.floor_number)"
                        class="w-full p-2.5 rounded-xl font-bold text-xs transition-all text-left flex items-center justify-between"
                        :class="selectedFloor === floor.floor_number 
                            ? 'bg-blue-600 text-white shadow-md ring-2 ring-blue-400' 
                            : (drawnFloors.has(floor.floor_number) ? 'bg-gray-700/80 text-emerald-300 hover:bg-gray-700' : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700')"
                    >
                        <span>{{ floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat` }}</span>
                        <span 
                            class="text-[10px] px-2 py-0.5 rounded-full font-black"
                            :class="drawnFloors.has(floor.floor_number) ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-gray-600 text-gray-400'"
                        >
                            {{ drawnFloors.has(floor.floor_number) ? 'Chizildi ✓' : 'Chizilmagan' }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Canvas Viewport (CENTER) -->
            <div class="flex-1 flex flex-col items-center justify-center p-4 overflow-hidden bg-gray-950 relative">
                <div
                    :style="{
                        transform: `scale(${zoom / 100})`,
                        transformOrigin: 'center',
                        transition: 'transform 0.15s ease-out'
                    }"
                >
                    <canvas
                        ref="canvasElement"
                        width="900"
                        height="700"
                        @mousedown="handleCanvasMouseDown"
                        @mousemove="handleCanvasMouseMove"
                        @mouseup="handleCanvasMouseUp"
                        @mouseleave="handleCanvasMouseUp"
                        @dblclick="handleCanvasDoubleClick"
                        class="bg-gray-900 rounded-2xl shadow-2xl border-2 border-gray-700 cursor-crosshair"
                    ></canvas>
                </div>

                <!-- Hint overlay -->
                <div v-if="drawingMode === 'polygon'" class="absolute bottom-4 left-6 bg-black/80 backdrop-blur-md px-4 py-2 rounded-xl border border-white/20 text-white text-xs font-semibold">
                    💡 Nuqtalarni belgilang. Yakunlash uchun oxirgi nuqtani <b>ikki marta bosing (Double-Click)</b>.
                </div>
            </div>

            <!-- Right Sidebar: Details & Coordinates (RIGHT) -->
            <div class="w-80 bg-gray-800 border-l border-gray-700 p-5 overflow-y-auto flex flex-col gap-4">
                <h3 class="text-white font-extrabold text-sm uppercase tracking-wide pb-2 border-b border-gray-700">
                    Qavat Koordinatasi
                </h3>

                <!-- Selected Floor Info Card -->
                <div v-if="selectedFloor" class="bg-gray-700/80 p-3.5 rounded-2xl border border-gray-600">
                    <p class="text-gray-200 text-xs font-bold">
                        <strong class="text-blue-400">Tanlangan qavat:</strong> 
                        {{ selectedFloor < 0 ? `${selectedFloor}-yerto'la` : `${selectedFloor}-qavat` }}
                    </p>
                    <p class="text-gray-400 text-[11px] mt-1.5">
                        {{ drawingMode === 'rect' ? "Fasad rasmida sichqoncha bilan to'rtburchak tortib chizing." : (drawingMode === 'polygon' ? "Nuqtalarni bosib ko'pburchak chizing." : "Shaklni tanlash va o'zgartirish rejimi.") }}
                    </p>
                </div>

                <div v-else class="bg-amber-900/40 p-3.5 rounded-2xl border border-amber-600/50 text-amber-300 text-xs font-bold">
                    ⚠️ Avval chap tarafdagi qavatlar ro'yxatidan qavatni tanlang.
                </div>

                <!-- Live Message Alert -->
                <div v-if="message" class="bg-blue-600/90 text-white p-3 rounded-xl border border-blue-400 text-xs font-bold shadow-md animate-in fade-in duration-150">
                    {{ message }}
                </div>

                <!-- Selected Shape Inspector -->
                <div v-if="selectedShapeObj" class="bg-gray-700/90 p-4 rounded-2xl border border-gray-600 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black text-amber-400">Tanlangan Shakl:</span>
                        <span class="text-xs font-bold text-white">{{ selectedShapeObj.floorLabel }}</span>
                    </div>

                    <div v-if="selectedShapeObj.type === 'rect'" class="grid grid-cols-2 gap-2 text-[11px] font-mono text-gray-300 bg-gray-800 p-2.5 rounded-xl border border-gray-700">
                        <div><b>X:</b> {{ selectedShapeObj.x }}</div>
                        <div><b>Y:</b> {{ selectedShapeObj.y }}</div>
                        <div><b>W:</b> {{ selectedShapeObj.w }}px</div>
                        <div><b>H:</b> {{ selectedShapeObj.h }}px</div>
                    </div>

                    <button 
                        @click="handleDeleteShape"
                        class="w-full py-2 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow transition-all flex items-center justify-center gap-1.5"
                    >
                        <span>🗑️</span> Ushbu shaklni o'chirish
                    </button>
                </div>

                <!-- Shapes List -->
                <div class="flex-1 flex flex-col gap-2 mt-2">
                    <div class="flex items-center justify-between">
                        <h4 class="text-white font-bold text-xs uppercase">Chizilgan Shakllar:</h4>
                        <span class="text-xs font-mono text-emerald-400 font-bold">{{ shapes.length }} ta</span>
                    </div>

                    <div class="space-y-1.5 max-h-72 overflow-y-auto pr-1">
                        <div
                            v-for="(shape, idx) in shapes"
                            :key="'shape-item-' + shape.id"
                            @click="selectedShapeId = shape.id; selectedFloor = shape.floor; redrawCanvas();"
                            class="p-2.5 rounded-xl cursor-pointer transition-all border text-xs font-bold flex flex-col gap-1"
                            :class="selectedShapeId === shape.id 
                                ? 'bg-emerald-900/60 border-emerald-400 text-white ring-1 ring-emerald-400' 
                                : 'bg-gray-700/60 border-gray-600 text-gray-300 hover:bg-gray-700'"
                        >
                            <div class="flex items-center justify-between">
                                <span>{{ idx + 1 }}. {{ shape.floorLabel }}</span>
                                <span class="text-[10px] text-gray-400 uppercase font-mono">{{ shape.type }}</span>
                            </div>
                            <div v-if="shape.type === 'rect'" class="text-[10px] font-mono text-gray-400">
                                x={{ shape.x }} y={{ shape.y }} w={{ shape.w }} h={{ shape.h }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Bottom Status Bar -->
        <div class="bg-gray-800 border-t border-gray-700 px-6 py-2.5 text-gray-400 text-xs flex items-center justify-between font-bold">
            <div>
                <span v-if="selectedFloor" class="text-white">
                    <strong class="text-blue-400">{{ selectedFloor < 0 ? `${selectedFloor}-yerto'la` : `${selectedFloor}-qavat` }}</strong> — 
                    {{ drawingMode === 'rect' ? "To'rtburchak chizish rejimi" : (drawingMode === 'select' ? "Tanlash rejimi" : "Ko'pburchak chizish rejimi") }}
                </span>
                <span v-else class="text-amber-400">⚠️ Qavatni tanlang</span>
            </div>
            <div>
                <strong class="text-emerald-400">{{ shapes.length }}</strong> shakl chizildi • <strong class="text-blue-400">{{ drawnFloors.size }}</strong> ta qavat qamrab olindi
            </div>
        </div>

    </div>
</template>
