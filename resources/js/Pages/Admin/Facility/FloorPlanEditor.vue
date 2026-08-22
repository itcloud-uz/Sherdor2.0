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

// Scope: 'facade' (Bino Fasadida Qavatlarni chizish) or 'floor' (Qavat Chizmasida Xonadonlarni chizish)
const editorScope = ref('facade');

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

// Selected Floor (for Facade and for Floor Scope)
const selectedFloor = ref(null);
const selectedFloorObj = computed(() => {
    return availableFloors.value.find(f => f.floor_number === selectedFloor.value) || null;
});

// Units on selected floor
const floorUnits = ref([]);
const selectedUnitId = ref(null);
const selectedUnitObj = computed(() => {
    return floorUnits.value.find(u => u.id === selectedUnitId.value || u.unit_number === selectedUnitId.value) || null;
});

// Canvas & Editor Elements
const fileInputRef = ref(null);
const blueprintFileInputRef = ref(null);
const canvasContainerRef = ref(null);
const canvasElement = ref(null);
const ctx = ref(null);

// Active Canvas Image (Facade image vs Floor Blueprint image)
const canvasImageObj = ref(null);
const canvasImageUrl = ref(null);

// Shapes (Floors list when in 'facade' scope; Units list when in 'floor' scope)
const shapes = ref([]);
const drawingMode = ref('rect'); // 'select', 'rect', 'polygon'
const isDrawing = ref(false);
const startPos = ref(null);
const zoom = ref(100);
const message = ref('');
const isError = ref(false);
const selectedShapeId = ref(null);
const isSaving = ref(false);

// Polygon in-progress drawing points
const polygonPoints = ref([]);

// Image natural size & render size
const imgRenderBounds = ref({ x: 0, y: 0, w: 800, h: 600 });

// Parse coordinates helper
const parseCoordinates = (raw) => {
    if (!raw) return null;
    if (typeof raw === 'object') return raw;
    try {
        return JSON.parse(raw);
    } catch(e) {
        return null;
    }
};

// Update Canvas Size dynamically based on container
const updateCanvasSize = () => {
    if (!canvasContainerRef.value || !canvasElement.value) return;
    const cw = canvasContainerRef.value.clientWidth || 1000;
    const ch = canvasContainerRef.value.clientHeight || 750;

    canvasElement.value.width = cw;
    canvasElement.value.height = ch;

    if (canvasImageObj.value) {
        const img = canvasImageObj.value;
        const scaleX = (cw - 30) / img.width;
        const scaleY = (ch - 30) / img.height;
        const scale = Math.min(scaleX, scaleY);

        const rw = Math.round(img.width * scale);
        const rh = Math.round(img.height * scale);
        const rx = Math.round((cw - rw) / 2);
        const ry = Math.round((ch - rh) / 2);

        imgRenderBounds.value = { x: rx, y: ry, w: rw, h: rh };
        recalculateShapeCanvasPositions();
    } else {
        imgRenderBounds.value = { x: 40, y: 40, w: cw - 80, h: ch - 80 };
    }

    redrawCanvas();
};

// Recalculate shape positions on canvas from stored natural/norm coordinates
const recalculateShapeCanvasPositions = () => {
    if (!canvasImageObj.value) return;
    const img = canvasImageObj.value;
    const b = imgRenderBounds.value;
    const nw = img.naturalWidth || b.w;
    const nh = img.naturalHeight || b.h;

    shapes.value = shapes.value.map(shape => {
        const coords = shape.rawCoords;
        if (!coords) return shape;

        if (shape.type === 'rect') {
            let cx, cy, cw, ch;
            if (coords.norm) {
                cx = Math.round(b.x + coords.norm.x * b.w);
                cy = Math.round(b.y + coords.norm.y * b.h);
                cw = Math.round(coords.norm.w * b.w);
                ch = Math.round(coords.norm.h * b.h);
            } else {
                const natX = coords.natural?.x ?? coords.left ?? coords.x ?? 0;
                const natY = coords.natural?.y ?? coords.top ?? coords.y ?? 0;
                const natW = coords.natural?.w ?? coords.width ?? coords.w ?? 200;
                const natH = coords.natural?.h ?? coords.height ?? coords.h ?? 60;

                cx = Math.round(b.x + (natX / nw) * b.w);
                cy = Math.round(b.y + (natY / nh) * b.h);
                cw = Math.round((natW / nw) * b.w);
                ch = Math.round((natH / nh) * b.h);
            }
            return { ...shape, x: cx, y: cy, w: cw, h: ch };
        } else if (shape.type === 'polygon' && (coords.points || coords.natural?.points)) {
            const rawPts = coords.natural?.points || coords.points;
            const cPts = rawPts.map(p => ({
                x: Math.round(b.x + ((p.x || p[0]) / nw) * b.w),
                y: Math.round(b.y + ((p.y || p[1]) / nh) * b.h)
            }));
            return { ...shape, points: cPts };
        }
        return shape;
    });
};

// Switch between Facade Editor and Floor Blueprint Editor
const openFloorBlueprintEditor = (floorNumber) => {
    selectedFloor.value = floorNumber;
    editorScope.value = 'floor';
    loadFloorData();
};

const returnToFacadeEditor = () => {
    editorScope.value = 'facade';
    loadBuildingFacadeData();
};

