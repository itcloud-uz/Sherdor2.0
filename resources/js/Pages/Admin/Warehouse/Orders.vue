<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    orders: Array,
    suppliers: Array,
    inventory_items: Array,
});

const showModal = ref(false);
const form = ref({
    supplier_id: '',
    order_date: new Date().toISOString().split('T')[0],
    expected_delivery_date: '',
    notes: '',
    items: [
        { item_name: '', unit: 'dona', quantity: 1, unit_price: 0 }
    ]
});

const isSubmitting = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

function openAddModal() {
    isEditing.value = false;
    editingId.value = null;
    form.value = {
        supplier_id: '',
        order_date: new Date().toISOString().split('T')[0],
        expected_delivery_date: '',
        notes: '',
        items: [{ item_name: '', unit: 'dona', quantity: 1, unit_price: 0 }]
    };
    showModal.value = true;
}

function openEditModal(order) {
    isEditing.value = true;
    editingId.value = order.id;
    form.value = {
        supplier_id: order.supplier_id,
        order_date: order.order_date,
        expected_delivery_date: order.expected_delivery_date || '',
        notes: order.notes || '',
        items: order.items.map(i => ({
            item_name: i.item_name,
            unit: i.unit,
            quantity: i.quantity,
            unit_price: i.unit_price
        }))
    };
    showModal.value = true;
}

function addItem() {
    form.value.items.push({ item_name: '', unit: 'dona', quantity: 1, unit_price: 0 });
}

function removeItem(index) {
    if (form.value.items.length > 1) {
        form.value.items.splice(index, 1);
    }
}

function submit() {
    isSubmitting.value = true;
    
    if (isEditing.value) {
        router.put(route('admin.warehouse.orders.update', editingId.value), form.value, {
            onSuccess: () => {
                isSubmitting.value = false;
                showModal.value = false;
            },
            onError: () => {
                isSubmitting.value = false;
            }
        });
    } else {
        router.post(route('admin.warehouse.orders.store'), form.value, {
            onSuccess: () => {
                isSubmitting.value = false;
                showModal.value = false;
            },
            onError: () => {
                isSubmitting.value = false;
            }
        });
    }
}

function deleteOrder(id) {
    if (confirm("Bu xarid buyurtmasini o'chirmoqchimisiz?")) {
        router.delete(route('admin.warehouse.orders.destroy', id));
    }
}

function receiveOrder(id) {
    if (confirm("Bu buyurtmani haqiqatan qabul qilasizmi? Bu mahsulotlarni omborga qo'shadi va ta'minotchiga qarz yozadi!")) {
        router.post(route('admin.warehouse.orders.receive', id));
    }
}

const formatMoney = (amount) => {
    return new Intl.NumberFormat('uz-UZ', { style: 'currency', currency: 'UZS' }).format(amount);
};
</script>

