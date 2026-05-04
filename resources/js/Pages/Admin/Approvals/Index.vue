<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    pending: Array,
    history: Array,
});

const activeTab = ref('pending');

function formatType(type) {
    const types = {
        'new_client': 'Yangi Mijoz',
        'book_unit': 'Uy Band Qilish',
        'new_contract': 'Yangi Shartnoma',
        'new_payment': 'Kassa To\'lovi',
        'hr_worker': 'Yangi Ishchi (HR)',
        'hr_machinery': 'Texnika (HR)',
        'hr_attendance': 'Davomat (HR)',
        'hr_action': 'Ishchi Harakati (HR)',
        'edit_contract': 'Shartnomani O\'zgartirish',
        'warehouse_kirim': 'Ombor Kirim',
        'warehouse_chiqim': 'Ombor Chiqim',
        'finance_expense': 'Kassa Chiqimi (Xarajat)'
    };
    return types[type] || type;
}

const showApproveModal = ref(false);
const activeRequest = ref(null);
const approveForm = ref({
    account_type: 'main',
    notes: 'Approved by admin'
});

function openApproveModal(req) {
    activeRequest.value = req;
    approveForm.value.account_type = 'main';
    approveForm.value.notes = 'Approved by admin';
    
    // Check if it requires financial interaction
    const requiresFinance = req.type === 'new_payment' || 
                            req.type === 'finance_expense' ||
                            (req.type === 'hr_action' && (req.payload?.action_type === 'advance' || req.payload?.action_type === 'bonus')) ||
                            (req.type === 'warehouse_kirim' && req.payload?.cost > 0);
                            
    if (requiresFinance) {
        showApproveModal.value = true;
    } else {
        submitApprove(); // Bypass modal if no finance needed
    }
}

function closeApproveModal() {
    showApproveModal.value = false;
    activeRequest.value = null;
}

function submitApprove() {
    if (!activeRequest.value) return;
    if (confirm('Siz ushbu so\'rovni tasdiqlamoqchimisiz? Ma\'lumotlar bazaga yoziladi!')) {
        router.post(route('admin.approvals.approve', activeRequest.value.id), approveForm.value, { 
            preserveScroll: true,
            onSuccess: () => closeApproveModal()
        });
    }
}

