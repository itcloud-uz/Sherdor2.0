<template>
    <div v-if="renderError" class="p-10 bg-red-50 text-red-700 h-screen w-screen overflow-auto">
        <h1 class="text-2xl font-bold mb-4">Frontend Error in BuildingForm.vue</h1>
        <pre class="whitespace-pre-wrap">{{ renderError.stack || renderError.message || renderError }}</pre>
    </div>
    <Head :title="pageTitle" v-if="!renderError" />
    <AdminLayout v-if="!renderError">
        <template #header>
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.facility')" class="p-2 rounded-full hover:bg-gray-200 text-gray-500 transition-colors" title="Orqaga">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </Link>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">
                            {{ pageTitle }}
                        </h2>
                        <p class="text-sm text-gray-500 mt-0.5">Obyekt va blok ma'lumotlarini kiritish va tahrirlash</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-6 md:p-8">
                <form @submit.prevent="submitBuilding" class="space-y-8">
                    <div v-if="Object.keys(buildingForm.errors).length > 0" class="p-4 bg-red-50 text-red-700 border border-red-200 rounded-xl text-sm font-medium mb-6">
                        <ul class="list-disc pl-5">
                            <li v-for="(error, key) in buildingForm.errors" :key="key">
                                {{ error }}
                            </li>
                        </ul>
                    </div>
                    
                    <!-- 1. TAHRIRLANAYOTGAN/QO'SHILAYOTGAN NAYSA OBJECT CONTAINER BO'LSA (total_floors === 0) -->
                    <div v-if="isObjectOnly" class="space-y-6">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-lg font-bold text-slate-800">Obyekt Ma'lumotlari</h3>
                        </div>

                        <!-- Obyekt Nomi & Foto -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">Obyekt Nomi <span class="text-red-500">*</span></label>
                                <input v-model="buildingForm.name" type="text" class="w-full bg-transparent border-b-2 border-gray-200 focus:border-blue-600 text-gray-900 text-base py-2 px-0 outline-none transition-all placeholder-gray-300 focus:ring-0" required placeholder="Masalan: Sherdor Turar Joy Majmuasi">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">Asosiy Foto</label>
                                <input type="file" @change="handleFileChange('photo', $event)" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors cursor-pointer">
                            </div>
                        </div>

                        <!-- Location Fields -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">Viloyat</label>
                                <input v-model="buildingForm.district" type="text" class="w-full bg-transparent border-b-2 border-gray-200 focus:border-blue-600 text-gray-900 text-base py-2 px-0 outline-none transition-all placeholder-gray-300 focus:ring-0">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">Tuman/Shahar</label>
                                <input v-model="buildingForm.city" type="text" class="w-full bg-transparent border-b-2 border-gray-200 focus:border-blue-600 text-gray-900 text-base py-2 px-0 outline-none transition-all placeholder-gray-300 focus:ring-0">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">Mahalla</label>
                                <input v-model="buildingForm.mahalla" type="text" class="w-full bg-transparent border-b-2 border-gray-200 focus:border-blue-600 text-gray-900 text-base py-2 px-0 outline-none transition-all placeholder-gray-300 focus:ring-0">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">Ko'cha</label>
                                <input v-model="buildingForm.street" type="text" class="w-full bg-transparent border-b-2 border-gray-200 focus:border-blue-600 text-gray-900 text-base py-2 px-0 outline-none transition-all placeholder-gray-300 focus:ring-0">
                            </div>
                        </div>

                        <!-- Files Section -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">2D Chizma (Layout)</label>
                                <input type="file" @change="handleFileChange('blueprint', $event)" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-colors cursor-pointer">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">3D Model (PNG, JPG, PDF)</label>
                                <input type="file" @change="handleFileChange('model_3d', $event)" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-colors cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <!-- 2. TAHRIRLANAYOTGAN/QO'SHILAYOTGAN NAYSA BLOK BO'LSA -->
                    <div v-else class="space-y-6">
                        <div class="border-b border-slate-100 pb-4">
                            <h3 class="text-lg font-bold text-slate-800">Blok Ma'lumotlari</h3>
                        </div>

                        <!-- ROW 1: BLOK NOMI & KORPUS NOMI -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">Blok Nomi <span class="text-red-500">*</span></label>
                                <input v-model="buildingForm.block_name" type="text" class="w-full bg-transparent border-b-2 border-gray-200 focus:border-blue-600 text-gray-900 text-base py-2 px-0 outline-none transition-all placeholder-gray-300 focus:ring-0" placeholder="Masalan: A Blok" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">Korpus Nomi</label>
                                <input v-model="buildingForm.corpus_name" type="text" class="w-full bg-transparent border-b-2 border-gray-200 focus:border-blue-600 text-gray-900 text-base py-2 px-0 outline-none transition-all placeholder-gray-300 focus:ring-0" placeholder="Masalan: 3-Korpus">
                            </div>
                        </div>

                        <!-- PODEZLAR (PODEZ - STAIRCASE/ENTRANCE) -->
                        <div class="pt-8">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-widest">Podezlar (Yo'laklar)</h3>
                                <button type="button" @click="addPodezToForm" class="px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white text-xs font-semibold rounded-full transition-all shadow-sm">
                                    + Podez Qo'shish
                                </button>
                            </div>
                            
                            <div class="space-y-4">
                                <div v-for="(podez, idx) in buildingForm.podezes" :key="podez.id" class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition-all relative group">
                                    <div class="flex justify-between items-center mb-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center font-bold text-sm">{{ idx + 1 }}</div>
                                            <span class="text-sm font-bold text-gray-800">Podez</span>
                                        </div>
                                        <button v-if="buildingForm.podezes.length > 1" type="button" @click="removePodezFromForm(podez.id)" class="text-red-500 hover:text-red-600 text-xs font-semibold px-3 py-1.5 rounded-full hover:bg-red-50 transition-colors opacity-0 group-hover:opacity-100">
                                            O'chirish
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
                                        <div>
                                            <label class="block text-[10px] font-semibold text-gray-400 mb-1 uppercase tracking-widest">Podez Nomi</label>
                                            <input v-model="podez.name" type="text" class="w-full bg-transparent border-b border-gray-200 text-gray-800 text-sm py-1.5 px-0 outline-none focus:border-blue-600 focus:ring-0" required>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-gray-400 mb-1 uppercase tracking-widest">Podez Raqami</label>
                                            <input v-model.number="podez.number" type="number" min="1" class="w-full bg-transparent border-b border-gray-200 text-gray-800 text-sm py-1.5 px-0 outline-none focus:border-blue-600 focus:ring-0" required>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-gray-400 mb-1 uppercase tracking-widest">Qavatlar Soni</label>
                                            <input v-model.number="podez.floors" type="number" min="1" max="100" class="w-full bg-transparent border-b border-gray-200 text-gray-800 text-sm py-1.5 px-0 outline-none focus:border-blue-600 focus:ring-0" required>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-gray-400 mb-1 uppercase tracking-widest">Podez Turi</label>
                                            <select v-model="podez.type" class="w-full bg-transparent border-b border-gray-200 text-gray-800 text-sm py-1.5 px-0 outline-none cursor-pointer focus:border-blue-600 focus:ring-0">
                                                <option value="Staircase">Zina (Staircase)</option>
                                                <option value="Elevator">Lift (Elevator)</option>
                                                <option value="Corridor">Koridor (Corridor)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-gray-400 mb-1 uppercase tracking-widest" title="Umumiy xonadonlar soni">Umumiy Xonadonlar</label>
                                            <input v-model.number="podez.total_apartments" type="number" min="0" max="5000" class="w-full bg-transparent border-b border-gray-200 text-gray-800 text-sm py-1.5 px-0 outline-none focus:border-blue-600 focus:ring-0" placeholder="0">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold text-gray-400 mb-1 uppercase tracking-widest" title="Umumiy do'konlar soni">Umumiy Do'konlar</label>
                                            <input v-model.number="podez.total_commercial" type="number" min="0" max="5000" class="w-full bg-transparent border-b border-gray-200 text-gray-800 text-sm py-1.5 px-0 outline-none focus:border-blue-600 focus:ring-0" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROW 5: 2D CHIZMA & 3D MODEL (PNG, JPG, PDF) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-8">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">2D Chizma (Layout)</label>
                                <input type="file" @change="handleFileChange('blueprint', $event)" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-colors cursor-pointer">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">3D Model (PNG, JPG, PDF)</label>
                                <input type="file" @change="handleFileChange('model_3d', $event)" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-colors cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT BUTTONS -->
                    <div class="flex items-center justify-end gap-4 pt-10 border-t border-gray-100 mt-10">
                        <Link :href="route('admin.facility')" class="text-sm font-semibold text-gray-500 hover:text-gray-800 transition-colors">
                            Bekor Qilish
                        </Link>
                        <button type="submit" :disabled="buildingForm.processing" class="px-6 py-2.5 bg-gray-900 text-white text-sm font-semibold rounded-full hover:bg-gray-800 transition-colors shadow-sm disabled:opacity-50 flex items-center justify-center gap-2 min-w-[120px]">
                            <svg v-if="buildingForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>{{ buildingForm.processing ? 'Saqlanmoqda...' : 'Saqlash' }}</span>
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
    isObjectOverride: Boolean,
});

