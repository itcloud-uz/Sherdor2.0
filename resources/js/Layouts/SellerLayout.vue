<template>
    <div class="min-h-screen bg-[#F8FAFC] flex font-sans">
        <!-- Sidebar -->
        <aside
            class="w-64 bg-white border-r border-gray-100 flex flex-col transition-all duration-300"
        >
            <!-- Logo area -->
            <div class="h-20 flex items-center px-6 border-b border-gray-50">
                <Link :href="route('admin.dashboard')" class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-slate-900 flex items-center justify-center overflow-hidden shrink-0 shadow-sm">
                        <img src="/images/logo.png" alt="Sher-Dor Logo" class="w-full h-full object-cover" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iIzFlMjkzYiIvPjx0ZXh0IHg9IjUwIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjQwIiBmaWxsPSIjZjVhZjE5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjM1ZW0iPlM8L3RleHQ+PC9zdmc+'" />
                    </div>
                    <div>
                        <h1 class="text-[#1E293B] font-bold text-[15px] leading-tight uppercase tracking-wide">SHER-DOR</h1>
                        <p class="text-[9px] text-gray-400 font-semibold uppercase tracking-wider">ERP SYSTEM</p>
                    </div>
                </Link>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto p-4 space-y-2 custom-scrollbar">
                
                <!-- CRM Accordion -->
                <div class="space-y-1">
                    <button
                        @click="isCrmMenuOpen = !isCrmMenuOpen"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group bg-blue-50/50 hover:bg-blue-50 text-blue-600 font-semibold"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-[16px]">🛒</span>
                            <span class="text-[14px] font-bold">CRM</span>
                        </div>
                        <svg
                            class="w-4 h-4 text-blue-500 transition-transform duration-200"
                            :class="isCrmMenuOpen ? 'rotate-180' : ''"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    
                    <div v-show="isCrmMenuOpen" class="pl-4 space-y-1 transition-all duration-300 border-l border-blue-100 ml-5 pt-1">
                        <Link
                            :href="route('admin.crm', { tab: 'dashboard' })"
                            class="flex items-center gap-2 py-2 pl-6 pr-3 rounded-lg text-[13px] font-medium transition-all duration-200"
                            :class="isCrmTabActive('dashboard') ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                        >
                            <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span> Dashboard
                        </Link>
                        <Link
                            :href="route('admin.crm', { tab: 'leads' })"
                            class="flex items-center gap-2 py-2 pl-6 pr-3 rounded-lg text-[13px] font-medium transition-all duration-200"
                            :class="isCrmTabActive('leads') ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                        >
                            <span class="text-[14px]">🎯</span> Lidlar
                        </Link>
                        <Link
                            :href="route('admin.crm', { tab: 'posts' })"
                            class="flex items-center gap-2 py-2 pl-6 pr-3 rounded-lg text-[13px] font-medium transition-all duration-200"
                            :class="isCrmTabActive('posts') ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                        >
                            <span class="text-[14px]">📝</span> Postlar
                        </Link>
                        <Link
                            :href="route('admin.telephony.index')"
                            class="flex items-center gap-2 py-2 pl-6 pr-3 rounded-lg text-[13px] font-medium transition-all duration-200"
                            :class="route().current('admin.telephony.index') ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                        >
                            <span class="text-[14px]">📞</span> Telefon
                        </Link>
                        <Link
                            :href="route('admin.ai_agents')"
                            class="flex items-center gap-2 py-2 pl-6 pr-3 rounded-lg text-[13px] font-medium transition-all duration-200"
                            :class="route().current('admin.ai_agents') ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                        >
                            <span class="text-[14px]">💬</span> SMS
                        </Link>
                        <Link
                            :href="route('admin.crm.showroom')"
                            class="flex items-center gap-2 py-2 pl-6 pr-3 rounded-lg text-[13px] font-medium transition-all duration-200"
                            :class="route().current('admin.crm.showroom') ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                        >
                            <span class="text-[14px]">🏢</span> Shou room
                        </Link>
                        <Link
                            :href="route('profile.edit')"
                            class="flex items-center gap-2 py-2 pl-6 pr-3 rounded-lg text-[13px] font-medium transition-all duration-200"
                            :class="route().current('profile.edit') ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                        >
                            <span class="text-[14px]">⚙️</span> Profil sozlamalari
                        </Link>
                    </div>
                </div>

                <!-- Transactions if has access -->
                <Link
                    v-if="hasPerm('access transactions')"
                    :href="route('admin.finance', { tab: 2 })"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-slate-50 border border-transparent mt-2"
                    :class="route().current('admin.finance') ? 'bg-blue-50 text-blue-600 font-semibold' : ''"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-[16px] text-slate-500 group-hover:text-slate-800 transition-colors font-bold" :class="route().current('admin.finance') ? 'text-blue-600' : ''">💸</span>
                        <span class="text-[13px] font-bold text-slate-500 group-hover:text-blue-700 transition-colors" :class="route().current('admin.finance') ? 'text-blue-700' : ''">Tranzaksiyalar</span>
                    </div>
                </Link>

            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden">
            <!-- Topbar -->
            <header class="h-20 bg-white border-b border-gray-100 flex items-center justify-between px-8 shrink-0">
                <div class="flex items-center gap-4">
                    <h2 class="text-[28px] font-extrabold text-[#F5AF19] uppercase tracking-tight">
                        <slot name="header">CRM</slot>
                    </h2>
                </div>

                <div class="flex items-center gap-6">
                    <div class="hidden md:flex items-center gap-2">
                        <span class="text-[13px] text-gray-500 font-medium">Ish vaqti :</span>
                        <span class="text-[13px] font-bold text-[#F5AF19]">09:00 | 19:00</span>
                    </div>
                    
                    <div class="flex items-center gap-1 text-[13px] font-bold text-gray-600">
                        <span class="text-[#F5AF19]">UZ</span>
                        <span class="text-gray-300">|</span>
                        <span>RU</span>
                    </div>

                    <div class="relative">
                        <button @click="isProfileMenuOpen = !isProfileMenuOpen" class="flex items-center gap-3 group">
                            <div class="hidden md:block text-right leading-tight">
                                <div class="text-[15px] font-bold text-gray-800 group-hover:text-blue-600 transition-colors">{{ $page.props.auth.user.name }}</div>
                                <div class="text-[11px] font-bold text-[#F5AF19] uppercase">{{ userRole }}</div>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-blue-100 border-2 border-white shadow-sm overflow-hidden relative">
                                <img v-if="$page.props.auth.user.avatar" :src="'/storage/' + $page.props.auth.user.avatar" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-blue-600 font-bold text-lg">
                                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                </div>
                            </div>
                        </button>

                        <transition
                            enter-active-class="transition ease-out duration-100"
                            enter-from-class="transform opacity-0 scale-95"
                            enter-to-class="transform opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-75"
                            leave-from-class="transform opacity-100 scale-100"
                            leave-to-class="transform opacity-0 scale-95"
                        >
                            <div v-show="isProfileMenuOpen" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">
                                <Link :href="route('profile.edit')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-slate-50 hover:text-blue-600">
                                    Profil sozlamalari
                                </Link>
                                <Link :href="route('logout')" method="post" as="button" class="w-full text-left block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    Tizimdan chiqish
                                </Link>
                            </div>
                        </transition>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="flex-1 overflow-y-auto p-6 md:p-8 custom-scrollbar">
                <slot />
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const isCrmMenuOpen = ref(true);
const isProfileMenuOpen = ref(false);

const userRole = computed(() => {
    const roles = page.props.auth.user?.roles || [];
    if (roles.length > 0) {
        return typeof roles[0] === 'string' ? roles[0] : roles[0].name;
    }
    return 'Xodim';
});

const hasPerm = (perm) => {
    const user = page.props.auth?.user;
    if (!user) return false;
    const rawRoles = user.roles || [];
    const roles = rawRoles.map(r => typeof r === 'string' ? r : r.name);
    if (roles.includes('Super_Admin') || roles.includes('Admin')) return true;
    const permissions = user.permissions || [];
    return permissions.includes(perm);
};

const isCrmTabActive = (tab) => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.crm') return false;
    try {
        const url = new URL(page.url, window.location.origin);
        const tabParam = url.searchParams.get('tab');
        if (!tabParam && tab === 'dashboard') return true;
        return tabParam === tab;
    } catch (e) {
        return false;
    }
};

onMounted(() => {
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.relative')) {
            isProfileMenuOpen.value = false;
        }
    });
});
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
