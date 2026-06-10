<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import ThemeProvider from '@/Components/ThemeProvider.vue';

const page = usePage();
const isMobileMenuOpen = ref(false);
const isFinanceMenuOpen = ref(false);
const isCrmMenuOpen = ref(false);
const isClientsMenuOpen = ref(false);
const isFacilityMenuOpen = ref(false);
const isHrMenuOpen = ref(false);
const isSettingsMenuOpen = ref(false);

const isSettingsTabActive = (tabId) => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.settings.index') return false;
    try {
        const url = new URL(page.url, window.location.origin);
        const tabParam = url.searchParams.get('tab');
        if (tabParam === null) {
            return tabId === 'branding';
        }
        return tabParam === tabId;
    } catch (e) {
        return false;
    }
};

const isTabActive = (tabId) => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.finance') return false;
    try {
        const url = new URL(page.url, window.location.origin);
        const tabParam = url.searchParams.get('tab');
        if (tabParam === null) {
            return tabId === 0;
        }
        return parseInt(tabParam, 10) === tabId;
    } catch (e) {
        return false;
    }
};

const isCrmTabActive = (tabId) => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.crm') return false;
    try {
        const url = new URL(page.url, window.location.origin);
        const tabParam = url.searchParams.get('tab');
        if (tabParam === null) {
            return tabId === 'dashboard';
        }
        return tabParam === tabId;
    } catch (e) {
        return false;
    }
};

const isClientsTabActive = (tabId) => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.crm') return false;
    try {
        const url = new URL(page.url, window.location.origin);
        const tabParam = url.searchParams.get('tab');
        return tabParam === tabId;
    } catch (e) {
        return false;
    }
};

const isFacilityTabActive = (tabId) => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.facility') return false;
    try {
        const url = new URL(page.url, window.location.origin);
        const tabParam = url.searchParams.get('tab');
        return tabParam === tabId;
    } catch (e) {
        return false;
    }
};

const isFacilityAsosiyActive = () => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.facility') return false;
    try {
        const url = new URL(page.url, window.location.origin);
        const tabParam = url.searchParams.get('tab');
        const viewParam = url.searchParams.get('view');
        return tabParam === null && viewParam === null;
    } catch (e) {
        return true;
    }
};

const isFacilityViewActive = (view, objectName = null) => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.facility') return false;
    try {
        const url = new URL(page.url, window.location.origin);
        const viewParam = url.searchParams.get('view');
        if (view === 'object_detail' && objectName) {
            const objectParam = url.searchParams.get('object');
            return viewParam === view && objectParam === objectName;
        }
        return viewParam === view;
    } catch (e) {
        return false;
    }
};

const currentActiveObject = ref(null);
const availableObjects = ref([]);
const isObjectsListOpen = ref(true);

const updateSidebarState = () => {
    if (typeof window !== 'undefined') {
        currentActiveObject.value = localStorage.getItem('fac_current_object');
        try {
            const objs = localStorage.getItem('fac_available_objects');
            if (objs) {
                availableObjects.value = JSON.parse(objs);
            }
        } catch (e) {
            console.error('Error parsing fac_available_objects', e);
        }
    }
};

const isHrTabActive = (tabId) => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.hr') return false;
    try {
        const url = new URL(page.url, window.location.origin);
        const tabParam = url.searchParams.get('tab');
        if (tabParam === null) {
            return tabId === 0;
        }
        return parseInt(tabParam, 10) === tabId;
    } catch (e) {
        return false;
    }
};

const navItems = [
    { name: 'dashboard', route: 'admin.dashboard', icon: '⬡' },
    { name: 'crm', route: 'admin.crm', icon: '🛒' },
    { name: 'clients_database', route: 'admin.crm', icon: '👥' },
    { name: 'facility_manager', route: 'admin.facility', icon: '🏗️' },
    { name: 'finance_core', route: 'admin.finance', icon: '🏦' },
    { name: 'partner_hub', route: 'admin.partners', icon: '🤝' },
    { name: 'Taminotchilar', route: 'admin.warehouse.index', icon: '📦' },
    { name: 'hr_payroll', route: 'admin.hr', icon: '👥' },
    { name: 'settings', route: 'admin.settings.index', icon: '⚙️' },
    { name: 'Developer', route: 'admin.developer.settings', icon: '🛠️' },
];

