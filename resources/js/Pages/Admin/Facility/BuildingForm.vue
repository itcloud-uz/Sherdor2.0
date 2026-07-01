<template>
    <div v-if="renderError" class="p-10 bg-red-50 text-red-700 h-screen w-screen overflow-auto">
        <h1 class="text-2xl font-bold mb-4">Frontend Error in BuildingForm.vue</h1>
        <pre class="whitespace-pre-wrap">{{ renderError.stack || renderError.message || renderError }}</pre>
    </div>
    <Head :title="pageTitle" v-if="!renderError" />
    <AdminLayout v-if="!renderError">
        <template #header>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800 font-sans tracking-tight">
                        {{ pageTitle }}
                    </h2>
                    <p class="text-sm text-slate-500 mt-1">Obyekt va blok ma'lumotlarini kiritish va tahrirlash</p>
                </div>
                <Link :href="route('admin.facility')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold text-sm rounded-xl transition-all shadow-sm">
                    Orqaga Qaytish
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-6 md:p-8">
                <form @submit.prevent="submitBuilding" class="space-y-8">
                    
                    <!-- 1. TAHRIRLANAYOTGAN/QO'SHILAYOTGAN NAYSA OBJECT CONTAINER BO'LSA (total_floors === 0) -->
                    <div v-if="isObjectOnly" class="space-y-6">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-lg font-bold text-slate-800">Obyekt Ma'lumotlari</h3>
                        </div>

                        <!-- Obyekt Nomi & Foto -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Obyekt Nomi <span class="text-red-500">*</span></label>
                                <input v-model="buildingForm.name" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-sm rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-3 outline-none transition-all" required placeholder="Masalan: Sherdor Turar Joy Majmuasi">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Asosiy Foto</label>
                                <input type="file" @change="handleFileChange('photo', $event)" accept="image/*" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl block p-2 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-white file:border file:border-slate-200 file:text-slate-700 hover:file:bg-slate-50 cursor-pointer transition-all">
                            </div>
                        </div>

                        <!-- Location Fields -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Viloyat</label>
                                <input v-model="buildingForm.district" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-sm rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-3 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Tuman/Shahar</label>
                                <input v-model="buildingForm.city" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-sm rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-3 outline-none transition-all">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Mahalla</label>
                                <input v-model="buildingForm.mahalla" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-sm rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-3 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Ko'cha</label>
                                <input v-model="buildingForm.street" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-sm rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-3 outline-none transition-all">
                            </div>
                        </div>

                        <!-- Files Section -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">2D Chizma (Layout)</label>
                                <input type="file" @change="handleFileChange('blueprint', $event)" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl block p-2 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-white file:border file:border-slate-200 file:text-slate-700 hover:file:bg-slate-50 cursor-pointer transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">3D Model (PNG, JPG, PDF)</label>
                                <input type="file" @change="handleFileChange('model_3d', $event)" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl block p-2 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-white file:border file:border-slate-200 file:text-slate-700 hover:file:bg-slate-50 cursor-pointer transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- 2. TAHRIRLANAYOTGAN/QO'SHILAYOTGAN NAYSA BLOK BO'LSA -->
                    <div v-else class="space-y-6">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-lg font-bold text-slate-800">Blok Ma'lumotlari</h3>
                        </div>

                        <!-- ROW 1: BLOK NOMI & KORPUS NOMI -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Blok Nomi <span class="text-red-500">*</span></label>
                                <input v-model="buildingForm.block_name" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-sm rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-3 outline-none transition-all" placeholder="Masalan: A Blok" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Korpus Nomi</label>
                                <input v-model="buildingForm.corpus_name" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-sm rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-3 outline-none transition-all" placeholder="Masalan: 3-Korpus">
                            </div>
                        </div>

                        <!-- PODEZLAR (PODEZ - STAIRCASE/ENTRANCE) -->
                        <div class="pt-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Podezlar (Podez - Staircase/Entrance)</h3>
                                <button type="button" @click="addPodezToForm" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-sans font-bold uppercase rounded-xl transition-all shadow-sm">
                                    + Podez Qo'shish
                                </button>
                            </div>
                            
                            <div class="space-y-4">
                                <div v-for="(podez, idx) in buildingForm.podezes" :key="podez.id" class="p-5 bg-slate-50 border border-slate-200 rounded-xl relative transition-all hover:border-blue-200 hover:shadow-sm">
                                    <div class="flex justify-between items-center mb-4 border-b border-slate-200 pb-2">
                                        <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Podez #{{ idx + 1 }}</span>
                                        <button v-if="buildingForm.podezes.length > 1" type="button" @click="removePodezFromForm(podez.id)" class="text-red-500 hover:text-red-600 text-xs font-bold flex items-center gap-1 transition-colors px-2 py-1 bg-red-50 rounded-lg hover:bg-red-100">
                                            O'chirish
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase">Podez Nomi</label>
                                            <input v-model="podez.name" type="text" class="w-full bg-white border border-slate-200 text-slate-800 text-xs rounded-lg block p-2.5 outline-none focus:ring-1 focus:ring-blue-500" required>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase">Podez Raqami</label>
                                            <input v-model.number="podez.number" type="number" min="1" class="w-full bg-white border border-slate-200 text-slate-800 text-xs rounded-lg block p-2.5 outline-none focus:ring-1 focus:ring-blue-500" required>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase">Qavatlar Soni</label>
                                            <input v-model.number="podez.floors" type="number" min="1" max="100" class="w-full bg-white border border-slate-200 text-slate-800 text-xs rounded-lg block p-2.5 outline-none focus:ring-1 focus:ring-blue-500" required>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase">Podez Turi</label>
                                            <select v-model="podez.type" class="w-full bg-white border border-slate-200 text-slate-800 text-xs rounded-lg block p-2.5 outline-none cursor-pointer focus:ring-1 focus:ring-blue-500">
                                                <option value="Staircase">Staircase</option>
                                                <option value="Elevator">Elevator</option>
                                                <option value="Corridor">Corridor</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase" title="1 qavatdagi xonadonlar soni">Xonadon / Qavat</label>
                                            <input v-model.number="podez.apartments_per_floor" type="number" min="0" max="50" class="w-full bg-white border border-slate-200 text-slate-800 text-xs rounded-lg block p-2.5 outline-none focus:ring-1 focus:ring-blue-500" placeholder="0">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase" title="1 qavatdagi do'konlar soni">Do'kon / Qavat</label>
                                            <input v-model.number="podez.commercial_per_floor" type="number" min="0" max="50" class="w-full bg-white border border-slate-200 text-slate-800 text-xs rounded-lg block p-2.5 outline-none focus:ring-1 focus:ring-blue-500" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROW 5: 2D CHIZMA & 3D MODEL (PNG, JPG, PDF) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">2D Chizma (Layout)</label>
                                <input type="file" @change="handleFileChange('blueprint', $event)" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl block p-2 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-white file:border file:border-slate-200 file:text-slate-700 hover:file:bg-slate-50 cursor-pointer transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">3D Model (PNG, JPG, PDF)</label>
                                <input type="file" @change="handleFileChange('model_3d', $event)" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl block p-2 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-white file:border file:border-slate-200 file:text-slate-700 hover:file:bg-slate-50 cursor-pointer transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT BUTTONS -->
                    <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                        <Link :href="route('admin.facility')" class="px-5 py-2.5 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-all shadow-sm">
                            Bekor Qilish
                        </Link>
                        <button type="submit" :disabled="buildingForm.processing" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                            <svg v-if="buildingForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span v-else>💾</span>
                            Saqlash
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onErrorCaptured } from 'vue';
import { useForm, Link, Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    isEditing: Boolean,
    building: Object, // If editing
    objectContainer: Object, // If adding block to object
});

