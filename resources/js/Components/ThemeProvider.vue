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

    const themeStyle = page.props.auth?.user?.theme_style || 'apple_glass';
    const isDarkTheme = ['galaxy', 'cyberpunk', 'minimalist', 'gold', 'itcloud_cyberglass'].includes(themeStyle);

    // Intelligent theme-responsive defaults
    const defaultLabelColor = isDarkTheme ? '#CCCCCC' : '#374151';
    const defaultDropdownBg = isDarkTheme ? '#111111' : '#FFFFFF';
    const defaultDropdownText = isDarkTheme ? '#ffffff' : '#111827';
    const bodyBg = isDarkTheme ? '#05050A' : '#F9FAFB';
    const bodyText = isDarkTheme ? '#FFFFFF' : '#111827';
    const colorScheme = isDarkTheme ? 'dark' : 'light';

    // Glass input dynamic variables
    const glassInputBg = isDarkTheme ? 'rgba(0, 0, 0, 0.5)' : 'rgba(255, 255, 255, 0.9)';
    const glassInputBorder = isDarkTheme ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
    const glassInputText = isDarkTheme ? '#FFFFFF' : '#111827';
    const glassInputPlaceholder = isDarkTheme ? 'rgba(255, 255, 255, 0.4)' : 'rgba(0, 0, 0, 0.4)';

    if (page.props.auth?.user) {
        const user = page.props.auth.user;
        
        // Use database customized value OR fall back to the intelligent theme-responsive default
        let labelColor = user.label_color;
        if (!labelColor || labelColor === '#CCCCCC') {
            labelColor = defaultLabelColor;
        }
        
        let dropdownBg = user.dropdown_bg_color;
        if (!dropdownBg || dropdownBg === '#111111') {
            dropdownBg = defaultDropdownBg;
        }
        
        let dropdownText = user.dropdown_text_color;
        if (!dropdownText || dropdownText === '#ffffff') {
            dropdownText = defaultDropdownText;
        }

        document.documentElement.style.setProperty('--label-font-size', (user.label_font_size || 12) + 'px');
        document.documentElement.style.setProperty('--label-color', labelColor);
        document.documentElement.style.setProperty('--dropdown-bg-color', dropdownBg);
        document.documentElement.style.setProperty('--dropdown-text-color', dropdownText);
    } else {
        // Guest mode fallback styles
        document.documentElement.style.setProperty('--label-font-size', '12px');
        document.documentElement.style.setProperty('--label-color', defaultLabelColor);
        document.documentElement.style.setProperty('--dropdown-bg-color', defaultDropdownBg);
        document.documentElement.style.setProperty('--dropdown-text-color', defaultDropdownText);
    }

    // Set globally shared layout variables
    document.documentElement.style.setProperty('--body-bg-color', bodyBg);
    document.documentElement.style.setProperty('--body-text-color', bodyText);
    document.documentElement.style.setProperty('--select-color-scheme', colorScheme);
    document.documentElement.style.setProperty('--glass-input-bg', glassInputBg);
    document.documentElement.style.setProperty('--glass-input-border', glassInputBorder);
    document.documentElement.style.setProperty('--glass-input-text', glassInputText);
    document.documentElement.style.setProperty('--glass-input-placeholder', glassInputPlaceholder);

    document.documentElement.classList.remove('theme-apple_glass', 'theme-galaxy', 'theme-cyberpunk', 'theme-minimalist', 'theme-gold', 'theme-itcloud_cyberglass');
    document.documentElement.classList.add(`theme-${themeStyle}`);
});
</script>
<template>
    <slot />
</template>
