<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

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

const showPassword = ref(false);
const canvasRef = ref(null);
let animationFrameId = null;

// Floating Bioluminescent Spores / Fireflies Animation
onMounted(() => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    let width = canvas.width = window.innerWidth;
    let height = canvas.height = window.innerHeight;

    const handleResize = () => {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    };
    window.addEventListener('resize', handleResize);

    const particles = [];
    const colors = ['#10b981', '#06b6d4', '#a855f7', '#34d399', '#c084fc'];

    for (let i = 0; i < 45; i++) {
        particles.push({
            x: Math.random() * width,
            y: Math.random() * height,
            radius: Math.random() * 2.5 + 1,
            color: colors[Math.floor(Math.random() * colors.length)],
            vx: (Math.random() - 0.5) * 0.6,
            vy: -Math.random() * 0.8 - 0.2,
            alpha: Math.random() * 0.7 + 0.3,
            pulseSpeed: Math.random() * 0.03 + 0.01,
            pulseVal: Math.random() * Math.PI,
        });
    }

    const render = () => {
        ctx.clearRect(0, 0, width, height);

        for (const p of particles) {
            p.y += p.vy;
            p.x += p.vx + Math.sin(p.pulseVal) * 0.3;
            p.pulseVal += p.pulseSpeed;

            if (p.y < -10) {
                p.y = height + 10;
                p.x = Math.random() * width;
            }
            if (p.x < -10) p.x = width + 10;
            if (p.x > width + 10) p.x = -10;

            const currentAlpha = (Math.sin(p.pulseVal) * 0.3 + 0.7) * p.alpha;

            ctx.save();
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = p.color;
            ctx.globalAlpha = currentAlpha;
            ctx.shadowBlur = 12;
            ctx.shadowColor = p.color;
            ctx.fill();
            ctx.restore();
        }

        animationFrameId = requestAnimationFrame(render);
    };

    render();

    onUnmounted(() => {
        window.removeEventListener('resize', handleResize);
        if (animationFrameId) cancelAnimationFrame(animationFrameId);
    });
});
</script>

