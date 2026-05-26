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
        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-8 rounded-2xl animate-[fadeIn_0.5s_ease-out]">
            <div class="mb-6 border-b border-gray-200 pb-4 flex items-center gap-4">
                <Link :href="route('admin.finance')" class="w-10 h-10 rounded-xl hover:bg-neon-primary/20 border border-gray-200 hover:border-neon-primary flex items-center justify-center text-gray-900 hover:text-neon-primary transition-all group">
                    <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </Link>
                <div>
                    <h2 class="text-2xl font-display text-gray-900 font-bold uppercase tracking-widest">Cheklar va Hujjatlar</h2>
                    <p class="text-[10px] font-mono text-gray-500 mt-1">Tizimga yuklangan barcha xarajat dalillari (fakturalar, cheklar)</p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-4 mb-8 border-b border-gray-100 pb-2">
                <button 
                    v-for="(_, key) in groupedReceipts" 
                    :key="key"
                    @click="activeTab = key"
                    class="px-6 py-2 rounded-t-xl font-mono text-xs uppercase transition-all"
                    :class="activeTab === key ? ' text-blue-600 border-b-2 border-blue-500' : 'text-gray-500 hover:text-gray-900'"
                >
                    {{ getTabLabel(key) }} ({{ groupedReceipts[key].length }})
                </button>
            </div>

            <!-- Gallery Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div v-for="receipt in groupedReceipts[activeTab]" :key="receipt.id" class="bg-white border border-gray-200 shadow-sm rounded-xl border border-gray-200 hover:border-blue-200 transition-all rounded-xl overflow-hidden group cursor-pointer" @click="openPreview(receipt.attachment_path)">
                    
                    <!-- Image Area -->
                    <div class="h-48 bg-gray-50 relative overflow-hidden flex items-center justify-center">
                        <template v-if="receipt.attachment_path.endsWith('.pdf')">
                            <div class="text-center text-red-600">
                                <span class="text-4xl mb-2 block">📄</span>
                                <span class="text-xs font-bold font-mono">PDF HUJJAT</span>
                            </div>
                        </template>
                        <template v-else>
                            <img :src="'/storage/' + receipt.attachment_path" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                        </template>
                        
                        <div class="absolute inset-0 bg-gray-50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <span class="px-4 py-2 bg-blue-50 border border-blue-500 text-blue-600 text-xs font-bold font-mono rounded">Ko'rish</span>
                        </div>
                    </div>

                    <!-- Details Area -->
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-lg font-display font-bold text-gray-900">{{ formatCurrency(receipt.amount) }}</span>
                            <span class="text-[9px] px-2 py-1 rounded text-gray-900 font-mono" :class="{'bg-emerald-50 text-emerald-600': receipt.status === 'approved', 'bg-amber-50 text-amber-600': receipt.status === 'pending'}">
                                {{ receipt.status }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 font-mono truncate mb-2" :title="receipt.notes">{{ receipt.notes || 'Izohsiz' }}</p>
                        
                        <div class="flex justify-between items-end border-t border-gray-100 pt-2 mt-2">
                            <div>
                                <span class="block text-[8px] text-gray-500 uppercase">Yukladi</span>
                                <span class="text-[10px] text-gray-900">{{ receipt.creator?.name || 'Tizim' }}</span>
                            </div>
                            <div class="text-right">
                                <span class="block text-[8px] text-gray-500 uppercase">{{ $t('date_label') }}</span>
                                <span class="text-[10px] text-blue-600">{{ formatDate(receipt.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="groupedReceipts[activeTab].length === 0" class="col-span-full py-16 text-center border border-dashed border-gray-200 rounded-xl">
                    <p class="text-gray-500 font-mono text-sm">Bu bo'limda hozircha cheklar yo'q.</p>
                </div>
            </div>
        </div>

        <!-- Image Preview Modal -->
        <div v-if="previewImage" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-50 p-4" @click="previewImage = null">
            <button @click="previewImage = null" class="absolute top-4 right-4 text-gray-900/50 hover:text-gray-900 transition-colors text-2xl z-[101]">✕</button>
            <div class="relative flex justify-center items-center rounded-lg shadow-sm overflow-hidden" style="width: 90vw; height: 90vh; max-width: 1200px;" @click.stop>
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
