<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
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
const isPartnersMenuOpen = ref(false);

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

const activeFinanceTabOverride = ref(null);

const handleFinanceTabClick = (tabId, e) => {
    if (route().current('admin.finance')) {
        if (e && e.preventDefault) e.preventDefault();
        activeFinanceTabOverride.value = tabId;
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabId);
        window.history.replaceState({}, '', url.pathname + url.search);
        window.dispatchEvent(new CustomEvent('switch-finance-tab', { detail: tabId }));
    }
};

const isFinanceSubTabActive = (tabId, subVal = null) => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.finance') return false;
    if (activeFinanceTabOverride.value !== null) {
        return activeFinanceTabOverride.value === tabId;
    }
    try {
        const url = new URL(page.url, window.location.origin);
        const tabParam = url.searchParams.get('tab');
        const activeTabVal = tabParam === null ? 0 : parseInt(tabParam, 10);
        if (activeTabVal !== tabId) return false;
        
        const subParam = url.searchParams.get('sub');
        if (tabId === 10) {
            if (subVal === 'payments') return subParam === 'payments' || subParam === null;
            if (subVal === 'debts') return subParam === 'debts';
        }
        return true;
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
        return tabParam === null && (viewParam === null || viewParam === 'objects');
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

const isBlockActive = (blockId) => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.facility') return false;
    try {
        const url = new URL(page.url, window.location.origin);
        const buildingIdParam = url.searchParams.get('building_id');
        return buildingIdParam !== null && Number(buildingIdParam) === Number(blockId);
    } catch (e) {
        return false;
    }
};

const isPodyezdActive = (blockId, podyezdNumber) => {
    const currentRoute = route().current();
    if (currentRoute !== 'admin.facility') return false;
    try {
        const url = new URL(page.url, window.location.origin);
        const buildingIdParam = url.searchParams.get('building_id');
        const podyezdParam = url.searchParams.get('podyezd');
        return buildingIdParam !== null && Number(buildingIdParam) === Number(blockId) && podyezdParam !== null && Number(podyezdParam) === Number(podyezdNumber);
    } catch (e) {
        return false;
    }
};

const isPartnerTabActive = () => {
    return route().current('admin.partners');
};

const isAssetTabActive = () => {
    return route().current('admin.fixed_assets');
};

// Automatically expand active facility and block on route/url changes
watch(
    () => page.url,
    (newUrl) => {
        try {
            const url = new URL(newUrl, window.location.origin);
            const objectParam = url.searchParams.get('object');
            const buildingIdParam = url.searchParams.get('building_id');
            
            if (objectParam) {
                openFacilities.value[objectParam] = true;
            }
            if (buildingIdParam) {
                openBlocks.value[buildingIdParam] = true;
            }
        } catch (e) {
            console.error('Error auto-expanding menu items:', e);
        }
    },
    { immediate: true }
);

const currentActiveObject = ref(null);
const availableObjects = ref([]);
const isObjectsListOpen = ref(true);
const openFacilities = ref({});
const openBlocks = ref({});

const toggleFacilityMenu = (facilityName) => {
    openFacilities.value[facilityName] = !openFacilities.value[facilityName];
};

const toggleBlockMenu = (blockId) => {
    openBlocks.value[blockId] = !openBlocks.value[blockId];
};

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
            return tabId === 0 || tabId === '0';
        }
        if (tabParam === 'sales_agents') {
            return tabId === 'sales_agents';
        }
        return parseInt(tabParam, 10) === tabId;
    } catch (e) {
        return false;
    }
};

