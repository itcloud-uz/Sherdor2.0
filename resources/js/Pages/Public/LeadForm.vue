<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import GalaxyBackground from '@/Components/GalaxyBackground.vue';
import axios from 'axios';

const props = defineProps({
    branding: Object,
});

const form = ref({
    full_name: '',
    phone: '',
    source: 'landing_page',
});

const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const submitLead = async () => {
    isSubmitting.value = true;
    errorMessage.value = '';
    
    try {
        const response = await axios.post(route('api.leads.store'), form.value);
        successMessage.value = response.data.message;
        form.value.full_name = '';
        form.value.phone = '';
    } catch (error) {
        if (error.response?.data?.errors) {
            errorMessage.value = Object.values(error.response.data.errors).flat().join('\n');
        } else {
            errorMessage.value = error.response?.data?.message || 'Xatolik yuz berdi. Iltimos keyinroq urinib ko\'ring.';
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Head title="Maxsus Taklif - Sher-Dor ERP" />
    
    <div class="min-h-screen text-white font-sans overflow-hidden relative flex flex-col items-center justify-center selection:bg-neon-cyan/30 selection:text-white">
        
        <!-- Animated Galaxy Background -->
        <GalaxyBackground :interactive="true" />

        <main class="relative z-10 w-full max-w-md px-6 py-12 flex flex-col items-center">
            
            <div class="w-16 h-16 mb-8 rounded-2xl bg-gradient-to-br from-neon-cyan to-neon-blue flex items-center justify-center shadow-[0_0_30px_rgba(0,240,255,0.5)]">
                <span class="font-display font-bold text-3xl text-black">S</span>
            </div>

            <div class="glass-panel w-full p-8 rounded-3xl border border-white/10 backdrop-blur-2xl shadow-[0_0_50px_rgba(0,0,0,0.8)] relative overflow-hidden">
                <!-- Decorative glow -->
                <div class="absolute -top-20 -right-20 w-40 h-40 bg-neon-cyan/20 blur-[60px] rounded-full pointer-events-none"></div>
                <div class="absolute -bottom-20 -left-20 w-40 h-40 bg-neon-magenta/20 blur-[60px] rounded-full pointer-events-none"></div>

                <div v-if="successMessage" class="text-center relative z-10 py-8">
                    <div class="w-20 h-20 mx-auto rounded-full bg-neon-green/20 border-2 border-neon-green flex items-center justify-center mb-6 shadow-[0_0_20px_rgba(57,255,20,0.4)]">
                        <span class="text-4xl">✨</span>
                    </div>
                    <h2 class="text-2xl font-display font-bold text-white mb-2 tracking-wide text-shadow-glow">Arizangiz qabul qilindi!</h2>
                    <p class="text-neon-green font-mono text-sm leading-relaxed">{{ successMessage }}</p>
                    
                    <button @click="successMessage = ''" class="mt-8 px-6 py-2 bg-white/10 hover:bg-white/20 border border-white/20 text-white font-mono text-sm uppercase tracking-widest rounded-xl transition-all">
                        Yangi ariza
                    </button>
                </div>

                <div v-else class="relative z-10">
                    <div class="text-center mb-8">
                        <h1 class="text-2xl md:text-3xl font-display font-bold text-transparent bg-clip-text bg-gradient-to-r from-neon-cyan to-white mb-2 tracking-wide">
                            Maxsus Taklif!
                        </h1>
                        <p class="text-text-muted text-sm font-light">Telefon raqamingizni qoldiring va bepul konsultatsiyaga ega bo'ling.</p>
                    </div>

                    <form @submit.prevent="submitLead" class="space-y-5">
                        
                        <div v-if="errorMessage" class="p-3 bg-neon-red/10 border border-neon-red/50 text-neon-red text-xs font-mono rounded-xl">
                            {{ errorMessage }}
                        </div>

                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1.5 ml-1">Ismingiz</label>
                            <input 
                                type="text" 
                                v-model="form.full_name" 
                                required 
                                placeholder="Masalan: Azizbek"
                                class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white font-mono text-sm focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all shadow-inner"
                            >
                        </div>

                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1.5 ml-1">Telefon Raqamingiz</label>
                            <input 
                                type="tel" 
                                v-model="form.phone" 
                                required 
                                placeholder="+998 90 123 45 67"
                                class="w-full bg-black/40 border border-white/10 rounded-xl p-4 text-white font-mono text-sm focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all shadow-inner"
                            >
                        </div>

                        <button 
                            type="submit" 
                            :disabled="isSubmitting" 
                            class="w-full mt-4 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan py-4 rounded-xl font-display font-bold tracking-widest uppercase hover:bg-neon-cyan hover:text-black hover:shadow-[0_0_30px_rgba(0,240,255,0.5)] transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                        >
                            <span v-if="isSubmitting" class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                            {{ isSubmitting ? 'Yuborilmoqda...' : 'Yuborish' }}
                        </button>
                    </form>
                </div>
            </div>
            
            <p class="mt-8 text-[10px] font-mono text-white/30 tracking-widest uppercase">
                &copy; {{ new Date().getFullYear() }} Sher-Dor ERP System
            </p>
        </main>
    </div>
</template>

<style scoped>
.glass-panel {
    background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
}
</style>