function reject(id) {
    const notes = prompt('Rad etish sababini kiriting (ixtiyoriy):');
    if (notes !== null) {
        router.post(route('admin.approvals.reject', id), { notes }, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Tasdiqlash Markazi" />
    <AdminLayout>
        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-display font-bold text-white uppercase tracking-wider">Tasdiqlash Markazi</h1>
                
                <div class="flex gap-2 p-1 bg-white/5 rounded-xl border border-white/10">
                    <button 
                        @click="activeTab = 'pending'" 
                        class="px-4 py-2 rounded-lg text-sm font-bold transition-all"
                        :class="activeTab === 'pending' ? 'bg-neon-cyan text-black' : 'text-text-muted hover:text-white'"
                    >Kutilayotgan ({{ pending.length }})</button>
                    <button 
                        @click="activeTab = 'history'" 
                        class="px-4 py-2 rounded-lg text-sm font-bold transition-all"
                        :class="activeTab === 'history' ? 'bg-white/20 text-white' : 'text-text-muted hover:text-white'"
                    >Tarix</button>
                </div>
            </div>

            <!-- PENDING TAB -->
            <div v-if="activeTab === 'pending'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="req in pending" :key="req.id" class="glass-card p-5 rounded-2xl border border-neon-cyan/30 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-xs font-mono bg-neon-cyan/20 text-neon-cyan px-2 py-1 rounded uppercase">{{ formatType(req.type) }}</span>
                            <span class="text-[10px] text-text-muted">{{ new Date(req.created_at).toLocaleString() }}</span>
                        </div>
                        
                        <p class="text-sm text-text-secondary mb-2">Operator: <span class="text-white font-bold">{{ req.user?.name }}</span></p>
                        
                        <div class="bg-black/50 rounded-lg p-3 text-xs font-mono text-text-muted overflow-auto max-h-40 mb-4 border border-white/5">
                            <pre v-if="req.type !== 'edit_contract'">{{ JSON.stringify(req.payload, null, 2) }}</pre>
                            <div v-else class="space-y-2">
                                <p class="text-neon-cyan font-bold mb-1">Taklif etilayotgan o'zgarishlar:</p>
                                <div class="grid grid-cols-2 gap-2 text-[10px]">
                                    <div><span class="text-white">Mijoz Ism:</span> {{ req.payload.client_first_name }}</div>
                                    <div><span class="text-white">Familiya:</span> {{ req.payload.client_last_name }}</div>
                                    <div><span class="text-white">Yangi Narx:</span> {{ req.payload.total_amount }}</div>
                                    <div><span class="text-white">Bosh. To'lov:</span> {{ req.payload.down_payment }}</div>
                                    <div><span class="text-white">Muddat:</span> {{ req.payload.term_months }} oy</div>
                                    <div><span class="text-white">To'lov turi:</span> {{ req.payload.payment_type }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-3">
                        <button @click="reject(req.id)" class="flex-1 bg-neon-red/10 border border-neon-red/30 text-neon-red py-2 rounded-lg hover:bg-neon-red hover:text-white transition-colors text-sm font-bold">Rad etish</button>
                        <button @click="openApproveModal(req)" class="flex-1 bg-neon-cyan/20 border border-neon-cyan/50 text-neon-cyan py-2 rounded-lg hover:bg-neon-cyan hover:text-black transition-colors text-sm font-bold shadow-[0_0_15px_rgba(0,240,255,0.15)]">Tasdiqlash</button>
                    </div>
                </div>
                
                <div v-if="pending.length === 0" class="col-span-full flex flex-col items-center justify-center p-12 glass-panel rounded-2xl border border-white/5">
                    <span class="text-6xl mb-4 opacity-50">✅</span>
                    <p class="text-white font-medium">Yangi so'rovlar yo'q!</p>
                </div>
            </div>

            <!-- HISTORY TAB -->
            <div v-if="activeTab === 'history'" class="glass-panel p-6 rounded-2xl border border-white/5">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/10 text-xs font-mono text-text-muted uppercase">
                                <th class="p-3">Sana</th>
                                <th class="p-3">Operator</th>
                                <th class="p-3">Tur</th>
                                <th class="p-3">Holat</th>
                                <th class="p-3">Admin Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="req in history" :key="req.id" class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="p-3 text-sm text-text-secondary">{{ new Date(req.created_at).toLocaleString() }}</td>
                                <td class="p-3 text-sm text-white">{{ req.user?.name }}</td>
                                <td class="p-3 text-sm text-white">{{ formatType(req.type) }}</td>
                                <td class="p-3">
                                    <span class="text-[10px] font-mono px-2 py-1 rounded uppercase" :class="req.status === 'approved' ? 'bg-neon-green/20 text-neon-green' : 'bg-neon-red/20 text-neon-red'">{{ req.status }}</span>
                                </td>
                                <td class="p-3 text-xs text-text-muted">{{ req.reviewer_notes || '-' }}</td>
                            </tr>
                            <tr v-if="history.length === 0">
                                <td colspan="5" class="p-6 text-center text-text-muted text-sm">Tarix bo'sh</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- APPROVAL MODAL (Financial) -->
        <div v-if="showApproveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
            <div class="glass-panel w-full max-w-md p-6 rounded-2xl border border-neon-cyan/50 shadow-[0_0_30px_rgba(0,240,255,0.15)] relative">
                <button @click="closeApproveModal" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
                
                <h3 class="text-lg font-display font-bold text-white uppercase tracking-widest mb-2">Moliyaviy Tasdiqlash</h3>
                <p class="text-[10px] font-mono text-text-muted mb-6">Ushbu jarayon to'g'ridan to'g'ri kassaga ta'sir qiladi. Hisob turini tanlang.</p>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Mablag' (Summa)</label>
                        <div class="text-xl font-bold text-neon-cyan mb-2 border border-neon-cyan/20 bg-neon-cyan/5 px-4 py-2 rounded-lg">
                            {{ activeRequest?.type === 'new_payment' || activeRequest?.type === 'finance_expense' || activeRequest?.type === 'hr_action' ? activeRequest?.payload?.amount : activeRequest?.payload?.cost }} so'm
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Qaysi Hisob (Kassa) orqali?</label>
                        <select v-model="approveForm.account_type" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-cyan transition-colors">
                            <option value="main">Asosiy Kassa (Naqd)</option>
                            <option value="bank">Bank Hisobi (Pul o'tkazma)</option>
                            <option value="credit">Kredit Hisobi</option>
                            <option value="debt">Qarz Hisobi</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Izoh (Admin Note)</label>
                        <input v-model="approveForm.notes" type="text" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-2 text-white text-sm" placeholder="Tasdiqlash bo'yicha izoh">
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="closeApproveModal" class="px-4 py-2 text-sm font-mono text-text-muted hover:text-white transition-colors">Bekor Qilish</button>
                    <button @click="submitApprove" class="px-6 py-2 text-sm font-bold font-mono bg-neon-cyan text-black rounded hover:shadow-[0_0_15px_#00F0FF] transition-all">Tasdiqlash va Yozish</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
