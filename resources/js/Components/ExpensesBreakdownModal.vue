<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean
});

const emit = defineEmits(['close']);

const isProcessing = ref(true);
const totalExpenses = ref(0);
const categories = ref([]);

function fetchBreakdown() {
    isProcessing.value = true;
    axios.get(route('admin.finance.expenses.breakdown'))
        .then(response => {
            totalExpenses.value = response.data.total_expenses;
            categories.value = response.data.categories;
        })
        .catch(err => {
            console.error('Failed to fetch expenses breakdown:', err);
        })
        .finally(() => {
            isProcessing.value = false;
        });
}

// Fetch data when the modal is shown
import { watch } from 'vue';
watch(() => props.show, (newVal) => {
    if (newVal) {
        fetchBreakdown();
    }
});

function close() {
    emit('close');
}

// Close on escape key
const handleEscape = (e) => {
    if (e.key === 'Escape' && props.show) {
        close();
    }
};

onMounted(() => document.addEventListener('keydown', handleEscape));
onUnmounted(() => document.removeEventListener('keydown', handleEscape));
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm animate-[fadeIn_0.3s_ease-out]">
        <div class="bg-white border border-gray-200 shadow-sm rounded-xl w-full max-w-4xl p-0 rounded-2xl relative border border-gray-200 flex flex-col max-h-[90vh]">
            
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gray-900/50 rounded-t-2xl shrink-0">
                <h3 class="text-2xl font-display font-bold text-gray-900 flex items-center gap-3">
                    <span class="w-3 h-3 rounded-full bg-red-600 shadow-sm"></span>
                    Chiqimlar Hisoboti (Batafsil)
                </h3>
                <button @click="close" class="text-gray-500 hover:text-gray-900 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="p-6 overflow-y-auto custom-scrollbar flex-1 relative">
                <div v-if="isProcessing" class="absolute inset-0 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm z-10 rounded-b-2xl">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-red-500"></div>
                </div>

                <div v-else>
                    <!-- Total Summary -->
                    <div class="mb-8 p-6 rounded-2xl bg-gradient-to-r from-neon-red/10 to-transparent border border-red-200">
                        <p class="text-sm font-mono text-gray-500 mb-1">JAMI TASDIQLANGAN CHIQIMLAR</p>
                        <h2 class="text-4xl font-display font-bold text-gray-900 drop-shadow-sm">
                            - {{ $currency(totalExpenses) }}
                        </h2>
                    </div>

                    <!-- Categories Breakdown -->
                    <h4 class="text-lg font-bold text-gray-900 mb-4 uppercase tracking-widest font-display">Kategoriyalar bo'yicha</h4>
                    
                    <div v-if="categories.length === 0" class="text-center py-10 text-gray-500 border border-dashed border-gray-200 rounded-xl">
                        Hozircha hech qanday tasdiqlangan chiqimlar yo'q.
                    </div>

                    <div v-else class="space-y-4">
                        <div v-for="(cat, index) in categories" :key="index" class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-gray-100 hover:border-gray-300 transition-all group">
                            <div class="flex justify-between items-center cursor-pointer" @click="cat.expanded = !cat.expanded">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg bg-gray-900/50 border border-gray-200 flex items-center justify-center text-red-600 font-bold">
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <h5 class="text-gray-900 font-bold text-lg">{{ cat.category_name }}</h5>
                                        <p class="text-xs text-gray-500">{{ cat.transactions.length }} ta tranzaksiya</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="text-xl font-bold text-gray-900">{{ $currency(cat.total) }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 transform transition-transform" :class="{'rotate-180': cat.expanded}" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                            
                            <!-- Transactions List (Collapsible) -->
                            <div v-show="cat.expanded" class="mt-4 pt-4 border-t border-gray-200 pl-[56px] space-y-2 animate-[fadeIn_0.2s_ease-out]">
                                <div v-for="txn in cat.transactions" :key="txn.id" class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                                    <div>
                                        <p class="text-sm text-gray-900/80">{{ txn.notes || 'Izohsiz' }}</p>
                                        <p class="text-xs text-gray-500 font-mono mt-1">Sana: {{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }} | ID: {{ txn.receipt_hash }}</p>
                                    </div>
                                    <span class="text-sm font-bold text-gray-900/90">{{ $currency(txn.amount) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer & Download -->
            <div class="p-6 border-t border-gray-200 bg-gray-900/50 rounded-b-2xl shrink-0 flex justify-between items-center">
                <p class="text-xs text-gray-500 max-w-md">Ushbu hisobot barcha tasdiqlangan va yopilgan chiqimlarni o'z ichiga oladi.</p>
                <div class="flex gap-4">
                    <button @click="close" class="px-6 py-2 rounded border border-gray-200 text-gray-900 hover:bg-gray-50 transition-colors">Yopish</button>
                    <a :href="route('admin.finance.expenses.report.export.pdf')" target="_blank" class="px-6 py-2 rounded bg-red-50 border border-red-500 text-red-600 font-bold hover:bg-red-600 hover:text-gray-900 transition-colors flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        PDF Yuklab Olish
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
