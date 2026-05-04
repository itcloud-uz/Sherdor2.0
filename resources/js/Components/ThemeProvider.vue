<script setup>
import { usePage } from '@inertiajs/vue3';
import { watchEffect } from 'vue';

const page = usePage();

function hexToRgb(hex) {
    var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : '0, 240, 255';
}

watchEffect(() => {
    if (page.props.branding) {
        const color = page.props.branding.primary_theme_color;
        const glow = page.props.branding.neon_glow_intensity;
        const rgb = hexToRgb(color);
        
        document.documentElement.style.setProperty('--color-neon-primary', color);
        // We use rgba for the shadow to add 30% opacity (0.3 = ~76 in hex, or just rgba)
        document.documentElement.style.setProperty('--shadow-neon-primary', `${glow} rgba(${rgb}, 0.3)`);
    }
});
</script>
<template>
    <slot />
</template>
