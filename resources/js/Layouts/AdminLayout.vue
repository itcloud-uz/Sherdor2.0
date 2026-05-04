<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import GalaxyBackground from '@/Components/GalaxyBackground.vue';
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
    { name: 'Cheklar', route: 'admin.receipts', icon: '🧾' },
    { name: 'Tasdiqlar', route: 'admin.approvals', icon: '✅' },
    { name: 'ai_agents', route: 'admin.ai_agents', icon: '🤖' },
    { name: 'settings', route: 'admin.settings.index', icon: '⚙️' },
    { name: 'Developer', route: 'admin.developer.settings', icon: '🛠️' },
];
</script>

<template>
    <ThemeProvider>
        <div class="min-h-screen bg-transparent text-text-primary flex relative overflow-hidden">
            
            <GalaxyBackground />

        <!-- Ambient Glowing Orbs (No Grids!) -->
        <div class="fixed inset-0 pointer-events-none z-0">
            <div class="absolute -top-[20%] -left-[10%] w-[800px] h-[800px] rounded-full bg-[#B026FF]/10 blur-[150px]"></div>
            <div class="absolute top-[40%] -right-[10%] w-[600px] h-[600px] rounded-full bg-[#00F0FF]/10 blur-[150px]"></div>
            <div class="absolute -bottom-[20%] left-[20%] w-[700px] h-[700px] rounded-full bg-[#39FF14]/5 blur-[150px]"></div>
        </div>

        <!-- Sidebar -->
        <aside class="w-64 glass-panel border-r border-white/10 flex flex-col z-10 relative">
            <div class="h-20 flex items-center justify-center border-b border-white/5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-black/50 border border-white/10 flex items-center justify-center shadow-neon-primary overflow-hidden">
                        <img v-if="$page.props.branding?.logo_path" :src="'/storage/' + $page.props.branding.logo_path" class="w-full h-full object-contain" />
                        <span v-else class="text-neon-primary font-bold text-xl font-display">S</span>
                    </div>
                    <div>
                        <h1 class="text-sm font-display font-bold tracking-widest text-white">SHER-DOR</h1>
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

            <div class="p-4 border-t border-white/5">
                <div class="glass-card p-4 flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center border border-white/20 mb-2 overflow-hidden">
                        <img v-if="$page.props.auth.user.photo_path" :src="'/storage/' + $page.props.auth.user.photo_path" class="w-full h-full object-cover">
                        <span v-else class="text-xs font-bold text-white">{{ $page.props.auth.user.name.charAt(0) }}</span>
                    </div>
                    <p class="text-sm font-semibold text-white truncate w-full text-center">{{ $page.props.auth.user.name }}</p>
                    <p class="text-[10px] font-mono text-neon-yellow mt-1">SUPER ADMIN</p>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col z-10 h-screen overflow-hidden relative">
            <!-- Top Header -->
            <header class="h-20 glass-panel border-b border-white/10 flex items-center justify-between px-8">
                <div>
                    <h2 class="text-xl font-display font-bold text-white">{{ $t('welcome') }}, {{ $page.props.auth.user.name }}</h2>
                    <p class="text-xs font-mono text-text-muted mt-1">{{ new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                </div>
                
                <div class="flex items-center gap-6">
                    <!-- Global Language Switcher -->
                    <LanguageSwitcher />

                    <!-- Live Server Status Indicator -->
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl border border-neon-green/20 bg-neon-green/5 shadow-neon-green">
                        <span class="w-2 h-2 rounded-full bg-neon-green animate-pulse"></span>
                        <span class="text-[10px] font-mono text-neon-green uppercase tracking-wider">UBUNTU ONLINE</span>
                    </div>
                    
                    <Link :href="route('logout')" method="post" as="button" class="text-xs font-mono text-neon-magenta hover:text-white transition-colors">LOGOUT</Link>
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
