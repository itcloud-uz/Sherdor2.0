<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/Modal.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const props = defineProps({
    fixedAssets: Array,
    partners: Array
});

const isProcessing = ref(false);
const searchQuery = ref('');
const filterType = ref('');
const filterStatus = ref('');

const typeOptions = [
    { label: 'Qurilish vositasi', value: 'construction_equipment' },
    { label: 'Ofis buyumi', value: 'office_furniture' },
    { label: 'Yengil konsturuksiya', value: 'light_structure' },
    { label: 'Boshqa', value: 'other' }
];

const statusOptions = [
    { label: 'Ishlatilmoqda (Aktiv)', value: 'active' },
    { label: 'Sotilgan', value: 'sold' },
    { label: 'Ishdan chiqqan', value: 'retired' }
];

const partnerOptions = computed(() => [
    { label: 'Kompaniya (Umumiy)', value: '' },
    ...props.partners.map(p => ({ label: p.user?.name, value: p.id }))
]);

const filterTypeOptions = [
    { label: 'Barcha turlar', value: '' },
    ...typeOptions
];

const filterStatusOptions = [
    { label: 'Barcha holatlar', value: '' },
    ...statusOptions
];

// Filtered assets list
const filteredAssets = computed(() => {
    return props.fixedAssets.filter(asset => {
        const matchesSearch = asset.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                             (asset.description && asset.description.toLowerCase().includes(searchQuery.value.toLowerCase()));
        const matchesType = !filterType.value || asset.type === filterType.value;
        const matchesStatus = !filterStatus.value || asset.status === filterStatus.value;
        return matchesSearch && matchesType && matchesStatus;
    });
});

// Helper for currency formatting
function formatCurrency(value) {
    if (!value) return '0';
    return Number(value).toLocaleString('uz-UZ');
}

// Helper for date formatting
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return `${d.getDate().toString().padStart(2,'0')}.${(d.getMonth()+1).toString().padStart(2,'0')}.${d.getFullYear()}`;
}