// Load Facade Data (Mode: 'facade')
const loadBuildingFacadeData = () => {
    if (!selectedBuilding.value) return;

    shapes.value = [];
    selectedShapeId.value = null;

    if (availableFloors.value.length > 0 && !selectedFloor.value) {
        selectedFloor.value = availableFloors.value[0].floor_number;
    }

    const bgUrl = selectedBuilding.value.facade_image 
        ? `/storage/${selectedBuilding.value.facade_image}` 
        : (selectedBuilding.value.photo_path ? `/storage/${selectedBuilding.value.photo_path}` : '/images/clean-building-facade.jpg');

    loadImage(bgUrl);

    // Load existing floor shapes from DB
    const loaded = [];
    availableFloors.value.forEach((floor) => {
        const coords = parseCoordinates(floor.facade_polygon);
        if (coords) {
            const floorLabel = floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat`;
            if (coords.type === 'rect') {
                loaded.push({
                    id: floor.id,
                    floorId: floor.id,
                    floor: floor.floor_number,
                    floorLabel: floorLabel,
                    type: 'rect',
                    x: coords.left || coords.x || 100,
                    y: coords.top || coords.y || 100,
                    w: coords.width || coords.w || 200,
                    h: coords.height || coords.h || 60,
                    rawCoords: coords
                });
            } else if (coords.type === 'polygon' && (coords.points || coords.natural?.points)) {
                loaded.push({
                    id: floor.id,
                    floorId: floor.id,
                    floor: floor.floor_number,
                    floorLabel: floorLabel,
                    type: 'polygon',
                    points: coords.natural?.points || coords.points,
                    rawCoords: coords
                });
            }
        }
    });

    shapes.value = loaded;
    nextTick(() => updateCanvasSize());
};

// Load Floor Blueprint & Units Data (Mode: 'floor')
const loadFloorData = () => {
    const floor = selectedFloorObj.value;
    if (!floor) return;

    shapes.value = [];
    selectedShapeId.value = null;

    // Blueprint background image for this floor
    const blueprintUrl = floor.blueprint_path 
        ? `/storage/${floor.blueprint_path}` 
        : '/images/floor-plan-blueprint.png';

    loadImage(blueprintUrl);

    // Units on this floor
    const unitsList = floor.units || [];
    floorUnits.value = unitsList.map(u => ({
        id: u.id,
        unit_number: u.unit_number,
        rooms_count: u.rooms_count || 1,
        area_sqm: u.area_sqm || 50,
        price: u.price || 0,
        status: u.status || 'available',
        type: u.type || 'residential',
        svg_polygon: u.svg_polygon
    }));

    if (floorUnits.value.length > 0 && !selectedUnitId.value) {
        selectedUnitId.value = floorUnits.value[0].id;
    }

    // Load shapes from units' svg_polygon
    const loadedShapes = [];
    floorUnits.value.forEach(unit => {
        const coords = parseCoordinates(unit.svg_polygon);
        if (coords) {
            if (coords.type === 'rect') {
                loadedShapes.push({
                    id: unit.id,
                    unitId: unit.id,
                    unit_number: unit.unit_number,
                    label: `${unit.unit_number}-uy`,
                    status: unit.status,
                    type: 'rect',
                    x: coords.left || coords.x || 100,
                    y: coords.top || coords.y || 100,
                    w: coords.width || coords.w || 150,
                    h: coords.height || coords.h || 120,
                    rawCoords: coords
                });
            } else if (coords.type === 'polygon' && (coords.points || coords.natural?.points)) {
                loadedShapes.push({
                    id: unit.id,
                    unitId: unit.id,
                    unit_number: unit.unit_number,
                    label: `${unit.unit_number}-uy`,
                    status: unit.status,
                    type: 'polygon',
                    points: coords.natural?.points || coords.points,
                    rawCoords: coords
                });
            }
        }
    });

    shapes.value = loadedShapes;
    nextTick(() => updateCanvasSize());
};

// Watchers for building/floor selection
watch(selectedBuilding, () => {
    if (editorScope.value === 'facade') {
        loadBuildingFacadeData();
    } else {
        loadFloorData();
    }
}, { immediate: true });

// Load Image into Canvas
const loadImage = (url) => {
    if (!url) {
        canvasImageObj.value = null;
        canvasImageUrl.value = null;
        redrawCanvas();
        return;
    }

    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
        canvasImageObj.value = img;
        canvasImageUrl.value = url;
        updateCanvasSize();
    };
    img.onerror = () => {
        // Fallback placeholder
        canvasImageObj.value = null;
        canvasImageUrl.value = null;
        updateCanvasSize();
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

    // Dark Slate canvas background for maximum visual clarity
    context.fillStyle = '#090d16';
    context.fillRect(0, 0, canvas.width, canvas.height);

    // Draw background image (Facade or Floor Blueprint)
    if (canvasImageObj.value) {
        const b = imgRenderBounds.value;
        context.drawImage(canvasImageObj.value, b.x, b.y, b.w, b.h);
        context.strokeStyle = 'rgba(255, 255, 255, 0.2)';
        context.lineWidth = 1.5;
        context.strokeRect(b.x, b.y, b.w, b.h);
    } else {
        context.fillStyle = '#1e293b';
        context.fillRect(40, 40, canvas.width - 80, canvas.height - 80);
        context.fillStyle = '#94a3b8';
        context.font = 'bold 15px sans-serif';
        context.textAlign = 'center';
        const emptyText = editorScope.value === 'facade' 
            ? 'Fasad rasmini yuklash uchun yuqoridagi tugmani bosing' 
            : 'Ushbu qavat uchun chizma (blueprint) yuklash uchun yuqoridagi tugmani bosing';
        context.fillText(emptyText, canvas.width / 2, canvas.height / 2);
    }

    // Draw all shapes
    drawShapes(context, shapes.value);

    // Draw polygon preview if in-progress
    if (drawingMode.value === 'polygon' && polygonPoints.value.length > 0) {
        context.strokeStyle = '#f97316';
        context.fillStyle = 'rgba(249, 115, 22, 0.25)';
        context.lineWidth = 2.5;
        context.setLineDash([6, 6]);

        context.beginPath();
        context.moveTo(polygonPoints.value[0].x, polygonPoints.value[0].y);
        for (let i = 1; i < polygonPoints.value.length; i++) {
            context.lineTo(polygonPoints.value[i].x, polygonPoints.value[i].y);
        }
        context.stroke();
        context.setLineDash([]);

        polygonPoints.value.forEach(p => {
            context.fillStyle = '#f97316';
            context.beginPath();
            context.arc(p.x, p.y, 5, 0, Math.PI * 2);
            context.fill();
        });
    }
};

// Draw all shapes with handles & labels
const drawShapes = (context, currentShapes) => {
    currentShapes.forEach((shape) => {
        const isSelected = shape.id === selectedShapeId.value;

        // Color coding: Facade (Orange/Amber) vs Unit (Green available / Yellow reserved / Red sold)
        let strokeColor = isSelected ? '#fbbf24' : '#f97316';
        let fillColor = isSelected ? 'rgba(251, 191, 36, 0.4)' : 'rgba(249, 115, 22, 0.25)';

        if (editorScope.value === 'floor') {
            if (shape.status === 'sold') {
                strokeColor = isSelected ? '#fbbf24' : '#ef4444';
                fillColor = isSelected ? 'rgba(251, 191, 36, 0.4)' : 'rgba(239, 68, 68, 0.35)';
            } else if (shape.status === 'reserved' || shape.status === 'booked') {
                strokeColor = isSelected ? '#fbbf24' : '#f59e0b';
                fillColor = isSelected ? 'rgba(251, 191, 36, 0.4)' : 'rgba(245, 158, 11, 0.35)';
            } else {
                strokeColor = isSelected ? '#fbbf24' : '#10b981';
                fillColor = isSelected ? 'rgba(251, 191, 36, 0.4)' : 'rgba(16, 185, 129, 0.35)';
            }
        }

        if (shape.type === 'rect') {
            context.strokeStyle = strokeColor;
            context.fillStyle = fillColor;
            context.lineWidth = isSelected ? 3 : 2;

            context.fillRect(shape.x, shape.y, shape.w, shape.h);
            context.strokeRect(shape.x, shape.y, shape.w, shape.h);

            // Label pill
            context.fillStyle = '#FFFFFF';
            context.font = 'bold 12px sans-serif';
            context.textAlign = 'left';
            const labelText = shape.label || shape.floorLabel || `${shape.unit_number || shape.floor}-uy`;
            context.fillText(labelText, shape.x + 8, shape.y + 20);

            if (isSelected) {
                drawResizeHandles(context, shape);
            }
        } else if (shape.type === 'polygon') {
            context.strokeStyle = strokeColor;
            context.fillStyle = fillColor;
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

                const centroid = getCentroid(shape.points);
                context.fillStyle = '#FFFFFF';
                context.font = 'bold 12px sans-serif';
                context.textAlign = 'center';
                const labelText = shape.label || shape.floorLabel || `${shape.unit_number || shape.floor}-uy`;
                context.fillText(labelText, centroid.x, centroid.y);
            }
        }
    });
};

// Active Dragging State for Moving & Resizing
const dragAction = ref('none'); // 'none', 'move_shape', 'resize_handle', 'drag_vertex'
const activeHandleIndex = ref(null); // 0..7
const activeVertexIndex = ref(null); // index of polygon point
const dragStartPos = ref({ x: 0, y: 0 });
const dragOriginalShape = ref(null);

// Get 8 handles for rectangle shape
const getShapeHandles = (shape) => {
    if (!shape || shape.type !== 'rect') return [];
    return [
        { index: 0, x: shape.x, y: shape.y, cursor: 'nwse-resize' }, // Top-Left
        { index: 1, x: shape.x + shape.w / 2, y: shape.y, cursor: 'ns-resize' }, // Top-Middle
        { index: 2, x: shape.x + shape.w, y: shape.y, cursor: 'nesw-resize' }, // Top-Right
        { index: 3, x: shape.x + shape.w, y: shape.y + shape.h / 2, cursor: 'ew-resize' }, // Middle-Right
        { index: 4, x: shape.x + shape.w, y: shape.y + shape.h, cursor: 'nwse-resize' }, // Bottom-Right
        { index: 5, x: shape.x + shape.w / 2, y: shape.y + shape.h, cursor: 'ns-resize' }, // Bottom-Middle
        { index: 6, x: shape.x, y: shape.y + shape.h, cursor: 'nesw-resize' }, // Bottom-Left
        { index: 7, x: shape.x, y: shape.y + shape.h / 2, cursor: 'ew-resize' }, // Middle-Left
    ];
};

// Check if point is inside rectangle
const isPointInRect = (x, y, rx, ry, rw, rh) => {
    return x >= rx && x <= rx + rw && y >= ry && y <= ry + rh;
};

// Check if point is inside polygon
const isPointInPolygon = (x, y, points) => {
    if (!points || points.length < 3) return false;
    let inside = false;
    for (let i = 0, j = points.length - 1; i < points.length; j = i++) {
        const xi = points[i].x || points[i][0], yi = points[i].y || points[i][1];
        const xj = points[j].x || points[j][0], yj = points[j].y || points[j][1];
        const intersect = ((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
};

// Check if point hits a handle (within 9px tolerance)
const getHandleUnderPoint = (shape, x, y) => {
    if (!shape || shape.type !== 'rect') return null;
    const handles = getShapeHandles(shape);
    for (const h of handles) {
        if (Math.abs(x - h.x) <= 9 && Math.abs(y - h.y) <= 9) {
            return h;
        }
    }
    return null;
};

// Check if point hits a polygon vertex (within 9px tolerance)
const getVertexUnderPoint = (shape, x, y) => {
    if (!shape || shape.type !== 'polygon' || !shape.points) return null;
    for (let i = 0; i < shape.points.length; i++) {
        const pt = shape.points[i];
        const px = pt.x || pt[0], py = pt.y || pt[1];
        if (Math.abs(x - px) <= 9 && Math.abs(y - py) <= 9) {
            return { index: i, x: px, y: py };
        }
    }
    return null;
};

const drawResizeHandles = (context, shape) => {
    if (shape.type === 'rect') {
        const handles = getShapeHandles(shape);
        context.fillStyle = '#fbbf24';
        context.strokeStyle = '#000000';
        context.lineWidth = 1.5;
        handles.forEach((h) => {
            context.fillRect(h.x - 5, h.y - 5, 10, 10);
            context.strokeRect(h.x - 5, h.y - 5, 10, 10);
        });
    } else if (shape.type === 'polygon' && shape.points) {
        context.fillStyle = '#fbbf24';
        context.strokeStyle = '#000000';
        context.lineWidth = 1.5;
        shape.points.forEach((pt) => {
            const px = pt.x || pt[0], py = pt.y || pt[1];
            context.fillRect(px - 5, py - 5, 10, 10);
            context.strokeRect(px - 5, py - 5, 10, 10);
        });
    }
};

const getCentroid = (points) => {
    let sx = 0, sy = 0;
    points.forEach(p => {
        sx += (p.x || p[0]);
        sy += (p.y || p[1]);
    });
    return { x: sx / points.length, y: sy / points.length };
};

// Facade Image Upload Handler
const handleImageUpload = (e) => {
    const file = e.target.files?.[0];
    if (!file || !selectedBuilding.value) return;

    const reader = new FileReader();
    reader.onload = (event) => {
        const imageData = event.target?.result;
        loadImage(imageData);
        isError.value = false;
        message.value = "✓ Fasad rasmi yuklandi!";
        setTimeout(() => message.value = '', 3000);
    };
    reader.readAsDataURL(file);

    const formData = new FormData();
    formData.append('facade', file);
    axios.post(route('admin.facility.buildings.upload_facade', selectedBuilding.value.id), formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    }).catch(() => {});
};

// Floor Blueprint Upload Handler (Mode: 'floor')
const handleBlueprintUpload = (e) => {
    const file = e.target.files?.[0];
    const floor = selectedFloorObj.value;
    if (!file || !floor) return;

    const reader = new FileReader();
    reader.onload = (event) => {
        const imageData = event.target?.result;
        loadImage(imageData);
        isError.value = false;
        message.value = "✓ Qavat chizmasi yuklandi!";
        setTimeout(() => message.value = '', 3000);
    };
    reader.readAsDataURL(file);

    const formData = new FormData();
    formData.append('blueprint', file);
    axios.post(route('admin.facility.floors.upload_blueprint', floor.id), formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    }).then(res => {
        if (res.data?.blueprint_path) {
            floor.blueprint_path = res.data.blueprint_path;
        }
    }).catch(() => {});
};

// Canvas Mouse Events
const handleCanvasMouseDown = (e) => {
    if (!canvasElement.value) return;

    const rect = canvasElement.value.getBoundingClientRect();
    const x = (e.clientX - rect.left) / (zoom.value / 100);
    const y = (e.clientY - rect.top) / (zoom.value / 100);

    const activeShape = selectedShapeObj.value;

    // 1. Check if clicking on 8 resize handles of selected shape
    if (activeShape && activeShape.type === 'rect') {
        const handle = getHandleUnderPoint(activeShape, x, y);
        if (handle) {
            dragAction.value = 'resize_handle';
            activeHandleIndex.value = handle.index;
            dragStartPos.value = { x, y };
            dragOriginalShape.value = { ...activeShape };
            return;
        }
    }

    // 2. Check if clicking on polygon vertex point of selected shape
    if (activeShape && activeShape.type === 'polygon') {
        const vertex = getVertexUnderPoint(activeShape, x, y);
        if (vertex) {
            dragAction.value = 'drag_vertex';
            activeVertexIndex.value = vertex.index;
            dragStartPos.value = { x, y };
            dragOriginalShape.value = {
                ...activeShape,
                points: activeShape.points.map(p => ({ x: p.x || p[0], y: p.y || p[1] }))
            };
            return;
        }
    }

    // 3. Check if clicking inside any shape to select and move it
    const clickedShape = shapes.value.find(s => {
        if (s.type === 'rect') return isPointInRect(x, y, s.x, s.y, s.w, s.h);
        if (s.type === 'polygon') return isPointInPolygon(x, y, s.points);
        return false;
    });

    if (clickedShape) {
        selectedShapeId.value = clickedShape.id;
        if (editorScope.value === 'facade') {
            selectedFloor.value = clickedShape.floor;
        } else {
            selectedUnitId.value = clickedShape.unitId || clickedShape.id;
        }

        // Initiate move dragging
        dragAction.value = 'move_shape';
        dragStartPos.value = { x, y };
        dragOriginalShape.value = {
            ...clickedShape,
            points: clickedShape.points ? clickedShape.points.map(p => ({ x: p.x || p[0], y: p.y || p[1] })) : null
        };
        redrawCanvas();
        return;
    }

    // 4. If in empty space:
    if (drawingMode.value === 'select') {
        selectedShapeId.value = null;
        redrawCanvas();
    } else if (drawingMode.value === 'rect') {
        if (editorScope.value === 'facade' && !selectedFloor.value) {
            isError.value = true;
            message.value = '⚠️ Avval chap tarafdan qavatni tanlang!';
            setTimeout(() => message.value = '', 3000);
            return;
        }
        if (editorScope.value === 'floor' && !selectedUnitId.value) {
            isError.value = true;
            message.value = '⚠️ Avval chap tarafdan xonadonni tanlang!';
            setTimeout(() => message.value = '', 3000);
            return;
        }
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

    // A. Handle Resizing Handle
    if (dragAction.value === 'resize_handle' && dragOriginalShape.value) {
        const orig = dragOriginalShape.value;
        const dx = x - dragStartPos.value.x;
        const dy = y - dragStartPos.value.y;

        const shape = shapes.value.find(s => s.id === selectedShapeId.value);
        if (shape && shape.type === 'rect') {
            let nx = orig.x, ny = orig.y, nw = orig.w, nh = orig.h;

            switch (activeHandleIndex.value) {
                case 0: // Top-Left
                    nx = orig.x + dx; ny = orig.y + dy; nw = orig.w - dx; nh = orig.h - dy; break;
                case 1: // Top-Middle
                    ny = orig.y + dy; nh = orig.h - dy; break;
                case 2: // Top-Right
                    ny = orig.y + dy; nw = orig.w + dx; nh = orig.h - dy; break;
                case 3: // Middle-Right
                    nw = orig.w + dx; break;
                case 4: // Bottom-Right
                    nw = orig.w + dx; nh = orig.h + dy; break;
                case 5: // Bottom-Middle
                    nh = orig.h + dy; break;
                case 6: // Bottom-Left
                    nx = orig.x + dx; nw = orig.w - dx; nh = orig.h + dy; break;
                case 7: // Middle-Left
                    nx = orig.x + dx; nw = orig.w - dx; break;
            }

            if (nw > 8 && nh > 8) {
                shape.x = Math.round(nx);
                shape.y = Math.round(ny);
                shape.w = Math.round(nw);
                shape.h = Math.round(nh);
            }
            redrawCanvas();
        }
        return;
    }

    // B. Handle Dragging Polygon Vertex
    if (dragAction.value === 'drag_vertex' && dragOriginalShape.value && activeVertexIndex.value !== null) {
        const orig = dragOriginalShape.value;
        const shape = shapes.value.find(s => s.id === selectedShapeId.value);
        if (shape && shape.type === 'polygon' && shape.points) {
            const origPt = orig.points[activeVertexIndex.value];
            const dx = x - dragStartPos.value.x;
            const dy = y - dragStartPos.value.y;

            shape.points[activeVertexIndex.value] = {
                x: Math.round(origPt.x + dx),
                y: Math.round(origPt.y + dy)
            };
            redrawCanvas();
        }
        return;
    }

    // C. Handle Moving Entire Shape
    if (dragAction.value === 'move_shape' && dragOriginalShape.value) {
        const orig = dragOriginalShape.value;
        const dx = x - dragStartPos.value.x;
        const dy = y - dragStartPos.value.y;

        const shape = shapes.value.find(s => s.id === selectedShapeId.value);
        if (shape) {
            if (shape.type === 'rect') {
                shape.x = Math.round(orig.x + dx);
                shape.y = Math.round(orig.y + dy);
            } else if (shape.type === 'polygon' && orig.points) {
                shape.points = orig.points.map(p => ({
                    x: Math.round(p.x + dx),
                    y: Math.round(p.y + dy)
                }));
            }
            redrawCanvas();
        }
        return;
    }

    // D. Live Rectangle Drawing Preview
    if (isDrawing.value && startPos.value && drawingMode.value === 'rect') {
        redrawCanvas();

        const context = ctx.value;
        if (!context) return;
        context.strokeStyle = '#f97316';
        context.fillStyle = 'rgba(249, 115, 22, 0.28)';
        context.lineWidth = 2;
        context.setLineDash([5, 5]);

        const rx = Math.min(startPos.value[0], x);
        const ry = Math.min(startPos.value[1], y);
        const rw = Math.abs(x - startPos.value[0]);
        const rh = Math.abs(y - startPos.value[1]);

        context.fillRect(rx, ry, rw, rh);
        context.strokeRect(rx, ry, rw, rh);
        context.setLineDash([]);
        return;
    }

    // E. Dynamic Cursor Update on Hover
    const activeShape = selectedShapeObj.value;
    if (activeShape && activeShape.type === 'rect') {
        const handle = getHandleUnderPoint(activeShape, x, y);
        if (handle) {
            canvasElement.value.style.cursor = handle.cursor;
            return;
        }
    }
    if (activeShape && activeShape.type === 'polygon') {
        const vertex = getVertexUnderPoint(activeShape, x, y);
        if (vertex) {
            canvasElement.value.style.cursor = 'grab';
            return;
        }
    }
    if (activeShape) {
        const isInside = activeShape.type === 'rect' 
            ? isPointInRect(x, y, activeShape.x, activeShape.y, activeShape.w, activeShape.h)
            : isPointInPolygon(x, y, activeShape.points);
        if (isInside) {
            canvasElement.value.style.cursor = 'move';
            return;
        }
    }

    canvasElement.value.style.cursor = drawingMode.value === 'select' ? 'default' : 'crosshair';
};

const handleCanvasMouseUp = (e) => {
    if (!canvasElement.value) return;

    if (dragAction.value !== 'none') {
        dragAction.value = 'none';
        activeHandleIndex.value = null;
        activeVertexIndex.value = null;
        dragOriginalShape.value = null;
        redrawCanvas();
        return;
    }

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
            if (editorScope.value === 'facade') {
                const floorObj = availableFloors.value.find(f => f.floor_number === selectedFloor.value);
                const floorId = floorObj ? floorObj.id : Date.now();
                const floorLabel = floorObj ? (floorObj.floor_number < 0 ? `${floorObj.floor_number}-yerto'la` : `${floorObj.floor_number}-qavat`) : `${selectedFloor.value}-qavat`;

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

                isError.value = false;
                message.value = `✓ ${floorLabel} chizildi!`;
                setTimeout(() => message.value = '', 2500);
            } else if (editorScope.value === 'floor') {
                const unit = selectedUnitObj.value;
                const unitId = unit ? unit.id : Date.now();
                const label = unit ? `${unit.unit_number}-uy` : 'Xonadon';

                const filtered = shapes.value.filter(s => s.unitId !== unitId && s.id !== unitId);

                const newShape = {
                    id: unitId,
                    unitId: unitId,
                    unit_number: unit?.unit_number,
                    label: label,
                    status: unit?.status || 'available',
                    type: 'rect',
                    x: Math.round(left),
                    y: Math.round(top),
                    w: Math.round(width),
                    h: Math.round(height)
                };

                shapes.value = [...filtered, newShape];
                selectedShapeId.value = newShape.id;
                redrawCanvas();

                isError.value = false;
                message.value = `✓ ${label} chizildi!`;
                setTimeout(() => message.value = '', 2500);
            }
        }
    }

    isDrawing.value = false;
    startPos.value = null;
};