const hasPerm = (perm) => {
    const user = page.props.auth?.user;
    if (!user) return false;
    const rawRoles = user.roles || [];
    const roles = rawRoles.map(r => typeof r === 'string' ? r : r.name);
    if (roles.includes('Super_Admin') || roles.includes('Admin')) return true;
    const permissions = user.permissions || [];
    return permissions.includes(perm);
};

const filteredNavItems = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return [];
    
    const rawRoles = user.roles || [];
    const roles = rawRoles.map(r => typeof r === 'string' ? r : r.name);

    return navItems.filter(item => {
        if (item.name === 'dashboard') return hasPerm('access dashboard');
        if (item.name === 'crm' || item.name === 'clients_database') return hasPerm('access clients');
        if (item.name === 'facility_manager') return hasPerm('access objects');
        if (item.name === 'finance_core') return hasPerm('access finance');
        if (item.name === 'partner_hub') return hasPerm('access finance');
        if (item.name === 'Taminotchilar') return hasPerm('access objects') || hasPerm('access finance');
        if (item.name === 'hr_payroll') return hasPerm('access hr');
        if (item.name === 'settings') return hasPerm('access settings');
        if (item.name === 'Developer') return roles.includes('Super_Admin');
        return false;
    });
});

const currentNav = computed(() => {
    const currentRouteName = route().current();
    if (!currentRouteName) return null;
    return navItems.find(item => currentRouteName.startsWith(item.route.replace('.index', '')));
});

let refreshInterval = null;
let navigateUnregister = null;

onMounted(() => {
    updateSidebarState();
    if (typeof window !== 'undefined') {
        window.addEventListener('storage', updateSidebarState);
    }
    navigateUnregister = router.on('navigate', () => {
        updateSidebarState();
    });

    const currentRoute = route().current();
    if (currentRoute) {
        if (currentRoute.includes('finance') || currentRoute.includes('approvals')) {
            isFinanceMenuOpen.value = true;
        }
        if (currentRoute.includes('crm') || currentRoute.includes('telephony') || currentRoute.includes('ai_agents')) {
            try {
                const url = new URL(page.url, window.location.origin);
                const tabParam = url.searchParams.get('tab') || 'dashboard';
                if (['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist'].includes(tabParam)) {
                    isClientsMenuOpen.value = true;
                } else {
                    isCrmMenuOpen.value = true;
                }
            } catch (e) {
                isCrmMenuOpen.value = true;
            }
        }
        if (currentRoute.includes('facility')) {
            isFacilityMenuOpen.value = true;
        }
        if (currentRoute.includes('hr')) {
            isHrMenuOpen.value = true;
        }
        if (currentRoute.includes('settings')) {
            isSettingsMenuOpen.value = true;
        }
    }
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    if (typeof window !== 'undefined') {
        window.removeEventListener('storage', updateSidebarState);
    }
    if (navigateUnregister) {
        navigateUnregister();
    }
});

const formattedDate = computed(() => {
    const days = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
    const now = new Date();
    const dayName = days[now.getDay()];
    const day = String(now.getDate()).padStart(2, '0');
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();
    return `${dayName}, ${day}.${month}.${year}`;
});
</script>

