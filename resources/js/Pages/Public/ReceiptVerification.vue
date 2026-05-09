<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({
    transaction: Object,
    contractDetails: Object
});

const isLoading = ref(true);

onMounted(() => {
    // Simulate verification delay for visual effect
    setTimeout(() => {
        isLoading.value = false;
    }, 2000);
});

// Format currency
const currency = (value) => {
    return new Intl.NumberFormat('uz-UZ').format(value || 0) + " so'm";
};

// Mask phone number
const maskPhone = (phone) => {
    if (!phone) return 'N/A';
    // e.g. +998901234567 -> +998 90 *** ** 67
    if (phone.length === 13) {
        return phone.substring(0, 4) + ' ' + phone.substring(4, 6) + ' *** ** ' + phone.substring(11, 13);
    }
    return phone;
};
</script>

<template>
    <Head title="To'lovni Tasdiqlash | Sher-Dor ERP" />

    <div class="min-h-screen bg-[#050505] bg-[url('/bg-noise.png')] bg-repeat text-white flex items-center justify-center p-4 relative overflow-hidden font-mono">
        <!-- Ambient lights -->
        <div class="fixed top-[-20%] left-[-10%] w-[50%] h-[50%] bg-neon-cyan/20 blur-[150px] rounded-full pointer-events-none"></div>
        <div class="fixed bottom-[-20%] right-[-10%] w-[50%] h-[50%] bg-neon-green/20 blur-[150px] rounded-full pointer-events-none"></div>

        <div class="w-full max-w-lg z-10">
            <!-- Loading State -->
            <div v-if="isLoading" class="flex flex-col items-center justify-center h-[600px]">
                <div class="w-24 h-24 border-4 border-white/10 border-t-neon-green rounded-full animate-spin mb-8 shadow-[0_0_30px_rgba(57,255,20,0.5)]"></div>
                <h2 class="text-2xl font-display font-bold animate-pulse text-neon-green tracking-widest text-center">To'lov Tasdiqlanmoqda...</h2>
                <p class="text-text-muted mt-4 text-sm uppercase tracking-widest">Ma'lumotlar bazasi tekshirilmoqda</p>
                <div class="mt-8 font-mono text-[10px] text-white/30">ID: {{ transaction.receipt_hash }}</div>
            </div>

            <!-- Success State -->
            <div v-else class="glass-panel p-4 md:p-8 rounded-3xl border border-neon-green/30 shadow-[0_0_50px_rgba(57,255,20,0.15)] animate-[fadeIn_1s_ease-out] relative overflow-hidden group">
                <div class="absolute inset-0 bg-neon-green/5 opacity-0 group-hover:opacity-100 transition-opacity duration-1000"></div>
                
                <!-- Header -->
                <div class="text-center mb-8 relative z-10">
                    <div class="w-20 h-20 bg-neon-green/20 rounded-full mx-auto flex items-center justify-center mb-6 shadow-[0_0_30px_rgba(57,255,20,0.4)]">
                        <span class="text-4xl text-neon-green">✓</span>
                    </div>
                    <h1 class="text-3xl font-display font-bold text-white mb-2 tracking-wide drop-shadow-[0_0_10px_rgba(57,255,20,0.5)]">Tasdiqlandi</h1>
                    <p class="text-neon-green text-sm uppercase tracking-widest font-bold">To'lovingiz qabul qilindi</p>
                    <p class="text-text-muted text-xs mt-2">Xaridingiz uchun rahmat!</p>
                </div>

                <!-- Transaction Details -->
                <div class="space-y-4 mb-8 relative z-10">
                    <div class="p-4 bg-black/40 rounded-xl border border-white/5 flex justify-between items-center">
                        <span class="text-xs text-text-muted uppercase tracking-widest">{{ $t('payment_amount_label') }}</span>
                        <span class="text-xl font-bold text-neon-cyan drop-shadow-[0_0_10px_rgba(0,240,255,0.4)]">
                            {{ currency(transaction.amount) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-4 bg-black/40 rounded-xl border border-white/5">
                            <span class="block text-[10px] text-text-muted uppercase mb-1">To'lov Usuli</span>
                            <span class="text-white text-sm uppercase font-bold">{{ transaction.payment_method }}</span>
                        </div>
                        <div class="p-4 bg-black/40 rounded-xl border border-white/5">
                            <span class="block text-[10px] text-text-muted uppercase mb-1">{{ $t('date_label') }}</span>
                            <span class="text-white text-sm font-bold">{{ new Date(transaction.updated_at).toLocaleDateString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Client Info -->
                <div v-if="transaction.payment_schedule_id && transaction.payment_schedule" class="p-5 border border-white/10 rounded-2xl bg-gradient-to-br from-white/5 to-transparent relative z-10 mb-8">
                    <h3 class="text-xs uppercase tracking-widest text-white/50 mb-4 border-b border-white/10 pb-2">Mijoz Ma'lumotlari</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-text-muted">Ism-Sharif:</span>
                            <span class="text-white font-bold">{{ transaction.payment_schedule.contract?.client?.first_name }} {{ transaction.payment_schedule.contract?.client?.last_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-muted">Kvartira (Obyekt):</span>
                            <span class="text-white font-bold">{{ contractDetails?.unit || 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-muted">Telefon:</span>
                            <span class="text-white font-bold">{{ maskPhone(transaction.payment_schedule.contract?.client?.phone) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Contract Balances -->
                <div v-if="contractDetails" class="space-y-3 relative z-10">
                    <div class="flex justify-between items-center text-xs p-2 rounded bg-white/5">
                        <span class="text-text-muted">Umumiy Shartnoma Summasi:</span>
                        <span class="text-white font-bold">{{ currency(contractDetails.total_price) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs p-2 rounded bg-neon-red/10 border border-neon-red/20">
                        <span class="text-neon-red font-bold">Qoldiq Qarz:</span>
                        <span class="text-neon-red font-bold">{{ currency(contractDetails.remaining) }}</span>
                    </div>
                </div>

                <!-- Footer / Support -->
                <div class="mt-8 pt-6 border-t border-white/10 text-center relative z-10">
                    <p class="text-[10px] text-text-muted mb-2">Tranzaksiya ID: <span class="text-white">{{ transaction.receipt_hash }}</span></p>
                    <p class="text-[10px] text-white/30 uppercase tracking-widest">Sher-Dor ERP - ITCloud tomonidan ishlab chiqilgan</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=JetBrains+Mono:wght@400;700&family=Outfit:wght@400;700;900&display=swap');

.font-display { font-family: 'Outfit', sans-serif; }
.font-mono { font-family: 'JetBrains Mono', monospace; }

.text-neon-cyan { color: #00F0FF; }
.text-neon-green { color: #39FF14; }
.text-neon-red { color: #FF0000; }
.text-text-muted { color: #9ca3af; }

.glass-panel {
    background: rgba(10, 10, 10, 0.6);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}
</style>
