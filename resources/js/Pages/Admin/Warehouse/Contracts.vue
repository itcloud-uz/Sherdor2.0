<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

const props = defineProps({
    contracts: Array,
    suppliers: Array,
});

const formatMoney = (amount) => {
    return new Intl.NumberFormat('uz-UZ', { style: 'currency', currency: 'UZS' }).format(amount);
};

const showModal = ref(false);
const isScanning = ref(false);
const scanError = ref('');
const form = ref({
    supplier_id: '',
    contract_number: '',
    start_date: '',
    end_date: '',
    total_amount: '',
    notes: ''
});

const openModal = () => {
    showModal.value = true;
    form.value = {
        supplier_id: '',
        contract_number: 'SC-' + Math.floor(Math.random() * 10000),
        start_date: new Date().toISOString().slice(0,10),
        end_date: '',
        total_amount: '',
        notes: ''
    };
};

const submit = () => {
    router.post(route('admin.warehouse.contracts.store'), form.value, {
        onSuccess: () => {
            showModal.value = false;
        }
    });
};

const fileInput = ref(null);

const triggerFileUpload = () => {
    if (fileInput.value) {
        fileInput.value.click();
    }
};

const handleFileUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    if (file.type !== 'application/pdf') {
        scanError.value = "Faqat PDF formatidagi fayllar qabul qilinadi.";
        return;
    }

    scanError.value = '';
    isScanning.value = true;

    const formData = new FormData();
    formData.append('document', file);

    try {
        const response = await axios.post(route('admin.warehouse.contracts.scan'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        if (response.data.success) {
            const data = response.data.data;
            if (response.data.is_json) {
                if (data.contract_number) form.value.contract_number = data.contract_number;
                if (data.total_amount) form.value.total_amount = data.total_amount;
                if (data.start_date) form.value.start_date = data.start_date;
                if (data.end_date) form.value.end_date = data.end_date;
                
                // If supplier ID or INN is matched, we could auto-select, but for now we set notes.
                form.value.notes = `Hujjatdan o'qilgan ma'lumotlar: \n` + JSON.stringify(data, null, 2);
            } else {
                form.value.notes = `QR Koddan o'qildi: \n` + data;
            }
        } else {
            scanError.value = response.data.message || 'Xatolik yuz berdi.';
        }
    } catch (error) {
        scanError.value = error.response?.data?.message || "Serverga ulanishda xatolik yuz berdi.";
    } finally {
        isScanning.value = false;
        event.target.value = null; // reset input
    }
};
</script>

<template>
    <Head title="Shartnomalar" />
    <AdminLayout>
        <div class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center mb-8">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.warehouse.index')" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white transition-all">
                        ←
                    </Link>
                    <h1 class="text-3xl font-display font-bold text-white tracking-widest uppercase shadow-neon-green/50 text-shadow-glow flex items-center gap-3 hidden">
                        <span class="text-neon-green">📝</span> Shartnomalar
                    </h1>
                </div>
                <button @click="openModal" class="bg-neon-green text-black px-4 py-2 rounded-lg font-bold hover:shadow-[0_0_15px_#39FF14] transition-all text-sm">
                    + Yangi Shartnoma
                </button>
            </div>

            <div class="glass-panel p-6 rounded-2xl border border-white/5">
                <div class="overflow-x-auto">
                    <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left text-sm font-mono border-collapse">
                        <thead>
                            <tr class="border-b border-white/10 text-[10px] text-text-muted uppercase tracking-widest bg-black/40">
                                <th class="p-4 font-medium rounded-tl-xl">Raqami</th>
                                <th class="p-4 font-medium">Ta'minotchi</th>
                                <th class="p-4 font-medium">Boshlanish</th>
                                <th class="p-4 font-medium">Tugash</th>
                                <th class="p-4 font-medium text-right">{{ $t('amount') }}</th>
                                <th class="p-4 font-medium text-right rounded-tr-xl">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in contracts" :key="c.id" class="border-b border-white/5 hover:bg-white/5 transition-colors group">
                                <td class="p-4 text-neon-cyan font-bold">{{ c.contract_number }}</td>
                                <td class="p-4 text-white">{{ c.supplier?.company_name || c.supplier?.name }}</td>
                                <td class="p-4 text-text-secondary">{{ c.start_date }}</td>
                                <td class="p-4 text-text-secondary">{{ c.end_date || '-' }}</td>
                                <td class="p-4 text-right text-neon-green font-bold">{{ formatMoney(c.total_amount) }}</td>
                                <td class="p-4 text-right">
                                    <span v-if="c.status === 'active'" class="px-2 py-1 bg-neon-green/20 text-neon-green border border-neon-green/30 text-[10px] uppercase rounded">Faol</span>
                                    <span v-else class="px-2 py-1 bg-text-muted/20 text-text-muted border border-text-muted/30 text-[10px] uppercase rounded">{{ c.status }}</span>
                                </td>
                            </tr>
                            <tr v-if="contracts.length === 0">
                                <td colspan="6" class="p-12 text-center text-text-muted text-xs border border-dashed border-white/10 rounded-xl">Shartnomalar yo'q</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- YANGA SHARTNOMA MODAL -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
            <div class="glass-panel w-full max-w-lg p-6 rounded-2xl border border-neon-green/50 shadow-[0_0_30px_rgba(57,255,20,0.15)] relative">
                <button @click="showModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
                
                <h2 class="text-xl font-display font-bold text-white mb-6 uppercase">Yangi Ta'minot Shartnomasi</h2>
                
                <!-- PDF Upload Area -->
                <div class="mb-6 p-4 border border-dashed border-neon-cyan/50 rounded-xl bg-neon-cyan/5 text-center transition-all hover:bg-neon-cyan/10">
                    <input type="file" ref="fileInput" @change="handleFileUpload" accept=".pdf" class="hidden" />
                    
                    <div v-if="isScanning" class="flex flex-col items-center justify-center py-4 text-neon-cyan">
                        <div class="w-8 h-8 border-4 border-neon-cyan border-t-transparent rounded-full animate-spin mb-2"></div>
                        <span class="text-xs font-mono font-bold animate-pulse">PDF O'qilmoqda...</span>
                    </div>
                    
                    <div v-else class="flex flex-col items-center">
                        <span class="text-3xl mb-2 text-neon-cyan">📄</span>
                        <button type="button" @click="triggerFileUpload" class="text-sm font-bold text-neon-cyan hover:text-white transition-colors mb-1 font-display">
                            Shartnoma PDF faylini yuklash (Skaner)
                        </button>
                        <p class="text-[10px] font-mono text-text-muted">QR kod orqali avtomatik to'ldirish</p>
                    </div>
                    
                    <div v-if="scanError" class="mt-3 p-2 bg-neon-red/10 border border-neon-red/30 text-neon-red text-xs rounded">
                        {{ scanError }}
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Ta'minotchi</label>
                            <select v-model="form.supplier_id" required class="glass-input w-full bg-black/50 text-white">
                                <option value="" disabled>Tanlang</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.company_name || s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Shartnoma Raqami</label>
                            <input v-model="form.contract_number" type="text" required class="glass-input w-full">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">{{ $t('start_date') }}</label>
                            <input v-model="form.start_date" type="date" required class="glass-input w-full bg-black/50 text-white" style="color-scheme: dark;">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Tugash Sanasi (Ixtiyoriy)</label>
                            <input v-model="form.end_date" type="date" class="glass-input w-full bg-black/50 text-white" style="color-scheme: dark;">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Umumiy Summa (UZS)</label>
                        <input v-model="form.total_amount" type="number" required min="0" class="glass-input w-full" placeholder="Shartnoma summasi">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Izoh / Shartlar</label>
                        <textarea v-model="form.notes" rows="3" class="glass-input w-full" placeholder="Qo'shimcha ma'lumotlar..."></textarea>
                    </div>
                    
                    <div class="pt-4 flex gap-3 justify-end">
                        <button type="button" @click="showModal = false" class="px-4 py-2 text-sm font-mono text-text-muted hover:text-white">{{ $t('cancel') }}</button>
                        <button type="submit" class="bg-neon-green text-black font-bold text-sm px-6 py-2 rounded hover:shadow-[0_0_15px_#39FF14] transition-all">{{ $t('save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
