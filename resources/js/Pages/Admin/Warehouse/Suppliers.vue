<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    suppliers: Array,
    stats: Object,
    inventory_items: Array,
});

const showModal = ref(false);
const showPaymentModal = ref(false);
const editMode = ref(false);
const selectedSupplier = ref(null);
const selectedSupplierView = ref(null);

const searchQuery = ref('');
const startDate = ref('');
const endDate = ref('');
const selectedTab = ref('Barcha');
const tabs = ['Barcha', 'Faol', 'Nofaol', 'Kutilmoqda', 'Bloklangan'];

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
    category: 'taminotchi',
    status: 'faol'
});

const isSubmitting = ref(false);

const openAddModal = () => {
    editMode.value = false;
    selectedSupplier.value = null;
    form.value = {
        name: '',
        company_name: '',
        phone: '',
        inn: '',
        bank_account: '',
        mfo: '',
        address: '',
        category: 'taminotchi',
        status: 'faol'
    };
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
        address: supplier.address,
        category: supplier.category || 'taminotchi',
        status: supplier.status || 'faol'
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
                // Update selectedSupplierView if open
                if (selectedSupplierView.value && selectedSupplierView.value.id === selectedSupplier.value.id) {
                    selectedSupplierView.value = { ...selectedSupplierView.value, ...form.value };
                }
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
        router.delete(route('admin.warehouse.suppliers.destroy', id), {
            onSuccess: () => {
                if (selectedSupplierView.value && selectedSupplierView.value.id === id) {
                    selectedSupplierView.value = null;
                }
            }
        });
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
            // Hot reload local balance view if detail is open
            if (selectedSupplierView.value && selectedSupplierView.value.id === selectedSupplier.value.id) {
                selectedSupplierView.value.balance = Number(selectedSupplierView.value.balance || 0) - Number(paymentForm.value.amount);
                // Also push transaction
                if (!selectedSupplierView.value.transactions) selectedSupplierView.value.transactions = [];
                selectedSupplierView.value.transactions.push({
                    id: Date.now(),
                    transaction_date: paymentForm.value.transaction_date,
                    payment_method: paymentForm.value.payment_method,
                    amount: paymentForm.value.amount,
                    notes: paymentForm.value.notes
                });
            }
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

const getSupplierStats = (s) => {
    const deliveredOrders = (s.orders || []).filter(o => o.status === 'delivered');
    const totalAmount = deliveredOrders.reduce((sum, o) => sum + Number(o.total_amount || 0), 0);
    const totalItems = deliveredOrders.reduce((sum, o) => {
        const itemQty = (o.items || []).reduce((iq, i) => iq + Number(i.quantity || 0), 0);
        return sum + itemQty;
    }, 0);
    return {
        xaridlarSoni: s.orders?.length || 0,
        umumiySumma: totalAmount,
        tovararSoni: totalItems,
        qarzdorlik: s.balance > 0 ? s.balance : 0,
        kredit: s.balance < 0 ? Math.abs(s.balance) : 0,
        oxirgiSana: s.orders?.length > 0 ? s.orders[s.orders.length - 1].order_date : '-'
    };
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
    return supplier.transactions ? [...supplier.transactions].sort((a, b) => new Date(a.transaction_date) - new Date(b.transaction_date)) : [];
};

const formatMoney = (amount) => {
    return new Intl.NumberFormat('uz-UZ', { style: 'currency', currency: 'UZS', maximumFractionDigits: 0 }).format(amount || 0).replace('UZS', "so'm");
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

const filteredSuppliers = computed(() => {
    return props.suppliers.filter(s => {
        // Status filter
        if (selectedTab.value !== 'Barcha') {
            const statusMap = {
                'Faol': 'faol',
                'Nofaol': 'nofaol',
                'Kutilmoqda': 'kutilmoqda',
                'Bloklangan': 'bloklangan'
            };
            const targetStatus = statusMap[selectedTab.value];
            if ((s.status || 'faol').toLowerCase() !== targetStatus) {
                return false;
            }
        }
        
        // Search filter
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase();
            const nameMatch = (s.name || '').toLowerCase().includes(query);
            const companyMatch = (s.company_name || '').toLowerCase().includes(query);
            const categoryMatch = (s.category || '').toLowerCase().includes(query);
            const phoneMatch = (s.phone || '').toLowerCase().includes(query);
            if (!nameMatch && !companyMatch && !categoryMatch && !phoneMatch) {
                return false;
            }
        }

        // Date filter
        if (startDate.value) {
            const sDate = new Date(startDate.value);
            const regDate = new Date(s.created_at || new Date());
            if (regDate < sDate) return false;
        }
        if (endDate.value) {
            const eDate = new Date(endDate.value);
            eDate.setHours(23, 59, 59, 999);
            const regDate = new Date(s.created_at || new Date());
            if (regDate > eDate) return false;
        }

        return true;
    });
});
</script>

<template>
    <Head title="Ta'minotchilar" />
    <AdminLayout>
        <div class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <!-- Header section matching Image 1 layout -->
            <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-4 mb-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.warehouse.index')" class="w-12 h-12 flex items-center justify-center rounded-full bg-white border border-gray-100 hover:bg-gray-50 text-gray-600 transition-all shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 font-sans tracking-tight">Ta'minotchilar</h1>
                        <p class="text-xs text-gray-500 mt-0.5">Kassa operatsiyalari va moliyaviy hisobotlar</p>
                    </div>
                </div>
                
                <!-- Filters & Search Matching Image 1 -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative">
                        <input type="text" v-model="searchQuery" placeholder="Qidirish" class="w-64 bg-white border border-gray-200 rounded-full pl-10 pr-4 py-2 text-sm focus:outline-none focus:border-amber-500 transition-colors shadow-sm" />
                        <span class="absolute left-3.5 top-2.5 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                            </svg>
                        </span>
                    </div>

                    <div class="flex items-center bg-white border border-gray-200 rounded-full px-3 py-2 text-sm gap-2 shadow-sm">
                        <input type="date" v-model="startDate" class="border-0 p-0 focus:ring-0 text-gray-500 w-28 text-xs font-sans bg-transparent" placeholder="Sanadan" />
                        <span class="text-gray-400">→</span>
                        <input type="date" v-model="endDate" class="border-0 p-0 focus:ring-0 text-gray-500 w-28 text-xs font-sans bg-transparent" placeholder="Sanagacha" />
                        <span class="text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                        </span>
                    </div>

                    <a :href="route('admin.warehouse.suppliers.export.excel')" class="px-4 py-2 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-700 text-sm font-semibold rounded-full flex items-center gap-1.5 transition-all shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Excel
                    </a>

                    <button @click="openAddModal" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-full flex items-center gap-1.5 transition-all shadow-sm">
                        <span>+</span> Yangi Qo'shish
                    </button>
                </div>
            </div>

            <!-- Hidden File Input for Document Upload -->
            <input type="file" ref="uploadInput" @change="handleFileUpload" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />

            <!-- Detail View -->
            <div v-if="selectedSupplierView" class="animate-[fadeIn_0.3s_ease-out] space-y-6">
                <button @click="selectedSupplierView = null" class="flex items-center gap-2 text-gray-500 hover:text-amber-500 transition-colors text-sm font-semibold">
                    ← Ortga qaytish
                </button>
                
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-6 relative">
                    <!-- Action Buttons -->
                    <div class="absolute top-6 right-6 flex flex-wrap items-center justify-end gap-2">
                        <a :href="route('admin.warehouse.suppliers.ledger', selectedSupplierView.id)" target="_blank" class="px-3.5 py-1.5 border border-gray-200 text-gray-700 hover:bg-gray-50 transition-all rounded-lg text-xs font-semibold" title="Hisobot (PDF) yuklab olish">
                            PDF
                        </a>
                        <a :href="route('admin.warehouse.suppliers.ledger.excel', selectedSupplierView.id)" target="_blank" class="px-3.5 py-1.5 border border-gray-200 text-gray-700 hover:bg-gray-50 transition-all rounded-lg text-xs font-semibold" title="Hisobot (Excel) yuklab olish">
                            EXCEL
                        </a>
                        <button @click="openAddOrderModal(selectedSupplierView)" class="px-3.5 py-1.5 bg-blue-50 border border-blue-100 text-blue-700 hover:bg-blue-100 transition-all rounded-lg text-xs font-semibold" title="Yangi Buyurtma">
                            + Buyurtma
                        </button>
                        <button @click="openPaymentModal(selectedSupplierView)" class="px-3.5 py-1.5 bg-emerald-50 border border-emerald-100 text-emerald-700 hover:bg-emerald-100 transition-all rounded-lg text-xs font-semibold" title="To'lov qilish">
                            To'lov
                        </button>
                        <button @click="openEditModal(selectedSupplierView)" class="px-3.5 py-1.5 border border-gray-200 text-gray-700 hover:bg-gray-50 transition-all rounded-lg text-xs font-semibold">
                            Tahrirlash
                        </button>
                    </div>

                    <div class="mb-6 mt-8 md:mt-0 pr-64">
                        <h3 class="text-2xl font-bold text-gray-900">{{ selectedSupplierView.company_name || selectedSupplierView.name }}</h3>
                        <p class="text-xs text-amber-600 font-semibold uppercase mt-1 tracking-wider">Vakil: {{ selectedSupplierView.name }}</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100/60 flex flex-col justify-center">
                            <span class="text-[10px] font-mono text-gray-500 uppercase mb-1">Telefon</span>
                            <span class="text-sm font-semibold text-gray-900 font-mono">{{ selectedSupplierView.phone || '-' }}</span>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100/60 flex flex-col justify-center">
                            <span class="text-[10px] font-mono text-gray-500 uppercase mb-1">Buyurtmalar</span>
                            <span class="text-sm font-semibold text-blue-600 font-mono">{{ selectedSupplierView.orders?.length || 0 }} ta</span>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100/60 flex flex-col justify-center">
                            <span class="text-[10px] font-mono text-gray-500 uppercase mb-1">Joriy Balans</span>
                            <div>
                                <span class="block text-lg font-bold font-mono" :class="selectedSupplierView.balance > 0 ? 'text-red-600' : (selectedSupplierView.balance < 0 ? 'text-emerald-600' : 'text-gray-900')">
                                    {{ formatMoney(Math.abs(selectedSupplierView.balance)) }}
                                </span>
                                <span class="text-[10px] font-mono uppercase tracking-widest block mt-0.5" :class="selectedSupplierView.balance > 0 ? 'text-red-500 font-medium' : (selectedSupplierView.balance < 0 ? 'text-emerald-500 font-medium' : 'text-gray-400')">
                                    {{ selectedSupplierView.balance > 0 ? 'Qarzimiz bor' : (selectedSupplierView.balance < 0 ? 'Haqimiz bor' : 'Nol balans') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Document Attachment Section -->
                    <div class="pt-4 border-t border-gray-100">
                        <div v-if="!selectedSupplierView.document_path" class="flex items-center justify-start max-w-sm">
                            <button @click="triggerUpload(selectedSupplierView.id)" class="w-full py-3 border border-dashed border-gray-300 text-gray-500 hover:text-gray-800 text-xs font-semibold rounded-xl transition-all">
                                📎 Hujjat Biriktirish
                            </button>
                        </div>
                        <div v-else class="flex flex-col gap-2 max-w-sm">
                            <div class="flex justify-between items-center bg-blue-50 border border-blue-100 p-3 rounded-xl">
                                <span class="text-xs text-blue-700 font-semibold flex items-center gap-2">
                                    📄 Hujjat biriktirilgan
                                </span>
                                <div class="flex items-center gap-2">
                                    <a :href="'/storage/' + selectedSupplierView.document_path" target="_blank" class="w-8 h-8 rounded bg-white text-blue-600 flex items-center justify-center border border-blue-200 hover:bg-blue-50 transition-all" title="Yuklab olish">
                                        ⬇️
                                    </a>
                                    <button @click="sendToTelegram(selectedSupplierView.id)" class="w-8 h-8 rounded bg-white text-blue-500 flex items-center justify-center border border-blue-200 hover:bg-blue-50 transition-all" title="Telegramga yuborish">
                                        ✈️
                                    </button>
                                </div>
                            </div>
                            <button @click="triggerUpload(selectedSupplierView.id)" class="text-left text-[10px] font-mono text-gray-400 hover:text-gray-700 transition-colors ml-1">
                                Yangilash
                            </button>
                        </div>
                    </div>

                    <!-- Grouped Ledger Table -->
                    <div class="pt-8 border-t border-gray-100 mt-6">
                        <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">Umumiy Hisob-kitob:</h4>
                        
                        <!-- Products Tables by Category -->
                        <div class="grid grid-cols-1 gap-6 mb-8">
                            <div v-for="(items, category) in getGroupedItems(selectedSupplierView)" :key="category" class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm">
                                <h5 class="text-center bg-gray-50 py-2.5 border-b border-gray-100 text-xs font-bold uppercase tracking-widest text-blue-700">{{ category }}</h5>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="bg-gray-50/50 border-b border-gray-100 text-gray-500 font-semibold">
                                                <th class="p-3 text-center w-12">№</th>
                                                <th class="p-3">Sana</th>
                                                <th class="p-3">O'lcham</th>
                                                <th class="p-3">O'lchov birligi</th>
                                                <th class="p-3 text-right">Miqdor</th>
                                                <th class="p-3 text-right">Narxi</th>
                                                <th class="p-3 text-right">Jami</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, idx) in items" :key="idx" class="border-b border-gray-50 hover:bg-gray-50/30 transition-all">
                                                <td class="p-3 text-center text-gray-500 font-mono">{{ idx + 1 }}</td>
                                                <td class="p-3 text-gray-700 font-mono">{{ item.date }}</td>
                                                <td class="p-3 text-amber-600 font-semibold">{{ item.size || '-' }}</td>
                                                <td class="p-3 text-gray-500">{{ item.unit }}</td>
                                                <td class="p-3 text-right font-bold text-gray-900">{{ item.quantity }}</td>
                                                <td class="p-3 text-right text-gray-600 font-mono">{{ formatMoney(item.unit_price) }}</td>
                                                <td class="p-3 text-right font-bold text-blue-600 font-mono">{{ formatMoney(item.total_price) }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-gray-50/20 font-bold border-t border-gray-100">
                                                <td colspan="6" class="p-3 text-right">Jami ({{ category }}):</td>
                                                <td class="p-3 text-right text-red-600 font-mono text-sm">{{ formatMoney(items.reduce((acc, curr) => acc + curr.total_price, 0)) }}</td>
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
                        <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">To'lovlar Tarixi (Chiqim):</h4>
                        <div class="overflow-x-auto bg-white rounded-xl border border-gray-100 shadow-sm">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-semibold uppercase">
                                        <th class="p-3">Sana</th>
                                        <th class="p-3">To'lov Turi</th>
                                        <th class="p-3">Izoh</th>
                                        <th class="p-3 text-right">Summa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in getPayments(selectedSupplierView)" :key="p.id" class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                        <td class="p-3 text-gray-800 font-mono">{{ p.transaction_date }}</td>
                                        <td class="p-3 text-emerald-600 uppercase font-semibold text-[10px]">{{ p.payment_method }}</td>
                                        <td class="p-3 text-gray-600">{{ p.notes || '-' }}</td>
                                        <td class="p-3 text-right text-emerald-600 font-bold font-mono">{{ formatMoney(p.amount) }}</td>
                                    </tr>
                                    <tr v-if="getPayments(selectedSupplierView).length === 0">
                                        <td colspan="4" class="p-6 text-center text-xs text-gray-500">To'lovlar topilmadi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Master View (Tables & Cards) matching Image 1 layout -->
            <div v-else class="space-y-6">
                <!-- 4 premium stats cards matching Image 1 -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Barcha taminotchilar</p>
                            <p class="text-3xl font-bold text-blue-600 mt-2">{{ stats.total_suppliers }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125a1.125 1.125 0 0 0 1.125-1.125V9.75M3.75 16.5V4.5a1.125 1.125 0 0 1 1.125-1.125h9.75a1.125 1.125 0 0 1 1.125 1.125v12.75m-12 0h12" />
                            </svg>
                        </div>
                    </div>
                    
                    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Qarzdorlik</p>
                            <p class="text-2xl font-bold text-red-600 mt-2">{{ formatMoney(stats.total_debt) }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center text-red-600 text-xl font-bold">
                            $
                        </div>
                    </div>
                    
                    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Haqdorlik</p>
                            <p class="text-2xl font-bold text-emerald-600 mt-2">{{ formatMoney(stats.total_credit) }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 text-xl font-bold">
                            $
                        </div>
                    </div>
                    
                    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Umumiy xaridlar</p>
                            <p class="text-3xl font-bold text-purple-600 mt-2">{{ stats.total_purchases }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Tabs Filter matching Image 1 layout -->
                <div class="flex gap-6 border-b border-gray-100 pb-1 mb-2 overflow-x-auto whitespace-nowrap">
                    <button v-for="tab in tabs" :key="tab" @click="selectedTab = tab" class="relative pb-3 text-sm font-semibold transition-all duration-200" :class="selectedTab === tab ? 'text-amber-500 font-bold border-b-2 border-amber-500' : 'text-gray-500 hover:text-gray-800'">
                        {{ tab }}
                    </button>
                </div>

                <!-- Main Suppliers Table matching Image 1 columns -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden animate-[fadeIn_0.3s_ease-out]">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 text-gray-500 font-bold border-b border-gray-100 text-[10px] uppercase tracking-wider">
                                    <th class="p-4 w-16 text-center">Nomer</th>
                                    <th class="p-4">Taminotchi</th>
                                    <th class="p-4">Kategoriya</th>
                                    <th class="p-4 text-center">Xaridlar soni</th>
                                    <th class="p-4 text-right">Umumiy summa</th>
                                    <th class="p-4 text-center">Tovarar soni</th>
                                    <th class="p-4 text-right">Qarzdorlik</th>
                                    <th class="p-4 text-right">Kredit</th>
                                    <th class="p-4 text-center">Oxirgi xarid</th>
                                    <th class="p-4 text-center w-24">Amallar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(s, index) in filteredSuppliers" :key="s.id" @click="selectedSupplierView = s" class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors cursor-pointer group">
                                    <td class="p-4 text-center text-gray-500 font-mono">{{ index + 1 }}</td>
                                    <td class="p-4">
                                        <div class="font-bold text-gray-900 group-hover:text-amber-600 transition-colors">
                                            {{ s.company_name || s.name }}
                                        </div>
                                        <div class="text-[10px] text-gray-400 font-medium uppercase mt-0.5">
                                            {{ s.name }}
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-600 rounded-md uppercase">
                                            {{ s.category || 'taminotchi' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center text-blue-600 font-semibold font-mono">
                                        {{ getSupplierStats(s).xaridlarSoni }}
                                    </td>
                                    <td class="p-4 text-right text-gray-900 font-semibold font-mono">
                                        {{ formatMoney(getSupplierStats(s).umumiySumma) }}
                                    </td>
                                    <td class="p-4 text-center text-gray-800 font-mono">
                                        {{ getSupplierStats(s).tovararSoni }}
                                    </td>
                                    <td class="p-4 text-right text-red-600 font-bold font-mono">
                                        {{ formatMoney(getSupplierStats(s).qarzdorlik) }}
                                    </td>
                                    <td class="p-4 text-right text-emerald-600 font-bold font-mono">
                                        {{ formatMoney(getSupplierStats(s).kredit) }}
                                    </td>
                                    <td class="p-4 text-center text-gray-500 font-mono text-[10px]">
                                        {{ getSupplierStats(s).oxirgiSana }}
                                    </td>
                                    <td class="p-4 text-center" @click.stop>
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button @click="openEditModal(s)" class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-all" title="Tahrirlash">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                                </svg>
                                            </button>
                                            <button @click="deleteSupplier(s.id)" class="p-1.5 rounded-lg border border-red-200 hover:bg-red-50 text-red-600 transition-all" title="O'chirish">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredSuppliers.length === 0">
                                    <td colspan="10" class="p-12 text-center text-gray-500 font-medium border border-dashed border-gray-100 rounded-xl">
                                        Ta'minotchilar topilmadi.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Supplier Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-905/40 backdrop-blur-sm">
            <div class="bg-white border border-gray-100 shadow-xl w-full max-w-2xl p-6 md:p-8 rounded-2xl relative max-h-[90vh] overflow-y-auto custom-scrollbar animate-[zoomIn_0.25s_ease-out]">
                <button @click="showModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                
                <h3 class="text-xl font-bold text-gray-900 mb-6">
                    {{ editMode ? "Ta'minotchini Tahrirlash" : "Yangi Ta'minotchi Qo'shish" }}
                </h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Kompaniya Nomi</label>
                        <input v-model="form.company_name" type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm" placeholder="MCHJ / XK / YaTT nomi">
                    </div>
                    
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Vakil Ismi (F.I.O) *</label>
                        <input v-model="form.name" type="text" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm" placeholder="Masalan: Abdullayev Anvar">
                    </div>

                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Telefon *</label>
                        <input v-model="form.phone" type="text" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm" placeholder="+998 90 123 45 67">
                    </div>

                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">STIR (INN)</label>
                        <input v-model="form.inn" type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm" placeholder="Masalan: 301234567">
                    </div>

                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Kategoriya</label>
                        <input v-model="form.category" type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm" placeholder="taminotchi, xizmat...">
                    </div>

                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Status</label>
                        <select v-model="form.status" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm bg-white">
                            <option value="faol">Faol</option>
                            <option value="nofaol">Nofaol</option>
                            <option value="kutilmoqda">Kutilmoqda</option>
                            <option value="bloklangan">Bloklangan</option>
                        </select>
                    </div>

                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Bank Hisob Raqami</label>
                        <input v-model="form.bank_account" type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm" placeholder="20208000...">
                    </div>

                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">MFO</label>
                        <input v-model="form.mfo" type="text" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm" placeholder="00440">
                    </div>

                    <div class="col-span-2">
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Manzil</label>
                        <textarea v-model="form.address" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm" placeholder="Toshkent sh., Chilonzor tumani..."></textarea>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end gap-3 border-t border-gray-100 pt-5">
                    <button @click="showModal = false" class="px-6 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-950 transition-colors">Bekor qilish</button>
                    <button @click="submit" :disabled="isSubmitting" class="px-8 py-2.5 text-sm font-bold bg-amber-500 hover:bg-amber-600 text-white rounded-xl transition-all disabled:opacity-50 shadow-md">
                        {{ isSubmitting ? 'Saqlanmoqda...' : 'Saqlash' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm">
            <div class="bg-white border border-gray-100 shadow-xl w-full max-w-md p-6 md:p-8 rounded-2xl relative max-h-[90vh] overflow-y-auto custom-scrollbar animate-[zoomIn_0.25s_ease-out]">
                <button @click="showPaymentModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">✕</button>
                
                <h3 class="text-xl font-bold text-gray-900 mb-6">
                    To'lov Qilish
                </h3>
                <p class="text-xs text-gray-500 -mt-4 mb-5 font-semibold">Kompaniya: {{ selectedSupplier?.company_name || selectedSupplier?.name }}</p>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">To'lov Summasi (UZS) *</label>
                        <input v-model="paymentForm.amount" type="number" step="0.01" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-bold focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none transition-colors text-right text-lg">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">To'lov Turi *</label>
                        <select v-model="paymentForm.payment_method" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none transition-colors text-sm bg-white">
                            <option value="bank">Bank / Pul o'tkazma</option>
                            <option value="card">Plastik karta</option>
                            <option value="cash">Naqd pul</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Sana *</label>
                        <input v-model="paymentForm.transaction_date" type="date" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none transition-colors text-sm">
                    </div>

                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Izoh</label>
                        <textarea v-model="paymentForm.notes" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none transition-colors text-sm" placeholder="To'lov bo'yicha qo'shimcha izohlar..."></textarea>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end gap-3 border-t border-gray-100 pt-5">
                    <button @click="showPaymentModal = false" class="px-6 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-950 transition-colors">Bekor qilish</button>
                    <button @click="submitPayment" :disabled="isSubmitting" class="px-8 py-2.5 text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl transition-all disabled:opacity-50 shadow-md">
                        {{ isSubmitting ? 'Saqlanmoqda...' : 'To\'lovni Tasdiqlash' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Order Modal (PO) -->
        <div v-if="showOrderModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm">
            <div class="bg-white border border-gray-100 shadow-xl w-full max-w-4xl p-6 md:p-8 rounded-2xl relative max-h-[90vh] overflow-y-auto custom-scrollbar animate-[zoomIn_0.25s_ease-out]">
                <button @click="showOrderModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">✕</button>
                
                <h3 class="text-xl font-bold text-gray-900 mb-6">Yangi Buyurtma yaratish</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Buyurtma Sanasi *</label>
                        <input v-model="orderForm.order_date" type="date" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition-colors text-sm">
                    </div>

                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Kutilayotgan Kelish Sanasi</label>
                        <input v-model="orderForm.expected_delivery_date" type="date" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition-colors text-sm">
                    </div>
                </div>

                <!-- Items -->
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Mahsulotlar Ro'yxati</h4>
                        <button @click="addOrderItem" class="text-xs font-semibold text-blue-600 hover:text-blue-800">+ Mahsulot Qo'shish</button>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(item, idx) in orderForm.items" :key="idx" class="flex flex-wrap gap-3 items-end p-4 rounded-xl border border-gray-100 bg-gray-50/30 relative">
                            <button v-if="orderForm.items.length > 1" @click="removeOrderItem(idx)" class="absolute top-3 right-3 text-red-400 hover:text-red-600 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                            
                            <div class="flex-1 min-w-[200px]">
                                <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Mahsulot nomi *</label>
                                <input v-model="item.item_name" type="text" list="inventory_list_po" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm focus:outline-none focus:border-blue-500" placeholder="Masalan: Sement, Armatura...">
                                <datalist id="inventory_list_po">
                                    <option v-for="inv in inventory_items" :key="inv.id" :value="inv.name"></option>
                                </datalist>
                            </div>
                            
                            <div class="w-24">
                                <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">O'lchami</label>
                                <input v-model="item.size" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm focus:outline-none focus:border-blue-500" placeholder="M400, 12mm...">
                            </div>
                            
                            <div class="w-28">
                                <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">O'lchov birligi</label>
                                <select v-model="item.unit" class="w-full border border-gray-200 rounded-lg px-2 py-2 text-gray-900 text-sm bg-white focus:outline-none focus:border-blue-500">
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
                                <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Soni *</label>
                                <input v-model="item.quantity" type="number" step="0.01" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm text-right focus:outline-none focus:border-blue-500">
                            </div>

                            <div class="w-32">
                                <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Narxi (UZS) *</label>
                                <input v-model="item.unit_price" type="number" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm text-right focus:outline-none focus:border-blue-500">
                            </div>
                            
                            <div class="pt-6 font-semibold text-gray-900 text-right pr-4 font-mono text-sm self-center">
                                {{ formatMoney(item.quantity * item.unit_price) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Izoh</label>
                    <textarea v-model="orderForm.notes" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition-colors text-sm" placeholder="Ixtiyoriy izohlar..."></textarea>
                </div>
                
                <div class="flex justify-between items-center border-t border-gray-100 pt-6">
                    <div class="text-gray-500 text-xs font-semibold uppercase tracking-wider">Jami qiymati: 
                        <span class="text-xl font-bold text-emerald-600 ml-2 font-mono">{{ formatMoney(orderForm.items.reduce((acc, curr) => acc + (curr.quantity * curr.unit_price), 0)) }}</span>
                    </div>
                    
                    <div class="flex gap-3">
                        <button @click="showOrderModal = false" class="px-6 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-950 transition-colors">Bekor qilish</button>
                        <button @click="submitOrder" :disabled="isSubmitting" class="px-8 py-2.5 text-sm font-bold bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-all disabled:opacity-50 shadow-md">
                            {{ isSubmitting ? 'Saqlanmoqda...' : 'Buyurtmani Yaratish' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
