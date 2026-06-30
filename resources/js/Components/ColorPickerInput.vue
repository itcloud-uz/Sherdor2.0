<script setup>
import { ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '#2563EB'
    },
    label: {
        type: String,
        default: 'Rang tanlang'
    }
});

const emit = defineEmits(['update:modelValue']);

const copySuccess = ref(false);

const updateColor = (event) => {
    emit('update:modelValue', event.target.value);
};

const copyToClipboard = () => {
    navigator.clipboard.writeText(props.modelValue).then(() => {
        copySuccess.value = true;
        setTimeout(() => {
            copySuccess.value = false;
        }, 2000);
    });
};
</script>

<template>
    <div class="flex flex-col">
        <label v-if="label" class="block text-[12px] font-medium text-[#64748B] mb-[6px]">{{ label }}</label>
        <div class="flex items-center gap-4">
            <!-- Color Preview / Picker -->
            <div 
                class="relative w-[60px] h-[60px] rounded-[8px] border-[2px] border-[#E2E8F0] overflow-hidden shrink-0 shadow-sm cursor-pointer"
                :style="{ backgroundColor: modelValue }"
            >
                <input 
                    type="color" 
                    :value="modelValue"
                    @input="updateColor"
                    class="absolute inset-0 w-[150%] h-[150%] top-[-25%] left-[-25%] cursor-pointer opacity-0"
                />
            </div>
            
            <!-- Hex Input and Copy Button -->
            <div class="flex-1 max-w-[200px] flex items-center border border-[#E2E8F0] rounded-[8px] bg-white h-[40px] focus-within:border-[#2563EB] focus-within:ring-[3px] focus-within:ring-[#2563EB]/10 transition-all">
                <input 
                    type="text" 
                    :value="modelValue"
                    @input="updateColor"
                    class="w-full pl-[12px] pr-[8px] py-[10px] text-[14px] font-regular text-[#1E293B] bg-transparent border-none focus:ring-0 uppercase font-mono"
                    maxlength="7"
                />
                <button 
                    type="button" 
                    @click="copyToClipboard"
                    class="px-[12px] text-[#64748B] hover:text-[#2563EB] transition-colors h-full flex items-center justify-center border-l border-[#E2E8F0]"
                    :title="copySuccess ? 'Nusxa olindi!' : 'Nusxa olish'"
                >
                    <svg v-if="!copySuccess" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </button>
            </div>
        </div>
    </div>
</template>
