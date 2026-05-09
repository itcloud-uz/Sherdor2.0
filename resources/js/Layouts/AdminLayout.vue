<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import BackgroundTheme from '@/Components/BackgroundTheme.vue';
import ThemeProvider from '@/Components/ThemeProvider.vue';

const page = usePage();

const navItems = [
    { name: 'dashboard', route: 'admin.dashboard', icon: '⬡' },
    { name: 'sales_department', route: 'admin.crm', icon: '🛒' },
    { name: 'facility_manager', route: 'admin.facility', icon: '🏗️' },
    { name: 'finance_core', route: 'admin.finance', icon: '🏦' },
    { name: 'partner_hub', route: 'admin.partners', icon: '🤝' },
    { name: 'Omborxona', route: 'admin.warehouse.index', icon: '📦' },
    { name: 'hr_payroll', route: 'admin.hr', icon: '👥' },
    { name: 'ai_agents', route: 'admin.ai_agents', icon: '🤖' },
    { name: 'IP Telefoniya', route: 'admin.telephony.index', icon: '📞' },
    { name: 'settings', route: 'admin.settings.index', icon: '⚙️' },
    { name: 'Developer', route: 'admin.developer.settings', icon: '🛠️' },
];

let refreshInterval = null;

onMounted(() => {
    refreshInterval = setInterval(() => {
        router.reload({ preserveState: true, preserveScroll: true });
    }, 5000);
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>

<template>
    <ThemeProvider>
        <div class="min-h-screen bg-transparent text-text-primary flex relative overflow-hidden">
            
            <BackgroundTheme />

        <!-- Sidebar -->
        <aside class="w-64 glass-panel border-r border-white/10 flex flex-col z-10 relative">
            <div class="h-20 flex items-center justify-center border-b border-white/5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-black/50 border border-white/10 flex items-center justify-center shadow-neon-primary overflow-hidden">
                        <img v-if="$page.props.branding?.logo_path" :src="'/storage/' + $page.props.branding.logo_path" class="w-full h-full object-contain" />
                        <span v-else class="text-neon-primary font-bold text-xl font-display">S</span>
                    </div>
                    <div>
                        <h1 class="text-sm font-display font-bold tracking-widest text-white">{{ $t('sher_dor') }}</h1>
                        <p class="text-[9px] font-mono text-neon-primary uppercase tracking-[0.2em]">Command Center</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 py-6 px-4 space-y-2 overflow-y-auto">
                <template v-for="item in navItems" :key="item.name">
                    <Link
                        :href="route(item.route)"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 group hover:bg-white/5 border border-transparent hover:border-white/10"
                    >
                        <span class="text-lg opacity-70 group-hover:opacity-100 group-hover:text-neon-cyan">{{ item.icon }}</span>
                        <span class="text-sm font-medium text-text-secondary group-hover:text-text-primary">{{ $t(item.name) }}</span>
                    </Link>
                </template>
            </nav>


        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col z-10 h-screen overflow-hidden relative">
            <!-- Top Header -->
            <header class="h-20 glass-panel border-b border-white/10 flex items-center justify-between px-8 relative z-50">
                <div>
                    <h2 class="text-xl font-display font-bold text-white">{{ $t('welcome') }}, {{ $page.props.auth.user.name }}</h2>
                    <p class="text-xs font-mono text-text-muted mt-1">{{ new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                </div>
                
                <div class="flex items-center gap-6">
                    <!-- User Profile Dropdown -->
                    <div class="relative group">
                        <button class="flex items-center gap-3 p-2 pr-4 rounded-xl hover:bg-white/5 transition-all border border-transparent hover:border-white/10 cursor-default">
                            <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center border border-white/20 overflow-hidden shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]">
                                <img v-if="$page.props.auth.user.photo_path" :src="'/storage/' + $page.props.auth.user.photo_path" class="w-full h-full object-cover">
                                <span v-else class="text-xs font-bold text-white">{{ $page.props.auth.user.name.charAt(0) }}</span>
                            </div>
                            <div class="text-left hidden sm:block">
                                <p class="text-sm font-semibold text-white leading-tight">{{ $page.props.auth.user.name }}</p>
                                <p class="text-[10px] font-mono text-neon-yellow mt-0.5">SUPER ADMIN</p>
                            </div>
                        </button>

                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 mt-1 w-56 bg-[#050505]/95 backdrop-blur-3xl border border-white/10 rounded-2xl shadow-[0_10px_40px_rgba(0,0,0,1)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform origin-top-right z-50 p-3 space-y-3">
                            
                            <!-- Global Language Switcher -->
                            <div>
                                <p class="text-[9px] font-mono text-text-muted mb-2 uppercase px-1">Interfeys Tili</p>
                                <LanguageSwitcher />
                            </div>

                            <hr class="border-white/10">

                            <!-- Live Server Status Indicator -->
                            <div class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-neon-green/20 bg-neon-green/5 shadow-[0_0_10px_rgba(57,255,20,0.1)] cursor-default">
                                <span class="w-2 h-2 rounded-full bg-neon-green animate-pulse"></span>
                                <span class="text-[10px] font-mono text-neon-green uppercase tracking-wider">UBUNTU ONLINE</span>
                            </div>
                            
                            <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center justify-center gap-2 px-3 py-2.5 text-xs font-mono text-neon-red hover:bg-neon-red/10 border border-transparent hover:border-neon-red/20 rounded-xl transition-colors">
                                TIZIMDAN CHIQISH
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="flex-1 overflow-y-auto p-8">
                <slot />
            </div>
        </main>
    </div>
    </ThemeProvider>
</template>
