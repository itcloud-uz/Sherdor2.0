<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);

const toggle = () => {
    emit('update:modelValue', !props.modelValue);
};
</script>

<template>
    <button 
        type="button"
        role="switch"
        :aria-checked="modelValue"
        @click="toggle"
        class="relative inline-flex items-center w-[48px] h-[24px] rounded-[12px] transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:ring-offset-2"
        :class="modelValue ? 'bg-[#10B981]' : 'bg-[#E2E8F0]'"
    >
        <span 
            class="inline-block w-[20px] h-[20px] bg-white rounded-full transition-transform duration-200 ease-in-out shadow-sm ml-[2px]"
            :class="modelValue ? 'translate-x-[24px]' : 'translate-x-[0px]'"
        />
    </button>
</template>
