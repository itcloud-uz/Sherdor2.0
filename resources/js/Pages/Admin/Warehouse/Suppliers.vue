<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    suppliers: Array,
});

const showModal = ref(false);
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

function submit() {
    isSubmitting.value = true;
    router.post(route('admin.warehouse.suppliers.store'), form.value, {
        onSuccess: () => {
            isSubmitting.value = false;
            showModal.value = false;
            form.value = { name: '', company_name: '', phone: '', inn: '', bank_account: '', mfo: '', address: '' };
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
}

const formatMoney = (amount) => {
    return new Intl.NumberFormat('uz-UZ', { style: 'currency', currency: 'UZS' }).format(amount);
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
                        <span class="text-neon-yellow">🏢</span> Ta'minotchilar
                    </h1>
                </div>
                
                <button @click="showModal = true" class="px-6 py-3 bg-neon-yellow/20 border border-neon-yellow text-neon-yellow font-bold font-mono text-sm uppercase tracking-widest rounded-xl hover:bg-neon-yellow hover:text-black transition-all shadow-[0_0_20px_rgba(255,255,0,0.2)]">
                    + Yangi Qo'shish
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="s in suppliers" :key="s.id" class="glass-panel p-6 rounded-2xl border border-white/10 hover:border-neon-yellow/50 transition-all group">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-white font-display">{{ s.company_name || s.name }}</h3>
                            <p class="text-[10px] font-mono text-text-muted uppercase mt-1">Vakil: {{ s.name }}</p>
                        </div>
                        <div class="w-10 h-10 bg-white/5 rounded-full flex items-center justify-center text-xl border border-white/10">
                            🏢
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

                    <div class="pt-4 border-t border-white/10 flex justify-between items-center">
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
                </div>

                <div v-if="suppliers.length === 0" class="col-span-full py-12 text-center text-text-muted font-mono text-xs border border-dashed border-white/10 rounded-2xl glass-panel">
                    Hozircha ta'minotchilar yo'q.
                </div>
            </div>
        </div>

        <!-- Add Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
            <div class="glass-panel w-full max-w-xl p-8 rounded-2xl border border-neon-yellow/50 shadow-[0_0_30px_rgba(255,255,0,0.15)] relative max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button @click="showModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
                
                <h3 class="text-xl font-display font-bold text-white uppercase tracking-widest mb-6">Yangi Ta'minotchi Qo'shish</h3>
                
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
                    <button @click="showModal = false" class="px-6 py-3 text-sm font-mono text-text-muted hover:text-white transition-colors">Bekor Qilish</button>
                    <button @click="submit" :disabled="isSubmitting" class="px-8 py-3 text-sm font-bold font-mono bg-neon-yellow text-black rounded-xl hover:shadow-[0_0_15px_#FFFF00] transition-all disabled:opacity-50">
                        {{ isSubmitting ? 'Saqlanmoqda...' : 'Saqlash' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