<template>
    <Head title="Tizimga Kirish - IQS ERP">
        <link rel="preload" as="image" href="/images/lush-tropical-city.jpg" />
    </Head>

    <!-- Vibrant Lush Tropical Cyber Metropolis Experience -->
    <div class="min-h-screen w-full relative flex items-center justify-center font-sans text-white overflow-hidden bg-[#040609] selection:bg-emerald-400 selection:text-black">
        
        <!-- High-Resolution Lush Tropical Futuristic Rainforest Background -->
        <div class="absolute inset-0 bg-[url('/images/lush-tropical-city.jpg')] bg-cover bg-center bg-no-repeat transform scale-100 animate-[tropicalZoom_30s_infinite_alternate] z-0"></div>
        
        <!-- Smooth Lighting Gradients (Light enough to show emerald greenery brilliantly) -->
        <div class="absolute inset-0 bg-gradient-to-r from-black/75 via-black/35 to-black/65 z-0"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(16,185,129,0.25)_0%,transparent_50%),radial-gradient(ellipse_at_top_right,rgba(168,85,247,0.3)_0%,transparent_60%)] z-0 pointer-events-none"></div>

        <!-- Floating Bioluminescent Spores Canvas -->
        <canvas ref="canvasRef" class="absolute inset-0 z-1 pointer-events-none"></canvas>

        <!-- Ambient Glowing Highlights -->
        <div class="fixed top-[-10%] left-[-5%] w-[500px] h-[500px] rounded-full bg-emerald-500/20 blur-[150px] pointer-events-none"></div>
        <div class="fixed bottom-[-10%] right-[-5%] w-[550px] h-[550px] rounded-full bg-violet-600/25 blur-[160px] pointer-events-none"></div>

        <!-- Main Grid Workspace -->
        <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative z-10 grid lg:grid-cols-12 gap-8 items-center min-h-screen">
            
            <!-- Left Side: Brand Story & Lush Tropical Future Concept (7 cols) -->
            <div class="hidden lg:flex lg:col-span-7 flex-col justify-between h-full py-12 pr-6">
                
                <!-- Brand Header -->
                <div class="flex items-center gap-3.5">
                    <Link :href="route('landing')" class="flex items-center gap-3.5 group">
                        <div class="w-13 h-13 rounded-2xl bg-gradient-to-tr from-emerald-400 via-cyan-400 to-purple-500 p-[2px] shadow-[0_0_35px_rgba(16,185,129,0.5)] group-hover:scale-105 transition-transform">
                            <div class="w-full h-full bg-[#080c10]/90 backdrop-blur-md rounded-[14px] flex items-center justify-center font-black text-xl text-emerald-400">
                                IQS
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-2xl font-black tracking-tight text-white flex items-center gap-2">
                                IQS ERP
                                <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-400/40 text-[10px] font-bold text-emerald-300 shadow-[0_0_10px_rgba(16,185,129,0.3)]">
                                    2.0 ULTRA
                                </span>
                            </span>
                            <span class="text-[10px] text-emerald-300 font-semibold uppercase tracking-wider">
                                Qur va Sotish • Men Quraman va Sotaman!
                            </span>
                        </div>
                    </Link>
                </div>

                <!-- Main Pitch Headline with Tropical Bioluminescent Glow -->
                <div class="my-auto max-w-xl space-y-6">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-950/40 border border-emerald-400/30 backdrop-blur-xl shadow-[0_0_25px_rgba(16,185,129,0.25)]">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                        <span class="text-xs font-bold text-emerald-200 uppercase tracking-wider">🌴 Ekologik & Zamonaviy Qurilish Ekotizimi</span>
                    </div>

                    <h1 class="text-4xl xl:text-5xl font-black tracking-tight leading-[1.15] text-white">
                        Kelajak Shahri va <br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 via-cyan-300 to-purple-400 drop-shadow-[0_0_40px_rgba(16,185,129,0.5)]">
                            Tropik Ekotizim Boshqaruvi
                        </span>
                    </h1>

                    <p class="text-gray-200 text-base xl:text-lg leading-relaxed font-normal drop-shadow-md">
                        Ko'p qavatli bino va turar-joy majmualarini yagona platformada boshqaring: 3D shaxmatka, smeta, kassa, omborxona va sotuv jarayonlarini to'liq avtomatlashtiring.
                    </p>

                    <!-- Feature Glow Badges -->
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <div class="p-3.5 rounded-2xl bg-black/40 border border-emerald-500/20 backdrop-blur-md flex items-center gap-3 hover:border-emerald-400/50 transition-all shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 flex items-center justify-center text-lg shadow-[0_0_15px_rgba(16,185,129,0.4)]">
                                🏢
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-white">3D Shaxmatka</h4>
                                <p class="text-[10px] text-emerald-300/80">Real vaqt monitoringi</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-black/40 border border-cyan-500/20 backdrop-blur-md flex items-center gap-3 hover:border-cyan-400/50 transition-all shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                            <div class="w-10 h-10 rounded-xl bg-cyan-500/20 border border-cyan-400/30 text-cyan-300 flex items-center justify-center text-lg shadow-[0_0_15px_rgba(6,182,212,0.4)]">
                                📊
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-white">Smeta & Ombor</h4>
                                <p class="text-[10px] text-cyan-300/80">Materiallar balansi</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-black/40 border border-purple-500/20 backdrop-blur-md flex items-center gap-3 hover:border-purple-400/50 transition-all shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                            <div class="w-10 h-10 rounded-xl bg-purple-500/20 border border-purple-400/30 text-purple-300 flex items-center justify-center text-lg shadow-[0_0_15px_rgba(168,85,247,0.4)]">
                                🤝
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-white">CRM & Shartnomalar</h4>
                                <p class="text-[10px] text-purple-300/80">Avtomatlashtirilgan</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-black/40 border border-emerald-500/20 backdrop-blur-md flex items-center gap-3 hover:border-emerald-400/50 transition-all shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 flex items-center justify-center text-lg shadow-[0_0_15px_rgba(16,185,129,0.4)]">
                                📱
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-white">SMS & Kassa</h4>
                                <p class="text-[10px] text-emerald-300/80">To'lovlar grafigi</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Partners & Developer Link -->
                <div class="pt-6 border-t border-white/10 flex items-center justify-between text-xs text-gray-300">
                    <div class="flex items-center gap-4 flex-wrap">
                        <span class="font-bold text-white">Sher-Dor</span>
                        <span class="text-emerald-400">•</span>
                        <span class="font-bold text-white">Hayot House</span>
                        <span class="text-emerald-400">•</span>
                        <span class="font-bold text-white">Baraka Residence</span>
                    </div>

                    <a href="https://itcloud.uz" target="_blank" class="text-cyan-300 font-bold hover:text-cyan-200 transition-colors flex items-center gap-1">
                        ITCloud.uz tomonidan taqdim etiladi ↗
                    </a>
                </div>

            </div>

            <!-- Right Side: Ultra-Glass Organic Login Card (5 cols) -->
            <div class="lg:col-span-5 flex flex-col items-center justify-center w-full">
                
                <div class="w-full max-w-md p-8 sm:p-10 rounded-[36px] bg-[#070d12]/75 backdrop-blur-3xl border border-emerald-400/30 shadow-[0_20px_70px_rgba(16,185,129,0.3)] transition-all duration-300 relative overflow-hidden">
                    
                    <!-- Decorative Top Tropical Aurora Line -->
                    <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-emerald-400 via-cyan-400 to-purple-500"></div>
                    <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-500/20 rounded-full blur-[70px] pointer-events-none"></div>

                    <!-- Mobile Brand Icon -->
                    <div class="lg:hidden flex items-center justify-center mb-6">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-emerald-400 via-cyan-400 to-purple-500 p-[1.5px] shadow-[0_0_25px_rgba(16,185,129,0.5)]">
                            <div class="w-full h-full bg-[#080c10] rounded-[14px] flex items-center justify-center font-black text-2xl text-emerald-400">
                                IQS
                            </div>
                        </div>
                    </div>

                    <!-- Card Header -->
                    <div class="text-center mb-8 relative z-10">
                        <h2 class="text-2xl sm:text-3xl font-black text-white mb-2 tracking-tight">Tizimga Kirish</h2>
                        <p class="text-emerald-200/80 text-xs sm:text-sm font-light">Boshqaruv kabinetiga xush kelibsiz</p>
                    </div>

                    <!-- Status Message -->
                    <div v-if="status" class="mb-6 p-3.5 rounded-2xl bg-emerald-500/20 border border-emerald-400/40 text-emerald-200 text-xs text-center font-medium">
                        {{ status }}
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submit" class="space-y-5 relative z-10">
                        
                        <!-- Email Input -->
                        <div>
                            <label for="email" class="block text-xs font-bold text-gray-200 uppercase tracking-wider mb-2">Email Manzil</label>
                            <div class="relative">
                                <span class="absolute left-4 top-3.5 text-emerald-400 text-sm">✉️</span>
                                <input
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="admin@itcloud.uz"
                                    class="w-full pl-11 pr-4 py-3.5 bg-black/50 border border-white/20 text-white placeholder-gray-400 rounded-2xl focus:border-emerald-400 focus:shadow-[0_0_25px_rgba(16,185,129,0.4)] focus:outline-none transition-all text-sm font-mono"
                                />
                            </div>
                            <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-400 font-medium">{{ form.errors.email }}</p>
                        </div>

                        <!-- Password Input -->
                        <div>
                            <label for="password" class="block text-xs font-bold text-gray-200 uppercase tracking-wider mb-2">Parol</label>
                            <div class="relative">
                                <span class="absolute left-4 top-3.5 text-emerald-400 text-sm">🔒</span>
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="form.password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                    class="w-full pl-11 pr-11 py-3.5 bg-black/50 border border-white/20 text-white placeholder-gray-400 rounded-2xl focus:border-emerald-400 focus:shadow-[0_0_25px_rgba(16,185,129,0.4)] focus:outline-none transition-all text-sm font-mono"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3.5 top-3.5 text-gray-300 hover:text-emerald-300 transition-colors"
                                    tabindex="-1"
                                >
                                    <span class="text-xs">{{ showPassword ? '🙈' : '👁️' }}</span>
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-400 font-medium">{{ form.errors.password }}</p>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between text-xs pt-1">
                            <label class="flex items-center gap-2 cursor-pointer select-none text-gray-200 hover:text-white">
                                <input 
                                    type="checkbox" 
                                    v-model="form.remember" 
                                    class="w-4 h-4 rounded-md bg-black/50 border-white/30 text-emerald-500 focus:ring-emerald-400 focus:ring-offset-0 transition-all cursor-pointer"
                                />
                                <span>Eslab qolish</span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-gray-300 hover:text-emerald-300 transition-colors hover:underline"
                            >
                                Parolni unutdingizmi?
                            </Link>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full py-4 rounded-2xl bg-gradient-to-r from-emerald-400 via-cyan-400 to-purple-500 hover:from-emerald-300 hover:to-purple-400 text-black font-black text-sm sm:text-base uppercase tracking-wider shadow-[0_0_35px_rgba(16,185,129,0.6)] hover:shadow-[0_0_50px_rgba(6,182,212,0.8)] hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-50 flex items-center justify-center gap-2.5"
                            >
                                <span v-if="form.processing" class="w-4 h-4 border-2 border-black border-t-transparent rounded-full animate-spin"></span>
                                <span>Tizimga Kirish</span>
                                <span v-if="!form.processing">→</span>
                            </button>
                        </div>

                    </form>

                    <!-- Navigation Shortcuts -->
                    <div class="mt-8 pt-6 border-t border-white/10 flex items-center justify-between text-xs text-gray-300 relative z-10">
                        <Link :href="route('landing')" class="hover:text-emerald-300 transition-colors flex items-center gap-1">
                            <span>←</span> Bosh sahifa
                        </Link>
                        <Link :href="route('promo.offers')" class="text-cyan-300 hover:underline flex items-center gap-1 font-semibold">
                            Uy sotib olish ↗
                        </Link>
                    </div>

                </div>

                <!-- Footer Tag -->
                <p class="mt-6 text-[11px] text-gray-300 text-center font-mono">
                    © 2026 ITCloud.uz • IQS ERP Platform
                </p>

            </div>

        </div>

    </div>
</template>

<style scoped>
@keyframes tropicalZoom {
    0% {
        transform: scale(1.0);
    }
    50% {
        transform: scale(1.05);
    }
    100% {
        transform: scale(1.0);
    }
}

/* Chrome, Safari, Edge autofill style override */
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 1000px rgba(7, 13, 18, 0.95) inset !important;
    -webkit-text-fill-color: #ffffff !important;
    transition: background-color 5000s ease-in-out 0s;
}

input:autofill {
    background-color: rgba(7, 13, 18, 0.95) !important;
    color: #ffffff !important;
}
</style>