// Finish Polygon with Double Click
const handleCanvasDoubleClick = () => {
    if (drawingMode.value === 'polygon' && polygonPoints.value.length >= 3) {
        if (editorScope.value === 'facade') {
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

            isError.value = false;
            message.value = `✓ ${floorLabel} ko'pburchak chizildi!`;
            setTimeout(() => message.value = '', 2500);
        } else if (editorScope.value === 'floor') {
            const unit = selectedUnitObj.value;
            const unitId = unit ? unit.id : Date.now();
            const label = unit ? `${unit.unit_number}-uy` : 'Xonadon';

            const filtered = shapes.value.filter(s => s.unitId !== unitId && s.id !== unitId);

            const newShape = {
                id: unitId,
                unitId: unitId,
                unit_number: unit?.unit_number,
                label: label,
                status: unit?.status || 'available',
                type: 'polygon',
                points: [...polygonPoints.value]
            };

            shapes.value = [...filtered, newShape];
            selectedShapeId.value = newShape.id;
            polygonPoints.value = [];
            redrawCanvas();

            isError.value = false;
            message.value = `✓ ${label} ko'pburchak chizildi!`;
            setTimeout(() => message.value = '', 2500);
        }
    }
};

// Select floor from left list (Facade mode)
const handleSelectFloor = (floorNum) => {
    selectedFloor.value = floorNum;
    const existing = shapes.value.find(s => s.floor === floorNum);
    selectedShapeId.value = existing?.id || null;
    redrawCanvas();
};

