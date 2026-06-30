<script setup>
import { ref } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    isOpen: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['toggle']);
</script>

<template>
    <div class="mb-3">
        <!-- HEADER -->
        <div 
            @click="emit('toggle')"
            class="flex items-center justify-between px-5 py-4 rounded-lg cursor-pointer transition-all duration-200"
            :class="[
                isOpen 
                    ? 'bg-white border border-blue-600 shadow-[0_2px_8px_rgba(37,99,235,0.1)] rounded-b-none border-b-0' 
                    : 'bg-slate-50 border border-slate-200 hover:bg-slate-100 hover:border-slate-400'
            ]"
        >
            <h4 class="text-[16px] font-semibold text-slate-800">{{ title }}</h4>
            <span class="text-blue-600 transition-transform duration-300" :class="{ 'rotate-180': isOpen }">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </span>
        </div>

        <!-- CONTENT -->
        <div 
            v-show="isOpen"
            class="px-5 py-6 bg-white border border-slate-200 border-t-0 rounded-b-lg overflow-hidden animate-[slideDown_0.3s_ease-out]"
        >
            <slot />
        </div>
    </div>
</template>

<style scoped>
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
