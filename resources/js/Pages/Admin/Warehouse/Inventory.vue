<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    inventory: Array,
    stats: Object,
    transactions: Array,
    suppliers: Array,
});

const searchQuery = ref('');
const startDate = ref('');
const endDate = ref('');
const selectedTab = ref('Barcha');
const tabs = ['Barcha', 'Faol', 'To\'xtatilgan', 'Qoralama'];

const showModal = ref(false);
const editMode = ref(false);
const selectedItem = ref(null);
const isSubmitting = ref(false);

const form = ref({
    name: '',
    category: '',
    unit: 'dona',
    quantity: 0,
    min_quantity: 0,
    price: 0,
    status: 'faol'
});

const openAddModal = () => {
    editMode.value = false;
    selectedItem.value = null;
    form.value = {
        name: '',
        category: '',
        unit: 'dona',
        quantity: 0,
        min_quantity: 0,
        price: 0,
        status: 'faol'
    };
    showModal.value = true;
};

const openEditModal = (item) => {
    editMode.value = true;
    selectedItem.value = item;
    form.value = {
        name: item.name,
        category: item.category,
        unit: item.unit || 'dona',
        quantity: item.quantity || 0,
        min_quantity: item.min_quantity || 0,
        price: item.price || item.average_cost || 0,
        status: item.status || 'faol'
    };
    showModal.value = true;
};

const submit = () => {
    isSubmitting.value = true;
    if (editMode.value) {
        router.put(route('admin.warehouse.inventory.update', selectedItem.value.id), form.value, {
            onSuccess: () => {
                isSubmitting.value = false;
                showModal.value = false;
            },
            onError: () => {
                isSubmitting.value = false;
            }
        });
    } else {
        router.post(route('admin.warehouse.inventory.store'), form.value, {
            onSuccess: () => {
                isSubmitting.value = false;
                showModal.value = false;
            },
            onError: () => {
                isSubmitting.value = false;
            }
        });
    }
};

const deleteItem = (id) => {
    if (confirm("Mahsulotni o'chirishga ishonchingiz komilmi?")) {
        router.delete(route('admin.warehouse.inventory.destroy', id));
    }
};

const formatMoney = (amount) => {
    return new Intl.NumberFormat('uz-UZ', { style: 'currency', currency: 'UZS', maximumFractionDigits: 0 }).format(amount || 0).replace('UZS', "so'm");
};

const filteredInventory = computed(() => {
    return props.inventory.filter(item => {
        // Status filter
        if (selectedTab.value !== 'Barcha') {
            const statusMap = {
                'Faol': 'faol',
                'To\'xtatilgan': 'to\'xtatilgan',
                'Qoralama': 'qoralama'
            };
            const targetStatus = statusMap[selectedTab.value];
            if ((item.status || 'faol').toLowerCase() !== targetStatus) {
                return false;
            }
        }

        // Search filter
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase();
            const nameMatch = (item.name || '').toLowerCase().includes(query);
            const categoryMatch = (item.category || '').toLowerCase().includes(query);
            const supplierMatch = (item.supplier_name || '').toLowerCase().includes(query);
            if (!nameMatch && !categoryMatch && !supplierMatch) {
                return false;
            }
        }

        // Date filter
        if (startDate.value) {
            const sDate = new Date(startDate.value);
            const regDate = new Date(item.created_at || new Date());
            if (regDate < sDate) return false;
        }
        if (endDate.value) {
            const eDate = new Date(endDate.value);
            eDate.setHours(23, 59, 59, 999);
            const regDate = new Date(item.created_at || new Date());
            if (regDate > eDate) return false;
        }

        return true;
    });
});
</script>

