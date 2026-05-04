<template>
    <div class="min-h-screen bg-[#050505] flex items-center justify-center p-4 relative overflow-hidden font-mono">
        <!-- Abstract Background Elements -->
        <div class="absolute inset-0 z-0 opacity-20 pointer-events-none">
            <div class="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-neon-cyan/20 rounded-full blur-[120px]"></div>
            <div class="absolute bottom-1/4 right-1/4 w-[600px] h-[600px] bg-neon-purple/20 rounded-full blur-[150px]"></div>
        </div>

        <div class="w-full max-w-md z-10">
            <div class="glass-panel p-8 rounded-2xl border border-white/10 shadow-[0_0_50px_rgba(0,0,0,0.5)] backdrop-blur-xl">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-display font-bold text-white tracking-widest mb-2 uppercase">Sher-Dor</h1>
                    <p class="text-neon-cyan text-sm tracking-widest uppercase">Xavfsizlik Darvozasi</p>
                </div>

                <div v-if="!is_internal" class="bg-black/40 border border-neon-yellow/30 p-4 rounded-xl mb-6">
                    <p class="text-xs text-text-muted text-center leading-relaxed">
                        Siz skaner qilgan chek <span class="text-white font-bold">{{ client_name_masked }}</span> ga tegishli. Maxfiylikni ta'minlash maqsadida chekni to'liq ko'rish uchun mijoz ma'lumotlarini tasdiqlang.
                    </p>
                </div>
                <div v-else class="bg-black/40 border border-neon-red/30 p-4 rounded-xl mb-6">
                    <p class="text-xs text-text-muted text-center leading-relaxed">
                        Ushbu chek kompaniyaning maxfiy ichki amaliyotiga tegishli. Ko'rish uchun xodim darajasida tizimga kiring.
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <template v-if="!is_internal">
                        <!-- Phone Input -->
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-2 tracking-widest uppercase">Telefon raqam</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 text-sm">📞</span>
                                </div>
                                <input 
                                    v-model="form.phone" 
                                    type="text" 
                                    class="glass-input w-full pl-10 text-white bg-black/50" 
                                    placeholder="+998 90 123 45 67"
                                    required
                                >
                            </div>
                        </div>

                        <!-- Passport Input -->
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-2 tracking-widest uppercase">Pasport / ID Karta Seriyasi</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 text-sm">🪪</span>
                                </div>
                                <input 
                                    v-model="form.passport" 
                                    type="text" 
                                    class="glass-input w-full pl-10 text-white bg-black/50 uppercase" 
                                    placeholder="Masalan: AB1234567"
                                    required
                                >
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <!-- Email Input -->
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-2 tracking-widest uppercase">Elektron Pochta / Login</label>
                            <input 
                                v-model="form.email" 
                                type="email" 
                                class="glass-input w-full text-white bg-black/50" 
                                placeholder="admin@itcloud.uz"
                                required
                            >
                        </div>

                        <!-- Password Input -->
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-2 tracking-widest uppercase">Maxfiy Parol</label>
                            <input 
                                v-model="form.password" 
                                type="password" 
                                class="glass-input w-full text-white bg-black/50" 
                                placeholder="••••••••"
                                required
                            >
                        </div>
                    </template>

                    <!-- Error Message -->
                    <div v-if="form.errors.message" class="p-3 bg-neon-red/10 border border-neon-red/30 rounded-xl text-center">
                        <p class="text-xs text-neon-red font-mono">{{ form.errors.message }}</p>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="w-full py-4 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan rounded-xl hover:bg-neon-cyan hover:text-black font-mono text-sm tracking-widest transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed uppercase font-bold mt-4"
                    >
                        <span v-if="form.processing">Tekshirilmoqda...</span>
                        <span v-else>Tasdiqlash va Ochish</span>
                    </button>
                </form>
            </div>
            
            <p class="text-center text-[10px] text-gray-600 mt-6 font-mono">
                &copy; {{ new Date().getFullYear() }} ITCloud. Barcha huquqlar himoyalangan.
            </p>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    hash: String,
    client_name_masked: String,
    is_internal: {
        type: Boolean,
        default: false
    }
});

const form = useForm({
    phone: '',
    passport: '',
    email: '',
    password: ''
});

function submit() {
    form.post(route('public.receipt.auth', props.hash), {
        preserveScroll: true
    });
}
</script>

<style scoped>
.glass-panel {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.01) 100%);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.glass-input {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    padding: 0.75rem;
    color: white;
    font-family: monospace;
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.glass-input:focus {
    outline: none;
    border-color: rgba(0, 240, 255, 0.5);
    box-shadow: 0 0 15px rgba(0, 240, 255, 0.1);
    background: rgba(0, 0, 0, 0.5);
}
</style>
