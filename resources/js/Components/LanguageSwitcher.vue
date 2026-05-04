<script setup>
import { useI18n } from 'vue-i18n';
import { ref } from 'vue';

const { locale } = useI18n();
const isOpen = ref(false);

const languages = [
    { code: 'uz', name: 'O\'zbek', flag: '🇺🇿' },
    { code: 'ru', name: 'Русский', flag: '🇷🇺' },
    { code: 'en', name: 'English', flag: '🇬🇧' }
];

function setLanguage(lang) {
    locale.value = lang;
    localStorage.setItem('locale', lang);
    isOpen.value = false;
    document.documentElement.lang = lang;
}

function currentLang() {
    return languages.find(l => l.code === locale.value) || languages[0];
}
</script>

<template>
    <div class="relative">
        <button 
            @click="isOpen = !isOpen" 
            class="flex items-center gap-2 px-3 py-1.5 rounded-xl border border-glass-border bg-glass hover:bg-glass-hover transition-all text-sm font-semibold text-text-secondary hover:text-text-primary backdrop-blur-xl"
        >
            <span class="text-lg">{{ currentLang().flag }}</span>
            <span class="uppercase tracking-wider text-[10px]">{{ currentLang().code }}</span>
        </button>

        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div 
                v-if="isOpen" 
                class="absolute right-0 mt-2 w-32 rounded-xl bg-black/80 backdrop-blur-3xl border border-white/10 shadow-depth overflow-hidden z-50"
            >
                <div class="py-1">
                    <button 
                        v-for="lang in languages" 
                        :key="lang.code"
                        @click="setLanguage(lang.code)"
                        :class="[
                            'w-full text-left px-4 py-2 text-sm flex items-center gap-2 transition-colors',
                            locale === lang.code 
                                ? 'bg-neon-cyan/10 text-neon-cyan font-bold' 
                                : 'text-text-secondary hover:bg-white/5 hover:text-text-primary'
                        ]"
                    >
                        <span>{{ lang.flag }}</span>
                        <span>{{ lang.name }}</span>
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>
