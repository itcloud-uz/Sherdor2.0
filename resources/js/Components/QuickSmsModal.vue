<template>
    <Modal :show="show" @close="closeModal" maxWidth="md">
        <div class="p-6 relative overflow-hidden">
            <!-- Background Glow -->
            <div class="absolute -top-40 -right-40 w-96 h-96 bg-blue-500/10 blur-[100px] pointer-events-none"></div>
            
            <div class="relative z-10">
                <div class="flex justify-between items-center mb-6 border-b border-gray-200/20 pb-4">
                    <h2 class="text-xl font-display font-black uppercase tracking-wider flex items-center gap-2">
                        <span class="text-blue-600">💬</span> Tezkor SMS
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase tracking-widest">Qabul qiluvchi (Mijoz)</label>
                        <div class="border border-gray-200/30 rounded-md shadow-sm px-3 py-2 w-full bg-gray-50 flex items-center gap-2 opacity-70 cursor-not-allowed">
                            <span class="text-xs">📞</span>
                            <span class="font-mono">{{ lead?.phone }}</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase tracking-widest">Xabar matni</label>
                        <textarea v-model="message" rows="4" class="border rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 w-full resize-none" placeholder="Assalomu alaykum, ..."></textarea>
                        <p class="text-[9px] font-mono text-gray-500 mt-1 text-right">{{ message.length }} belgilar</p>
                    </div>
                </div>

                <div class="mt-8 flex gap-3">
                    <button @click="closeModal" type="button" class="flex-1 py-2 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-all">{{ $t('cancel') }}</button>
                    <button @click="sendSms" :disabled="isSending || !message.trim()" class="flex-1 py-2 bg-blue-600/10 border border-blue-500/20 text-blue-600 dark:text-blue-400 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-blue-600 hover:text-white transition-all shadow-sm disabled:opacity-50 flex justify-center items-center gap-2">
                        <span v-if="isSending" class="animate-pulse">Yuborilmoqda...</span>
                        <template v-else>
                            <span>🚀</span> Yuborish
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
    lead: Object
});

const emit = defineEmits(['update:show', 'sent']);

const message = ref('');
const isSending = ref(false);

watch(() => props.show, (newVal) => {
    if (newVal) {
        message.value = ''; // reset on open
    }
});

function closeModal() {
    emit('update:show', false);
}

function sendSms() {
    if (!message.value.trim() || !props.lead) return;
    
    isSending.value = true;
    router.post(route('admin.crm.leads.quick_sms', props.lead.id), {
        message: message.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSending.value = false;
            emit('sent', props.lead);
            closeModal();
        },
        onError: () => {
            isSending.value = false;
        }
    });
}
</script>