const renderError = ref(null);
onErrorCaptured((err, instance, info) => {
    console.error('Error captured in BuildingForm:', err, info);
    renderError.value = err;
    return false;
});

const isObjectOnly = computed(() => {
    if (props.isEditing && props.building) {
        return props.building.total_floors === 0;
    }
    if (!props.isEditing && !props.objectContainer) {
        // Adding new object
        return true;
    }
    return false;
});

const pageTitle = computed(() => {
    if (props.isEditing) {
        return isObjectOnly.value ? "Obyektni Tahrirlash" : "Blokni Tahrirlash";
    }
    return isObjectOnly.value ? "Yangi Obyekt Qo'shish" : "Yangi Blok Qo'shish";
});

const buildingForm = useForm({
    name: props.building?.name || (props.objectContainer ? props.objectContainer.name : ''),
    city: props.building?.city || '',
    district: props.building?.district || '',
    mahalla: props.building?.mahalla || '',
    street: props.building?.street || '',
    total_floors: props.building?.total_floors || 0,
    blocks_count: props.building?.blocks_count || 0,
    entrances_count: props.building?.entrances_count || 0,
    total_shops: props.building?.total_shops || 0,
    total_apartments: props.building?.total_apartments || 0,
    shops_per_floor: props.building?.shops_per_floor || 0,
    apartments_per_floor: props.building?.apartments_per_floor || 0,
    estimated_budget: props.building?.estimated_budget || 0,
    blueprint: null,
    model_3d: null,
    photo: null,
    is_object_only: isObjectOnly.value ? 1 : 0,
    basement_floors: props.building?.basement_floors || 0,
    block_name: props.building?.block_name || '',
    corpus_name: props.building?.corpus_name || '',
    podezes: props.building?.podezes?.map(p => ({
        id: p.id,
        name: p.podez_name,
        number: p.podez_number,
        floors: p.total_floors,
        type: p.podez_type || 'Staircase',
        apartments_per_floor: p.apartments_per_floor || 0,
        commercial_per_floor: p.commercial_per_floor || 0,
    })) || [
        { id: 'temp_' + Date.now(), name: 'P-1', number: 1, floors: 5, type: 'Staircase', apartments_per_floor: 0, commercial_per_floor: 0 }
    ]
});

function handleFileChange(field, event) {
    const file = event.target.files[0];
    if (file) {
        buildingForm[field] = file;
    }
}

function addPodezToForm() {
    const nextNum = buildingForm.podezes.length + 1;
    buildingForm.podezes.push({
        id: 'temp_' + Date.now() + '_' + Math.random(),
        name: 'P-' + nextNum,
        number: nextNum,
        floors: 5,
        type: 'Staircase',
        apartments_per_floor: 0,
        commercial_per_floor: 0,
    });
}

function removePodezFromForm(podezId) {
    if (buildingForm.podezes.length > 1) {
        if (props.building && props.building.podezes) {
            const existingPodez = props.building.podezes.find(p => p.id === podezId);
            if (existingPodez) {
                let unitsCount = 0;
                if (existingPodez.floors) {
                    existingPodez.floors.forEach(f => {
                        if (f.units) unitsCount += f.units.length;
                    });
                }
                if (unitsCount > 0) {
                    alert("Bu podezda kvartiralar bor! Avval kvartiralarni o'chiring.");
                    return;
                }
            }
        }
        buildingForm.podezes = buildingForm.podezes.filter(p => p.id !== podezId);
    }
}

function submitBuilding() {
    if (props.isEditing) {
        buildingForm.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(route('admin.facility.buildings.update', props.building.id));
    } else {
        buildingForm.post(route('admin.facility.buildings.store'));
    }
}
</script>
