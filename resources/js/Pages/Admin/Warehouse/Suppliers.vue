<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    suppliers: Array,
});

const showModal = ref(false);
const editMode = ref(false);
const selectedSupplier = ref(null);

const form = ref({
    name: '',
    company_name: '',
    phone: '',
    inn: '',
    bank_account: '',
    mfo: '',
    address: '',
});

const isSubmitting = ref(false);

const openAddModal = () => {
    editMode.value = false;
    selectedSupplier.value = null;
    form.value = { name: '', company_name: '', phone: '', inn: '', bank_account: '', mfo: '', address: '' };
    showModal.value = true;
};

const openEditModal = (supplier) => {
    editMode.value = true;
    selectedSupplier.value = supplier;
    form.value = { 
        name: supplier.name, 
        company_name: supplier.company_name, 
        phone: supplier.phone, 
        inn: supplier.inn, 
        bank_account: supplier.bank_account, 
        mfo: supplier.mfo, 
        address: supplier.address 
    };
    showModal.value = true;
};

function submit() {
    isSubmitting.value = true;
    if (editMode.value) {
        router.put(route('admin.warehouse.suppliers.update', selectedSupplier.value.id), form.value, {
            onSuccess: () => {
                isSubmitting.value = false;
                showModal.value = false;
            },
            onError: () => { isSubmitting.value = false; }
        });
    } else {
        router.post(route('admin.warehouse.suppliers.store'), form.value, {
            onSuccess: () => {
                isSubmitting.value = false;
                showModal.value = false;
            },
            onError: () => { isSubmitting.value = false; }
        });
    }
}

const deleteSupplier = (id) => {
    if (confirm("Ta'minotchini o'chirishga ishonchingiz komilmi?")) {
        router.delete(route('admin.warehouse.suppliers.destroy', id));
    }
};

const formatMoney = (amount) => {
    return new Intl.NumberFormat('uz-UZ', { style: 'currency', currency: 'UZS' }).format(amount);
};

const uploadInput = ref(null);
const currentSupplierIdForUpload = ref(null);

const triggerUpload = (supplierId) => {
    currentSupplierIdForUpload.value = supplierId;
    if (uploadInput.value) {
        uploadInput.value.click();
    }
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (!file || !currentSupplierIdForUpload.value) return;

    const formData = new FormData();
    formData.append('document', file);

    router.post(route('admin.warehouse.suppliers.upload', currentSupplierIdForUpload.value), formData, {
        onFinish: () => {
            event.target.value = '';
            currentSupplierIdForUpload.value = null;
        }
    });
};

const sendToTelegram = (id) => {
    if (confirm("Hujjatni Telegramga yuborasizmi?")) {
        router.post(route('admin.warehouse.suppliers.telegram', id));
    }
};
</script>

