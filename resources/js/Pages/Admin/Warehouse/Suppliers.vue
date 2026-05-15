<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    suppliers: Array,
    inventory_items: Array,
});

const showModal = ref(false);
const showPaymentModal = ref(false);
const editMode = ref(false);
const selectedSupplier = ref(null);

const paymentForm = ref({
    amount: '',
    payment_method: 'bank',
    transaction_date: new Date().toISOString().split('T')[0],
    notes: ''
});

const showOrderModal = ref(false);
const orderForm = ref({
    supplier_id: '',
    order_date: new Date().toISOString().split('T')[0],
    expected_delivery_date: '',
    notes: '',
    items: [
        { item_name: '', unit: 'dona', quantity: 1, unit_price: 0 }
    ]
});

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

const openPaymentModal = (supplier) => {
    selectedSupplier.value = supplier;
    paymentForm.value = {
        amount: '',
        payment_method: 'bank',
        transaction_date: new Date().toISOString().split('T')[0],
        notes: ''
    };
    showPaymentModal.value = true;
};

const submitPayment = () => {
    isSubmitting.value = true;
    router.post(route('admin.warehouse.suppliers.pay', selectedSupplier.value.id), paymentForm.value, {
        onSuccess: () => {
            isSubmitting.value = false;
            showPaymentModal.value = false;
        },
        onError: () => { isSubmitting.value = false; }
    });
};

function openAddOrderModal(supplier) {
    orderForm.value = {
        supplier_id: supplier.id,
        order_date: new Date().toISOString().split('T')[0],
        expected_delivery_date: '',
        notes: '',
        items: [{ item_name: '', unit: 'dona', quantity: 1, unit_price: 0 }]
    };
    showOrderModal.value = true;
}

function addOrderItem() {
    orderForm.value.items.push({ item_name: '', unit: 'dona', quantity: 1, unit_price: 0 });
}

function removeOrderItem(index) {
    if (orderForm.value.items.length > 1) {
        orderForm.value.items.splice(index, 1);
    }
}

