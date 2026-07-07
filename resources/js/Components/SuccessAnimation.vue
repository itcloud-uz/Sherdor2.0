<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const isVisible = ref(false);
const message = ref('Muvaffaqiyatli saqlandi!');
let timeoutId = null;

function triggerSuccess(customMessage) {
    if (timeoutId) {
        clearTimeout(timeoutId);
        isVisible.value = false;
    }
    
    message.value = customMessage || 'Muvaffaqiyatli saqlandi!';
    
    // Tiny delay to restart CSS animations
    setTimeout(() => {
        isVisible.value = true;
        
        timeoutId = setTimeout(() => {
            isVisible.value = false;
        }, 1500);
    }, 50);
}

const handleEvent = (e) => {
    triggerSuccess(e.detail?.message);
};

onMounted(() => {
    window.addEventListener('show-success-notification', handleEvent);
});

onUnmounted(() => {
    window.removeEventListener('show-success-notification', handleEvent);
    if (timeoutId) clearTimeout(timeoutId);
});
</script>

<template>
    <div
        v-if="isVisible"
        class="fixed inset-0 z-[9999] flex items-center justify-center pointer-events-none transition-all duration-300"
        :class="isVisible ? 'bg-slate-900/10 backdrop-blur-[1px]' : 'bg-transparent opacity-0 pointer-events-none'"
    >
        <div
            class="bg-white/95 backdrop-blur-md border border-emerald-500/20 shadow-2xl rounded-3xl p-6 w-64 flex flex-col items-center justify-center text-center transform transition-all duration-300"
            :class="isVisible ? 'scale-100 opacity-100 pointer-events-auto' : 'scale-90 opacity-0 pointer-events-none'"
        >
            <div class="w-20 h-20 flex items-center justify-center relative mb-4">
                <svg class="w-16 h-16 text-emerald-500 mx-auto" viewBox="0 0 52 52">
                    <circle class="checkmark-circle" cx="26" cy="26" r="23" fill="none" />
                    <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                </svg>
            </div>
            
            <h3 class="text-sm font-sans font-bold text-slate-800 tracking-wide uppercase select-none">
                {{ message }}
            </h3>
        </div>
    </div>
</template>

<style scoped>
.checkmark-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 4;
    stroke-miterlimit: 10;
    stroke: #10b981;
    fill: none;
    animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.checkmark-check {
    transform-origin: 50% 50%;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    stroke-width: 4;
    stroke: #10b981;
    stroke-linecap: round;
    animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.6s forwards;
}

@keyframes stroke {
    100% {
        stroke-dashoffset: 0;
    }
}
</style>
