<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, Object],
        default: ''
    },
    options: {
        type: Array,
        default: () => []
    },
    labelKey: {
        type: String,
        default: 'label'
    },
    valueKey: {
        type: String,
        default: 'value'
    },
    placeholder: {
        type: String,
        default: 'Tanlang...'
    },
    required: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false
    },
    customClass: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref(null);

const filteredOptions = computed(() => {
    if (!searchQuery.value) return props.options;
    const query = searchQuery.value.toLowerCase();
    return props.options.filter(option => {
        const label = String(getLabel(option)).toLowerCase();
        return label.includes(query);
    });
});

const selectedOption = computed(() => {
    return props.options.find(option => String(getValue(option)) === String(props.modelValue));
});

function getLabel(option) {
    if (typeof option === 'object' && option !== null) {
        // Handle nested paths like user.name or just simple keys
        return props.labelKey.split('.').reduce((o, i) => (o ? o[i] : ''), option) || '';
    }
    return option;
}

function getValue(option) {
    if (typeof option === 'object' && option !== null) {
        return props.valueKey.split('.').reduce((o, i) => (o !== undefined && o !== null ? o[i] : ''), option);
    }
    return option;
}

function toggleDropdown() {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
    }
}

function selectOption(option) {
    const val = getValue(option);
    emit('update:modelValue', val);
    emit('change', option);
    isOpen.value = false;
}

function handleClickOutside(event) {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<template>
    <div class="relative w-full" ref="dropdownRef">
        <!-- Main Select Button -->
        <div 
            @click="toggleDropdown"
            class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full pl-3 pr-8 cursor-pointer flex items-center min-h-[38px] text-xs select-none"
            :class="[disabled ? 'opacity-50 cursor-not-allowed' : '', customClass]"
            :style="{ 
                borderColor: isOpen ? '#3b82f6' : '',
                boxShadow: isOpen ? '0 0 0 1px rgba(59, 130, 246, 0.5)' : ''
            }"
        >
            <span v-if="selectedOption" class="text-gray-900 truncate pr-2 font-medium">
                {{ getLabel(selectedOption) }}
            </span>
            <span v-else class="text-gray-500 truncate pr-2">
                {{ placeholder }}
            </span>
            
            <div class="absolute right-2.5 top-1/2 transform -translate-y-1/2 pointer-events-none text-gray-400">
                <svg v-if="isOpen" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </div>
        </div>

        <!-- Hidden input for form binding -->
        <input 
            type="hidden" 
            :value="modelValue" 
        >

        <!-- Dropdown Options -->
        <div 
            v-if="isOpen" 
            class="absolute z-50 w-full mt-1 max-h-60 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl searchable-select-dropdown"
        >
            <!-- Search Input -->
            <div class="sticky top-0 p-2 z-10 border-b border-gray-100 bg-gray-50">
                <div class="relative">
                    <input 
                        type="text" 
                        v-model="searchQuery" 
                        class="w-full bg-white border border-gray-200 rounded-md py-1 pl-7 pr-2 text-xs text-gray-900 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Izlash..."
                        @click.stop
                    >
                    <div class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </div>
                </div>
            </div>

            <!-- Options List -->
            <div class="py-1">
                <div 
                    v-for="(option, index) in filteredOptions" 
                    :key="index"
                    @click="selectOption(option)"
                    class="px-3 py-1.5 hover:bg-blue-600 hover:text-white cursor-pointer text-xs transition-colors flex items-center justify-between"
                    :class="{ 'bg-blue-50 text-blue-700 font-bold': String(getValue(option)) === String(modelValue) }"
                >
                    <span>{{ getLabel(option) }}</span>
                    <span v-if="String(getValue(option)) === String(modelValue)" class="text-blue-600 font-bold text-[10px]">✓</span>
                </div>
                
                <div v-if="filteredOptions.length === 0" class="px-4 py-3 text-xs text-center text-gray-400 italic">
                    Hech narsa topilmadi
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Scrollbar qismini yashirish yoki chiroyli qilish */
div::-webkit-scrollbar {
    width: 6px;
}
div::-webkit-scrollbar-track {
    background: transparent;
}
div::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}
div::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.4);
}
</style>
