<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import dayjs from 'dayjs';

const props = defineProps({
    requests: Object
});

const isProcessing = ref(false);

const approve = (id) => {
    if (confirm('Siz rostanam ushbu so\'rovni tasdiqlab, tranzaksiyaga o\'zgartirish kiritmoqchimisiz?')) {
        isProcessing.value = true;
        router.post(route('admin.finance.transaction_requests.approve', id), {}, {
            preserveScroll: true,
            onFinish: () => isProcessing.value = false
        });
    }
};

const reject = (id) => {
    if (confirm('So\'rovni rad etasizmi?')) {
        isProcessing.value = true;
        router.post(route('admin.finance.transaction_requests.reject', id), {}, {
            preserveScroll: true,
            onFinish: () => isProcessing.value = false
        });
    }
};

const formatCurrency = (val) => {
    if (!val) return '0.00';
    return Number(val).toLocaleString('ru-RU');
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return dayjs(dateString).format('DD.MM.YYYY HH:mm');
};
</script>

<template>
    <AdminLayout>
        <Head title="Tranzaksiya So'rovlari" />
        <div class="p-6 max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Tranzaksiya O'zgartirish/O'chirish So'rovlari</h1>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-500 font-semibold">
                                <th class="p-4">So'rov Sanasi</th>
                                <th class="p-4">So'rovchi</th>
                                <th class="p-4">Tranzaksiya</th>
                                <th class="p-4">Amal</th>
                                <th class="p-4">Sabab</th>
                                <th class="p-4">Holat</th>
                                <th class="p-4 text-right">Boshqaruv</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="req in requests.data" :key="req.id" class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="p-4 text-xs text-gray-600">{{ formatDate(req.created_at) }}</td>
                                <td class="p-4 text-xs font-medium text-gray-800">{{ req.requester?.name }}</td>
                                <td class="p-4 text-xs">
                                    <div v-if="req.transaction" class="flex flex-col gap-1">
                                        <span class="font-semibold" :class="req.transaction.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                            {{ formatCurrency(req.transaction.amount) }} {{ req.transaction.currency }}
                                        </span>
                                        <span class="text-gray-500">{{ req.transaction.counterparty?.name || 'Kirim/Chiqim' }}</span>
                                    </div>
                                    <span v-else class="text-gray-400">Tranzaksiya topilmadi</span>
                                </td>
                                <td class="p-4 text-xs font-bold uppercase" :class="req.action === 'delete' ? 'text-red-500' : 'text-blue-500'">
                                    {{ req.action === 'delete' ? "O'chirish" : "Tahrirlash" }}
                                </td>
                                <td class="p-4 text-xs text-gray-600 max-w-xs truncate" :title="req.reason">{{ req.reason }}</td>
                                <td class="p-4 text-xs">
                                    <span v-if="req.status === 'pending'" class="px-2 py-1 rounded bg-amber-100 text-amber-700 font-medium">Kutmoqda</span>
                                    <span v-else-if="req.status === 'approved'" class="px-2 py-1 rounded bg-green-100 text-green-700 font-medium">Tasdiqlangan</span>
                                    <span v-else class="px-2 py-1 rounded bg-red-100 text-red-700 font-medium">Rad etilgan</span>
                                </td>
                                <td class="p-4 text-right space-x-2">
                                    <button v-if="req.status === 'pending'" @click="approve(req.id)" :disabled="isProcessing" class="px-3 py-1.5 text-xs bg-green-50 text-green-600 hover:bg-green-600 hover:text-white border border-green-200 rounded-lg transition-colors font-bold uppercase">Tasdiqlash</button>
                                    <button v-if="req.status === 'pending'" @click="reject(req.id)" :disabled="isProcessing" class="px-3 py-1.5 text-xs bg-red-50 text-red-600 hover:bg-red-600 hover:text-white border border-red-200 rounded-lg transition-colors font-bold uppercase">Rad etish</button>
                                </td>
                            </tr>
                            <tr v-if="!requests.data.length">
                                <td colspan="7" class="p-8 text-center text-gray-500 text-sm">Hozircha so'rovlar yo'q</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination -->
            <div class="mt-4 flex justify-between items-center" v-if="requests.links?.length > 3">
                <div class="flex items-center gap-1">
                    <template v-for="(link, i) in requests.links" :key="i">
                        <Link 
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1 rounded border text-sm"
                            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            v-html="link.label"
                        />
                        <span v-else class="px-3 py-1 rounded border bg-gray-50 text-gray-400 text-sm" v-html="link.label"></span>
                    </template>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
