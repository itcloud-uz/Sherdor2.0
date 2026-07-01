<script setup>
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

        <!-- Top Navigation (Header) -->
        <header class="h-20 md:h-24 glass-panel border-b border-gray-200 flex items-center justify-between px-4 md:px-8 z-50 relative">
            <div class="flex items-center gap-4 md:gap-8">
                <div class="flex items-center gap-2 md:gap-3">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl bg-gray-500/50 border border-gray-200 flex items-center justify-center shadow-neon-primary overflow-hidden">
                        <img v-if="$page.props.branding?.logo_path" :src="'/storage/' + $page.props.branding.logo_path" class="w-full h-full object-contain" />
                        <span v-else class="text-neon-primary font-bold text-xl md:text-2xl font-display">O</span>
                    </div>
                    <div>
                        <h1 class="text-base md:text-lg font-display font-bold tracking-widest text-gray-900">OPERATOR</h1>
                        <p class="text-[8px] md:text-[10px] font-mono text-neon-primary uppercase tracking-[0.2em] hidden sm:block">Action Hub</p>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-4 md:gap-6">
                <div class="hidden sm:block">
                    <LanguageSwitcher />
                </div>
                
                <!-- Profile Avatar -->
                <div class="relative group">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-full border border-gray-300 overflow-hidden cursor-pointer">
                        <img v-if="$page.props.auth.user.photo_path" :src="'/storage/' + $page.props.auth.user.photo_path" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center bg-gray-500/50">
                            <span class="text-gray-900 font-bold">{{ $page.props.auth.user.name.charAt(0) }}</span>
                        </div>
                    </div>

                    <!-- Dropdown settings/logout -->
                    <div class="absolute right-0 mt-2 w-48 bg-[#0B1120] border border-gray-300 rounded-xl shadow-[0_10px_40px_rgba(0,0,0,1)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform origin-top-right z-50">
                        <div class="p-3 border-b border-gray-200">
                            <p class="text-sm text-gray-900 font-bold truncate">{{ $page.props.auth.user.name }}</p>
                            <p class="text-[10px] text-neon-cyan font-mono truncate">Operator</p>
                        </div>
                        <div class="p-2 space-y-1">
                            <Link :href="route('settings.index')" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-text-secondary hover:text-gray-900 hover:bg-gray-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $t('settings') }}
                            </Link>
                            <Link :href="route('logout')" method="post" as="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-neon-red hover:bg-neon-red/10 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                {{ $t('logout') }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content (Full Width) -->
        <main class="flex-1 overflow-y-auto overflow-x-hidden relative z-0 p-4 sm:p-6 lg:p-4 md:p-8" scroll-region>
            <slot />
        </main>

    </div>
    </ThemeProvider>
</template>
