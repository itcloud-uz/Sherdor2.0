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
        const color = page.props.branding.primary_theme_color || '#00F0FF';
        const glow = page.props.branding.neon_glow_intensity || '0 0 15px';
        const rgb = hexToRgb(color);
        
        document.documentElement.style.setProperty('--color-neon-primary', color);
        document.documentElement.style.setProperty('--shadow-sm', `${glow} rgba(${rgb}, 0.3)`);
    }

    if (page.props.auth?.user) {
        const user = page.props.auth.user;
        document.documentElement.style.setProperty('--label-font-size', (user.label_font_size || 12) + 'px');
        document.documentElement.style.setProperty('--label-color', user.label_color || '#CCCCCC');
        document.documentElement.style.setProperty('--dropdown-bg-color', user.dropdown_bg_color || '#111111');
        document.documentElement.style.setProperty('--dropdown-text-color', user.dropdown_text_color || '#ffffff');
    }

    const themeStyle = page.props.auth?.user?.theme_style || 'apple_glass';
    document.documentElement.classList.remove('theme-apple_glass', 'theme-galaxy', 'theme-cyberpunk', 'theme-minimalist', 'theme-gold', 'theme-itcloud_cyberglass');
    document.documentElement.classList.add(`theme-${themeStyle}`);
});
</script>
<template>
    <slot />
</template>