const renderError = ref(null);
onErrorCaptured((err, instance, info) => {
    console.error('Error captured in BuildingForm:', err, info);
    renderError.value = err;
    return false;
});

const isObjectOnly = computed(() => {
    if (props.isObjectOverride) {
        return true;
    }
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
    blocks_count: props.building?.blocks_count || 1,
    entrances_count: props.building?.entrances_count || 1,
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
        apartments_per_floor: p.apartments_per_floor,
        commercial_per_floor: p.commercial_per_floor,
        total_apartments: p.total_apartments > 0 ? p.total_apartments : 0,
        total_commercial: p.total_commercial > 0 ? p.total_commercial : 0,
    })) || [
        { 
            id: 'temp_' + Date.now(), 
            name: 'P-1', 
            number: 1, 
            floors: props.building?.total_floors || (props.objectContainer ? props.objectContainer.total_floors : 5), 
            type: 'Staircase', 
            total_apartments: props.building?.total_apartments || (props.objectContainer ? props.objectContainer.total_apartments : 0), 
            total_commercial: props.building?.total_shops || (props.objectContainer ? props.objectContainer.total_shops : 0) 
        }
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
        floors: props.building?.total_floors || (props.objectContainer ? props.objectContainer.total_floors : 5),
        type: 'Staircase',
        total_apartments: props.building?.total_apartments || (props.objectContainer ? props.objectContainer.total_apartments : 0),
        total_commercial: props.building?.total_shops || (props.objectContainer ? props.objectContainer.total_shops : 0),
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
