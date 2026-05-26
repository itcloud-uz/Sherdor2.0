<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const page = usePage();
const isMobileMenuOpen = ref(false);

const navItems = [
    { name: 'dashboard', route: 'admin.dashboard', icon: '⬡' },
    { name: 'sales_department', route: 'admin.crm', icon: '🛒' },
    { name: 'facility_manager', route: 'admin.facility', icon: '🏗️' },
    { name: 'finance_core', route: 'admin.finance', icon: '🏦' },
    { name: 'partner_hub', route: 'admin.partners', icon: '🤝' },
    { name: 'Taminotchilar', route: 'admin.warehouse.index', icon: '📦' },
    { name: 'hr_payroll', route: 'admin.hr', icon: '👥' },
    { name: 'ai_agents', route: 'admin.ai_agents', icon: '🤖' },
    { name: 'IP Telefoniya', route: 'admin.telephony.index', icon: '📞' },
    { name: 'settings', route: 'admin.settings.index', icon: '⚙️' },
    { name: 'Developer', route: 'admin.developer.settings', icon: '🛠️' },
];

const filteredNavItems = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return [];
    
    const rawRoles = user.roles || [];
    const roles = rawRoles.map(r => typeof r === 'string' ? r : r.name);
    const isAdmin = roles.includes('Super_Admin') || roles.includes('Admin');
    const isPartner = roles.includes('Partner');
    const isOperator = roles.includes('Operator');

    return navItems.filter(item => {
        if (isAdmin) return true;
        
        if (isPartner) {
            return ['dashboard', 'sales_department', 'facility_manager', 'finance_core', 'partner_hub', 'Taminotchilar', 'hr_payroll'].includes(item.name);
        }
        
        if (isOperator) {
            return ['dashboard', 'sales_department', 'facility_manager', 'finance_core', 'Taminotchilar', 'hr_payroll'].includes(item.name);
        }

        return false;
    });
});

const currentNav = computed(() => {
    const currentRouteName = route().current();
    if (!currentRouteName) return null;
    return navItems.find(item => currentRouteName.startsWith(item.route.replace('.index', '')));
});

let refreshInterval = null;

onMounted(() => {
    // Auto-refresh removed for performance optimization.
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900 flex relative overflow-hidden font-sans">
        
        <!-- Mobile Overlay -->
        <div v-if="isMobileMenuOpen" @click="isMobileMenuOpen = false" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-40 md:hidden"></div>

        <!-- Sidebar -->
        <aside :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'" class="fixed md:relative w-64 h-full bg-white border-r border-gray-200 flex flex-col z-50 transition-transform duration-300 shadow-sm">
            <div class="h-20 flex items-center justify-center border-b border-gray-100 px-4">
                <div class="flex items-center gap-3 w-full">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center overflow-hidden shrink-0">
                        <img v-if="$page.props.branding?.logo_path" :src="'/storage/' + $page.props.branding.logo_path" class="w-full h-full object-contain" />
                        <span v-else class="text-blue-600 font-bold text-xl font-display">S</span>
                    </div>
                    <div class="truncate">
                        <h1 class="text-sm font-display font-bold tracking-wide text-gray-900 truncate">{{ $t('sher_dor') }}</h1>
                        <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest truncate">ERP System</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 py-6 px-4 space-y-1 overflow-y-auto">
                <template v-for="item in filteredNavItems" :key="item.name">
                    <Link
                        :href="route(item.route)"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-blue-50 border border-transparent hover:border-blue-100"
                        :class="currentNav?.name === item.name ? 'bg-blue-50 border-blue-100' : ''"
                    >
                        <span class="text-lg text-gray-500 group-hover:text-blue-600 transition-colors" :class="currentNav?.name === item.name ? 'text-blue-600' : ''">{{ item.icon }}</span>
                        <span class="text-sm font-medium text-gray-600 group-hover:text-blue-700 transition-colors" :class="currentNav?.name === item.name ? 'text-blue-700 font-semibold' : ''">{{ $t(item.name) }}</span>
                    </Link>
                </template>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col z-10 h-screen overflow-hidden relative">
            <!-- Top Header -->
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 md:px-8 relative z-30 shrink-0 shadow-sm">
                <!-- Left side: Title/Icon -->
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="md:hidden p-2 text-gray-500 hover:text-blue-600 focus:outline-none shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <slot name="header">
                        <h2 class="text-lg md:text-xl font-display font-semibold text-gray-900 flex items-center gap-3 truncate">
                            <span class="text-xl md:text-2xl text-gray-500">{{ currentNav?.icon || '⬡' }}</span> 
                            <span class="truncate">{{ currentNav ? $t(currentNav.name) : 'Dashboard' }}</span>
                        </h2>
                    </slot>
                </div>
                
                <!-- Center: Date -->
                <div class="hidden md:flex items-center justify-center flex-1 shrink-0">
                    <p class="text-xs font-medium text-gray-500 bg-gray-50 px-4 py-1.5 rounded-full border border-gray-200">{{ new Date().toLocaleDateString('en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                </div>

                <!-- Right side: User Profile -->
                <div class="flex items-center gap-6 justify-end flex-1 shrink-0">
                    <!-- User Profile Dropdown -->
                    <div class="relative group">
                        <button class="flex items-center gap-3 p-1.5 pr-3 rounded-lg hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-200 cursor-default">
                            <div class="text-right hidden sm:block">
                                <p class="text-sm font-semibold text-gray-900 leading-tight">{{ $page.props.auth?.user?.name }}</p>
                                <p class="text-[10px] font-medium text-gray-500 mt-0.5">{{ ($page.props.auth?.user?.roles && $page.props.auth.user.roles.length > 0) ? (typeof $page.props.auth.user.roles[0] === 'string' ? $page.props.auth.user.roles[0] : $page.props.auth.user.roles[0].name).toUpperCase() : 'USER' }}</p>
                            </div>
                            <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center border border-gray-200 overflow-hidden">
                                <img v-if="$page.props.auth?.user?.photo_path" :src="'/storage/' + $page.props.auth.user.photo_path" class="w-full h-full object-cover">
                                <span v-else class="text-xs font-bold text-gray-600">{{ $page.props.auth?.user?.name?.charAt(0) }}</span>
                            </div>
                        </button>

                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 mt-1 w-56 bg-white border border-gray-200 rounded-xl shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform origin-top-right z-50 p-2 space-y-2">
                            
                            <!-- Global Language Switcher -->
                            <div class="p-2">
                                <p class="text-[10px] font-semibold text-gray-500 mb-2 uppercase">Interfeys Tili</p>
                                <LanguageSwitcher />
                            </div>

                            <hr class="border-gray-100">

                            <!-- Live Server Status Indicator -->
                            <div class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-green-200 bg-green-50 mx-2 cursor-default">
                                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                <span class="text-[10px] font-semibold text-green-700 uppercase tracking-wider">SYSTEM ONLINE</span>
                            </div>
                            
                            <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                TIZIMDAN CHIQISH
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="flex-1 overflow-y-auto p-4 md:p-8 bg-gray-50">
                <slot />
            </div>
        </main>
    </div>
</template>