// Select unit from left list (Floor mode)
const handleSelectUnit = (unitId) => {
    selectedUnitId.value = unitId;
    const existing = shapes.value.find(s => s.unitId === unitId || s.id === unitId);
    selectedShapeId.value = existing?.id || null;
    redrawCanvas();
};

// Add New Unit to Current Floor
const addNewUnit = () => {
    const nextNum = (floorUnits.value.length + 1) * 10 + (selectedFloor.value > 0 ? selectedFloor.value : 1);
    const newUnit = {
        id: Date.now(),
        unit_number: String(nextNum),
        rooms_count: 2,
        area_sqm: 60,
        price: 600000000,
        status: 'available',
        type: 'residential',
        svg_polygon: null
    };
    floorUnits.value.push(newUnit);
    selectedUnitId.value = newUnit.id;
};

// Auto-Split Building Generator State
const showAutoSplitModal = ref(false);
const autoSplitFromFloor = ref(1);
const autoSplitToFloor = ref(16);
const autoSplitMakeCommercialTaller = ref(true); // 1-floor 1.25x taller
const autoSplitTargetBox = ref(null);

const openAutoSplitModal = () => {
    if (availableFloors.value.length === 0) {
        isError.value = true;
        message.value = '⚠️ Binoda qavatlar mavjud emas!';
        setTimeout(() => message.value = '', 3000);
        return;
    }
    
    // Set default floor range
    const floorNums = availableFloors.value.map(f => f.floor_number).sort((a, b) => a - b);
    autoSplitFromFloor.value = floorNums[0] || 1;
    autoSplitToFloor.value = floorNums[floorNums.length - 1] || 16;
    
    // Check if there is an existing selected rect or bounds to use as template
    const selected = selectedShapeObj.value;
    if (selected && selected.type === 'rect') {
        autoSplitTargetBox.value = { x: selected.x, y: selected.y, w: selected.w, h: selected.h };
    } else if (shapes.value.length > 0) {
        // Calculate overall bounding box of existing shapes
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        shapes.value.forEach(s => {
            if (s.type === 'rect') {
                minX = Math.min(minX, s.x);
                minY = Math.min(minY, s.y);
                maxX = Math.max(maxX, s.x + s.w);
                maxY = Math.max(maxY, s.y + s.h);
            }
        });
        if (minX < Infinity) {
            autoSplitTargetBox.value = { x: minX, y: minY, w: maxX - minX, h: maxY - minY };
        } else {
            const b = imgRenderBounds.value;
            autoSplitTargetBox.value = { x: Math.round(b.x + b.w * 0.25), y: Math.round(b.y + b.h * 0.05), w: Math.round(b.w * 0.5), h: Math.round(b.h * 0.9) };
        }
    } else {
        const b = imgRenderBounds.value;
        autoSplitTargetBox.value = { x: Math.round(b.x + b.w * 0.25), y: Math.round(b.y + b.h * 0.05), w: Math.round(b.w * 0.5), h: Math.round(b.h * 0.9) };
    }
    
    showAutoSplitModal.value = true;
};

