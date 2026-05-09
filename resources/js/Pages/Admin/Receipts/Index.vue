<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    groupedReceipts: Object
});

const activeTab = ref('partners');
const previewImage = ref(null);

function getTabLabel(tab) {
    if (tab === 'partners') return "Hamkorlar Cheklari";
    if (tab === 'finance') return "Umumiy Xarajat Cheklari";
    return "Boshqa Cheklar";
}

function openPreview(path) {
    previewImage.value = '/storage/' + path;
}

function formatCurrency(val) {
    return new Intl.NumberFormat('uz-UZ').format(val || 0) + ' UZS';
}

function formatDate(dateString) {
    if (!dateString) return '';
    const d = new Date(dateString);
    return `${d.getDate().toString().padStart(2,'0')}.${(d.getMonth()+1).toString().padStart(2,'0')}.${d.getFullYear()} ${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
}
</script>

<template>
    <Head title="Cheklar va Hujjatlar" />
    <AdminLayout>
        <div class="glass-panel p-8 rounded-2xl animate-[fadeIn_0.5s_ease-out]">
            <div class="mb-6 border-b border-white/10 pb-4 flex items-center gap-4">
                <Link :href="route('admin.finance')" class="w-10 h-10 rounded-xl bg-white/5 hover:bg-neon-primary/20 border border-white/10 hover:border-neon-primary flex items-center justify-center text-white hover:text-neon-primary transition-all group">
                    <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </Link>
                <div>
                    <h2 class="text-2xl font-display text-white font-bold uppercase tracking-widest">Cheklar va Hujjatlar</h2>
                    <p class="text-[10px] font-mono text-text-muted mt-1">Tizimga yuklangan barcha xarajat dalillari (fakturalar, cheklar)</p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-4 mb-8 border-b border-white/5 pb-2">
                <button 
                    v-for="(_, key) in groupedReceipts" 
                    :key="key"
                    @click="activeTab = key"
                    class="px-6 py-2 rounded-t-xl font-mono text-xs uppercase transition-all"
                    :class="activeTab === key ? 'bg-white/10 text-neon-cyan border-b-2 border-neon-cyan' : 'text-text-muted hover:text-white'"
                >
                    {{ getTabLabel(key) }} ({{ groupedReceipts[key].length }})
                </button>
            </div>

            <!-- Gallery Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <div v-for="receipt in groupedReceipts[activeTab]" :key="receipt.id" class="glass-card border border-white/10 hover:border-neon-cyan/50 transition-all rounded-xl overflow-hidden group cursor-pointer" @click="openPreview(receipt.attachment_path)">
                    
                    <!-- Image Area -->
                    <div class="h-48 bg-black/50 relative overflow-hidden flex items-center justify-center">
                        <template v-if="receipt.attachment_path.endsWith('.pdf')">
                            <div class="text-center text-neon-red">
                                <span class="text-4xl mb-2 block">📄</span>
                                <span class="text-xs font-bold font-mono">PDF HUJJAT</span>
                            </div>
                        </template>
                        <template v-else>
                            <img :src="'/storage/' + receipt.attachment_path" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                        </template>
                        
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <span class="px-4 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan text-xs font-bold font-mono rounded">Ko'rish</span>
                        </div>
                    </div>

                    <!-- Details Area -->
                    <div class="p-4 bg-white/5">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-lg font-display font-bold text-white">{{ formatCurrency(receipt.amount) }}</span>
                            <span class="text-[9px] px-2 py-1 rounded bg-white/10 text-white font-mono" :class="{'bg-neon-green/20 text-neon-green': receipt.status === 'approved', 'bg-neon-yellow/20 text-neon-yellow': receipt.status === 'pending'}">
                                {{ receipt.status }}
                            </span>
                        </div>
                        <p class="text-xs text-text-muted font-mono truncate mb-2" :title="receipt.notes">{{ receipt.notes || 'Izohsiz' }}</p>
                        
                        <div class="flex justify-between items-end border-t border-white/5 pt-2 mt-2">
                            <div>
                                <span class="block text-[8px] text-text-muted uppercase">Yukladi</span>
                                <span class="text-[10px] text-white">{{ receipt.creator?.name || 'Tizim' }}</span>
                            </div>
                            <div class="text-right">
                                <span class="block text-[8px] text-text-muted uppercase">{{ $t('date_label') }}</span>
                                <span class="text-[10px] text-neon-cyan">{{ formatDate(receipt.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="groupedReceipts[activeTab].length === 0" class="col-span-full py-16 text-center border border-dashed border-white/10 rounded-xl">
                    <p class="text-text-muted font-mono text-sm">Bu bo'limda hozircha cheklar yo'q.</p>
                </div>
            </div>
        </div>

        <!-- Image Preview Modal -->
        <div v-if="previewImage" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4 backdrop-blur-md" @click="previewImage = null">
            <button @click="previewImage = null" class="absolute top-4 right-4 text-white/50 hover:text-white transition-colors text-2xl z-[101]">✕</button>
            <div class="relative flex justify-center items-center rounded-lg shadow-[0_0_50px_rgba(0,240,255,0.2)] overflow-hidden" style="width: 90vw; height: 90vh; max-width: 1200px;" @click.stop>
                <template v-if="previewImage.endsWith('.pdf')">
                    <iframe :src="previewImage" class="w-full h-full bg-white rounded-lg"></iframe>
                </template>
                <template v-else>
                    <img :src="previewImage" class="w-full h-full object-contain rounded-lg" />
                </template>
            </div>
        </div>
    </AdminLayout>
</template>