function submitOrder() {
    isSubmitting.value = true;
    router.post(route('admin.warehouse.orders.store'), orderForm.value, {
        onSuccess: () => {
            isSubmitting.value = false;
            showOrderModal.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
}

const getLedger = (supplier) => {
    let ledger = [];
    
    if (supplier.orders) {
        supplier.orders.forEach(o => {
            ledger.push({
                type: 'order',
                id: o.id,
                date: o.order_date,
                number: o.order_number,
                amount: o.total_amount,
                status: o.status,
                items: o.items
            });
        });
    }
    
    if (supplier.transactions) {
        supplier.transactions.forEach(t => {
            ledger.push({
                type: 'payment',
                id: t.id,
                date: t.transaction_date,
                number: 'To\'lov: ' + t.payment_method.toUpperCase(),
                amount: t.amount,
                notes: t.notes
            });
        });
    }
    
    // Sort by date ascending (newest at the bottom)
    return ledger.sort((a, b) => new Date(a.date) - new Date(b.date));
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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="s in suppliers" :key="s.id" class="glass-panel p-6 rounded-2xl border border-white/10 hover:border-neon-yellow/50 transition-all group relative">
                    <!-- Edit & Delete Buttons -->
                    <div class="absolute top-4 right-4 flex items-center gap-2">
                        <a :href="route('admin.warehouse.suppliers.ledger', s.id)" target="_blank" class="px-3 py-1 bg-white/5 border border-white/20 text-white hover:bg-white hover:text-black transition-all rounded text-[10px] font-mono uppercase tracking-widest" title="Hisobot (PDF) yuklab olish">
                            PDF Hisobot
                        </a>
                        <button @click="openAddOrderModal(s)" class="px-3 py-1 bg-neon-magenta/20 border border-neon-magenta/30 text-neon-magenta hover:bg-neon-magenta hover:text-black transition-all rounded text-[10px] font-mono uppercase tracking-widest" title="Yangi Buyurtma">
                            + Buyurtma
                        </button>
                        <button @click="openPaymentModal(s)" class="px-3 py-1 bg-neon-green/20 border border-neon-green/30 text-neon-green hover:bg-neon-green hover:text-black transition-all rounded text-[10px] font-mono uppercase tracking-widest" title="To'lov qilish">
                            To'lov
                        </button>
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
                            <span class="text-[10px] font-mono text-text-muted uppercase">Buyurtmalar</span>
                            <span class="text-sm font-bold text-neon-cyan font-mono">{{ s.orders?.length || 0 }} ta</span>
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

                    <!-- Ledger Table -->
                    <div class="pt-4 border-t border-white/10 mt-4">
                        <h4 class="text-[10px] font-mono text-text-muted uppercase mb-3">Umumiy Hisob-kitob (Akt Sверка):</h4>
                        <div class="overflow-x-auto max-h-[400px] custom-scrollbar">
                            <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left text-sm font-mono border border-white/5 rounded-lg">
                                <thead class="sticky top-0 bg-black z-10">
                                    <tr class="bg-white/5 text-text-muted text-[10px] uppercase">
                                        <th class="p-2">Sana</th>
                                        <th class="p-2">Hujjat</th>
                                        <th class="p-2">Kategoriya / Izoh</th>
                                        <th class="p-2 text-right">Qarz (Kirim)</th>
                                        <th class="p-2 text-right">To'lov (Chiqim)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in getLedger(s)" :key="idx" class="border-t border-white/5" :class="item.type === 'payment' ? 'bg-neon-green/5' : ''">
                                        <td class="p-2 text-white">{{ item.date }}</td>
                                        <td class="p-2">
                                            <span v-if="item.type === 'order'" class="text-neon-cyan">{{ item.number }}</span>
                                            <span v-else class="text-neon-green">{{ item.number }}</span>
                                        </td>
                                        <td class="p-2">
                                            <span v-if="item.type === 'order'" class="text-xs text-text-secondary">
                                                Buyurtma ({{ item.status === 'delivered' ? 'Qabul qilingan' : 'Kutilmoqda' }})<br>
                                                <span v-for="i in item.items" :key="i.id" class="text-[9px] block">{{ i.item_name }} ({{ i.quantity }} {{ i.unit }})</span>
                                            </span>
                                            <span v-else class="text-xs text-neon-green/80">
                                                To'lov qaydi<br>
                                                <span class="text-[9px] block text-text-muted">{{ item.notes || 'Izohsiz' }}</span>
                                            </span>
                                        </td>
                                        <td class="p-2 text-right text-neon-red font-bold">
                                            <span v-if="item.type === 'order'">{{ formatMoney(item.amount) }}</span>
                                            <span v-else>-</span>
                                        </td>
                                        <td class="p-2 text-right text-neon-green font-bold">
                                            <span v-if="item.type === 'payment'">{{ formatMoney(item.amount) }}</span>
                                            <span v-else>-</span>
                                        </td>
                                    </tr>
                                    <tr v-if="getLedger(s).length === 0">
                                        <td colspan="5" class="p-4 text-center text-xs text-text-muted">Hozircha hisob-kitoblar yo'q</td>
                                    </tr>
                                </tbody>
                            </table>
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
            <div class="glass-panel w-full max-w-xl p-4 md:p-8 rounded-2xl border border-neon-yellow/50 shadow-[0_0_30px_rgba(255,255,0,0.15)] relative max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button @click="showModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
                
                <h3 class="text-xl font-display font-bold text-white uppercase tracking-widest mb-6">
                    {{ editMode ? "Ta'minotchini Tahrirlash" : "Yangi Ta'minotchi Qo'shish" }}
                </h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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


                </div>
                
                <div class="mt-8 flex justify-end gap-3">
                    <button @click="showModal = false" class="px-6 py-3 text-sm font-mono text-text-muted hover:text-white transition-colors">{{ $t('cancel_button') }}</button>
                    <button @click="submit" :disabled="isSubmitting" class="px-8 py-3 text-sm font-bold font-mono bg-neon-yellow text-black rounded-xl hover:shadow-[0_0_15px_#FFFF00] transition-all disabled:opacity-50">
                        {{ isSubmitting ? 'Saqlanmoqda...' : 'Saqlash' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
            <div class="glass-panel w-full max-w-md p-4 md:p-8 rounded-2xl border border-neon-green/50 shadow-[0_0_30px_rgba(57,255,20,0.15)] relative max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button @click="showPaymentModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
                
                <h3 class="text-xl font-display font-bold text-white uppercase tracking-widest mb-6">
                    To'lov Qilish ({{ selectedSupplier?.company_name || selectedSupplier?.name }})
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">To'lov Summasi (UZS) *</label>
                        <input v-model="paymentForm.amount" type="number" step="0.01" required class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-green transition-colors text-right text-lg">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">To'lov Turi *</label>
                        <select v-model="paymentForm.payment_method" required class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-green transition-colors">
                            <option value="bank">Bank / Pul o'tkazma</option>
                            <option value="card">Plastik karta</option>
                            <option value="cash">Naqd pul</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Sana *</label>
                        <input v-model="paymentForm.transaction_date" type="date" required class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-green transition-colors">
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Izoh</label>
                        <textarea v-model="paymentForm.notes" rows="2" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-green transition-colors"></textarea>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end gap-3">
                    <button @click="showPaymentModal = false" class="px-6 py-3 text-sm font-mono text-text-muted hover:text-white transition-colors">{{ $t('cancel_button') }}</button>
                    <button @click="submitPayment" :disabled="isSubmitting" class="px-8 py-3 text-sm font-bold font-mono bg-neon-green text-black rounded-xl hover:shadow-[0_0_15px_#39FF14] transition-all disabled:opacity-50">
                        {{ isSubmitting ? 'Saqlanmoqda...' : 'To\'lovni Tasdiqlash' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Order Modal -->
        <div v-if="showOrderModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
            <div class="glass-panel w-full max-w-4xl p-4 md:p-8 rounded-2xl border border-neon-magenta/50 shadow-[0_0_30px_rgba(255,0,255,0.15)] relative max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button @click="showOrderModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
                
                <h3 class="text-xl font-display font-bold text-white uppercase tracking-widest mb-6">Yangi Buyurtma (PO)</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Buyurtma Sanasi *</label>
                        <input v-model="orderForm.order_date" type="date" required class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-magenta transition-colors">
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Kutilyotgan Kelish Sanasi</label>
                        <input v-model="orderForm.expected_delivery_date" type="date" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-magenta transition-colors">
                    </div>
                </div>

                <!-- Items -->
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="text-[10px] font-mono text-text-muted uppercase">Mahsulotlar Ro'yxati</h4>
                        <button @click="addOrderItem" class="text-xs font-mono text-neon-cyan hover:text-white">+ Mahsulot Qo'shish</button>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(item, idx) in orderForm.items" :key="idx" class="flex flex-wrap gap-3 items-start bg-white/5 p-3 rounded-xl border border-white/5 relative">
                            <button v-if="orderForm.items.length > 1" @click="removeOrderItem(idx)" class="absolute top-2 right-2 text-neon-red/50 hover:text-neon-red">✕</button>
                            
                            <div class="flex-1 min-w-[150px]">
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Nomi *</label>
                                <input v-model="item.item_name" type="text" list="inventory_list" required class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white text-sm" placeholder="Sement, Armatura...">
                                <datalist id="inventory_list">
                                    <option v-for="inv in inventory_items" :key="inv.id" :value="inv.name"></option>
                                </datalist>
                            </div>
                            
                            <div class="w-24">
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">O'lchov</label>
                                <select v-model="item.unit" class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-white text-sm">
                                    <option value="dona">Dona</option>
                                    <option value="kg">Kg</option>
                                    <option value="tonna">Tonna</option>
                                    <option value="metr">Metr</option>
                                    <option value="m2">M²</option>
                                    <option value="m3">M³</option>
                                    <option value="qop">Qop</option>
                                    <option value="litr">Litr</option>
                                    <option value="komplekt">Komplekt</option>
                                </select>
                            </div>

                            <div class="w-24">
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Soni *</label>
                                <input v-model="item.quantity" type="number" step="0.01" required class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white text-sm text-right">
                            </div>

                            <div class="w-32">
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Narxi (UZS) *</label>
                                <input v-model="item.unit_price" type="number" required class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white text-sm text-right">
                            </div>
                            
                            <div class="w-full sm:w-auto pt-6 text-right sm:pr-6">
                                <span class="text-xs font-bold text-white font-mono">{{ formatMoney((item.quantity * item.unit_price) || 0) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Qo'shimcha Izoh</label>
                    <textarea v-model="orderForm.notes" rows="2" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-magenta transition-colors" placeholder="Ixtiyoriy..."></textarea>
                </div>
                
                <div class="flex justify-between items-center border-t border-white/10 pt-6">
                    <div class="text-text-muted text-xs font-mono uppercase tracking-widest">Umumiy Qiymat: <span class="text-xl font-bold text-neon-green ml-2">{{ formatMoney(orderForm.items.reduce((acc, curr) => acc + (curr.quantity * curr.unit_price), 0)) }}</span></div>
                    
                    <div class="flex gap-3">
                        <button @click="showOrderModal = false" class="px-6 py-3 text-sm font-mono text-text-muted hover:text-white transition-colors">{{ $t('cancel_button') }}</button>
                        <button @click="submitOrder" :disabled="isSubmitting" class="px-8 py-3 text-sm font-bold font-mono bg-neon-magenta text-black rounded-xl hover:shadow-[0_0_15px_#FF00FF] transition-all disabled:opacity-50">
                            {{ isSubmitting ? 'Saqlanmoqda...' : 'Buyurtmani Yaratish' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
