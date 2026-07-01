<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    machineries: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <AdminLayout>
        <Head title="Texnikalar" />

        <div class="p-6 bg-gray-50 min-h-screen">
            <div class="max-w-screen-2xl mx-auto">
                
                <!-- Main Header -->
                <div class="mb-6 flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-800">TEXNIKALAR</h1>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Mavjud texnikalar ro'yxati</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3">№</th>
                                    <th class="px-4 py-3">Texnika Nomi</th>
                                    <th class="px-4 py-3">Raqami</th>
                                    <th class="px-4 py-3">Turi</th>
                                    <th class="px-4 py-3">Haydovchi</th>
                                    <th class="px-4 py-3">Sohibkari</th>
                                    <th class="px-4 py-3">To'lov turi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="props.machineries.length === 0">
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-400">Texnikalar mavjud emas.</td>
                                </tr>
                                <tr v-for="(machinery, idx) in props.machineries" :key="machinery.id" class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3">{{ idx + 1 }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ machinery.name }}</td>
                                    <td class="px-4 py-3">{{ machinery.license_plate || '-' }}</td>
                                    <td class="px-4 py-3">{{ machinery.machinery_type || '-' }}</td>
                                    <td class="px-4 py-3">{{ machinery.driver_name || '-' }}</td>
                                    <td class="px-4 py-3">{{ machinery.owner?.name || '-' }}</td>
                                    <td class="px-4 py-3">{{ machinery.payment_type || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AdminLayout>
</template>