<template>
    <Head title="Ta'minotchilar" />
    <AdminLayout>
        <div class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center mb-8">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.warehouse.index')" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white transition-all">
                        ←
                    </Link>
                    <h1 class="text-3xl font-display font-bold text-white tracking-widest uppercase shadow-neon-yellow/50 text-shadow-glow flex items-center gap-3">
                        <span class="text-neon-yellow">🏢</span>{{ $t('suppliers') }}</h1>
                </div>
                
                <button @click="openAddModal" class="px-6 py-3 bg-neon-yellow/20 border border-neon-yellow text-neon-yellow font-bold font-mono text-sm uppercase tracking-widest rounded-xl hover:bg-neon-yellow hover:text-black transition-all shadow-[0_0_20px_rgba(255,255,0,0.2)]">
                    + Yangi Qo'shish
                </button>
            </div>

            <!-- Hidden File Input for Document Upload -->
            <input type="file" ref="uploadInput" @change="handleFileUpload" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="s in suppliers" :key="s.id" class="glass-panel p-6 rounded-2xl border border-white/10 hover:border-neon-yellow/50 transition-all group relative">
                    <!-- Edit & Delete Buttons -->
                    <div class="absolute top-4 right-4 flex items-center gap-2">
                        <button @click="openEditModal(s)" class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all" title="Tahrirlash">
                            ✏️
                        </button>
                        <button @click="deleteSupplier(s.id)" class="w-8 h-8 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500 hover:text-white flex items-center justify-center transition-all" title="O'chirish">
                            🗑️
                        </button>
                    </div>

                    <div class="flex justify-between items-start mb-4 pr-20">
                        <div>
                            <h3 class="text-xl font-bold text-white font-display">{{ s.company_name || s.name }}</h3>
                            <p class="text-[10px] font-mono text-text-muted uppercase mt-1">Vakil: {{ s.name }}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-2 mb-4 bg-black/40 p-4 rounded-xl border border-white/5">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-mono text-text-muted uppercase">Telefon</span>
                            <span class="text-sm font-bold text-white font-mono">{{ s.phone || '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-mono text-text-muted uppercase">STIR (INN)</span>
                            <span class="text-sm font-bold text-white font-mono">{{ s.inn || '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-mono text-text-muted uppercase">Shartnomalar</span>
                            <span class="text-sm font-bold text-neon-cyan font-mono">{{ s.contracts?.length || 0 }} ta</span>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-white/10 flex justify-between items-center mb-4">
                        <span class="text-[10px] font-mono text-text-muted uppercase">Joriy Balans</span>
                        <div class="text-right">
                            <span class="block text-lg font-bold font-mono" :class="s.balance > 0 ? 'text-neon-red' : (s.balance < 0 ? 'text-neon-green' : 'text-white')">
                                {{ formatMoney(Math.abs(s.balance)) }}
                            </span>
                            <span class="text-[10px] font-mono uppercase tracking-widest" :class="s.balance > 0 ? 'text-neon-red/70' : (s.balance < 0 ? 'text-neon-green/70' : 'text-text-muted')">
                                {{ s.balance > 0 ? 'Qarzimiz bor' : (s.balance < 0 ? 'Haqimiz bor' : 'Nol') }}
                            </span>
                        </div>
                    </div>

                    <!-- Document Attachment Section -->
                    <div class="pt-4 border-t border-white/10">
                        <div v-if="!s.document_path" class="flex items-center justify-center">
                            <button @click="triggerUpload(s.id)" class="w-full py-2 bg-white/5 border border-dashed border-white/20 text-text-muted hover:text-white hover:border-white/50 text-[10px] font-mono uppercase tracking-widest rounded transition-all">
                                📎 Hujjat Yuklash
                            </button>
                        </div>
                        <div v-else class="flex flex-col gap-2">
                            <div class="flex justify-between items-center bg-neon-cyan/5 border border-neon-cyan/20 p-2 rounded">
                                <span class="text-[10px] font-mono text-neon-cyan uppercase flex items-center gap-2">
                                    📄 Hujjat biriktirilgan
                                </span>
                                <div class="flex items-center gap-2">
                                    <a :href="'/storage/' + s.document_path" target="_blank" class="w-6 h-6 rounded bg-neon-cyan/20 text-neon-cyan flex items-center justify-center hover:bg-neon-cyan hover:text-black transition-all" title="Yuklab olish">
                                        ⬇️
                                    </a>
                                    <button @click="sendToTelegram(s.id)" class="w-6 h-6 rounded bg-blue-500/20 text-blue-400 flex items-center justify-center hover:bg-blue-500 hover:text-white transition-all" title="Telegramga yuborish">
                                        ✈️
                                    </button>
                                </div>
                            </div>
                            <button @click="triggerUpload(s.id)" class="text-center text-[10px] font-mono text-text-muted hover:text-white transition-colors">
                                Yangilash
                            </button>
                        </div>
                    </div>

                </div>

                <div v-if="suppliers.length === 0" class="col-span-full py-12 text-center text-text-muted font-mono text-xs border border-dashed border-white/10 rounded-2xl glass-panel">
                    Hozircha ta'minotchilar yo'q.
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
            <div class="glass-panel w-full max-w-xl p-8 rounded-2xl border border-neon-yellow/50 shadow-[0_0_30px_rgba(255,255,0,0.15)] relative max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button @click="showModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
                
                <h3 class="text-xl font-display font-bold text-white uppercase tracking-widest mb-6">
                    {{ editMode ? "Ta'minotchini Tahrirlash" : "Yangi Ta'minotchi Qo'shish" }}
                </h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Kompaniya Nomi</label>
                        <input v-model="form.company_name" type="text" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-yellow transition-colors" placeholder="MCHJ / XK nomi">
                    </div>
                    
                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Vakil Ismi (F.I.O) *</label>
                        <input v-model="form.name" type="text" required class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-yellow transition-colors" placeholder="Ism familiyasi">
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Telefon</label>
                        <input v-model="form.phone" type="text" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-yellow transition-colors" placeholder="+998 90 123 45 67">
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">STIR (INN)</label>
                        <input v-model="form.inn" type="text" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-yellow transition-colors" placeholder="9 xonali raqam">
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">MFO</label>
                        <input v-model="form.mfo" type="text" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-yellow transition-colors" placeholder="Bank kodi">
                    </div>

                    <div class="col-span-2">
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Bank Hisob Raqami</label>
                        <input v-model="form.bank_account" type="text" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-yellow transition-colors" placeholder="2020 8000 ...">
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end gap-3">
                    <button @click="showModal = false" class="px-6 py-3 text-sm font-mono text-text-muted hover:text-white transition-colors">{{ $t('cancel_button') }}</button>
                    <button @click="submit" :disabled="isSubmitting" class="px-8 py-3 text-sm font-bold font-mono bg-neon-yellow text-black rounded-xl hover:shadow-[0_0_15px_#FFFF00] transition-all disabled:opacity-50">
                        {{ isSubmitting ? 'Saqlanmoqda...' : 'Saqlash' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
