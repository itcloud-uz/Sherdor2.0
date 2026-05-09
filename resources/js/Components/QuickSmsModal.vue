<template>
    <Modal :show="show" @close="closeModal" maxWidth="md">
        <div class="glass-panel p-6 border border-neon-cyan/50 bg-[#020205] relative overflow-hidden">
            <!-- Background Glow -->
            <div class="absolute -top-40 -right-40 w-96 h-96 bg-neon-cyan/20 blur-[100px] pointer-events-none"></div>
            
            <div class="relative z-10">
                <div class="flex justify-between items-center mb-6 border-b border-white/10 pb-4">
                    <h2 class="text-xl font-display font-black text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-neon-cyan">💬</span> Tezkor SMS
                    </h2>
                    <button @click="closeModal" class="text-text-muted hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase tracking-widest">Qabul qiluvchi (Mijoz)</label>
                        <div class="glass-input w-full bg-black/50 text-white flex items-center gap-2 opacity-70 cursor-not-allowed">
                            <span class="text-xs">📞</span>
                            <span class="font-mono">{{ lead?.phone }}</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase tracking-widest">Xabar matni</label>
                        <textarea v-model="message" rows="4" class="glass-input w-full focus:border-neon-cyan focus:ring-neon-cyan resize-none" placeholder="Assalomu alaykum, ..."></textarea>
                        <p class="text-[9px] font-mono text-text-muted mt-1 text-right">{{ message.length }} belgilar</p>
                    </div>
                </div>

                <div class="mt-8 flex gap-3">
                    <button @click="closeModal" type="button" class="flex-1 py-2 bg-black/50 border border-white/20 text-white text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-white/10 transition-all">{{ $t('cancel') }}</button>
                    <button @click="sendSms" :disabled="isSending || !message.trim()" class="flex-1 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-cyan hover:text-black transition-all shadow-[0_0_10px_rgba(0,240,255,0.2)] disabled:opacity-50 flex justify-center items-center gap-2">
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
