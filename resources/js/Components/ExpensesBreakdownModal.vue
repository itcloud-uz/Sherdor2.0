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
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-[fadeIn_0.3s_ease-out]">
        <div class="glass-panel w-full max-w-4xl p-0 rounded-2xl relative border border-white/10 flex flex-col max-h-[90vh]">
            
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b border-white/10 bg-black/40 rounded-t-2xl shrink-0">
                <h3 class="text-2xl font-display font-bold text-white flex items-center gap-3">
                    <span class="w-3 h-3 rounded-full bg-neon-red shadow-[0_0_10px_#ff003c]"></span>
                    Chiqimlar Hisoboti (Batafsil)
                </h3>
                <button @click="close" class="text-text-muted hover:text-white transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="p-6 overflow-y-auto custom-scrollbar flex-1 relative">
                <div v-if="isProcessing" class="absolute inset-0 flex items-center justify-center bg-black/50 backdrop-blur-sm z-10 rounded-b-2xl">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-neon-red"></div>
                </div>

                <div v-else>
                    <!-- Total Summary -->
                    <div class="mb-8 p-6 rounded-2xl bg-gradient-to-r from-neon-red/10 to-transparent border border-neon-red/20">
                        <p class="text-sm font-mono text-text-muted mb-1">JAMI TASDIQLANGAN CHIQIMLAR</p>
                        <h2 class="text-4xl font-display font-bold text-white drop-shadow-[0_0_15px_rgba(255,0,0,0.4)]">
                            - {{ $currency(totalExpenses) }}
                        </h2>
                    </div>

                    <!-- Categories Breakdown -->
                    <h4 class="text-lg font-bold text-white mb-4 uppercase tracking-widest font-display">Kategoriyalar bo'yicha</h4>
                    
                    <div v-if="categories.length === 0" class="text-center py-10 text-text-muted border border-dashed border-white/10 rounded-xl">
                        Hozircha hech qanday tasdiqlangan chiqimlar yo'q.
                    </div>

                    <div v-else class="space-y-4">
                        <div v-for="(cat, index) in categories" :key="index" class="glass-card p-4 rounded-xl border border-white/5 hover:border-white/20 transition-all group">
                            <div class="flex justify-between items-center cursor-pointer" @click="cat.expanded = !cat.expanded">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg bg-black/50 border border-white/10 flex items-center justify-center text-neon-red font-bold">
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <h5 class="text-white font-bold text-lg">{{ cat.category_name }}</h5>
                                        <p class="text-xs text-text-muted">{{ cat.transactions.length }} ta tranzaksiya</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="text-xl font-bold text-white">{{ $currency(cat.total) }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-text-muted transform transition-transform" :class="{'rotate-180': cat.expanded}" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                            
                            <!-- Transactions List (Collapsible) -->
                            <div v-show="cat.expanded" class="mt-4 pt-4 border-t border-white/10 pl-[56px] space-y-2 animate-[fadeIn_0.2s_ease-out]">
                                <div v-for="txn in cat.transactions" :key="txn.id" class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                                    <div>
                                        <p class="text-sm text-white/80">{{ txn.notes || 'Izohsiz' }}</p>
                                        <p class="text-xs text-text-muted font-mono mt-1">Sana: {{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }} | ID: {{ txn.receipt_hash }}</p>
                                    </div>
                                    <span class="text-sm font-bold text-white/90">{{ $currency(txn.amount) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer & Download -->
            <div class="p-6 border-t border-white/10 bg-black/40 rounded-b-2xl shrink-0 flex justify-between items-center">
                <p class="text-xs text-text-muted max-w-md">Ushbu hisobot barcha tasdiqlangan va yopilgan chiqimlarni o'z ichiga oladi.</p>
                <div class="flex gap-4">
                    <button @click="close" class="px-6 py-2 rounded border border-white/10 text-white hover:bg-white/5 transition-colors">Yopish</button>
                    <a :href="route('admin.finance.expenses.report.export.pdf')" target="_blank" class="px-6 py-2 rounded bg-neon-red/20 border border-neon-red text-neon-red font-bold hover:bg-neon-red hover:text-white transition-colors flex items-center gap-2">
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