const navItems = [
    { name: 'dashboard', route: 'admin.dashboard', icon: '⬡' },
    { name: 'facility_manager', route: 'admin.facility', icon: '🏗️' },
    { name: 'crm', route: 'admin.crm', icon: '🛒' },
    { name: 'clients_database', route: 'admin.crm', icon: '👥' },
    { name: 'finance_core', route: 'admin.finance', icon: '🏦' },
    { name: 'transactions', route: 'admin.finance', icon: '💸' },
    { name: 'hr_payroll', route: 'admin.hr', icon: '👥' },
    { name: 'partner_hub', route: 'admin.partners', icon: '🤝' },
    { name: 'Asosiy Vositalar', route: 'admin.fixed_assets', icon: '📦' },
    { name: 'Taminotchilar', route: 'admin.warehouse.index', icon: '📦' },
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
        const isSellerOrOperator = roles.includes('Sotuvchi') || roles.includes('Operator');
        
        if (isSellerOrOperator) {
            // Seller/Operator should ONLY see CRM and Transactions
            if (item.name === 'crm') return true; // Always show CRM to them
            if (item.name === 'transactions') return true; // Always show Transactions to them
            return false; // Hide everything else (Admin Dashboard, Facility, Warehouse, etc.)
        }

        if (item.name === 'dashboard') return hasPerm('access dashboard');
        if (item.name === 'crm' || item.name === 'clients_database') return hasPerm('access clients');
        if (item.name === 'facility_manager') return hasPerm('access objects');
        if (item.name === 'finance_core') return hasPerm('access finance');
        if (item.name === 'transactions') return hasPerm('access transactions') && !hasPerm('access finance');
        if (item.name === 'partner_hub') return hasPerm('access finance');
        if (item.name === 'Asosiy Vositalar') return hasPerm('access finance');
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
                if (['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts', 'client_payments', 'client_payment_reports', 'overdue', 'sales_agents'].includes(tabParam)) {
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
        if (currentRoute.includes('partners') || currentRoute.includes('fixed_assets')) {
            isPartnersMenuOpen.value = true;
        }

    }

    refreshInterval = setInterval(() => {
        if (typeof document !== 'undefined' && document.visibilityState === 'visible') {
            const activeRoute = route().current();
            if (activeRoute && (
                activeRoute.startsWith('admin.crm') || 
                activeRoute.startsWith('admin.finance') || 
                activeRoute.startsWith('admin.hr') || 
                activeRoute.startsWith('admin.facility') ||
                activeRoute.startsWith('admin.dashboard')
            )) {
                router.reload({
                    preserveScroll: true,
                    preserveState: true
                });
            }
        }
    }, 30000); // 30 seconds
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
        <aside :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'" class="fixed md:relative w-[260px] h-full bg-white border-r border-gray-200 flex flex-col z-50 transition-transform duration-300 shadow-sm">
            <div class="h-20 flex items-center justify-center border-b border-gray-100 px-4">
                <div class="flex items-center gap-3 w-full">
                    <div class="w-12 h-12 rounded-full border-2 border-[#dfb15b] bg-[#090b10] flex items-center justify-center overflow-hidden shrink-0 shadow-sm">
                        <img v-if="$page.props.branding?.logo_path" :src="'/storage/' + $page.props.branding.logo_path" class="w-full h-full object-cover" />
                        <img v-else src="/logo-sherdor.png" class="w-full h-full object-cover scale-110" />
                    </div>
                    <div class="truncate">
                        <h1 class="text-base font-sans font-bold tracking-wider text-slate-800 uppercase leading-none">{{ $page.props.branding?.app_name || 'SHER-DOR' }}</h1>
                        <p class="text-[9px] font-sans font-semibold text-slate-400 uppercase tracking-[0.18em] leading-none mt-1">ERP SYSTEM</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 py-6 px-4 space-y-1 overflow-y-auto">
                <template v-for="item in filteredNavItems" :key="item.name">
                    <!-- Custom nested menu for finance_core -->
                    <div v-if="item.name === 'finance_core'" class="space-y-1" :key="item.name + '-group'">
                        <button
                            @click="isFinanceMenuOpen = !isFinanceMenuOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-slate-50 border border-transparent text-left"
                            :class="route().current('admin.finance') ? 'bg-blue-50 text-blue-600 font-semibold' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-[16px] text-slate-500 group-hover:text-slate-800 transition-colors font-bold" :class="route().current('admin.finance') ? 'text-blue-600' : ''">$</span>
                                <span class="text-[13px] font-medium text-slate-500 group-hover:text-blue-700 transition-colors" :class="route().current('admin.finance') ? 'text-blue-700 font-semibold' : ''">Moliya</span>
                            </div>
                            <svg
                                class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-transform duration-200"
                                :class="isFinanceMenuOpen ? 'rotate-180' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <div v-show="isFinanceMenuOpen" class="pl-4 pr-1 py-1 space-y-1.5 transition-all duration-300 ml-1">
                            <!-- Kassalar -->
                            <Link
                                :href="route('admin.finance', { tab: 8 })"
                                @click="handleFinanceTabClick(8, $event)"
                                preserve-state
                                preserve-scroll
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isFinanceSubTabActive(8)
                                    ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span>
                                Kassalar
                            </Link>

                            <!-- Tranzaksiyalar -->
                            <Link
                                :href="route('admin.finance', { tab: 2 })"
                                @click="handleFinanceTabClick(2, $event)"
                                preserve-state
                                preserve-scroll
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isFinanceSubTabActive(2, 'transactions')
                                    ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span>
                                Tranzaksiyalar
                            </Link>

                            <!-- Pul o'tkazmalari -->
                            <Link
                                :href="route('admin.finance', { tab: 6 })"
                                @click="handleFinanceTabClick(6, $event)"
                                preserve-state
                                preserve-scroll
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isFinanceSubTabActive(6)
                                    ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span>
                                Pul o'tkazmalari
                            </Link>

                            <!-- Botdan kirimlar -->
                            <Link
                                :href="route('admin.finance', { tab: 12 })"
                                @click="handleFinanceTabClick(12, $event)"
                                preserve-state
                                preserve-scroll
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isFinanceSubTabActive(12)
                                    ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span>
                                Botdan kirimlar
                            </Link>

                            <!-- Qo'shimcha operatsiyalar -->
                            <Link
                                :href="route('admin.finance', { tab: 1 })"
                                @click="handleFinanceTabClick(1, $event)"
                                preserve-state
                                preserve-scroll
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isFinanceSubTabActive(1)
                                    ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span>
                                Qo'shimcha operatsiyalar
                            </Link>

                            <!-- Hisobotlar -->
                            <Link
                                :href="route('admin.finance', { tab: 11 })"
                                @click="handleFinanceTabClick(11, $event)"
                                preserve-state
                                preserve-scroll
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isFinanceSubTabActive(11)
                                    ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span>
                                Hisobotlar
                            </Link>

                            <!-- Harakatlar -->
                            <Link
                                :href="route('admin.finance', { tab: 13 })"
                                @click="handleFinanceTabClick(13, $event)"
                                preserve-state
                                preserve-scroll
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isFinanceSubTabActive(13)
                                    ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span>
                                Harakatlar
                            </Link>

                            <!-- Kategoriyalar -->
                            <Link
                                :href="route('admin.finance', { tab: 14 })"
                                @click="handleFinanceTabClick(14, $event)"
                                preserve-state
                                preserve-scroll
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isFinanceSubTabActive(14)
                                    ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span>
                                Kategoriyalar
                            </Link>

                            <!-- So'rovlar -->
                            <Link
                                v-if="hasPerm('delete_transaction')"
                                :href="route('admin.finance.transaction_requests.index')"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="route().current('admin.finance.transaction_requests.index')
                                    ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span>
                                So'rovlar (Tranzaksiyalar)
                            </Link>
                        </div>
                    </div>

                    <!-- Single menu for transactions (Sotuvchi / Operator) -->
                    <Link
                        v-else-if="item.name === 'transactions'"
                        :href="route('admin.finance', { tab: 2 })"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-slate-50 border border-transparent"
                        :class="route().current('admin.finance') && $page.url.includes('tab=2') ? 'bg-blue-50 text-blue-600 font-semibold' : ''"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-[16px] text-slate-500 group-hover:text-slate-800 transition-colors font-bold" :class="route().current('admin.finance') && $page.url.includes('tab=2') ? 'text-blue-600' : ''">{{ item.icon }}</span>
                            <span class="text-[13px] font-medium text-slate-500 group-hover:text-blue-700 transition-colors" :class="route().current('admin.finance') && $page.url.includes('tab=2') ? 'text-blue-700 font-semibold' : ''">Tranzaksiyalar</span>
                        </div>
                    </Link>

                    <!-- Custom nested menu for crm -->
                    <div v-else-if="item.name === 'crm'" class="space-y-1" :key="item.name + '-group'">
                        <button
                            @click="isCrmMenuOpen = !isCrmMenuOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-slate-50 border border-transparent text-left"
                            :class="((route().current('admin.crm') && !['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts', 'client_payments', 'client_payment_reports'].includes($page.url.split('tab=')[1]?.split('&')[0])) || route().current('admin.telephony.index') || route().current('admin.ai_agents')) ? 'bg-blue-50 text-blue-600 font-semibold' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-[16px] text-slate-500 group-hover:text-slate-800 transition-colors" :class="((route().current('admin.crm') && !['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts', 'client_payments', 'client_payment_reports'].includes($page.url.split('tab=')[1]?.split('&')[0])) || route().current('admin.telephony.index') || route().current('admin.ai_agents')) ? 'text-blue-600' : ''">{{ item.icon }}</span>
                                <span class="text-[13px] font-medium text-slate-500 group-hover:text-blue-700 transition-colors" :class="((route().current('admin.crm') && !['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts', 'client_payments', 'client_payment_reports'].includes($page.url.split('tab=')[1]?.split('&')[0])) || route().current('admin.telephony.index') || route().current('admin.ai_agents')) ? 'text-blue-700 font-semibold' : ''">CRM</span>
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
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isCrmTabActive('dashboard') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span> Dashboard
                            </Link>

                            <!-- Lidlar -->
                            <Link
                                :href="route('admin.crm', { tab: 'leads' })"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isCrmTabActive('leads') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="text-[14px]">🎯</span> Lidlar
                            </Link>
                            
                            <!-- Postlar -->
                            <Link
                                :href="route('admin.crm', { tab: 'posts' })"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isCrmTabActive('posts') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="text-[14px]">📝</span> Postlar
                            </Link>

                            <!-- Telefon / IP Telefoniya -->
                            <Link
                                :href="route('admin.telephony.index')"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="route().current('admin.telephony.index') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="text-[14px]">📞</span> Telefon
                            </Link>

                            <!-- SMS -->
                            <Link
                                :href="route('admin.ai_agents')"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="route().current('admin.ai_agents') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="text-[14px]">💬</span> SMS
                            </Link>

                            <!-- Shou room -->
                            <Link
                                :href="route('admin.crm.showroom')"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="route().current('admin.crm.showroom') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="text-[14px]">🏢</span> Shou room
                            </Link>

                            <!-- Profil sozlamalari -->
                            <Link
                                :href="route('profile.edit')"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="route().current('profile.edit') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="text-[14px]">⚙️</span> Profil sozlamalari
                            </Link>
                        </div>
                    </div>

                    <!-- Custom nested menu for clients_database (Sotuv bo'limi) -->
                    <div v-else-if="item.name === 'clients_database'" class="space-y-1" :key="item.name + '-group'">
                        <button
                            @click="isClientsMenuOpen = !isClientsMenuOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-slate-50 border border-transparent text-left"
                            :class="(route().current('admin.crm') && ['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts', 'client_payments', 'client_payment_reports', 'overdue', 'sales_agents'].includes($page.url.split('tab=')[1]?.split('&')[0])) ? 'bg-blue-50 text-blue-600 font-semibold' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-[16px] text-slate-500 group-hover:text-slate-800 transition-colors" :class="(route().current('admin.crm') && ['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts', 'client_payments', 'client_payment_reports', 'overdue', 'sales_agents'].includes($page.url.split('tab=')[1]?.split('&')[0])) ? 'text-blue-600' : ''">💼</span>
                                <span class="text-[13px] font-medium text-slate-500 group-hover:text-blue-700 transition-colors" :class="(route().current('admin.crm') && ['clients_new', 'clients', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts', 'client_payments', 'client_payment_reports', 'overdue', 'sales_agents'].includes($page.url.split('tab=')[1]?.split('&')[0])) ? 'text-blue-700 font-semibold' : ''">Sotuv bo'limi</span>
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
                            <!-- Sotuv xodimlari -->
                            <Link
                                :href="route('admin.crm', { tab: 'sales_agents' })"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isClientsTabActive('sales_agents') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span> Sotuv xodimlari
                            </Link>

                            <!-- Mijozlar -->
                            <Link
                                :href="route('admin.crm', { tab: 'clients' })"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="['clients', 'clients_new', 'clients_active', 'clients_debtors', 'clients_blacklist'].includes($page.url.split('tab=')[1]?.split('&')[0]) ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                👥 Mijozlar
                            </Link>

                            <!-- Bekor qilingan shartnomalar -->
                            <Link
                                :href="route('admin.crm', { tab: 'canceled' })"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isClientsTabActive('canceled') ? 'bg-red-50 text-red-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                ❌ Bekor qilingan shartnomalar
                            </Link>



                            <!-- Shartnoma shablonlari -->
                            <Link
                                :href="route('admin.contract-templates.index')"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="$page.component === 'Admin/ContractTemplates/Index' ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                📝 Shablonlar
                            </Link>

                            <!-- Mijoz to'lovlari -->
                            <Link
                                :href="route('admin.crm', { tab: 'client_payments' })"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isClientsTabActive('client_payments') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                💳 Mijoz to'lovlari
                            </Link>

                            <!-- Mijoz to'lov hisobotlari -->
                            <Link
                                :href="route('admin.crm', { tab: 'client_payment_reports' })"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isClientsTabActive('client_payment_reports') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                📊 To'lov hisobotlari
                            </Link>

                            <!-- Qarzdorlar radari -->
                            <Link
                                v-if="hasPerm('access debtors')"
                                :href="route('admin.crm', { tab: 'overdue' })"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isClientsTabActive('overdue') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                ⏳ Qarzdorlar
                            </Link>
                        </div>
                    </div>

                    <!-- Custom nested menu for facility_manager -->
                    <div v-else-if="item.name === 'facility_manager'" class="space-y-1" :key="item.name + '-group'">
                        <button
                            @click="isFacilityMenuOpen = !isFacilityMenuOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-slate-50 border border-transparent text-left"
                            :class="route().current('admin.facility') ? 'bg-blue-50 text-blue-600 font-semibold' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-[16px] text-slate-500 group-hover:text-slate-800 transition-colors" :class="route().current('admin.facility') ? 'text-blue-600' : ''">{{ item.icon }}</span>
                                <span class="text-[13px] font-medium text-slate-500 group-hover:text-blue-700 transition-colors" :class="route().current('admin.facility') ? 'text-blue-700 font-semibold' : ''">{{ $t(item.name) }}</span>
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
                            <!-- Obyektlar Toggle and Nested Collapsible Tree -->
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
                                </div>
                            </div>
                            
                            <!-- Obyektlar hisoboti -->
                            <div class="space-y-1 mt-1">
                                <div
                                    class="flex items-center justify-between rounded-lg hover:bg-blue-50/50 transition-colors"
                                    :class="isFacilityViewActive('report') ? 'bg-blue-50/50 border border-blue-100/30' : ''"
                                >
                                    <Link
                                        :href="route('admin.facility', { view: 'report' })"
                                        class="flex-1 flex items-center gap-2 py-2 px-3 text-xs font-medium text-gray-500 hover:text-blue-600 transition-colors"
                                        :class="isFacilityViewActive('report') ? 'text-blue-600 font-semibold' : ''"
                                    >
                                        📊 Obyektlar hisoboti
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom nested menu for hr_payroll -->
                    <div v-else-if="item.name === 'hr_payroll'" class="space-y-1" :key="item.name + '-group'">
                        <button
                            @click="isHrMenuOpen = !isHrMenuOpen"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-slate-50 border border-transparent text-left"
                            :class="route().current('admin.hr') ? 'bg-blue-50 text-blue-600 font-semibold' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-[16px] text-slate-500 group-hover:text-slate-800 transition-colors" :class="route().current('admin.hr') ? 'text-blue-600' : ''">{{ item.icon }}</span>
                                <span class="text-[13px] font-medium text-slate-500 group-hover:text-blue-700 transition-colors" :class="route().current('admin.hr') ? 'text-blue-700 font-semibold' : ''">HRM</span>
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
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isHrTabActive(0) ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span> Xodimlar
                            </Link>
                            
                            <!-- Sotuv xodimlari -->
                            <Link
                                :href="route('admin.hr', { tab: 'sales_agents' })"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isHrTabActive('sales_agents') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span> Sotuv xodimlari
                            </Link>
                            
                            <!-- Maoshlar -->
                            <Link
                                :href="route('admin.hr', { tab: 1 })"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isHrTabActive(1) ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span> Maoshlar
                            </Link>
                            
                            <!-- KPI -->
                            <Link
                                :href="route('admin.hr', { tab: 2 })"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="isHrTabActive(2) ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span> KPI
                            </Link>

                            <!-- Qurilish Ish Haqi -->
                            <Link
                                :href="route('admin.hr.qurilish_ish_haqi')"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="route().current('admin.hr.qurilish_ish_haqi') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span> Qurilish ish haqi
                            </Link>

                            <!-- Texnikalar -->
                            <Link
                                :href="route('admin.hr.texnikalar')"
                                class="flex items-center gap-2 py-2 pl-8 pr-3 rounded-lg text-[12px] font-normal transition-all duration-200"
                                :class="route().current('admin.hr.texnikalar') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                            >
                                <span class="w-[4px] h-[4px] rounded-full flex-shrink-0 bg-current opacity-60 mr-1"></span> Texnikalar
                            </Link>
                        </div>
                    </div>

                    <!-- Regular Items -->
                    <Link
                        v-else
                        :href="route(item.route)"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors duration-200 group hover:bg-slate-50 border border-transparent"
                        :class="currentNav?.name === item.name ? 'bg-blue-50 text-blue-600 font-semibold' : ''"
                    >
                        <span class="text-[16px] text-slate-500 group-hover:text-slate-800 transition-colors" :class="currentNav?.name === item.name ? 'text-blue-600' : ''">{{ item.icon }}</span>
                        <span class="text-[13px] font-medium text-slate-500 group-hover:text-blue-700 transition-colors" :class="currentNav?.name === item.name ? 'text-blue-700 font-semibold' : ''">{{ $t(item.name) }}</span>
                    </Link>
                </template>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
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
            <div class="flex-1 overflow-y-auto p-4 md:p-8 bg-gray-50" scroll-region>
                <slot />
            </div>
        </main>
    </div>
    </ThemeProvider>
</template>