<template>
    <ThemeProvider>
        <div class="min-h-screen bg-gray-50 text-gray-900 flex relative overflow-hidden font-sans">
        
        <!-- Mobile Overlay -->
        <div v-if="isMobileMenuOpen" @click="isMobileMenuOpen = false" class="fixed inset-0 bg-gray-500/50  z-40 md:hidden"></div>

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
                    <!-- Custom nested menu for finance_core -->
                    <div v-if="item.name === 'finance_core'" class="space-y-1" :key="item.name + '-group'">
                        <button
                            @click="isFinanceMenuOpen = !isFinanceMenuOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-blue-50 border border-transparent hover:border-blue-100 text-left"
                            :class="(route().current('admin.finance') || route().current('admin.approvals')) ? 'bg-blue-50/70 border-blue-100' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-lg text-gray-500 group-hover:text-blue-600 transition-colors" :class="(route().current('admin.finance') || route().current('admin.approvals')) ? 'text-blue-600' : ''">{{ item.icon }}</span>
                                <span class="text-sm font-medium text-gray-600 group-hover:text-blue-700 transition-colors" :class="(route().current('admin.finance') || route().current('admin.approvals')) ? 'text-blue-700 font-semibold' : ''">{{ $t(item.name) }}</span>
                            </div>
                            <svg
                                class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-transform duration-200"
                                :class="isFinanceMenuOpen ? 'rotate-180' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <div v-show="isFinanceMenuOpen" class="pl-4 space-y-1 transition-all duration-300 border-l border-gray-100 ml-5">
                            <!-- Boshqaruv paneli -->
                            <Link
                                :href="route('admin.finance', { tab: 0 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isTabActive(0) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                📊 Boshqaruv paneli
                            </Link>
                            
                            <!-- Harakatlar Markazi -->
                            <Link
                                :href="route('admin.finance', { tab: 1 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isTabActive(1) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                ⚖️ Harakatlar Markazi
                            </Link>
                            <!-- Kassalar -->
                            <Link
                                :href="route('admin.finance', { tab: 8 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isTabActive(8) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                🏦 Kassalar va Banklar
                            </Link>

                            <!-- Kontragentlar -->
                            <Link
                                :href="route('admin.finance', { tab: 9 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isTabActive(9) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                👥 Kontragentlar
                            </Link>

                            <!-- Hisob-fakturalar -->
                            <Link
                                :href="route('admin.finance', { tab: 10 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isTabActive(10) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                📄 Hisob-fakturalar
                            </Link>

                            <!-- Tranzaksiya Yaratish -->
                            <Link
                                v-if="hasPerm('access transactions')"
                                :href="route('admin.finance', { tab: 3 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isTabActive(3) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                💸 Tranzaksiya Yaratish
                            </Link>
                            
                            <!-- Qarzlar -->
                            <Link
                                v-if="hasPerm('access debtors')"
                                :href="route('admin.finance', { tab: 4 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isTabActive(4) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                📉 Qarzlar
                            </Link>
                            
                            <!-- Ta'minotchilar -->
                            <Link
                                :href="route('admin.finance', { tab: 5 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isTabActive(5) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                🏢 Ta'minotchilar
                            </Link>
                            
                            <!-- O'tkazmalar -->
                            <Link
                                :href="route('admin.finance', { tab: 6 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isTabActive(6) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                🔄 O'tkazmalar
                            </Link>
                            
                            <!-- Hisobot (Kategoriya) -->
                            <Link
                                v-if="hasPerm('access reports')"
                                :href="route('admin.finance', { tab: 7 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isTabActive(7) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                📊 Hisobot (Kategoriya)
                            </Link>
                            
                            <!-- Tasdiqlar -->
                            <Link
                                :href="route('admin.approvals')"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="route().current('admin.approvals') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                ✔️ Tasdiqlar
                            </Link>
                            
                            <!-- Jurnal -->
                            <Link
                                v-if="hasPerm('access transactions')"
                                :href="route('admin.finance', { tab: 2 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isTabActive(2) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                📜 Jurnal
                            </Link>
                        </div>
                    </div>

                    <!-- Custom nested menu for crm -->
                    <div v-else-if="item.name === 'crm'" class="space-y-1" :key="item.name + '-group'">
                        <button
                            @click="isCrmMenuOpen = !isCrmMenuOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-blue-50 border border-transparent hover:border-blue-100 text-left"
                            :class="((route().current('admin.crm') && !['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist'].includes($page.url.split('tab=')[1]?.split('&')[0])) || route().current('admin.telephony.index') || route().current('admin.ai_agents')) ? 'bg-blue-50/70 border-blue-100' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-lg text-gray-500 group-hover:text-blue-600 transition-colors" :class="((route().current('admin.crm') && !['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist'].includes($page.url.split('tab=')[1]?.split('&')[0])) || route().current('admin.telephony.index') || route().current('admin.ai_agents')) ? 'text-blue-600' : ''">{{ item.icon }}</span>
                                <span class="text-sm font-medium text-gray-600 group-hover:text-blue-700 transition-colors" :class="((route().current('admin.crm') && !['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist'].includes($page.url.split('tab=')[1]?.split('&')[0])) || route().current('admin.telephony.index') || route().current('admin.ai_agents')) ? 'text-blue-700 font-semibold' : ''">CRM</span>
                            </div>
                            <svg
                                class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-transform duration-200"
                                :class="isCrmMenuOpen ? 'rotate-180' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <div v-show="isCrmMenuOpen" class="pl-4 space-y-1 transition-all duration-300 border-l border-gray-100 ml-5">
                            <!-- Dashboard -->
                            <Link
                                :href="route('admin.crm', { tab: 'dashboard' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isCrmTabActive('dashboard') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                📊 Dashboard
                            </Link>

                            <!-- Lidlar -->
                            <Link
                                :href="route('admin.crm', { tab: 'leads' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isCrmTabActive('leads') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                🎯 Lidlar
                            </Link>
                            
                            <!-- Postlar -->
                            <Link
                                :href="route('admin.crm', { tab: 'posts' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isCrmTabActive('posts') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                📝 Postlar
                            </Link>

                            <!-- Shartnomalar -->
                            <Link
                                :href="route('admin.crm', { tab: 'contracts' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isCrmTabActive('contracts') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                📄 Shartnomalar
                            </Link>
                            
                            <!-- Qarzdorlik radari -->
                            <Link
                                v-if="hasPerm('access debtors')"
                                :href="route('admin.crm', { tab: 'overdue' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isCrmTabActive('overdue') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                ⏳ Qarzdorlar radari
                            </Link>

                            <!-- Sozlamalar -->
                            <Link
                                :href="route('admin.crm', { tab: 'settings' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isCrmTabActive('settings') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                ⚙️ Sozlamalar
                            </Link>

                            <!-- SMS -->
                            <Link
                                :href="route('admin.ai_agents')"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="route().current('admin.ai_agents') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                💬 SMS
                            </Link>

                            <!-- IP Telefoniya -->
                            <Link
                                :href="route('admin.telephony.index')"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="route().current('admin.telephony.index') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                📞 IP Telefoniya
                            </Link>
                        </div>
                    </div>

                    <!-- Custom nested menu for clients_database (Mijozlar bazasi) -->
                    <div v-else-if="item.name === 'clients_database'" class="space-y-1" :key="item.name + '-group'">
                        <button
                            @click="isClientsMenuOpen = !isClientsMenuOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-blue-50 border border-transparent hover:border-blue-100 text-left"
                            :class="(route().current('admin.crm') && ['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist'].includes($page.url.split('tab=')[1]?.split('&')[0])) ? 'bg-blue-50/70 border-blue-100' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-lg text-gray-500 group-hover:text-blue-600 transition-colors" :class="(route().current('admin.crm') && ['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist'].includes($page.url.split('tab=')[1]?.split('&')[0])) ? 'text-blue-600' : ''">{{ item.icon }}</span>
                                <span class="text-sm font-medium text-gray-600 group-hover:text-blue-700 transition-colors" :class="(route().current('admin.crm') && ['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist'].includes($page.url.split('tab=')[1]?.split('&')[0])) ? 'text-blue-700 font-semibold' : ''">Mijozlar bazasi</span>
                            </div>
                            <svg
                                class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-transform duration-200"
                                :class="isClientsMenuOpen ? 'rotate-180' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <div v-show="isClientsMenuOpen" class="pl-4 space-y-1 transition-all duration-300 border-l border-gray-100 ml-5">
                            <!-- Yangi mijozlar -->
                            <Link
                                :href="route('admin.crm', { tab: 'clients_new' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isClientsTabActive('clients_new') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                🟢 Yangi mijozlar
                            </Link>

                            <!-- Mijozlar -->
                            <Link
                                :href="route('admin.crm', { tab: 'clients' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isClientsTabActive('clients') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                👥 Mijozlar
                            </Link>
                            
                            <!-- Faol mijozlar -->
                            <Link
                                :href="route('admin.crm', { tab: 'clients_active' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isClientsTabActive('clients_active') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                🤝 Faol mijozlar
                            </Link>

                            <!-- Qarzdor mijozlar -->
                            <Link
                                v-if="hasPerm('access debtors')"
                                :href="route('admin.crm', { tab: 'clients_debtors' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isClientsTabActive('clients_debtors') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                ⏳ Qarzdor mijozlar
                            </Link>
                            
                            <!-- Qora ro'yxat -->
                            <Link
                                :href="route('admin.crm', { tab: 'clients_blacklist' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isClientsTabActive('clients_blacklist') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                ❌ Qora ro'yxat
                            </Link>
                        </div>
                    </div>

                    <!-- Custom nested menu for facility_manager -->
                    <div v-else-if="item.name === 'facility_manager'" class="space-y-1" :key="item.name + '-group'">
                        <button
                            @click="isFacilityMenuOpen = !isFacilityMenuOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-blue-50 border border-transparent hover:border-blue-100 text-left"
                            :class="route().current('admin.facility') ? 'bg-blue-50/70 border-blue-100' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-lg text-gray-500 group-hover:text-blue-600 transition-colors" :class="route().current('admin.facility') ? 'text-blue-600' : ''">{{ item.icon }}</span>
                                <span class="text-sm font-medium text-gray-600 group-hover:text-blue-700 transition-colors" :class="route().current('admin.facility') ? 'text-blue-700 font-semibold' : ''">{{ $t(item.name) }}</span>
                            </div>
                            <svg
                                class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-transform duration-200"
                                :class="isFacilityMenuOpen ? 'rotate-180' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <div v-show="isFacilityMenuOpen" class="pl-4 space-y-1 transition-all duration-300 border-l border-gray-100 ml-5">
                            <!-- Asosiy -->
                            <Link
                                :href="route('admin.facility')"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isFacilityAsosiyActive() ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                🏠 Asosiy
                            </Link>

                            <!-- Obyektlar Toggle and Link -->
                            <div class="space-y-1">
                                <div
                                    class="flex items-center justify-between rounded-lg hover:bg-blue-50/50 transition-colors"
                                    :class="isFacilityViewActive('objects') ? 'bg-blue-50/50 border border-blue-100/30' : ''"
                                >
                                    <Link
                                        :href="route('admin.facility', { view: 'objects' })"
                                        class="flex-1 flex items-center gap-2 py-2 px-3 text-xs font-medium text-gray-500 hover:text-blue-600 transition-colors"
                                        :class="isFacilityViewActive('objects') ? 'text-blue-600 font-semibold' : ''"
                                    >
                                        🏢 Obyektlar
                                    </Link>
                                    <button
                                        @click="isObjectsListOpen = !isObjectsListOpen"
                                        class="p-2 text-gray-400 hover:text-blue-600 focus:outline-none shrink-0"
                                    >
                                        <svg
                                            class="w-3 h-3 transition-transform duration-200"
                                            :class="isObjectsListOpen ? 'rotate-180' : ''"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>
                                
                                <!-- Collapsible list of available objects -->
                                <div v-show="isObjectsListOpen" class="pl-3 space-y-1 transition-all duration-300 border-l border-gray-100 ml-4">
                                    <template v-for="obj in (availableObjects.length > 0 ? availableObjects : ['SHER-DOR TJM'])" :key="obj">
                                        <Link
                                            :href="route('admin.facility', { view: 'object_detail', object: obj })"
                                            class="flex items-center gap-2 py-1.5 px-3 rounded-lg text-[11px] font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                            :class="isFacilityViewActive('object_detail', obj) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                                        >
                                            🏗️ {{ obj }}
                                        </Link>
                                    </template>
                                </div>
                            </div>

                            <!-- Bloklar -->
                            <Link
                                :href="route('admin.facility', { view: 'blocks' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isFacilityViewActive('blocks') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                📦 Bloklar
                            </Link>
                        </div>
                    </div>

                    <!-- Custom nested menu for hr_payroll -->
                    <div v-else-if="item.name === 'hr_payroll'" class="space-y-1" :key="item.name + '-group'">
                        <button
                            @click="isHrMenuOpen = !isHrMenuOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-blue-50 border border-transparent hover:border-blue-100 text-left"
                            :class="route().current('admin.hr') ? 'bg-blue-50/70 border-blue-100' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-lg text-gray-500 group-hover:text-blue-600 transition-colors" :class="route().current('admin.hr') ? 'text-blue-600' : ''">{{ item.icon }}</span>
                                <span class="text-sm font-medium text-gray-600 group-hover:text-blue-700 transition-colors" :class="route().current('admin.hr') ? 'text-blue-700 font-semibold' : ''">HRM</span>
                            </div>
                            <svg
                                class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-transform duration-200"
                                :class="isHrMenuOpen ? 'rotate-180' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <div v-show="isHrMenuOpen" class="pl-4 space-y-1 transition-all duration-300 border-l border-gray-100 ml-5">
                            <!-- Xodimlar -->
                            <Link
                                :href="route('admin.hr', { tab: 0 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isHrTabActive(0) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                👥 Xodimlar
                            </Link>
                            
                            <!-- Maoshlar -->
                            <Link
                                :href="route('admin.hr', { tab: 1 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isHrTabActive(1) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                💸 Maoshlar
                            </Link>
                            
                            <!-- KPI -->
                            <Link
                                :href="route('admin.hr', { tab: 2 })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isHrTabActive(2) ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                📈 KPI
                            </Link>
                        </div>
                    </div>

                    <!-- Custom nested menu for settings -->
                    <div v-else-if="item.name === 'settings'" class="space-y-1" :key="item.name + '-group'">
                        <button
                            @click="isSettingsMenuOpen = !isSettingsMenuOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-blue-50 border border-transparent hover:border-blue-100 text-left"
                            :class="route().current('admin.settings.index') ? 'bg-blue-50/70 border-blue-100' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-lg text-gray-500 group-hover:text-blue-600 transition-colors" :class="route().current('admin.settings.index') ? 'text-blue-600' : ''">{{ item.icon }}</span>
                                <span class="text-sm font-medium text-gray-600 group-hover:text-blue-700 transition-colors" :class="route().current('admin.settings.index') ? 'text-blue-700 font-semibold' : ''">{{ $t(item.name) }}</span>
                            </div>
                            <svg
                                class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-transform duration-200"
                                :class="isSettingsMenuOpen ? 'rotate-180' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <div v-show="isSettingsMenuOpen" class="pl-4 space-y-1 transition-all duration-300 border-l border-gray-100 ml-5">
                            <!-- Brending va Tashqi Ko'rinish -->
                            <Link
                                :href="route('admin.settings.index', { tab: 'branding' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isSettingsTabActive('branding') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                🎨 Brending va Tashqi Ko'rinish
                            </Link>

                            <!-- Financial Defaults -->
                            <Link
                                :href="route('admin.settings.index', { tab: 'financial' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isSettingsTabActive('financial') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                🏦 Financial Defaults
                            </Link>

                            <!-- Profil va Dizayn -->
                            <Link
                                :href="route('admin.settings.index', { tab: 'profile' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isSettingsTabActive('profile') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                👤 Profil va Dizayn
                            </Link>

                            <!-- Manbalar (Lead Sources) -->
                            <Link
                                :href="route('admin.settings.index', { tab: 'lead_sources' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isSettingsTabActive('lead_sources') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                🎯 Manbalar (Lead Sources)
                            </Link>

                            <!-- SMS Xabarnoma -->
                            <Link
                                :href="route('admin.settings.index', { tab: 'sms_broadcast' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isSettingsTabActive('sms_broadcast') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                💬 SMS Xabarnoma
                            </Link>

                            <!-- Xodimlar (Users) -->
                            <Link
                                :href="route('admin.settings.index', { tab: 'users_management' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isSettingsTabActive('users_management') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                👥 Xodimlar (Users)
                            </Link>

                            <!-- Rollar va Ruxsatlar -->
                            <Link
                                :href="route('admin.settings.index', { tab: 'roles_permissions' })"
                                class="flex items-center gap-2 py-2 px-3 rounded-lg text-xs font-medium text-gray-500 hover:text-blue-600 hover:bg-blue-50/50 transition-colors"
                                :class="isSettingsTabActive('roles_permissions') ? 'text-blue-600 bg-blue-50/50 font-semibold border border-blue-100/30' : ''"
                            >
                                🔐 Rollar va Ruxsatlar
                            </Link>
                        </div>
                    </div>

                    <!-- Regular Items -->
                    <Link
                        v-else
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
            <header class="h-20 bg-white border-b border-gray-100 flex items-center justify-between px-6 md:px-8 relative z-30 shrink-0 shadow-sm">
                <!-- Left side: Title/Icon -->
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="md:hidden p-2 text-gray-500 hover:text-blue-600 focus:outline-none shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <slot name="header">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-yellow-400 flex items-center justify-center text-white shrink-0 shadow-sm border border-amber-300">
                                <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="5" />
                                    <path d="M9 3v18M15 3v18" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 leading-tight">
                                    {{ currentNav ? $t(currentNav.name) : 'Dashboard' }}
                                </h2>
                                <p class="text-xs text-gray-500 font-medium mt-0.5">{{ formattedDate }}</p>
                            </div>
                        </div>
                    </slot>
                </div>
                
                <!-- Right side: User Profile & Lang Switcher -->
                <div class="flex items-center gap-4 justify-end flex-1 shrink-0">
                    <!-- Language Switcher Directly Visible -->
                    <LanguageSwitcher />

                    <!-- User Profile Dropdown -->
                    <div class="relative group">
                        <button class="flex items-center gap-3 p-1.5 pr-3 rounded-xl hover:bg-gray-50 transition-all border border-transparent hover:border-gray-200 cursor-default">
                            <div class="w-9 h-9 rounded-full bg-amber-500 text-white font-bold flex items-center justify-center text-sm border border-amber-400 overflow-hidden shadow-sm shrink-0">
                                <img v-if="$page.props.auth?.user?.photo_path" :src="'/storage/' + $page.props.auth.user.photo_path" class="w-full h-full object-cover">
                                <span v-else>{{ $page.props.auth?.user?.name?.charAt(0).toUpperCase() }}</span>
                            </div>
                            <div class="text-right hidden sm:block">
                                <p class="text-sm font-semibold text-gray-800 leading-tight">{{ $page.props.auth?.user?.name }}</p>
                                <p class="text-[10px] font-medium text-gray-400 mt-0.5">{{ ($page.props.auth?.user?.roles && $page.props.auth.user.roles.length > 0) ? (typeof $page.props.auth.user.roles[0] === 'string' ? $page.props.auth.user.roles[0] : $page.props.auth.user.roles[0].name) : 'Administrator' }}</p>
                            </div>
                        </button>

                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded-xl shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform origin-top-right z-50 p-2 space-y-1">
                            <div class="flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg border border-green-200 bg-green-50/50 cursor-default">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                <span class="text-[9px] font-bold text-green-700 uppercase tracking-wider">SYSTEM ONLINE</span>
                            </div>
                            <hr class="border-gray-100 my-1">
                            <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                TIZIMDAN CHIQISH
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
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
    </ThemeProvider>
</template>
