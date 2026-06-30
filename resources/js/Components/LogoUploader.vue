<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [File, String],
        default: null
    },
    label: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue']);
const fileInput = ref(null);
const isDragging = ref(false);

const previewUrl = computed(() => {
    if (!props.modelValue) return null;
    if (typeof props.modelValue === 'string') return props.modelValue;
    return URL.createObjectURL(props.modelValue);
});

const triggerSelect = () => {
    fileInput.value.click();
};

const handleFile = (file) => {
    if (file && file.type.startsWith('image/')) {
        emit('update:modelValue', file);
    } else {
        alert("Faqat rasm fayllarini yuklash mumkin!");
    }
};

const onFileSelect = (event) => {
    const file = event.target.files[0];
    handleFile(file);
};

const onDrop = (event) => {
    isDragging.value = false;
    const file = event.dataTransfer.files[0];
    handleFile(file);
};
</script>

<template>
    <div class="flex flex-col">
        <label v-if="label" class="block text-[12px] font-medium text-[#64748B] mb-[6px]">{{ label }}</label>
        
        <div 
            @click="triggerSelect"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            class="relative flex flex-col items-center justify-center w-[120px] h-[120px] rounded-[12px] border-[2px] border-dashed cursor-pointer transition-colors"
            :class="[
                isDragging ? 'border-[#2563EB] bg-[#2563EB]/5' : 'border-[#E2E8F0] bg-[#F8FAFC] hover:bg-[#F1F5F9]'
            ]"
        >
            <input 
                ref="fileInput" 
                type="file" 
                accept="image/*" 
                class="hidden" 
                @change="onFileSelect" 
            />
            
            <template v-if="previewUrl">
                <img :src="previewUrl" alt="Logo Preview" class="w-[100px] h-[100px] object-contain rounded-[8px]" />
                <!-- Overlay on hover for changing -->
                <div class="absolute inset-0 bg-black/40 rounded-[12px] flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                    <span class="text-white text-[12px] font-medium">O'zgartirish</span>
                </div>
            </template>
            <template v-else>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-[#94A3B8] mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <span class="text-[#64748B] text-[10px] text-center px-2 leading-tight">Logotipni shu yerga qo'ying yoki bosing</span>
            </template>
        </div>
    </div>
</template>
