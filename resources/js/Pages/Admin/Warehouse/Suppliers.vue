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
const selectedSupplierView = ref(null);

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
        items: [{ item_name: '', size: '', unit: 'dona', quantity: 1, unit_price: 0 }]
    };
    showOrderModal.value = true;
}

function addOrderItem() {
    orderForm.value.items.push({ item_name: '', size: '', unit: 'dona', quantity: 1, unit_price: 0 });
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
                number: t.payment_method,
                amount: t.amount,
                notes: t.notes
            });
        });
    }
    
    return ledger.sort((a, b) => new Date(a.date) - new Date(b.date));
};

const getGroupedItems = (supplier) => {
    let grouped = {};
    if (supplier.orders) {
        supplier.orders.filter(o => o.status === 'delivered').forEach(o => {
            if (o.items) {
                o.items.forEach(i => {
                    let name = (i.item_name || '').toUpperCase();
                    if (!grouped[name]) {
                        grouped[name] = [];
                    }
                    grouped[name].push({
                        date: o.order_date,
                        size: i.size,
                        unit: i.unit,
                        quantity: i.quantity,
                        unit_price: i.unit_price,
                        total_price: i.total_price || (i.quantity * i.unit_price)
                    });
                });
            }
        });
    }
    return grouped;
};

const getPayments = (supplier) => {
    return supplier.transactions ? supplier.transactions.sort((a, b) => new Date(a.transaction_date) - new Date(b.transaction_date)) : [];
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
                    <Link :href="route('admin.warehouse.index')" class="w-10 h-10 flex items-center justify-center rounded-xl border border-gray-200 hover:bg-gray-100 text-gray-900 transition-all">
                        ←
                    </Link>
                    <h1 class="text-3xl font-display font-bold text-gray-900 tracking-widest uppercase shadow-neon-yellow/50 flex items-center gap-3 hidden">
                        <span class="text-amber-600">🏢</span>{{ $t('suppliers') }}</h1>
                </div>
                
                <button @click="openAddModal" class="px-6 py-3 bg-amber-50 border border-amber-500 text-amber-600 font-bold font-mono text-sm uppercase tracking-widest rounded-xl hover:bg-amber-500 hover:text-black transition-all shadow-sm">
                    + Yangi Qo'shish
                </button>
            </div>

            <!-- Hidden File Input for Document Upload -->
            <input type="file" ref="uploadInput" @change="handleFileUpload" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />

            <!-- Master View: Table -->
            <div v-if="!selectedSupplierView" class="bg-white border border-gray-200 shadow-sm rounded-xl p-1 rounded-2xl border border-gray-200 relative overflow-hidden animate-[fadeIn_0.3s_ease-out]">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm font-mono border-collapse">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                            <tr class="/50 text-gray-500 text-[10px] uppercase tracking-widest border-b border-gray-200 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <th class="p-4 rounded-tl-xl">Kompaniya / Vakil</th>
                                <th class="p-4">Telefon</th>
                                <th class="p-4">Buyurtmalar</th>
                                <th class="p-4 text-right">Balans</th>
                                <th class="p-4 text-center rounded-tr-xl">Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in suppliers" :key="s.id" @click="selectedSupplierView = s" class="border-b border-gray-100 hover: transition-all cursor-pointer group bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <td class="p-4">
                                    <div class="font-bold text-gray-900 font-display group-hover:text-amber-600 transition-colors">{{ s.company_name || s.name }}</div>
                                    <div class="text-[10px] text-gray-500 uppercase mt-1">{{ s.name }}</div>
                                </td>
                                <td class="p-4 text-gray-900">{{ s.phone || '-' }}</td>
                                <td class="p-4 text-blue-600">{{ s.orders?.length || 0 }} ta</td>
                                <td class="p-4 text-right">
                                    <span class="block font-bold" :class="s.balance > 0 ? 'text-red-600' : (s.balance < 0 ? 'text-emerald-600' : 'text-gray-900')">
                                        {{ formatMoney(Math.abs(s.balance)) }}
                                    </span>
                                </td>
                                <td class="p-4 text-center" @click.stop>
                                    <div class="flex items-center justify-center gap-2">
                                        <button @click="openEditModal(s)" class="w-8 h-8 rounded-lg border border-gray-200 hover:bg-white/20 text-gray-900 flex items-center justify-center transition-all" title="Tahrirlash">
                                            ✏️
                                        </button>
                                        <button @click="deleteSupplier(s.id)" class="w-8 h-8 rounded-lg bg-red-500/10 border border-red-200 text-red-400 hover:bg-red-500 hover:text-gray-900 flex items-center justify-center transition-all" title="O'chirish">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="suppliers.length === 0">
                                <td colspan="5" class="p-12 text-center text-gray-500 font-mono text-xs border border-dashed border-gray-200 rounded-xl">
                                    Hozircha ta'minotchilar yo'q.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Detail View -->
            <div v-else class="animate-[fadeIn_0.3s_ease-out]">
                <button @click="selectedSupplierView = null" class="mb-6 flex items-center gap-2 text-gray-500 hover:text-amber-600 transition-colors font-mono text-xs uppercase tracking-widest">
                    <span>←</span> Ortga qaytish
                </button>
                
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-amber-200 relative shadow-sm">
                    <!-- Edit & Delete Buttons -->
                    <div class="absolute top-4 right-4 flex flex-wrap items-center justify-end gap-2">
                        <a :href="route('admin.warehouse.suppliers.ledger', selectedSupplierView.id)" target="_blank" class="px-3 py-1 border border-gray-300 text-gray-900 hover:bg-white hover:text-black transition-all rounded text-[10px] font-mono uppercase tracking-widest" title="Hisobot (PDF) yuklab olish">
                            PDF
                        </a>
                        <a :href="route('admin.warehouse.suppliers.ledger.excel', selectedSupplierView.id)" target="_blank" class="px-3 py-1 border border-gray-300 text-gray-900 hover:bg-white hover:text-black transition-all rounded text-[10px] font-mono uppercase tracking-widest" title="Hisobot (Excel) yuklab olish">
                            EXCEL
                        </a>
                        <button @click="openAddOrderModal(selectedSupplierView)" class="px-3 py-1 bg-indigo-50 border border-neon-magenta/30 text-indigo-600 hover:bg-indigo-600 hover:text-black transition-all rounded text-[10px] font-mono uppercase tracking-widest" title="Yangi Buyurtma">
                            + Buyurtma
                        </button>
                        <button @click="openPaymentModal(selectedSupplierView)" class="px-3 py-1 bg-emerald-50 border border-emerald-200 text-emerald-600 hover:bg-emerald-600 hover:text-black transition-all rounded text-[10px] font-mono uppercase tracking-widest" title="To'lov qilish">
                            To'lov
                        </button>
                    </div>

                    <div class="flex justify-between items-start mb-6 pr-20 mt-8 md:mt-0">
                        <div>
                            <h3 class="text-3xl font-bold text-gray-900 font-display">{{ selectedSupplierView.company_name || selectedSupplierView.name }}</h3>
                            <p class="text-[12px] font-mono text-amber-600 uppercase mt-1 tracking-widest">Vakil: {{ selectedSupplierView.name }}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-gray-500/50 p-4 rounded-xl border border-gray-100 flex flex-col justify-center">
                            <span class="text-[10px] font-mono text-gray-500 uppercase mb-1">Telefon</span>
                            <span class="text-sm font-bold text-gray-900 font-mono">{{ selectedSupplierView.phone || '-' }}</span>
                        </div>
                        <div class="bg-gray-500/50 p-4 rounded-xl border border-gray-100 flex flex-col justify-center">
                            <span class="text-[10px] font-mono text-gray-500 uppercase mb-1">Buyurtmalar</span>
                            <span class="text-sm font-bold text-blue-600 font-mono">{{ selectedSupplierView.orders?.length || 0 }} ta</span>
                        </div>
                        <div class="bg-gray-500/50 p-4 rounded-xl border border-gray-100 flex flex-col justify-center">
                            <span class="text-[10px] font-mono text-gray-500 uppercase mb-1">Joriy Balans</span>
                            <div class="text-left">
                                <span class="block text-xl font-bold font-mono" :class="selectedSupplierView.balance > 0 ? 'text-red-600' : (selectedSupplierView.balance < 0 ? 'text-emerald-600' : 'text-gray-900')">
                                    {{ formatMoney(Math.abs(selectedSupplierView.balance)) }}
                                </span>
                                <span class="text-[10px] font-mono uppercase tracking-widest" :class="selectedSupplierView.balance > 0 ? 'text-red-600/70' : (selectedSupplierView.balance < 0 ? 'text-emerald-600/70' : 'text-gray-500')">
                                    {{ selectedSupplierView.balance > 0 ? 'Qarzimiz bor' : (selectedSupplierView.balance < 0 ? 'Haqimiz bor' : 'Nol') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Document Attachment Section -->
                    <div class="pt-4 border-t border-gray-200">
                        <div v-if="!selectedSupplierView.document_path" class="flex items-center justify-start max-w-sm">
                            <button @click="triggerUpload(selectedSupplierView.id)" class="w-full py-3 border border-dashed border-gray-300 text-gray-500 hover:text-gray-900 hover:border-gray-1000 text-[10px] font-mono uppercase tracking-widest rounded transition-all">
                                📎 Hujjat Biriktirish
                            </button>
                        </div>
                        <div v-else class="flex flex-col gap-2 max-w-sm">
                            <div class="flex justify-between items-center bg-blue-50 border border-blue-200 p-3 rounded-xl">
                                <span class="text-[10px] font-mono text-blue-600 uppercase flex items-center gap-2">
                                    📄 Hujjat biriktirilgan
                                </span>
                                <div class="flex items-center gap-2">
                                    <a :href="'/storage/' + selectedSupplierView.document_path" target="_blank" class="w-8 h-8 rounded bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-black transition-all" title="Yuklab olish">
                                        ⬇️
                                    </a>
                                    <button @click="sendToTelegram(selectedSupplierView.id)" class="w-8 h-8 rounded bg-blue-500/20 text-blue-400 flex items-center justify-center hover:bg-blue-500 hover:text-gray-900 transition-all" title="Telegramga yuborish">
                                        ✈️
                                    </button>
                                </div>
                            </div>
                            <button @click="triggerUpload(selectedSupplierView.id)" class="text-left text-[10px] font-mono text-gray-500 hover:text-gray-900 transition-colors ml-1">
                                Yangilash
                            </button>
                        </div>
                    </div>

                    <!-- Grouped Ledger Table -->
                    <div class="pt-8 border-t border-gray-200 mt-6">
                        <h4 class="text-xs font-mono text-gray-900 uppercase tracking-widest mb-4">Umumiy Hisob-kitob:</h4>
                        
                        <!-- Products Tables by Category -->
                        <div class="grid grid-cols-1 gap-6 mb-8">
                            <div v-for="(items, category) in getGroupedItems(selectedSupplierView)" :key="category" class="bg-gray-500/50 rounded-xl border border-gray-100 overflow-hidden">
                                <h5 class="text-center bg-gray-500/50 py-2 border-b border-gray-200 text-[10px] font-bold uppercase tracking-widest text-blue-600">{{ category }}</h5>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-[10px] font-mono border-collapse">
                                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                            <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                                <th class="p-2 border-b border-r border-gray-200 text-center">№</th>
                                                <th class="p-2 border-b border-r border-gray-200">Sana</th>
                                                <th class="p-2 border-b border-r border-gray-200">O'lcham</th>
                                                <th class="p-2 border-b border-r border-gray-200">O'lchov</th>
                                                <th class="p-2 border-b border-r border-gray-200 text-right">Miqdor</th>
                                                <th class="p-2 border-b border-r border-gray-200 text-right">Narxi</th>
                                                <th class="p-2 border-b border-gray-200 text-right">Jami</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, idx) in items" :key="idx" class="border-b border-gray-100 hover: bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                                <td class="p-2 border-r border-gray-100 text-center text-gray-600">{{ idx + 1 }}</td>
                                                <td class="p-2 border-r border-gray-100 text-gray-900">{{ item.date }}</td>
                                                <td class="p-2 border-r border-gray-100 text-amber-600">{{ item.size || '-' }}</td>
                                                <td class="p-2 border-r border-gray-100 text-gray-500">{{ item.unit }}</td>
                                                <td class="p-2 border-r border-gray-100 text-right font-bold text-gray-900">{{ item.quantity }}</td>
                                                <td class="p-2 border-r border-gray-100 text-right">{{ formatMoney(item.unit_price) }}</td>
                                                <td class="p-2 text-right font-bold text-blue-600">{{ formatMoney(item.total_price) }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="/50 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                                <td colspan="6" class="p-2 text-right font-bold border-r border-gray-200">Jami ({{ category }}):</td>
                                                <td class="p-2 text-right font-bold text-red-600">{{ formatMoney(items.reduce((acc, curr) => acc + curr.total_price, 0)) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div v-if="Object.keys(getGroupedItems(selectedSupplierView)).length === 0" class="col-span-full p-8 text-center text-xs text-gray-500 border border-dashed border-gray-200 rounded-xl">
                                Hozircha mahsulotlar qabul qilinmagan.
                            </div>
                        </div>

                        <!-- Payments Table -->
                        <h4 class="text-xs font-mono text-gray-900 uppercase tracking-widest mb-4">To'lovlar Tarixi (Chiqim):</h4>
                        <div class="overflow-x-auto bg-gray-500/50 rounded-xl border border-gray-100">
                            <table class="w-full text-left text-sm font-mono border-collapse">
                                <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                    <tr class="text-gray-500 text-[10px] uppercase border-b border-gray-200 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                        <th class="p-3">Sana</th>
                                        <th class="p-3">To'lov Turi</th>
                                        <th class="p-3">Izoh</th>
                                        <th class="p-3 text-right">Summa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in getPayments(selectedSupplierView)" :key="p.id" class="border-b border-gray-100 hover: transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                        <td class="p-3 text-gray-900">{{ p.transaction_date }}</td>
                                        <td class="p-3 text-emerald-600 uppercase text-xs">{{ p.payment_method }}</td>
                                        <td class="p-3 text-gray-600 text-xs">{{ p.notes || '-' }}</td>
                                        <td class="p-3 text-right text-emerald-600 font-bold">{{ formatMoney(p.amount) }}</td>
                                    </tr>
                                    <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="getPayments(selectedSupplierView).length === 0">
                                        <td colspan="4" class="p-6 text-center text-xs text-gray-500">To'lovlar topilmadi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500/50">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl w-full max-w-xl p-4 md:p-8 rounded-2xl border border-amber-200 shadow-sm relative max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button @click="showModal = false" class="absolute top-4 right-4 text-gray-500 hover:text-gray-900">✕</button>
                
                <h3 class="text-xl font-display font-bold text-gray-900 uppercase tracking-widest mb-6">
                    {{ editMode ? "Ta'minotchini Tahrirlash" : "Yangi Ta'minotchi Qo'shish" }}
                </h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Kompaniya Nomi</label>
                        <input v-model="form.company_name" type="text" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-amber-500 transition-colors" placeholder="MCHJ / XK nomi">
                    </div>
                    
                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Vakil Ismi (F.I.O) *</label>
                        <input v-model="form.name" type="text" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-amber-500 transition-colors" placeholder="Ism familiyasi">
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Telefon</label>
                        <input v-model="form.phone" type="text" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-amber-500 transition-colors" placeholder="+998 90 123 45 67">
                    </div>


                </div>
                
                <div class="mt-8 flex justify-end gap-3">
                    <button @click="showModal = false" class="px-6 py-3 text-sm font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel_button') }}</button>
                    <button @click="submit" :disabled="isSubmitting" class="px-8 py-3 text-sm font-bold font-mono bg-amber-500 text-white rounded-xl hover:shadow-sm transition-all disabled:opacity-50">
                        {{ isSubmitting ? 'Saqlanmoqda...' : 'Saqlash' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500/50">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl w-full max-w-md p-4 md:p-8 rounded-2xl border border-emerald-200 shadow-sm relative max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button @click="showPaymentModal = false" class="absolute top-4 right-4 text-gray-500 hover:text-gray-900">✕</button>
                
                <h3 class="text-xl font-display font-bold text-gray-900 uppercase tracking-widest mb-6">
                    To'lov Qilish ({{ selectedSupplier?.company_name || selectedSupplier?.name }})
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">To'lov Summasi (UZS) *</label>
                        <input v-model="paymentForm.amount" type="number" step="0.01" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-emerald-500 transition-colors text-right text-lg">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">To'lov Turi *</label>
                        <select v-model="paymentForm.payment_method" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-emerald-500 transition-colors">
                            <option value="bank">Bank / Pul o'tkazma</option>
                            <option value="card">Plastik karta</option>
                            <option value="cash">Naqd pul</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Sana *</label>
                        <input v-model="paymentForm.transaction_date" type="date" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-emerald-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Izoh</label>
                        <textarea v-model="paymentForm.notes" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-emerald-500 transition-colors"></textarea>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end gap-3">
                    <button @click="showPaymentModal = false" class="px-6 py-3 text-sm font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel_button') }}</button>
                    <button @click="submitPayment" :disabled="isSubmitting" class="px-8 py-3 text-sm font-bold font-mono bg-emerald-600 text-white rounded-xl hover:shadow-sm transition-all disabled:opacity-50">
                        {{ isSubmitting ? 'Saqlanmoqda...' : 'To\'lovni Tasdiqlash' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Order Modal -->
        <div v-if="showOrderModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500/50">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl w-full max-w-4xl p-4 md:p-8 rounded-2xl border border-neon-magenta/50 shadow-sm relative max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button @click="showOrderModal = false" class="absolute top-4 right-4 text-gray-500 hover:text-gray-900">✕</button>
                
                <h3 class="text-xl font-display font-bold text-gray-900 uppercase tracking-widest mb-6">Yangi Buyurtma (PO)</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Buyurtma Sanasi *</label>
                        <input v-model="orderForm.order_date" type="date" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-neon-magenta transition-colors">
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Kutilyotgan Kelish Sanasi</label>
                        <input v-model="orderForm.expected_delivery_date" type="date" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-neon-magenta transition-colors">
                    </div>
                </div>

                <!-- Items -->
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="text-[10px] font-mono text-gray-500 uppercase">Mahsulotlar Ro'yxati</h4>
                        <button @click="addOrderItem" class="text-xs font-mono text-blue-600 hover:text-gray-900">+ Mahsulot Qo'shish</button>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(item, idx) in orderForm.items" :key="idx" class="flex flex-wrap gap-3 items-start p-3 rounded-xl border border-gray-100 relative">
                            <button v-if="orderForm.items.length > 1" @click="removeOrderItem(idx)" class="absolute top-2 right-2 text-red-600/50 hover:text-red-600">✕</button>
                            
                            <div class="flex-1 min-w-[150px]">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Nomi *</label>
                                <input v-model="item.item_name" type="text" list="inventory_list" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm" placeholder="Sement, Armatura...">
                                <datalist id="inventory_list">
                                    <option v-for="inv in inventory_items" :key="inv.id" :value="inv.name"></option>
                                </datalist>
                            </div>
                            
                            <div class="w-24">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">O'lcham</label>
                                <input v-model="item.size" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm" placeholder="d20, 10x10...">
                            </div>
                            
                            <div class="w-24">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">O'lchov</label>
                                <select v-model="item.unit" class="w-full border border-gray-200 rounded-lg px-2 py-2 text-gray-900 text-sm">
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
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Soni *</label>
                                <input v-model="item.quantity" type="number" step="0.01" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm text-right">
                            </div>

                            <div class="w-32">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Narxi (UZS) *</label>
                                <input v-model="item.unit_price" type="number" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm text-right">
                            </div>
                            
                            <div class="w-full sm:w-auto pt-6 text-right sm:pr-6">
                                <span class="text-xs font-bold text-gray-900 font-mono">{{ formatMoney((item.quantity * item.unit_price) || 0) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Qo'shimcha Izoh</label>
                    <textarea v-model="orderForm.notes" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-neon-magenta transition-colors" placeholder="Ixtiyoriy..."></textarea>
                </div>
                
                <div class="flex justify-between items-center border-t border-gray-200 pt-6">
                    <div class="text-gray-500 text-xs font-mono uppercase tracking-widest">Umumiy Qiymat: <span class="text-xl font-bold text-emerald-600 ml-2">{{ formatMoney(orderForm.items.reduce((acc, curr) => acc + (curr.quantity * curr.unit_price), 0)) }}</span></div>
                    
                    <div class="flex gap-3">
                        <button @click="showOrderModal = false" class="px-6 py-3 text-sm font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel_button') }}</button>
                        <button @click="submitOrder" :disabled="isSubmitting" class="px-8 py-3 text-sm font-bold font-mono bg-indigo-600 text-black rounded-xl hover:shadow-sm transition-all disabled:opacity-50">
                            {{ isSubmitting ? 'Saqlanmoqda...' : 'Buyurtmani Yaratish' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