<template>
    <Head title="Xarid Buyurtmalari" />
    <AdminLayout>
        <div class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center mb-8">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.warehouse.index')" class="w-10 h-10 flex items-center justify-center rounded-xl border border-gray-200 hover:bg-gray-100 text-gray-900 transition-all">
                        ←
                    </Link>
                    <h1 class="text-3xl font-display font-bold text-gray-900 tracking-widest uppercase shadow-neon-magenta/50 flex items-center gap-3 hidden">
                        <span class="text-indigo-600">🛒</span> Xarid Buyurtmalari (PO)
                    </h1>
                </div>
                
                <button @click="openAddModal" class="px-6 py-3 bg-indigo-50 border border-neon-magenta text-indigo-600 font-bold font-mono text-sm uppercase tracking-widest rounded-xl hover:bg-indigo-600 hover:text-black transition-all shadow-sm">
                    + Yangi Xarid
                </button>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <div v-for="order in orders" :key="order.id" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-200 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 p-4 flex gap-2">
                        <span v-if="order.status === 'pending'" class="px-3 py-1 bg-amber-50 text-amber-600 border border-amber-200 text-[10px] font-mono tracking-widest uppercase rounded">Kutilmoqda</span>
                        <span v-if="order.status === 'delivered'" class="px-3 py-1 bg-emerald-50 text-emerald-600 border border-emerald-200 text-[10px] font-mono tracking-widest uppercase rounded">Qabul Qilindi</span>
                        
                        <a :href="route('admin.warehouse.orders.pdf', order.id)" target="_blank" class="px-3 py-1 border border-gray-300 text-gray-900 hover:bg-white hover:text-black transition-all rounded text-[10px] font-mono uppercase tracking-widest" title="PDF formatida yuklab olish">
                            PDF
                        </a>
                        <button @click="openEditModal(order)" class="px-3 py-1 bg-blue-50 border border-blue-200 text-blue-600 hover:bg-blue-600 hover:text-black transition-all rounded text-[10px] font-mono uppercase tracking-widest" title="Tahrirlash">
                            ✏️
                        </button>
                        <button @click="deleteOrder(order.id)" class="px-3 py-1 bg-indigo-50 border border-neon-magenta/30 text-indigo-600 hover:bg-indigo-600 hover:text-black transition-all rounded text-[10px] font-mono uppercase tracking-widest" title="O'chirish">
                            🗑️
                        </button>
                    </div>

                    <div class="flex gap-8 mb-6 border-b border-gray-100 pb-6">
                        <div>
                            <h3 class="text-xs font-mono text-gray-500 uppercase mb-1">Buyurtma Raqami</h3>
                            <p class="text-xl font-bold text-blue-600 font-mono">{{ order.order_number }}</p>
                        </div>
                        <div>
                            <h3 class="text-xs font-mono text-gray-500 uppercase mb-1">Ta'minotchi</h3>
                            <p class="text-lg font-bold text-gray-900 font-display">{{ order.supplier?.company_name || order.supplier?.name }}</p>
                        </div>
                        <div>
                            <h3 class="text-xs font-mono text-gray-500 uppercase mb-1">{{ $t('date_label') }}</h3>
                            <p class="text-sm font-bold text-gray-900 font-mono">{{ order.order_date }}</p>
                        </div>
                        <div>
                            <h3 class="text-xs font-mono text-gray-500 uppercase mb-1">Umumiy Qiymat</h3>
                            <p class="text-xl font-bold text-emerald-600 font-mono">{{ formatMoney(order.total_amount) }}</p>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-[10px] font-mono text-gray-500 uppercase mb-3">Mahsulotlar ro'yxati:</h4>
                        <div class="overflow-x-auto">
                            <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left text-sm font-mono border border-gray-100 rounded-lg">
                                <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                    <tr class="text-gray-500 text-[10px] uppercase bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                        <th class="p-2">Mahsulot</th>
                                        <th class="p-2 text-right">Miqdor</th>
                                        <th class="p-2 text-right">Narxi</th>
                                        <th class="p-2 text-right">Jami</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in order.items" :key="item.id" class="border-t border-gray-100 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                        <td class="p-2 text-gray-900">{{ item.item_name }}</td>
                                        <td class="p-2 text-right text-blue-600">{{ item.quantity }} {{ item.unit }}</td>
                                        <td class="p-2 text-right text-gray-600">{{ formatMoney(item.unit_price) }}</td>
                                        <td class="p-2 text-right text-gray-900 font-bold">{{ formatMoney(item.total_price) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-if="order.status === 'pending'" class="mt-6 flex justify-end">
                        <button @click="receiveOrder(order.id)" class="px-6 py-2 bg-emerald-600 text-white font-bold font-mono text-xs uppercase tracking-widest rounded hover:shadow-sm transition-all">
                            Mahsulotlarni Omborga Kirim Qilish
                        </button>
                    </div>
                </div>

                <div v-if="orders.length === 0" class="py-12 text-center text-gray-500 font-mono text-xs border border-dashed border-gray-200 rounded-2xl bg-white border border-gray-200 shadow-sm rounded-xl">
                    Hozircha buyurtmalar yo'q.
                </div>
            </div>
        </div>

        <!-- Add Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500/50">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl w-full max-w-4xl p-4 md:p-8 rounded-2xl border border-neon-magenta/50 shadow-sm relative max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button @click="showModal = false" class="absolute top-4 right-4 text-gray-500 hover:text-gray-900">✕</button>
                
                <h3 class="text-xl font-display font-bold text-gray-900 uppercase tracking-widest mb-6">{{ isEditing ? 'Xarid Buyurtmasini Tahrirlash' : 'Yangi Xarid Buyurtmasi' }}</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                    <div class="col-span-3 md:col-span-1">
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Ta'minotchi *</label>
                        <select v-model="form.supplier_id" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-neon-magenta transition-colors">
                            <option value="">Tanlang</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.company_name || s.name }}</option>
                        </select>
                    </div>
                    
                    <div class="col-span-3 md:col-span-1">
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Buyurtma Sanasi *</label>
                        <input v-model="form.order_date" type="date" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-neon-magenta transition-colors">
                    </div>

                    <div class="col-span-3 md:col-span-1">
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Kutilyotgan Kelish Sanasi</label>
                        <input v-model="form.expected_delivery_date" type="date" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-neon-magenta transition-colors">
                    </div>
                </div>

                <!-- Items -->
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="text-[10px] font-mono text-gray-500 uppercase">Mahsulotlar Ro'yxati</h4>
                        <button @click="addItem" class="text-xs font-mono text-blue-600 hover:text-gray-900">+ Mahsulot Qo'shish</button>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(item, idx) in form.items" :key="idx" class="flex gap-3 items-start p-3 rounded-xl border border-gray-100 relative">
                            <button v-if="form.items.length > 1" @click="removeItem(idx)" class="absolute top-2 right-2 text-red-600/50 hover:text-red-600">✕</button>
                            
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Nomi *</label>
                                <input v-model="item.item_name" type="text" list="inventory_list" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm" placeholder="Sement, Armatura...">
                                <datalist id="inventory_list">
                                    <option v-for="inv in inventory_items" :key="inv.id" :value="inv.name"></option>
                                </datalist>
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
<div v-if="item.unit_price" class="text-xs text-blue-600 mt-1 font-bold tracking-wider">{{ new Intl.NumberFormat('uz-UZ').format(item.unit_price) }} UZS</div>
                            </div>
                            
                            <div class="w-32 pt-6 text-right pr-6">
                                <span class="text-xs font-bold text-gray-900 font-mono">{{ formatMoney((item.quantity * item.unit_price) || 0) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Qo'shimcha Izoh</label>
                    <textarea v-model="form.notes" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-gray-900 font-medium focus:border-neon-magenta transition-colors" placeholder="Ixtiyoriy..."></textarea>
                </div>
                
                <div class="flex justify-between items-center border-t border-gray-200 pt-6">
                    <div class="text-gray-500 text-xs font-mono uppercase tracking-widest">Umumiy Qiymat: <span class="text-xl font-bold text-emerald-600 ml-2">{{ formatMoney(form.items.reduce((acc, curr) => acc + (curr.quantity * curr.unit_price), 0)) }}</span></div>
                    
                    <div class="flex gap-3">
                        <button @click="showModal = false" class="px-6 py-3 text-sm font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel_button') }}</button>
                        <button @click="submit" :disabled="isSubmitting" class="px-8 py-3 text-sm font-bold font-mono bg-indigo-600 text-black rounded-xl hover:shadow-sm transition-all disabled:opacity-50">
                            {{ isSubmitting ? 'Saqlanmoqda...' : (isEditing ? 'Saqlash' : 'Buyurtmani Yaratish') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
