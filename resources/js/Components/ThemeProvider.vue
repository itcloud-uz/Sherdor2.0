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
    const modalBg = isDarkTheme ? '#1f2937' : '#ffffff';
    const modalText = isDarkTheme ? '#f9fafb' : '#111827';
    document.documentElement.style.setProperty('--modal-bg-color', modalBg);
    document.documentElement.style.setProperty('--modal-text-color', modalText);

    // Premium high-contrast unit status colors
    const unitAvailableBg = isDarkTheme ? 'rgba(16, 185, 129, 0.15)' : '#d1fae5';
    const unitAvailableBorder = isDarkTheme ? '#10b981' : '#059669';
    const unitAvailableText = isDarkTheme ? '#34d399' : '#065f46';

    const unitReservedBg = isDarkTheme ? 'rgba(245, 158, 11, 0.15)' : '#fef3c7';
    const unitReservedBorder = isDarkTheme ? '#f59e0b' : '#d97706';
    const unitReservedText = isDarkTheme ? '#fbbf24' : '#92400e';

    const unitSoldBg = isDarkTheme ? 'rgba(239, 68, 68, 0.15)' : '#fee2e2';
    const unitSoldBorder = isDarkTheme ? '#ef4444' : '#dc2626';
    const unitSoldText = isDarkTheme ? '#f87171' : '#991b1b';

    document.documentElement.style.setProperty('--unit-available-bg', unitAvailableBg);
    document.documentElement.style.setProperty('--unit-available-border', unitAvailableBorder);
    document.documentElement.style.setProperty('--unit-available-text', unitAvailableText);

    document.documentElement.style.setProperty('--unit-reserved-bg', unitReservedBg);
    document.documentElement.style.setProperty('--unit-reserved-border', unitReservedBorder);
    document.documentElement.style.setProperty('--unit-reserved-text', unitReservedText);

    document.documentElement.style.setProperty('--unit-sold-bg', unitSoldBg);
    document.documentElement.style.setProperty('--unit-sold-border', unitSoldBorder);
    document.documentElement.style.setProperty('--unit-sold-text', unitSoldText);

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