// Execute Auto-Split
const executeAutoSplit = () => {
    if (!autoSplitTargetBox.value) return;

    const fromNum = Math.min(autoSplitFromFloor.value, autoSplitToFloor.value);
    const toNum = Math.max(autoSplitFromFloor.value, autoSplitToFloor.value);

    const targetFloors = availableFloors.value
        .filter(f => f.floor_number >= fromNum && f.floor_number <= toNum)
        .sort((a, b) => a.floor_number - b.floor_number); // e.g. 1 to 16

    if (targetFloors.length === 0) {
        isError.value = true;
        message.value = '⚠️ Tanlangan oraliqda qavatlar topilmadi!';
        setTimeout(() => message.value = '', 3000);
        return;
    }

    const box = autoSplitTargetBox.value;

    // Weight distribution: if commercial 1st floor is taller (weight 1.25), others weight 1.0
    let totalWeight = 0;
    const weights = targetFloors.map(fl => {
        const isCommercial = autoSplitMakeCommercialTaller.value && fl.floor_number === 1;
        const w = isCommercial ? 1.25 : 1.0;
        totalWeight += w;
        return w;
    });

    const unitHeight = box.h / totalWeight;

    // Remove shapes in this floor range
    const remainingShapes = shapes.value.filter(s => s.floor < fromNum || s.floor > toNum);
    const newShapes = [];

    let currentBottom = box.y + box.h;

    // Bottom-to-top layout (1-qavat is at the bottom, 16-qavat is at top)
    targetFloors.forEach((fl, i) => {
        const floorHeight = Math.round(weights[i] * unitHeight);
        const floorTop = Math.round(currentBottom - floorHeight);
        const floorLabel = fl.floor_number < 0 ? `${fl.floor_number}-yerto'la` : `${fl.floor_number}-qavat`;

        newShapes.push({
            id: fl.id,
            floorId: fl.id,
            floor: fl.floor_number,
            floorLabel: floorLabel,
            type: 'rect',
            x: Math.round(box.x),
            y: floorTop,
            w: Math.round(box.w),
            h: floorHeight
        });

        currentBottom = floorTop;
    });

    shapes.value = [...remainingShapes, ...newShapes];
    selectedShapeId.value = newShapes[0]?.id || null;
    showAutoSplitModal.value = false;
    redrawCanvas();

    isError.value = false;
    message.value = `✓ ${newShapes.length} ta qavat tekis va mukammal taqsimlandi!`;
    setTimeout(() => message.value = '', 3500);
};

// Align All Floor Widths & Left position
const alignAllFloorWidths = () => {
    if (shapes.value.length === 0) return;
    
    // Find reference x and w
    const selected = selectedShapeObj.value;
    let targetX = 0, targetW = 0;
    if (selected && selected.type === 'rect') {
        targetX = selected.x;
        targetW = selected.w;
    } else {
        const rects = shapes.value.filter(s => s.type === 'rect');
        if (rects.length === 0) return;
        targetX = rects[0].x;
        targetW = rects[0].w;
    }

    shapes.value.forEach(s => {
        if (s.type === 'rect') {
            s.x = targetX;
            s.w = targetW;
        }
    });

    redrawCanvas();
    isError.value = false;
    message.value = '✓ Barcha qavatlarning chap va o\'ng chegaralari bitta chiziqqa tekislandi!';
    setTimeout(() => message.value = '', 3000);
};

// Delete selected shape
const handleDeleteShape = () => {
    if (!selectedShapeId.value) return;
    const filtered = shapes.value.filter(s => s.id !== selectedShapeId.value);
    shapes.value = filtered;
    selectedShapeId.value = null;
    redrawCanvas();
    isError.value = false;
    message.value = "🗑️ Shakl o'chirildi!";
    setTimeout(() => message.value = '', 2000);
};

// Save shapes to database (Facade Mode OR Floor Units Mode)
const handleSave = async () => {
    if (!selectedBuilding.value) return;

    try {
        isSaving.value = true;
        isError.value = false;
        message.value = '💾 Saqlash jarayonida...';

        const img = canvasImageObj.value;
        const b = imgRenderBounds.value;
        const nw = img ? img.naturalWidth : b.w;
        const nh = img ? img.naturalHeight : b.h;

        if (editorScope.value === 'facade') {
            const floorCoords = shapes.value.map(s => {
                const floorObj = availableFloors.value.find(f => f.floor_number === s.floor);
                let shapeJson = null;
                if (s.type === 'rect') {
                    const normX = Math.min(1, Math.max(0, (s.x - b.x) / b.w));
                    const normY = Math.min(1, Math.max(0, (s.y - b.y) / b.h));
                    const normW = Math.min(1 - normX, Math.max(0.001, s.w / b.w));
                    const normH = Math.min(1 - normY, Math.max(0.001, s.h / b.h));

                    const natX = Math.round(normX * nw);
                    const natY = Math.round(normY * nh);
                    const natW = Math.round(normW * nw);
                    const natH = Math.round(normH * nh);

                    shapeJson = {
                        type: 'rect',
                        left: natX,
                        top: natY,
                        width: natW,
                        height: natH,
                        natural: { x: natX, y: natY, w: natW, h: natH },
                        norm: { x: normX, y: normY, w: normW, h: normH }
                    };
                } else if (s.type === 'polygon' && s.points) {
                    const natPoints = s.points.map(p => ({
                        x: Math.round(Math.min(1, Math.max(0, (p.x - b.x) / b.w)) * nw),
                        y: Math.round(Math.min(1, Math.max(0, (p.y - b.y) / b.h)) * nh)
                    }));
                    const normPoints = s.points.map(p => ({
                        x: Math.min(1, Math.max(0, (p.x - b.x) / b.w)),
                        y: Math.min(1, Math.max(0, (p.y - b.y) / b.h))
                    }));
                    shapeJson = {
                        type: 'polygon',
                        points: natPoints,
                        natural: { points: natPoints },
                        norm: { points: normPoints }
                    };
                }
                return {
                    id: floorObj?.id || s.floorId || s.id,
                    floor_id: floorObj?.id || s.floorId || s.id,
                    facade_polygon: shapeJson,
                    polygon: shapeJson
                };
            });

            const targetUrl = route('admin.facility.buildings.save_facade_floors', selectedBuilding.value.id);
            await axios.post(targetUrl, {
                floors: floorCoords,
                facade_data: { shapes: shapes.value }
            });

            isError.value = false;
            message.value = '✅ Barcha qavat koordinatalari muvaffaqiyatli saqlandi!';
        } else if (editorScope.value === 'floor') {
            const floor = selectedFloorObj.value;
            if (!floor) return;

            const unitShapesPayload = floorUnits.value.map(unit => {
                const shape = shapes.value.find(s => s.unitId === unit.id || s.id === unit.id || s.unit_number === unit.unit_number);
                let shapeJson = null;

                if (shape) {
                    if (shape.type === 'rect') {
                        const normX = Math.min(1, Math.max(0, (shape.x - b.x) / b.w));
                        const normY = Math.min(1, Math.max(0, (shape.y - b.y) / b.h));
                        const normW = Math.min(1 - normX, Math.max(0.001, shape.w / b.w));
                        const normH = Math.min(1 - normY, Math.max(0.001, shape.h / b.h));

                        const natX = Math.round(normX * nw);
                        const natY = Math.round(normY * nh);
                        const natW = Math.round(normW * nw);
                        const natH = Math.round(normH * nh);

                        shapeJson = {
                            type: 'rect',
                            left: natX,
                            top: natY,
                            width: natW,
                            height: natH,
                            natural: { x: natX, y: natY, w: natW, h: natH },
                            norm: { x: normX, y: normY, w: normW, h: normH }
                        };
                    } else if (shape.type === 'polygon' && shape.points) {
                        const natPoints = shape.points.map(p => ({
                            x: Math.round(((p.x - b.x) / b.w) * nw),
                            y: Math.round(((p.y - b.y) / b.h) * nh)
                        }));
                        const normPoints = shape.points.map(p => ({
                            x: (p.x - b.x) / b.w,
                            y: (p.y - b.y) / b.h
                        }));
                        shapeJson = {
                            type: 'polygon',
                            points: natPoints,
                            natural: { points: natPoints },
                            norm: { points: normPoints }
                        };
                    }
                }

                return {
                    id: typeof unit.id === 'number' && unit.id < 1000000000000 ? unit.id : null,
                    unit_number: unit.unit_number,
                    rooms_count: Number(unit.rooms_count) || 1,
                    area_sqm: Number(unit.area_sqm) || 50,
                    price: Number(unit.price) || 0,
                    status: unit.status || 'available',
                    type: unit.type || 'residential',
                    svg_polygon: shapeJson ? JSON.stringify(shapeJson) : null
                };
            });

            const targetUrl = route('admin.facility.floors.save_shapes', floor.id);
            await axios.post(targetUrl, { shapes: unitShapesPayload });

            isError.value = false;
            message.value = '✅ Qavat xonadonlari va chizmalari muvaffaqiyatli saqlandi!';
        }

        setTimeout(() => message.value = '', 3500);
    } catch(err) {
        console.error('Save error:', err);
        isError.value = true;
        message.value = '❌ Saqlashda xatolik: ' + (err.response?.data?.message || err.message || 'Xatolik');
        setTimeout(() => message.value = '', 4000);
    } finally {
        isSaving.value = false;
    }
};

