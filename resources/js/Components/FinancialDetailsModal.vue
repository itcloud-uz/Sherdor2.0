<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    category: String, // 'cash', 'bank', 'credit', 'overall', 'financial_flow'
});

const emit = defineEmits(['close']);

const loading = ref(false);
const transactions = ref([]);
const startDate = ref('');
const endDate = ref('');

const categoryTitleMap = {
    'cash': 'KASSA - Batafsil',
    'bank': 'HISOB RAQAM - Batafsil',
    'credit': 'KREDIT - Batafsil',
    'overall': 'UMUMIY - Batafsil',
    'financial_flow': 'MOLIYAVIY OQIM - Batafsil'
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('uz-UZ', { style: 'currency', currency: 'UZS', maximumFractionDigits: 0 }).format(val || 0);
};

const fetchData = async () => {
    if (!props.show || !props.category) return;
    
    loading.value = true;
    try {
        const res = await axios.get(route('admin.financial.reports'), {
            params: {
                category: props.category,
                start_date: startDate.value || null,
                end_date: endDate.value || null,
            }
        });
        transactions.value = res.data.data;
    } catch (error) {
        console.error("Error fetching detailed financial reports:", error);
    } finally {
        loading.value = false;
    }
};

const downloadData = () => {
    const url = new URL(route('admin.financial.reports'));
    url.searchParams.append('category', props.category);
    if (startDate.value) url.searchParams.append('start_date', startDate.value);
    if (endDate.value) url.searchParams.append('end_date', endDate.value);
    url.searchParams.append('export', 'true');
    
    window.location.href = url.toString();
};

watch(() => props.show, (newVal) => {
    if (newVal) {
        // Reset filters when opening
        startDate.value = '';
        endDate.value = '';
        fetchData();
    }
});

const applyFilter = () => {
    fetchData();
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" maxWidth="6xl">
        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-gray-300 bg-[#05050A] flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
                <h2 class="text-2xl font-display font-bold text-gray-900 uppercase tracking-widest flex items-center gap-3">
                    <span class="w-3 h-3 rounded-full bg-blue-600 animate-pulse"></span>
                    {{ categoryTitleMap[category] || 'MOLIYAVIY HISOBOT' }}
                </h2>
                <button @click="emit('close')" class="text-gray-500 hover:text-gray-900 transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-4 mb-6 items-end bg-gray-500/50 p-4 rounded-xl border border-gray-100">
                <div class="w-full sm:w-auto">
                    <label class="block text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">Boshlanish Sanasi</label>
                    <input v-model="startDate" type="date" class="border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all text-sm w-full">
                </div>
                <div class="w-full sm:w-auto">
                    <label class="block text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">Tugash Sanasi</label>
                    <input v-model="endDate" type="date" class="border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all text-sm w-full">
                </div>
                <div class="flex gap-3 mt-2 sm:mt-0 ml-auto w-full sm:w-auto">
                    <button @click="applyFilter" class="flex-1 sm:flex-none px-4 py-2 border border-gray-300 text-gray-900 font-mono text-xs rounded hover:bg-gray-100 transition-colors">
                        Filtrlash
                    </button>
                    <button @click="downloadData" class="flex-1 sm:flex-none px-4 py-2 bg-blue-50 border border-blue-200 text-blue-600 font-mono font-bold text-xs rounded hover:bg-blue-600 hover:text-black transition-colors shadow-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Yuklab Olish (PDF)
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="flex-1 overflow-y-auto custom-scrollbar border border-gray-100 rounded-xl bg-gray-500/50 relative">
                <div v-if="loading" class="absolute inset-0 bg-gray-500/50 flex justify-center items-center z-10">
                    <div class="w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                </div>
                
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                        <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                            <th class="p-4 text-xs font-mono text-gray-500 uppercase tracking-widest border-b border-gray-200">Sana</th>
                            <th class="p-4 text-xs font-mono text-gray-500 uppercase tracking-widest border-b border-gray-200">Kategoriya</th>
                            <th class="p-4 text-xs font-mono text-gray-500 uppercase tracking-widest border-b border-gray-200">Tur</th>
                            <th class="p-4 text-xs font-mono text-gray-500 uppercase tracking-widest border-b border-gray-200">Tafsilot (Sabab)</th>
                            <th class="p-4 text-xs font-mono text-gray-500 uppercase tracking-widest border-b border-gray-200 text-right">Miqdor (UZS)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in transactions" :key="t.id" class="border-b border-gray-100 hover: transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                            <td class="p-4 text-sm text-gray-900 font-mono whitespace-nowrap">{{ t.date }}</td>
                            <td class="p-4 text-sm text-gray-500 uppercase">{{ t.category }}</td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-[10px] uppercase tracking-widest font-bold" 
                                    :class="t.type === 'income' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-red-50 text-red-600 border border-red-200'">
                                    {{ t.type === 'income' ? 'Tushum' : 'Chiqim' }}
                                </span>
                            </td>
                            <td class="p-4 text-sm text-gray-900 max-w-[300px] truncate" :title="t.description">{{ t.description }}</td>
                            <td class="p-4 text-sm font-bold text-right font-display whitespace-nowrap"
                                :class="t.type === 'income' ? 'text-emerald-600 drop-shadow-sm' : 'text-red-600 drop-shadow-sm'">
                                {{ t.type === 'income' ? '+' : '-' }}{{ formatCurrency(t.amount) }}
                            </td>
                        </tr>
                        <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="!loading && transactions.length === 0">
                            <td colspan="5" class="p-8 text-center text-gray-500 font-mono text-sm">
                                Ushbu sanalar oralig'ida ma'lumot topilmadi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.3);
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 240, 255, 0.5);
}
</style>
