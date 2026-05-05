<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GalaxyBackground from '@/Components/GalaxyBackground.vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Tizimga Kirish - Sher-Dor ERP" />

    <div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden selection:bg-white/30 selection:text-white font-sans text-white">
        
        <!-- Authentic Galaxy Background -->
        <GalaxyBackground />

        <div class="w-full max-w-md relative z-10 animate-[fadeIn_0.5s_ease-out]">
            
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-[1.5rem] bg-gradient-to-tr from-gray-200 to-white shadow-[0_0_30px_rgba(255,255,255,0.15)] mb-6 backdrop-blur-xl">
                    <span class="font-display font-black text-4xl text-black">S</span>
                </div>
                <h1 class="text-3xl font-display font-bold text-white tracking-widest">SHER-DOR</h1>
                <p class="text-gray-400 font-medium text-sm mt-2">Boshqaruv Tizimiga Kirish</p>
            </div>

            <!-- Apple Glassmorphism Panel -->
            <div class="bg-white/5 p-8 rounded-[2rem] border border-white/10 shadow-[0_0_50px_rgba(0,0,0,0.5)] backdrop-blur-3xl">
                
                <div v-if="status" class="mb-6 text-sm font-medium text-green-400 bg-green-400/10 border border-green-400/20 p-3 rounded-xl text-center">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-2 ml-1">Email manzil</label>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full px-5 py-4 bg-black/40 border border-white/10 text-white placeholder-white/30 rounded-2xl focus:ring-2 focus:ring-white/30 focus:border-white/30 transition-all backdrop-blur-md"
                            placeholder="admin@itcloud.uz"
                        />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-400 font-medium">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2 ml-1">Parol</label>
                        <input
                            id="password"
                            type="password"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            class="w-full px-5 py-4 bg-black/40 border border-white/10 text-white placeholder-white/30 rounded-2xl focus:ring-2 focus:ring-white/30 focus:border-white/30 transition-all backdrop-blur-md"
                            placeholder="••••••••"
                        />
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-400 font-medium">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between mt-2">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" v-model="form.remember" class="w-5 h-5 rounded-md border-white/20 bg-black/50 text-white focus:ring-white/50 focus:ring-offset-black transition-all">
                            <span class="text-sm text-gray-400 group-hover:text-white transition-colors">Eslab qolish</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-gray-400 hover:text-white transition-colors hover:underline"
                        >
                            Parolni unutdingizmi?
                        </Link>
                    </div>

                    <div class="pt-4">
                        <button
                            type="submit"
                            class="w-full px-6 py-4 bg-white text-black font-display font-bold text-lg rounded-2xl hover:bg-gray-200 shadow-[0_0_20px_rgba(255,255,255,0.2)] hover:shadow-[0_0_30px_rgba(255,255,255,0.4)] transition-all transform active:scale-95 disabled:opacity-50 flex justify-center items-center gap-3"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" class="animate-spin -ml-1 h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            TIZIMGA KIRISH
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="text-center mt-8">
                <Link :href="route('landing')" class="text-sm font-medium text-gray-400 hover:text-white transition-colors border-b border-dashed border-white/20 pb-1">
                    ← Asosiy sahifaga qaytish
                </Link>
            </div>
        </div>
    </div>
</template>
