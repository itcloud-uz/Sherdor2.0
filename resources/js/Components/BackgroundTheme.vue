<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import GalaxyBackground from '@/Components/GalaxyBackground.vue';

const page = usePage();
const themeStyle = computed(() => page.props.auth?.user?.theme_style || 'apple_glass');
const customBg = computed(() => page.props.branding?.background_image_path);

</script>

<template>
    <!-- CUSTOM BACKGROUND IMAGE OVERLAY -->
    <template v-if="customBg">
        <div class="fixed inset-0 pointer-events-none z-0 bg-gray-500/50"></div>
    </template>
    
    <template v-else>
        <!-- GALAXY THEME -->
        <template v-if="themeStyle === 'galaxy'">
        <GalaxyBackground />
    </template>

    <!-- APPLE GLASS THEME -->
    <template v-else-if="themeStyle === 'apple_glass'">
        <div class="fixed inset-0 pointer-events-none z-0 bg-white">
            <!-- macOS style vibrant mesh gradient orbs -->
            <div class="absolute top-[-10%] left-[-10%] w-[50vw] h-[50vw] rounded-full bg-gradient-to-br from-indigo-600/40 to-purple-600/40 blur-[140px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[60vw] h-[60vw] rounded-full bg-gradient-to-tl from-blue-500/40 to-cyan-400/40 blur-[140px]"></div>
            <div class="absolute top-[30%] right-[30%] w-[30vw] h-[30vw] rounded-full bg-pink-600/30 blur-[120px]"></div>
        </div>
    </template>

    <!-- CYBERPUNK THEME -->
    <template v-else-if="themeStyle === 'cyberpunk'">
        <div class="fixed inset-0 pointer-events-none z-0 bg-[#020202]">
            <!-- Intense Neon Orbs -->
            <div class="absolute top-1/4 left-1/4 w-[500px] h-[500px] rounded-full bg-[var(--color-neon-primary)]/20 blur-[120px]"></div>
            <div class="absolute bottom-1/4 right-1/4 w-[600px] h-[600px] rounded-full bg-[#FF003C]/20 blur-[120px]"></div>
            
            <!-- Cyberpunk Scanlines (Horizontal subtle lines) -->
            <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[length:100%_4px]"></div>
        </div>
    </template>

    <!-- MINIMALIST THEME -->
    <template v-else-if="themeStyle === 'minimalist'">
        <div class="fixed inset-0 pointer-events-none z-0 bg-[#09090B]"></div>
    </template>

    <!-- GOLD ROYAL THEME -->
    <template v-else-if="themeStyle === 'gold'">
        <div class="fixed inset-0 pointer-events-none z-0 bg-[#0A0500]">
            <div class="absolute -top-[20%] -left-[10%] w-[800px] h-[800px] rounded-full bg-[#FFD700]/10 blur-[150px]"></div>
            <div class="absolute top-[40%] -right-[10%] w-[600px] h-[600px] rounded-full bg-[#FF8C00]/10 blur-[150px]"></div>
            <div class="absolute -bottom-[20%] left-[20%] w-[700px] h-[700px] rounded-full bg-[#FFA500]/5 blur-[150px]"></div>
        </div>
    </template>

        <!-- ITCLOUD CYBERGLASS -->
        <template v-else-if="themeStyle === 'itcloud_cyberglass'">
            <!-- Interactive Galaxy Background -->
            <GalaxyBackground :interactive="true" />
            
            <!-- Apple Glass / Cyberglass overlay -->
            <div class="fixed inset-0 pointer-events-none z-0">
                <div class="absolute top-[10%] left-[20%] w-[40%] h-[40%] rounded-full bg-[#00F0FF]/10 blur-[180px]"></div>
                <div class="absolute bottom-[10%] right-[20%] w-[30%] h-[30%] rounded-full bg-[#B026FF]/10 blur-[180px]"></div>
            </div>
        </template>
    </template>
</template>
