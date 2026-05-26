<script setup>
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
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
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.finance')" class="w-10 h-10 rounded-xl hover:bg-neon-primary/20 border border-gray-200 hover:border-neon-primary flex items-center justify-center text-gray-900 hover:text-neon-primary transition-all group">
                        <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </Link>
                </div>
                
                <div class="flex gap-2 p-1 rounded-xl border border-gray-200">
                    <button 
                        @click="activeTab = 'pending'" 
                        class="px-4 py-2 rounded-lg text-sm font-bold transition-all"
                        :class="activeTab === 'pending' ? 'bg-blue-600 text-white' : 'text-gray-500 hover:text-gray-900'"
                    >Kutilayotgan ({{ pending.length }})</button>
                    <button 
                        @click="activeTab = 'history'" 
                        class="px-4 py-2 rounded-lg text-sm font-bold transition-all"
                        :class="activeTab === 'history' ? ' text-gray-900' : 'text-gray-500 hover:text-gray-900'"
                    >Tarix</button>
                </div>
            </div>

            <!-- PENDING TAB -->
            <div v-if="activeTab === 'pending'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="req in pending" :key="req.id" class="bg-white border border-gray-200 shadow-sm rounded-xl p-5 rounded-2xl border border-blue-200 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-xs font-mono bg-blue-50 text-blue-600 px-2 py-1 rounded uppercase">{{ formatType(req.type) }}</span>
                            <span class="text-[10px] text-gray-500">{{ new Date(req.created_at).toLocaleString() }}</span>
                        </div>
                        
                        <p class="text-sm text-gray-600 mb-2">Operator: <span class="text-gray-900 font-bold">{{ req.user?.name }}</span></p>
                        
                        <div class="bg-gray-500/50 rounded-lg p-3 text-xs font-mono text-gray-500 overflow-auto max-h-40 mb-4 border border-gray-100">
                            <pre v-if="req.type !== 'edit_contract'">{{ JSON.stringify(req.payload, null, 2) }}</pre>
                            <div v-else class="space-y-2">
                                <p class="text-blue-600 font-bold mb-1">Taklif etilayotgan o'zgarishlar:</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[10px]">
                                    <div><span class="text-gray-900">Mijoz Ism:</span> {{ req.payload.client_first_name }}</div>
                                    <div><span class="text-gray-900">Familiya:</span> {{ req.payload.client_last_name }}</div>
                                    <div><span class="text-gray-900">Yangi Narx:</span> {{ req.payload.total_amount }}</div>
                                    <div><span class="text-gray-900">Bosh. To'lov:</span> {{ req.payload.down_payment }}</div>
                                    <div><span class="text-gray-900">Muddat:</span> {{ req.payload.term_months }} oy</div>
                                    <div><span class="text-gray-900">To'lov turi:</span> {{ req.payload.payment_type }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-3">
                        <button @click="reject(req.id)" class="flex-1 bg-red-50 border border-red-200 text-red-600 py-2 rounded-lg hover:bg-red-600 hover:text-gray-900 transition-colors text-sm font-bold">Rad etish</button>
                        <button @click="openApproveModal(req)" class="flex-1 bg-blue-50 border border-blue-200 text-blue-600 py-2 rounded-lg hover:bg-blue-600 hover:text-black transition-colors text-sm font-bold shadow-sm">Tasdiqlash</button>
                    </div>
                </div>
                
                <div v-if="pending.length === 0" class="col-span-full flex flex-col items-center justify-center p-12 bg-white border border-gray-200 shadow-sm rounded-xl rounded-2xl border border-gray-100">
                    <span class="text-6xl mb-4 opacity-50">✅</span>
                    <p class="text-gray-900 font-medium">Yangi so'rovlar yo'q!</p>
                </div>
            </div>

            <!-- HISTORY TAB -->
            <div v-if="activeTab === 'history'" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                            <tr class="border-b border-gray-200 text-xs font-mono text-gray-500 uppercase bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <th class="p-3">{{ $t('date_label') }}</th>
                                <th class="p-3">Operator</th>
                                <th class="p-3">Tur</th>
                                <th class="p-3">Holat</th>
                                <th class="p-3">Admin Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="req in history" :key="req.id" class="border-b border-gray-100 hover: transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <td class="p-3 text-sm text-gray-600">{{ new Date(req.created_at).toLocaleString() }}</td>
                                <td class="p-3 text-sm text-gray-900">{{ req.user?.name }}</td>
                                <td class="p-3 text-sm text-gray-900">{{ formatType(req.type) }}</td>
                                <td class="p-3">
                                    <span class="text-[10px] font-mono px-2 py-1 rounded uppercase" :class="req.status === 'approved' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'">{{ req.status }}</span>
                                </td>
                                <td class="p-3 text-xs text-gray-500">{{ req.reviewer_notes || '-' }}</td>
                            </tr>
                            <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="history.length === 0">
                                <td colspan="5" class="p-6 text-center text-gray-500 text-sm">Tarix bo'sh</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- APPROVAL MODAL (Financial) -->
        <div v-if="showApproveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500/50">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl w-full max-w-md p-6 rounded-2xl border border-blue-200 shadow-sm relative">
                <button @click="closeApproveModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-900">✕</button>
                
                <h3 class="text-lg font-display font-bold text-gray-900 uppercase tracking-widest mb-2">Moliyaviy Tasdiqlash</h3>
                <p class="text-[10px] font-mono text-gray-500 mb-6">Ushbu jarayon to'g'ridan to'g'ri kassaga ta'sir qiladi. Hisob turini tanlang.</p>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Mablag' (Summa)</label>
                        <div class="text-xl font-bold text-blue-600 mb-2 border border-blue-200 bg-blue-50 px-4 py-2 rounded-lg">
                            {{ activeRequest?.type === 'new_payment' || activeRequest?.type === 'finance_expense' || activeRequest?.type === 'hr_action' ? activeRequest?.payload?.amount : activeRequest?.payload?.cost }} so'm
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Qaysi Hisob (Kassa) orqali?</label>
                        <select v-model="approveForm.account_type" class="w-full bg-gray-500/50 border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-blue-500 transition-colors">
                            <option value="main">Asosiy Kassa (Naqd)</option>
                            <option value="bank">Bank Hisobi (Pul o'tkazma)</option>
                            <option value="credit">Kredit Hisobi</option>
                            <option value="debt">Qarz Hisobi</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Izoh (Admin Note)</label>
                        <input v-model="approveForm.notes" type="text" class="w-full bg-gray-500/50 border border-gray-200 rounded-xl px-4 py-2 text-gray-900 text-sm" placeholder="Tasdiqlash bo'yicha izoh">
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="closeApproveModal" class="px-4 py-2 text-sm font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel_button') }}</button>
                    <button @click="submitApprove" class="px-6 py-2 text-sm font-bold font-mono bg-blue-600 text-white rounded hover:shadow-sm transition-all">Tasdiqlash va Yozish</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