// Export CSV
function exportCSV() {
    const headers = ['Nomi', 'Turi', 'Sotib olingan', 'Narxi', 'Holati', 'Hamkor/Mas\'ul'];
    const rows = filteredAssets.value.map(asset => {
        const typeLabel = asset.type === 'construction_equipment' ? 'Qurilish vositasi' : 
                          asset.type === 'office_furniture' ? 'Ofis buyumi' : 
                          asset.type === 'light_structure' ? 'Yengil konsturuksiya' : asset.type;
        const partnerName = asset.partner?.user?.name || 'Kompaniya';
        return [
            `"${asset.name.replace(/"/g, '""')}"`,
            typeLabel,
            formatDate(asset.purchase_date),
            asset.purchase_cost,
            asset.status.toUpperCase(),
            partnerName
        ];
    });

    const csvContent = "data:text/csv;charset=utf-8,\uFEFF" 
        + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `Asosiy_Vositalar_${new Date().getTime()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Asset Management state
const showAssetModal = ref(false);
const isEditingAsset = ref(false);
const currentAssetId = ref(null);
const assetForm = useForm({
    name: '',
    type: 'construction_equipment',
    purchase_date: '',
    purchase_cost: '',
    status: 'active',
    description: '',
    partner_id: ''
});

function openAddAssetModal() {
    isEditingAsset.value = false;
    currentAssetId.value = null;
    assetForm.reset();
    assetForm.purchase_date = new Date().toISOString().split('T')[0];
    showAssetModal.value = true;
}

function openEditAssetModal(asset) {
    isEditingAsset.value = true;
    currentAssetId.value = asset.id;
    assetForm.name = asset.name;
    assetForm.type = asset.type;
    assetForm.purchase_date = asset.purchase_date.substring(0, 10);
    assetForm.purchase_cost = asset.purchase_cost;
    assetForm.status = asset.status;
    assetForm.description = asset.description;
    assetForm.partner_id = asset.partner_id || '';
    showAssetModal.value = true;
}

function submitAsset() {
    isProcessing.value = true;
    if (isEditingAsset.value) {
        assetForm.put(route('admin.partners.assets.update', currentAssetId.value), {
            onSuccess: () => {
                showAssetModal.value = false;
                isProcessing.value = false;
            },
            onError: () => isProcessing.value = false
        });
    } else {
        assetForm.post(route('admin.partners.assets.store'), {
            onSuccess: () => {
                showAssetModal.value = false;
                isProcessing.value = false;
            },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteAsset(id) {
    if (confirm("Ushbu asosiy vositani o'chirishga ishonchingiz komilmi?")) {
        router.delete(route('admin.partners.assets.destroy', id));
    }
}
</script>

<template>
    <Head title="Asosiy Vositalar" />
    <AdminLayout>
        <div class="bg-slate-50 min-h-screen p-4 md:p-8 rounded-2xl animate-[fadeIn_0.4s_ease-out]">
            <!-- Sleek Header Card -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 bg-white border border-slate-100 p-6 rounded-2xl shadow-sm gap-4">
                <div>
                    <h3 class="text-lg font-display font-black text-slate-800 tracking-wide uppercase">Asosiy Vositalar</h3>
                    <p class="text-xs text-slate-400 font-medium mt-1">Kompaniyaning jismoniy aktivlari, uskunalar va jihozlar boshqaruvi</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 mt-4 sm:mt-0">

                    <button @click="openAddAssetModal" class="px-5 py-2.5 bg-emerald-600 text-white text-xs font-mono font-bold rounded-xl hover:bg-emerald-700 transition-all shadow-md shadow-emerald-600/10 flex items-center justify-center gap-2 active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                        Yangi Vosita Qo'shish
                    </button>
                </div>
            </div>

            <!-- Dashboard Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Stat 1 -->
                <div class="relative overflow-hidden bg-white border border-slate-100 shadow-sm rounded-2xl p-6">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-100/30 rounded-full blur-2xl"></div>
                    <div class="relative z-10 flex justify-between items-center">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Vositalar soni</p>
                            <p class="text-3xl font-display font-black text-slate-800 leading-none">{{ fixedAssets.length }} <span class="text-xs font-medium text-slate-400">dona</span></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        </div>
                    </div>
                </div>

                <!-- Stat 2 -->
                <div class="relative overflow-hidden bg-white border border-slate-100 shadow-sm rounded-2xl p-6">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-100/30 rounded-full blur-2xl"></div>
                    <div class="relative z-10 flex justify-between items-center">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Jami Sotib olish qiymati</p>
                            <p class="text-2xl font-display font-black text-slate-800 leading-none">{{ formatCurrency(fixedAssets.reduce((sum, a) => sum + Number(a.purchase_cost), 0)) }} <span class="text-xs font-medium text-slate-400">UZS</span></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                    </div>
                </div>

                <!-- Stat 3 -->
                <div class="relative overflow-hidden bg-white border border-slate-100 shadow-sm rounded-2xl p-6">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-purple-100/30 rounded-full blur-2xl"></div>
                    <div class="relative z-10 flex justify-between items-center">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Faol vositalar</p>
                            <p class="text-3xl font-display font-black text-slate-800 leading-none">{{ fixedAssets.filter(a => a.status === 'active').length }} <span class="text-xs font-medium text-slate-400">dona</span></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & Search Controls -->
            <div class="bg-white border border-slate-100 shadow-sm rounded-2xl p-5 mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="w-full md:w-1/3 relative">
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        placeholder="Nomi yoki izoh bo'yicha qidirish..." 
                        class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-slate-50/50"
                    >
                    <svg class="h-4 w-4 text-slate-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                
                <div class="w-full md:w-auto flex flex-col sm:flex-row gap-3 items-center ml-auto">
                    <SearchableSelect 
                        v-model="filterType" 
                        :options="filterTypeOptions" 
                        placeholder="Barcha turlar"
                        class="w-full sm:w-44 text-xs font-mono"
                    />

                    <SearchableSelect 
                        v-model="filterStatus" 
                        :options="filterStatusOptions" 
                        placeholder="Barcha holatlar"
                        class="w-full sm:w-44 text-xs font-mono"
                    />

                    <button @click="exportCSV" class="w-full sm:w-auto px-4 py-2 border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white rounded-xl text-xs font-mono font-bold flex items-center justify-center gap-1.5 transition-all">
                        📥 Excelga Yuklash
                    </button>
                </div>
            </div>

            <!-- Table View -->
            <div class="bg-white border border-slate-100 shadow-sm rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left font-mono text-xs">
                        <thead class="bg-slate-50 text-slate-400 uppercase text-[10px] font-bold tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="p-5">Vosita nomi & Izoh</th>
                                <th class="p-5">Turi</th>
                                <th class="p-5">Sana</th>
                                <th class="p-5">Narxi</th>
                                <th class="p-5">Holati & Mas'ul</th>
                                <th class="p-5 text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="asset in filteredAssets" :key="asset.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-5">
                                    <div class="font-bold text-slate-800 text-sm leading-snug">{{ asset.name }}</div>
                                    <div class="text-[10px] text-slate-400 font-normal mt-1 max-w-xs truncate">{{ asset.description || 'Izoh yozilmagan' }}</div>
                                </td>
                                <td class="p-5">
                                    <span class="px-2.5 py-0.5 rounded-full text-slate-600 bg-slate-100 border border-slate-200/50 text-[9px] font-semibold uppercase">
                                        {{ 
                                            asset.type === 'construction_equipment' ? 'Qurilish vositasi' : 
                                            asset.type === 'office_furniture' ? 'Ofis buyumi' : 
                                            asset.type === 'light_structure' ? 'Yengil konsturuksiya' : 'Boshqa' 
                                        }}
                                    </span>
                                </td>
                                <td class="p-5 text-slate-500">
                                    {{ formatDate(asset.purchase_date) }}
                                </td>
                                <td class="p-5 font-bold text-slate-900 text-sm">
                                    {{ formatCurrency(asset.purchase_cost) }} <span class="text-[10px] text-slate-400 font-medium">UZS</span>
                                </td>
                                <td class="p-5">
                                    <span 
                                        class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider"
                                        :class="{ 
                                            'bg-emerald-50 text-emerald-600 border border-emerald-200': asset.status === 'active', 
                                            'bg-red-50 text-red-600 border border-red-200': asset.status === 'sold', 
                                            'bg-slate-100 text-slate-500 border border-slate-200': asset.status === 'retired', 
                                        }"
                                    >
                                        {{ asset.status === 'active' ? 'Faol' : asset.status === 'sold' ? 'Sotilgan' : 'Ishdan chiqqan' }}
                                    </span>
                                    <div v-if="asset.partner" class="text-[9px] font-medium text-slate-400 mt-1">Sarmoyador: {{ asset.partner.user?.name }}</div>
                                    <div v-else class="text-[9px] font-medium text-slate-400 mt-1">Kompaniya mulki</div>
                                </td>
                                <td class="p-5 text-right">
                                    <div class="flex justify-end gap-3">
                                        <button @click="openEditAssetModal(asset)" class="p-1.5 bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white rounded-lg transition-all" title="Tahrirlash">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </button>
                                        <button @click="deleteAsset(asset.id)" class="p-1.5 bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white rounded-lg transition-all" title="O'chirish">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredAssets.length === 0">
                                <td colspan="7" class="p-16 text-center text-slate-400 font-mono italic">Qidiruv bo'yicha hech qanday asosiy vosita topilmadi.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add / Edit Asset Modal -->
        <Modal :show="showAssetModal" @close="showAssetModal = false" maxWidth="lg">
            <div class="p-6 max-h-[90vh] overflow-y-auto custom-scrollbar relative">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white rounded-full transition-all shadow-sm z-50 font-bold" @click="showAssetModal = false">✖</button>
                <h2 class="text-lg font-display font-black uppercase tracking-widest mb-6 border-b border-slate-100 pb-4 text-slate-800">
                    {{ isEditingAsset ? 'Asosiy Vosita Tahrirlash' : 'Yangi Asosiy Vosita' }}
                </h2>
                
                <form @submit.prevent="submitAsset" class="space-y-5 font-mono text-xs text-slate-700">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-slate-400 font-bold uppercase tracking-wider mb-1.5">Nomi / Model</label>
                            <input v-model="assetForm.name" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs bg-slate-50/50" placeholder="Masalan: Beton qorgich, Ofis stoli..." required>
                        </div>
                        
                        <div>
                            <label class="block text-slate-400 font-bold uppercase tracking-wider mb-1.5">Turi</label>
                            <SearchableSelect 
                                v-model="assetForm.type" 
                                :options="typeOptions" 
                                placeholder="Turi"
                                class="text-xs"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-slate-400 font-bold uppercase tracking-wider mb-1.5">Holati</label>
                            <SearchableSelect 
                                v-model="assetForm.status" 
                                :options="statusOptions" 
                                placeholder="Holati"
                                class="text-xs"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-slate-400 font-bold uppercase tracking-wider mb-1.5">Sotib olingan sana</label>
                            <input v-model="assetForm.purchase_date" type="date" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs bg-slate-50/50" required>
                        </div>

                        <div>
                            <label class="block text-slate-400 font-bold uppercase tracking-wider mb-1.5">Sotib olish narxi (UZS)</label>
                            <input v-model="assetForm.purchase_cost" type="number" step="0.01" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs bg-slate-50/50" required>
                        </div>


                        <div>
                            <label class="block text-slate-400 font-bold uppercase tracking-wider mb-1.5">Mas'ul Hamkor (Taqsimot)</label>
                            <SearchableSelect 
                                v-model="assetForm.partner_id" 
                                :options="partnerOptions" 
                                placeholder="Hamkor tanlang..."
                                class="text-xs"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-slate-400 font-bold uppercase tracking-wider mb-1.5">Qisqacha izoh</label>
                            <textarea v-model="assetForm.description" rows="3" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs bg-slate-50/50" placeholder="Texnik holati yoki boshqa ma'lumotlar..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 pt-4 border-t border-slate-100 mt-6">
                        <button type="button" @click="showAssetModal = false" class="px-5 py-2.5 text-slate-500 hover:bg-slate-100 rounded-xl text-xs uppercase font-bold transition-all">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs uppercase font-bold transition-all shadow-md shadow-emerald-600/10">
                            {{ isEditingAsset ? 'Yangilash' : 'Saqlash' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