<template>
    <Head title="Omborxona - Ombor Qoldiqlari" />
    <AdminLayout>
        <div class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <!-- Header section matching Image 2 layout -->
            <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-4 mb-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.warehouse.index')" class="w-12 h-12 flex items-center justify-center rounded-full bg-white border border-gray-100 hover:bg-gray-50 text-gray-600 transition-all shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 font-sans tracking-tight">Omborxona</h1>
                        <p class="text-xs text-gray-500 mt-0.5">Ombor mahsulotlari va inventarizatsiya</p>
                    </div>
                </div>
                
                <!-- Filters & Search Matching Image 2 -->
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

                    <a :href="route('admin.warehouse.inventory.export.excel')" class="px-4 py-2 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-700 text-sm font-semibold rounded-full flex items-center gap-1.5 transition-all shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Excel
                    </a>

                    <button @click="openAddModal" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-full flex items-center gap-1.5 transition-all shadow-sm">
                        <span>+</span> Mahsulot Qo'shish
                    </button>
                </div>
            </div>

            <!-- 4 premium stats cards matching Image 2 layout -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Jami maxsulotlar turi</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2 font-mono">{{ stats.total_types }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                        <!-- Box icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                        </svg>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Kam qolgan mahsulotlar</p>
                        <p class="text-3xl font-bold text-amber-500 mt-2 font-mono">{{ stats.low_stock }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                        <!-- Warning icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Kutilayotgan buyurtmalar</p>
                        <p class="text-3xl font-bold text-emerald-600 mt-2 font-mono">{{ stats.pending_orders }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                        <!-- Shopping Cart -->
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                        </svg>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Barcha mahsulotlar narxi</p>
                        <p class="text-xl font-bold text-purple-600 mt-2 font-mono truncate" :title="formatMoney(stats.total_value)">{{ formatMoney(stats.total_value) }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600">
                        <!-- Group icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Tabs Filter matching Image 2 layout -->
            <div class="flex gap-6 border-b border-gray-100 pb-1 mb-2 overflow-x-auto whitespace-nowrap">
                <button v-for="tab in tabs" :key="tab" @click="selectedTab = tab" class="relative pb-3 text-sm font-semibold transition-all duration-200" :class="selectedTab === tab ? 'text-amber-500 font-bold border-b-2 border-amber-500' : 'text-gray-500 hover:text-gray-800'">
                    {{ tab }}
                </button>
            </div>

            <!-- Inventory Table matching Image 2 columns -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden animate-[fadeIn_0.3s_ease-out]">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-50/50 text-gray-500 font-bold border-b border-gray-100 text-[10px] uppercase tracking-wider">
                                <th class="p-4 w-12 text-center">№</th>
                                <th class="p-4">Mahsulot nomi</th>
                                <th class="p-4">Kategoriya</th>
                                <th class="p-4 text-right">Umumiy miqdor</th>
                                <th class="p-4 text-right">Jami summa</th>
                                <th class="p-4 text-right">Birlik narxi</th>
                                <th class="p-4 text-right">Min. miqdor</th>
                                <th class="p-4">Ta'minotchi</th>
                                <th class="p-4 text-center w-24">Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in filteredInventory" :key="item.id" class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="p-4 text-center text-gray-500 font-mono">{{ index + 1 }}</td>
                                <td class="p-4 font-bold text-gray-900">{{ item.name }}</td>
                                <td class="p-4 text-gray-600">
                                    <span class="px-2.5 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-600 rounded-md">
                                        {{ item.category || 'Asbob-uskunalar' }}
                                    </span>
                                </td>
                                <td class="p-4 text-right font-mono font-semibold">
                                    <span :class="Number(item.quantity) <= Number(item.min_quantity) ? 'text-red-600 font-bold' : 'text-gray-800'">
                                        {{ item.quantity }}
                                    </span>
                                    <span class="text-[10px] ml-1 text-gray-400 font-normal uppercase">{{ item.unit }}</span>
                                </td>
                                <td class="p-4 text-right text-emerald-600 font-bold font-mono">
                                    {{ formatMoney(item.quantity * (item.average_cost || item.price)) }}
                                </td>
                                <td class="p-4 text-right text-gray-800 font-mono">
                                    {{ formatMoney(item.average_cost || item.price) }}
                                </td>
                                <td class="p-4 text-right text-gray-500 font-mono">
                                    {{ item.min_quantity || '0' }} <span class="text-[9px] uppercase">{{ item.unit }}</span>
                                </td>
                                <td class="p-4 text-gray-900 font-semibold">
                                    {{ item.supplier_name || '—' }}
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button @click="openEditModal(item)" class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-all" title="Tahrirlash">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                            </svg>
                                        </button>
                                        <button @click="deleteItem(item.id)" class="p-1.5 rounded-lg border border-red-200 hover:bg-red-50 text-red-600 transition-all" title="O'chirish">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredInventory.length === 0">
                                <td colspan="9" class="p-12 text-center text-gray-500 font-medium border border-dashed border-gray-100 rounded-xl">
                                    Omborda qoldiqlar topilmadi.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add/Edit Inventory Item Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm">
            <div class="bg-white border border-gray-100 shadow-xl w-full max-w-lg p-6 md:p-8 rounded-2xl relative max-h-[90vh] overflow-y-auto custom-scrollbar animate-[zoomIn_0.25s_ease-out]">
                <button @click="showModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                
                <h3 class="text-xl font-bold text-gray-900 mb-6">
                    {{ editMode ? "Mahsulotni Tahrirlash" : "Yangi Mahsulot Qo'shish" }}
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Mahsulot Nomi *</label>
                        <input v-model="form.name" type="text" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm" placeholder="Masalan: Konditsioner">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Kategoriya *</label>
                        <input v-model="form.category" type="text" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm" placeholder="Masalan: Maishiy texnika">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">O'lchov birligi *</label>
                            <select v-model="form.unit" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm bg-white">
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

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Status *</label>
                            <select v-model="form.status" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm bg-white">
                                <option value="faol">Faol</option>
                                <option value="to'xtatilgan">To'xtatilgan</option>
                                <option value="qoralama">Qoralama</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Miqdori *</label>
                            <input v-model="form.quantity" type="number" step="0.01" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm text-right">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Birlik Narxi (UZS) *</label>
                            <input v-model="form.price" type="number" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm text-right">
<div v-if="form.price" class="text-xs text-blue-600 mt-1 font-bold tracking-wider">{{ new Intl.NumberFormat('uz-UZ').format(form.price) }} UZS</div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-500 mb-1 uppercase tracking-wider">Min. miqdor *</label>
                            <input v-model="form.min_quantity" type="number" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 font-medium focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-none transition-colors text-sm text-right">
                        </div>
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
    </AdminLayout>
</template>
