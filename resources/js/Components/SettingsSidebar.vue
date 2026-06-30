<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    activeSection: {
        type: String,
        required: true
    }
});

const emit = defineEmits(['change-section']);

const menuItems = [
    { id: 'branding', label: 'Branding va Tashqi Ko\'rinish', icon: '🎨' },
    { id: 'system', label: 'Tizim Sozlamalari', icon: '⚙️' },
    { id: 'company', label: 'Kompaniya Ma\'lumotlari', icon: '🏢' },
    { id: 'security', label: 'Xavfsizlik', icon: '🛡️' },
    { id: 'notifications', label: 'Bildirishnomalar', icon: '🔔' },
    // Keeping existing tabs
    { id: 'roles_permissions', label: 'Rollar va Huquqlar', icon: '🔑' },
    { id: 'users_management', label: 'Xodimlar Boshqaruvi', icon: '👥' },
    { id: 'lead_sources', label: 'Lid Manbalari', icon: '🎯' },
    { id: 'sms_broadcast', label: 'SMS Xabarnomalar', icon: '💬' }
];
</script>

<template>
    <div class="w-full md:w-[280px] bg-white border-r border-[#E2E8F0] md:min-h-[calc(100vh-64px)] shrink-0 flex flex-col">
        <!-- Sidebar Header -->
        <div class="p-[24px] border-b border-[#E2E8F0]">
            <h2 class="text-[20px] font-bold text-[#1E293B] font-display">Sozlamalar</h2>
            <p class="text-[12px] text-[#64748B] mt-1">Tizimni o'zingizga moslashtiring</p>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="flex-1 py-[16px] overflow-y-auto">
            <ul class="space-y-[4px]">
                <li v-for="item in menuItems" :key="item.id">
                    <button 
                        @click="emit('change-section', item.id)"
                        class="w-full text-left flex items-center px-[16px] py-[12px] text-[14px] font-medium border-l-[3px] transition-colors"
                        :class="[
                            activeSection === item.id 
                                ? 'bg-[#F1F5F9] border-[#2563EB] text-[#2563EB]' 
                                : 'border-transparent text-[#64748B] hover:bg-[#F1F5F9] hover:border-[#2563EB]'
                        ]"
                    >
                        <span class="w-[20px] h-[20px] flex items-center justify-center mr-[12px] text-[16px]">
                            {{ item.icon }}
                        </span>
                        {{ item.label }}
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</template>
