<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SellerLayout from '@/Layouts/SellerLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const CurrentLayout = computed(() => {
    const roles = page.props.auth.user?.roles || [];
    const roleNames = roles.map(r => typeof r === 'string' ? r : r.name);
    if (roleNames.includes('Sotuvchi') || roleNames.includes('Operator')) {
        return SellerLayout;
    }
    return AdminLayout;
});
</script>

<template>
    <Head title="Profil sozlamalari" />

    <component :is="CurrentLayout">
        <div class="h-[calc(100vh-80px)] bg-slate-50 overflow-y-auto p-6 md:p-10 custom-scrollbar">
            <div class="mx-auto max-w-4xl space-y-8">
                <!-- Title Header -->
                <div class="mb-4">
                    <h2 class="text-2xl font-bold font-display text-gray-900 tracking-tight">
                        Profil sozlamalari
                    </h2>
                    <p class="text-sm text-gray-500 font-mono mt-1">Shaxsiy ma'lumotlar va xavfsizlik</p>
                </div>

                <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-150">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-150">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-red-100">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </component>
</template>