const drawnFloors = computed(() => new Set(shapes.value.map(s => s.floor)));
const drawnUnits = computed(() => new Set(shapes.value.map(s => s.unit_number || s.unitId)));
const selectedShapeObj = computed(() => shapes.value.find(s => s.id === selectedShapeId.value) || null);

onMounted(() => {
    window.addEventListener('resize', updateCanvasSize);
    nextTick(() => {
        if (canvasElement.value) {
            ctx.value = canvasElement.value.getContext('2d');
            loadBuildingFacadeData();
        }
    });
});

onUnmounted(() => {
    window.removeEventListener('resize', updateCanvasSize);
});
</script>

<template>
    <Head :title="editorScope === 'facade' ? '3D Maket / Fasad Muharriri' : `${selectedFloorObj?.floor_number}-Qavat Chizmasi Muharriri`" />

    <!-- Clean, Unified, Minimalist White & Orange Studio -->
    <div class="fixed inset-0 z-50 flex flex-col w-screen h-screen overflow-hidden bg-[#f1f5f9] font-sans text-slate-800 select-none">
        
        <!-- Clean Unified Navbar (Height 54px, perfectly aligned, zero overflow) -->
        <header class="h-[54px] bg-white border-b border-slate-200 px-4 flex items-center justify-between gap-3 shadow-xs shrink-0 z-10">
            <!-- Left: Back Button & Mode / Building Select -->
            <div class="flex items-center gap-2.5 shrink-0">
                <button
                    v-if="editorScope === 'floor'"
                    @click="returnToFacadeEditor"
                    class="h-8 px-3 bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs rounded-xl shadow-xs transition-all flex items-center gap-1.5"
                    title="Bino fasadiga qaytish"
                >
                    <span>←</span> Fasadga Qaytish
                </button>

                <Link
                    v-else
                    :href="route('admin.dashboard')"
                    class="h-8 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition-all flex items-center gap-1.5"
                    title="Boshqaruv paneliga qaytish"
                >
                    <span>←</span> Ortga
                </Link>

                <div class="h-4 w-px bg-slate-200"></div>

                <!-- Building and Floor Selector -->
                <div class="flex items-center gap-2">
                    <span class="font-black text-xs text-slate-900 flex items-center gap-1 whitespace-nowrap">
                        <span class="text-orange-500">{{ editorScope === 'facade' ? '🏢' : '📐' }}</span> 
                        {{ editorScope === 'facade' ? 'Fasad Muharriri' : `${selectedFloorObj?.floor_number}-Qavat` }}
                    </span>

                    <select 
                        v-if="editorScope === 'facade'"
                        v-model="selectedBuildingId" 
                        class="h-8 bg-slate-50 hover:bg-slate-100 text-slate-900 font-bold text-xs px-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500 cursor-pointer max-w-[200px] truncate"
                    >
                        <option v-for="b in buildings" :key="b.id" :value="b.id">
                            {{ b.name }} ({{ b.block_name || 'A-1 Blok' }})
                        </option>
                    </select>

                    <select 
                        v-else
                        v-model="selectedFloor"
                        @change="loadFloorData"
                        class="h-8 bg-orange-50 hover:bg-orange-100 text-orange-950 font-bold text-xs px-2.5 rounded-xl border border-orange-200 focus:ring-2 focus:ring-orange-500 cursor-pointer"
                    >
                        <option v-for="fl in availableFloors" :key="fl.id" :value="fl.floor_number">
                            {{ fl.floor_number < 0 ? `${fl.floor_number}-yerto'la` : `${fl.floor_number}-qavat` }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Center: Drawing Tools, Zoom, & Auto-Split -->
            <div class="flex items-center gap-2">
                <!-- Tool Segmented Control -->
                <div class="h-8 bg-slate-100 p-0.5 rounded-xl flex items-center gap-0.5 border border-slate-200">
                    <button
                        @click="drawingMode = 'select'"
                        :class="drawingMode === 'select' ? 'bg-orange-500 text-white shadow-xs font-black' : 'text-slate-700 hover:bg-white'"
                        class="h-7 px-2.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1"
                        title="Tanlash va Ko'chirish"
                    >
                        <span>👆</span> Tanlash
                    </button>

                    <button
                        @click="drawingMode = 'rect'"
                        :class="drawingMode === 'rect' ? 'bg-orange-500 text-white shadow-xs font-black' : 'text-slate-700 hover:bg-white'"
                        class="h-7 px-2.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1"
                        title="To'rtburchak chizish"
                    >
                        <span>⬛</span> To'rtburchak
                    </button>

                    <button
                        @click="drawingMode = 'polygon'"
                        :class="drawingMode === 'polygon' ? 'bg-orange-500 text-white shadow-xs font-black' : 'text-slate-700 hover:bg-white'"
                        class="h-7 px-2.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1"
                        title="Ko'pburchak chizish"
                    >
                        <span>⬡</span> Ko'pburchak
                    </button>
                </div>

                <!-- Zoom Controls -->
                <div class="h-8 bg-slate-100 p-0.5 rounded-xl flex items-center gap-0.5 border border-slate-200">
                    <button
                        @click="zoom = Math.max(zoom - 10, 50); redrawCanvas();"
                        class="h-7 px-2 text-slate-700 hover:bg-white rounded-lg text-xs font-bold"
                        title="Kichiklashtirish"
                    >
                        🔍-
                    </button>

                    <button
                        @click="zoom = 100; redrawCanvas();"
                        class="h-7 px-2 text-slate-800 font-mono font-bold text-xs hover:bg-white rounded-lg"
                        title="100% asl o'lcham"
                    >
                        {{ zoom }}%
                    </button>

                    <button
                        @click="zoom = Math.min(zoom + 10, 200); redrawCanvas();"
                        class="h-7 px-2 text-slate-700 hover:bg-white rounded-lg text-xs font-bold"
                        title="Kattalashtirish"
                    >
                        🔍+
                    </button>
                </div>

                <!-- Auto-Split & Alignment Tools (Facade Mode) -->
                <div v-if="editorScope === 'facade'" class="flex items-center gap-1.5">
                    <button
                        @click="openAutoSplitModal"
                        class="h-8 px-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition-all flex items-center gap-1"
                        title="Binoning barcha qavatlarini bir martada tekis taqsimlash"
                    >
                        <span>⚡</span> Avto-Taqsimlash
                    </button>
                    <button
                        @click="alignAllFloorWidths"
                        class="h-8 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs rounded-xl border border-slate-200 shadow-xs transition-all flex items-center gap-1"
                        title="Barcha qavatlarning chap va o'ng chegaralarini bitta to'g'ri chiziqqa tekislash"
                    >
                        <span>📏</span> Tekislash
                    </button>
                </div>
            </div>

            <!-- Right: Upload, Delete, Save & Showroom -->
            <div class="flex items-center gap-2 shrink-0">
                <!-- Facade Upload (Scope: Facade) -->
                <label v-if="editorScope === 'facade'" class="h-8 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-xl font-bold text-xs cursor-pointer flex items-center gap-1.5 transition-all">
                    <span>🖼️</span> Fasad
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept="image/*"
                        @change="handleImageUpload"
                        class="hidden"
                    />
                </label>

                <!-- Floor Blueprint Upload (Scope: Floor) -->
                <label v-else class="h-8 px-3 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl font-bold text-xs cursor-pointer flex items-center gap-1.5 transition-all">
                    <span>📐</span> Qavat Chizmasi
                    <input
                        ref="blueprintFileInputRef"
                        type="file"
                        accept="image/*"
                        @change="handleBlueprintUpload"
                        class="hidden"
                    />
                </label>

                <button
                    v-if="selectedShapeId"
                    @click="handleDeleteShape"
                    class="h-8 px-2.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-xl font-bold text-xs transition-all flex items-center gap-1"
                    title="Tanlangan shaklni o'chirish"
                >
                    <span>🗑️</span>
                </button>

                <button
                    @click="handleSave"
                    :disabled="isSaving"
                    class="h-8 px-4 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-xl font-black text-xs flex items-center gap-1.5 shadow-md shadow-orange-500/20 transition-all cursor-pointer shrink-0"
                >
                    <span>{{ isSaving ? '⏳' : '💾' }}</span>
                    <span>Saqlash</span>
                </button>

                <Link
                    :href="route('admin.crm.showroom')"
                    class="h-8 px-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs transition-all flex items-center gap-1.5 shadow-xs shrink-0"
                >
                    <span>👁️</span> Shou-room
                </Link>
            </div>
        </header>

        <!-- Main Workspace Area -->
        <main class="flex flex-1 overflow-hidden p-3 gap-3">
            
            <!-- Left Sidebar: Floors List (Facade mode) OR Units List (Floor mode) -->
            <aside class="w-64 bg-white border border-slate-200/90 rounded-2xl p-3.5 overflow-y-auto flex flex-col gap-2.5 shadow-sm shrink-0">
                
                <!-- ================= MODE 1: FACADE FLOORS LIST ================= -->
                <template v-if="editorScope === 'facade'">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <h3 class="text-slate-900 font-black text-xs uppercase tracking-wider">Qavatlar Ro'yxati</h3>
                        <span class="text-[11px] font-mono font-bold text-orange-600 bg-orange-50 px-2 py-0.5 rounded-md">{{ drawnFloors.size }}/{{ availableFloors.length }}</span>
                    </div>

                    <div class="space-y-1 flex-1 overflow-y-auto pr-1">
                        <button
                            v-for="floor in availableFloors"
                            :key="'fl-btn-' + floor.id"
                            @click="handleSelectFloor(floor.floor_number)"
                            class="w-full p-2.5 rounded-xl font-bold text-xs transition-all text-left flex items-center justify-between border"
                            :class="selectedFloor === floor.floor_number 
                                ? 'bg-orange-500 text-white border-orange-600 shadow-sm ring-2 ring-orange-300' 
                                : (drawnFloors.has(floor.floor_number) ? 'bg-orange-50/70 border-orange-200 text-orange-900 hover:bg-orange-100/70' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100')"
                        >
                            <span>{{ floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat` }}</span>
                            <span 
                                class="text-[10px] px-2 py-0.5 rounded-full font-black"
                                :class="selectedFloor === floor.floor_number ? 'bg-white/20 text-white' : (drawnFloors.has(floor.floor_number) ? 'bg-orange-200/80 text-orange-900' : 'bg-slate-200 text-slate-500')"
                            >
                                {{ drawnFloors.has(floor.floor_number) ? 'Chizildi ✓' : 'Chizilmagan' }}
                            </span>
                        </button>
                    </div>
                </template>

                <!-- ================= MODE 2: FLOOR UNITS LIST ================= -->
                <template v-else>
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <div>
                            <h3 class="text-slate-900 font-black text-xs uppercase tracking-wider">Xonadonlar</h3>
                            <span class="text-[10px] text-slate-500 font-semibold">{{ selectedFloorObj?.floor_number }}-Qavat</span>
                        </div>
                        <button
                            @click="addNewUnit"
                            class="px-2 py-1 bg-orange-500 hover:bg-orange-600 text-white text-[11px] font-bold rounded-lg transition-all flex items-center gap-1 shadow-sm"
                        >
                            <span>➕</span> Qo'shish
                        </button>
                    </div>

                    <div class="space-y-1 flex-1 overflow-y-auto pr-1">
                        <button
                            v-for="unit in floorUnits"
                            :key="'unit-btn-' + unit.id"
                            @click="handleSelectUnit(unit.id)"
                            class="w-full p-2.5 rounded-xl font-bold text-xs transition-all text-left flex items-center justify-between border"
                            :class="selectedUnitId === unit.id 
                                ? 'bg-orange-500 text-white border-orange-600 shadow-sm ring-2 ring-orange-300' 
                                : (drawnUnits.has(unit.unit_number) || drawnUnits.has(unit.id) ? 'bg-emerald-50/80 border-emerald-200 text-emerald-950 hover:bg-emerald-100/70' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100')"
                        >
                            <div class="flex flex-col">
                                <span class="font-extrabold">{{ unit.unit_number }}-xonadon</span>
                                <span class="text-[10px] font-normal" :class="selectedUnitId === unit.id ? 'text-white/80' : 'text-slate-500'">
                                    {{ unit.rooms_count }}-xona • {{ unit.area_sqm }} m²
                                </span>
                            </div>
                            <span 
                                class="text-[10px] px-2 py-0.5 rounded-full font-black"
                                :class="selectedUnitId === unit.id ? 'bg-white/20 text-white' : (drawnUnits.has(unit.unit_number) || drawnUnits.has(unit.id) ? 'bg-emerald-200/80 text-emerald-900' : 'bg-slate-200 text-slate-500')"
                            >
                                {{ (drawnUnits.has(unit.unit_number) || drawnUnits.has(unit.id)) ? 'Chizildi ✓' : 'Chizilmagan' }}
                            </span>
                        </button>
                    </div>
                </template>

            </aside>

            <!-- Center Canvas Viewport (Maximally Enlarged!) -->
            <section 
                ref="canvasContainerRef"
                class="flex-1 bg-slate-950 rounded-2xl border border-slate-800 shadow-inner relative overflow-hidden flex items-center justify-center"
            >
                <div
                    :style="{
                        transform: `scale(${zoom / 100})`,
                        transformOrigin: 'center',
                        transition: 'transform 0.15s ease-out'
                    }"
                    class="w-full h-full flex items-center justify-center"
                >
                    <canvas
                        ref="canvasElement"
                        @mousedown="handleCanvasMouseDown"
                        @mousemove="handleCanvasMouseMove"
                        @mouseup="handleCanvasMouseUp"
                        @mouseleave="handleCanvasMouseUp"
                        @dblclick="handleCanvasDoubleClick"
                        class="cursor-crosshair block"
                    ></canvas>
                </div>

                <!-- Hint overlay -->
                <div v-if="drawingMode === 'polygon'" class="absolute bottom-4 left-6 bg-slate-900/90 backdrop-blur-md px-4 py-2 rounded-xl border border-slate-700 text-white text-xs font-semibold shadow-lg">
                    💡 Nuqtalarni belgilang. Yakunlash uchun oxirgi nuqtani <b>ikki marta bosing (Double-Click)</b>.
                </div>
            </section>

            <!-- Right Sidebar: Coordinates & Shapes Inspector -->
            <aside class="w-80 bg-white border border-slate-200/90 rounded-2xl p-4 overflow-y-auto flex flex-col gap-3 shadow-sm shrink-0">
                
                <!-- ================= FACADE SCOPE INSPECTOR ================= -->
                <template v-if="editorScope === 'facade'">
                    <h3 class="text-slate-900 font-black text-xs uppercase tracking-wider pb-2 border-b border-slate-100">
                        Qavat Koordinatasi
                    </h3>

                    <!-- Selected Floor Info & Edit Blueprint Button -->
                    <div v-if="selectedFloor" class="bg-orange-50 p-3 rounded-2xl border border-orange-200/80 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600 text-xs font-bold">Tanlangan qavat:</span>
                            <span class="px-2.5 py-0.5 bg-orange-500 text-white rounded-full text-xs font-black">
                                {{ selectedFloor < 0 ? `${selectedFloor}-yerto'la` : `${selectedFloor}-qavat` }}
                            </span>
                        </div>

                        <!-- Open Floor Blueprint Editor Button -->
                        <button
                            @click="openFloorBlueprintEditor(selectedFloor)"
                            class="w-full py-2 px-3 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <span>📐</span>
                            <span>Qavat Chizmasini Tahrirlash →</span>
                        </button>
                    </div>

                    <div v-else class="bg-amber-50 p-3 rounded-xl border border-amber-200 text-amber-800 text-xs font-bold">
                        ⚠️ Avval chap tarafdagi ro'yxatdan qavatni tanlang.
                    </div>
                </template>

                <!-- ================= FLOOR SCOPE UNIT INSPECTOR ================= -->
                <template v-else>
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <h3 class="text-slate-900 font-black text-xs uppercase tracking-wider">
                            Xonadon Tafsilotlari
                        </h3>
                        <span class="text-xs font-mono font-bold text-orange-600">{{ selectedUnitObj ? `${selectedUnitObj.unit_number}-xonadon` : '' }}</span>
                    </div>

                    <div v-if="selectedUnitObj" class="space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 block mb-1">Xonadon №</label>
                                <input 
                                    v-model="selectedUnitObj.unit_number" 
                                    type="text" 
                                    class="w-full h-8 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold px-2 focus:ring-1 focus:ring-orange-500"
                                />
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 block mb-1">Xonalar soni</label>
                                <input 
                                    v-model="selectedUnitObj.rooms_count" 
                                    type="number" 
                                    min="1" max="10"
                                    class="w-full h-8 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold px-2 focus:ring-1 focus:ring-orange-500"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 block mb-1">Maydoni (m²)</label>
                                <input 
                                    v-model="selectedUnitObj.area_sqm" 
                                    type="number" 
                                    step="0.1"
                                    class="w-full h-8 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold px-2 focus:ring-1 focus:ring-orange-500"
                                />
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 block mb-1">Holati</label>
                                <select 
                                    v-model="selectedUnitObj.status"
                                    class="w-full h-8 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold px-2 focus:ring-1 focus:ring-orange-500"
                                >
                                    <option value="available">🟢 Sotuvda</option>
                                    <option value="reserved">🟡 Band (Bron)</option>
                                    <option value="sold">🔴 Sotilgan</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-bold text-slate-500 block mb-1">Narxi (UZS)</label>
                            <input 
                                v-model="selectedUnitObj.price" 
                                type="number" 
                                class="w-full h-8 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold px-2 focus:ring-1 focus:ring-orange-500"
                            />
                        </div>
                    </div>
                </template>

                <!-- Live Message Alert -->
                <div 
                    v-if="message" 
                    class="p-2.5 rounded-xl border text-xs font-bold shadow-xs animate-in fade-in duration-150"
                    :class="isError ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'"
                >
                    {{ message }}
                </div>

                <!-- Selected Shape Coordinates -->
                <div v-if="selectedShapeObj" class="bg-slate-50 p-3 rounded-2xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-slate-800">Shakl koordinatalari:</span>
                        <span class="text-[10px] font-mono font-bold text-orange-600 uppercase bg-orange-50 px-2 py-0.5 rounded border border-orange-200">
                            {{ selectedShapeObj.type }}
                        </span>
                    </div>

                    <div v-if="selectedShapeObj.type === 'rect'" class="grid grid-cols-2 gap-1.5 text-[11px] font-mono text-slate-700 bg-white p-2 rounded-xl border border-slate-200">
                        <div><span class="text-slate-400">X:</span> {{ selectedShapeObj.x }}</div>
                        <div><span class="text-slate-400">Y:</span> {{ selectedShapeObj.y }}</div>
                        <div><span class="text-slate-400">W:</span> {{ selectedShapeObj.w }}px</div>
                        <div><span class="text-slate-400">H:</span> {{ selectedShapeObj.h }}px</div>
                    </div>

                    <button 
                        @click="handleDeleteShape"
                        class="w-full py-1.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-1 cursor-pointer"
                    >
                        <span>🗑️</span> Ushbu Shaklni O'chirish
                    </button>
                </div>

                <!-- Shapes List -->
                <div class="flex-1 flex flex-col gap-2 mt-1">
                    <div class="flex items-center justify-between">
                        <h4 class="text-slate-800 font-bold text-xs uppercase">
                            {{ editorScope === 'facade' ? "Chizilgan Qavatlar:" : "Chizilgan Xonadonlar:" }}
                        </h4>
                        <span class="text-xs font-mono text-orange-600 font-bold bg-orange-50 px-2 py-0.5 rounded">{{ shapes.length }} ta</span>
                    </div>

                    <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                        <div
                            v-for="(shape, idx) in shapes"
                            :key="'shape-item-' + shape.id"
                            @click="selectedShapeId = shape.id; redrawCanvas();"
                            class="p-2 rounded-xl cursor-pointer transition-all border text-xs font-bold flex flex-col gap-0.5"
                            :class="selectedShapeId === shape.id 
                                ? 'bg-orange-50 border-orange-300 text-orange-950 ring-1 ring-orange-400' 
                                : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'"
                        >
                            <div class="flex items-center justify-between">
                                <span>{{ idx + 1 }}. {{ shape.label || shape.floorLabel }}</span>
                                <span class="text-[10px] text-slate-400 uppercase font-mono">{{ shape.type }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </main>

        <!-- Clean Status Bar (Height 32px) -->
        <footer class="h-8 bg-white border-t border-slate-200 px-5 text-slate-600 text-xs flex items-center justify-between font-semibold shrink-0">
            <div>
                <span v-if="editorScope === 'facade' && selectedFloor" class="text-slate-800">
                    <strong class="text-orange-600">{{ selectedFloor < 0 ? `${selectedFloor}-yerto'la` : `${selectedFloor}-qavat` }}</strong> — 
                    {{ drawingMode === 'rect' ? "To'rtburchak chizish rejimi" : (drawingMode === 'select' ? "Tanlash rejimi" : "Ko'pburchak chizish rejimi") }}
                </span>
                <span v-else-if="editorScope === 'floor' && selectedUnitObj" class="text-slate-800">
                    <strong class="text-orange-600">{{ selectedFloorObj?.floor_number }}-Qavat › {{ selectedUnitObj.unit_number }}-xonadon</strong> — 
                    {{ drawingMode === 'rect' ? "To'rtburchak chizish rejimi" : (drawingMode === 'select' ? "Tanlash rejimi" : "Ko'pburchak chizish rejimi") }}
                </span>
                <span v-else class="text-amber-600">⚠️ {{ editorScope === 'facade' ? 'Qavatni tanlang' : 'Xonadonni tanlang' }}</span>
            </div>
            <div>
                <strong class="text-orange-600">{{ shapes.length }}</strong> shakl chizildi • <strong class="text-slate-800">{{ editorScope === 'facade' ? drawnFloors.size : drawnUnits.size }}</strong> ta qamrab olindi
            </div>
        </footer>

        <!-- Auto Split Modal -->
        <div v-if="showAutoSplitModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
            <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-lg w-full p-6 text-slate-800 space-y-5 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl font-bold">⚡</div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Bino Qavatlarini Tekis Taqsimlash</h3>
                            <p class="text-xs text-slate-500 font-medium">Barcha qavatlarni bir xil, tekis va chiroyli joylashtirish</p>
                        </div>
                    </div>
                    <button @click="showAutoSplitModal = false" class="text-slate-400 hover:text-slate-700 text-lg font-bold">✕</button>
                </div>

                <div class="space-y-4 text-xs font-bold">
                    <!-- Info Box -->
                    <div class="p-3.5 bg-blue-50 border border-blue-200 rounded-2xl text-blue-900 space-y-1">
                        <p class="font-extrabold flex items-center gap-1.5">
                            <span>💡</span> Qanday ishlaydi?
                        </p>
                        <p class="text-[11px] font-normal leading-relaxed text-blue-800">
                            Tizim binoning butun maydonini pastdan tepaga qarab tanlangan barcha qavatlarga 100% teng, tekis va millimetr aniqlikda bo'lib beradi.
                        </p>
                    </div>

                    <!-- Floor Range Selection -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-700 mb-1">Boshlang'ich Qavat:</label>
                            <select v-model="autoSplitFromFloor" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs font-bold">
                                <option v-for="fl in availableFloors" :key="'from-' + fl.id" :value="fl.floor_number">
                                    {{ fl.floor_number < 0 ? `${fl.floor_number}-yerto'la` : `${fl.floor_number}-qavat` }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-700 mb-1">Tugash Qavati:</label>
                            <select v-model="autoSplitToFloor" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs font-bold">
                                <option v-for="fl in availableFloors" :key="'to-' + fl.id" :value="fl.floor_number">
                                    {{ fl.floor_number < 0 ? `${fl.floor_number}-yerto'la` : `${fl.floor_number}-qavat` }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Commercial First Floor Option -->
                    <label class="flex items-center gap-2.5 p-3 bg-slate-50 border border-slate-200 rounded-2xl cursor-pointer hover:bg-slate-100 transition-colors">
                        <input type="checkbox" v-model="autoSplitMakeCommercialTaller" class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4" />
                        <div>
                            <span class="text-slate-900 font-extrabold">1-qavatni balandroq qilish (Tijorat / Do'konlar)</span>
                            <p class="text-[11px] text-slate-500 font-normal">1-qavat shifti baland bo'lgani sababli unga 1.25x ko'proq balandlik beriladi</p>
                        </div>
                    </label>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                    <button
                        @click="showAutoSplitModal = false"
                        class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all"
                    >
                        Bekor qilish
                    </button>
                    <button
                        @click="executeAutoSplit"
                        class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-black text-xs rounded-xl shadow-lg shadow-blue-600/30 transition-all flex items-center gap-1.5"
                    >
                        <span>✨</span> Tekis Taqsimlash
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
