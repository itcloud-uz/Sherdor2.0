<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import BackgroundTheme from '@/Components/BackgroundTheme.vue';
import ThemeProvider from '@/Components/ThemeProvider.vue';

const page = usePage();


</script>

<template>
    <ThemeProvider>
        <div class="min-h-screen bg-transparent text-text-primary flex flex-col relative overflow-hidden">
            
            <BackgroundTheme />

        <!-- Top Navigation -->
        <header class="h-20 md:h-24 glass-panel border-b border-gray-200 flex items-center justify-between px-4 md:px-8 z-10 relative">
            <div class="flex items-center gap-4 md:gap-8">
                <div class="flex items-center gap-2 md:gap-3">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl bg-gray-500/50 border border-gray-200 flex items-center justify-center shadow-neon-primary overflow-hidden">
                        <img v-if="$page.props.branding?.logo_path" :src="'/storage/' + $page.props.branding.logo_path" class="w-full h-full object-contain" />
                        <span v-else class="text-neon-primary font-bold text-xl md:text-2xl font-display">P</span>
                    </div>
                    <div>
                        <h1 class="text-base md:text-lg font-display font-bold tracking-widest text-gray-900">PARTNER</h1>
                        <p class="text-[8px] md:text-[10px] font-mono text-neon-primary uppercase tracking-[0.2em] hidden sm:block">Consensus Hub</p>
                    </div>
                </div>


            </div>
            
            <div class="flex items-center gap-4 md:gap-6">
                <div class="hidden sm:block">
                    <LanguageSwitcher />
                </div>
                
                <div class="flex items-center gap-3 md:border-l border-gray-200 md:pl-6">
                    <div class="text-right hidden sm:block">
                        <p class="text-xs font-semibold text-gray-900">{{ $page.props.auth.user.name }}</p>
                        <p class="text-[9px] font-mono text-neon-yellow">PARTNER</p>
                    </div>
                    <!-- Avatar Link to Settings -->
                    <Link :href="route('settings.index')" class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-gray-500/50 border border-neon-primary/50 shadow-[0_0_15px_rgba(var(--color-neon-primary),0.2)] flex items-center justify-center overflow-hidden shrink-0 hover:scale-105 hover:shadow-[0_0_20px_var(--color-neon-primary)] transition-all">
                        <img v-if="$page.props.auth.user.photo_path" :src="'/storage/' + $page.props.auth.user.photo_path" class="w-full h-full object-cover" />
                        <span v-else class="text-xl">👤</span>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col z-10 overflow-y-auto relative p-4 md:p-8 max-w-7xl mx-auto w-full" scroll-region>
            <slot />
        </main>
    </div>
    </ThemeProvider>
</template>
