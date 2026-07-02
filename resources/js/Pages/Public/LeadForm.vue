<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import GalaxyBackground from '@/Components/GalaxyBackground.vue';
import axios from 'axios';

const props = defineProps({
    branding: Object,
    promoSettings: Object,
    availableUnits: {
        type: Array,
        default: () => []
    }
});

const uzbekistanRegions = [
    'Toshkent shahri', 'Toshkent viloyati', 'Andijon viloyati', 'Buxoro viloyati',
    'Farg\'ona viloyati', 'Jizzax viloyati', 'Xorazm viloyati', 'Namangan viloyati',
    'Navoiy viloyati', 'Qashqadaryo viloyati', 'Qoraqalpog\'iston Respublikasi',
    'Samarqand viloyati', 'Sirdaryo viloyati', 'Surxondaryo viloyati'
];

const form = ref({
    full_name: '',
    phone: '',
    region: '',
    district: '',
    comment: '',
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
        successMessage.value = props.promoSettings?.promo_success_message || response.data.message;
        form.value.full_name = '';
        form.value.phone = '';
        form.value.region = '';
        form.value.district = '';
        form.value.comment = '';
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
    <Head :title="'Maxsus Taklif - ' + ($page.props.branding?.app_name || 'Sher-Dor ERP')" />
    
    <div class="min-h-screen text-white font-sans overflow-hidden relative flex flex-col items-center justify-center selection:bg-neon-cyan/30 selection:text-white">
        
        <!-- Animated Galaxy Background -->
        <GalaxyBackground :interactive="true" />

        <main class="relative z-10 w-full max-w-6xl px-6 py-12 flex flex-col items-center">
            
            <div class="w-16 h-16 mb-8 rounded-2xl bg-gradient-to-br from-neon-cyan to-neon-blue flex items-center justify-center shadow-[0_0_30px_rgba(0,240,255,0.5)]">
                <span class="font-display font-bold text-3xl text-black">S</span>
            </div>

            <div class="w-full flex flex-col-reverse lg:flex-row gap-8 items-start justify-center">
                
                <!-- LEFT SIDE: PROMO CONTENT & AVAILABLE UNITS -->
                <div class="flex-1 w-full space-y-8">
                    
                    <!-- PROMO CONTENT -->
                    <div v-if="promoSettings && (promoSettings.promo_title || promoSettings.promo_image)" class="glass-panel p-6 md:p-8 rounded-3xl border border-white/10 shadow-[0_0_50px_rgba(0,0,0,0.5)] relative overflow-hidden flex flex-col">
                        <div class="absolute -top-20 -left-20 w-40 h-40 bg-neon-magenta/20 blur-[60px] rounded-full pointer-events-none"></div>
                        
                        <div class="relative z-10 flex flex-col">
                            <img v-if="promoSettings.promo_image" :src="'/storage/' + promoSettings.promo_image" class="w-full h-48 md:h-80 object-cover rounded-2xl border border-white/5 mb-6 shadow-lg" alt="Promo Image">
                            
                            <h1 v-if="promoSettings.promo_title" class="text-3xl md:text-5xl font-display font-bold text-transparent bg-clip-text bg-gradient-to-r from-neon-magenta to-white mb-2 tracking-wide leading-tight">
                                {{ promoSettings.promo_title }}
                            </h1>
                            <p v-if="promoSettings.promo_subtitle" class="text-neon-cyan font-mono text-sm tracking-widest uppercase mb-6">{{ promoSettings.promo_subtitle }}</p>
                            
                            <div v-if="promoSettings.promo_description" class="prose prose-invert prose-sm md:prose-base max-w-none text-text-muted font-light leading-relaxed whitespace-pre-wrap">
                                {{ promoSettings.promo_description }}
                            </div>
                        </div>
                    </div>

                    <!-- AVAILABLE UNITS LISTING -->
                    <div v-if="availableUnits && availableUnits.length > 0" class="glass-panel p-6 md:p-8 rounded-3xl border border-white/10 shadow-[0_0_50px_rgba(0,0,0,0.5)] relative overflow-hidden">
                        <div class="absolute -bottom-20 -right-20 w-64 h-64 bg-neon-magenta/20 blur-[80px] rounded-full pointer-events-none"></div>
                        
                        <div class="mb-8 relative z-10">
                            <h2 class="text-2xl md:text-3xl font-display font-bold text-transparent bg-clip-text bg-gradient-to-r from-neon-cyan to-neon-magenta mb-2 tracking-wide">
                                Sotuvdagi Xonadonlar (Mavjud variantlar)
                            </h2>
                            <p class="text-text-muted font-light text-sm">O'zingizga ma'qul bo'lgan xonadonni tanlang va xarid qiling!</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6 relative z-10">
                            <div v-for="unit in availableUnits" :key="unit.id" class="bg-black/40 border border-white/10 rounded-2xl p-4 md:p-5 hover:border-neon-cyan/50 hover:shadow-[0_0_20px_rgba(0,240,255,0.2)] transition-all group backdrop-blur-md flex flex-col">
                                <div class="flex justify-between items-start mb-4 border-b border-white/10 pb-4">
                                    <div>
                                        <span class="px-2 py-1 bg-neon-green/20 text-neon-green text-[9px] md:text-[10px] font-mono font-bold uppercase tracking-widest rounded-full mb-2 inline-block">Sotuvda</span>
                                        <h3 class="text-base md:text-lg font-bold text-white font-display flex items-center gap-2">
                                            <span class="text-neon-cyan">🏢</span> {{ unit.building_name }}
                                        </h3>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3 md:gap-4 mb-4 flex-1">
                                    <div>
                                        <div class="text-[9px] md:text-[10px] font-mono text-text-muted uppercase tracking-widest mb-0.5">Qavat</div>
                                        <div class="text-xs md:text-sm font-semibold text-white">{{ unit.floor_level }}-qavat</div>
                                    </div>
                                    <div>
                                        <div class="text-[9px] md:text-[10px] font-mono text-text-muted uppercase tracking-widest mb-0.5">Xonadon №</div>
                                        <div class="text-xs md:text-sm font-semibold text-white">№{{ unit.unit_number }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[9px] md:text-[10px] font-mono text-text-muted uppercase tracking-widest mb-0.5">Xonalar</div>
                                        <div class="text-xs md:text-sm font-semibold text-white">{{ unit.rooms }} xona</div>
                                    </div>
                                    <div>
                                        <div class="text-[9px] md:text-[10px] font-mono text-text-muted uppercase tracking-widest mb-0.5">Maydoni</div>
                                        <div class="text-xs md:text-sm font-semibold text-white">{{ unit.area }} m²</div>
                                    </div>
                                </div>
                                
                                <button @click="form.comment = `Sizdagi ${unit.building_name} binosi, ${unit.floor_level}-qavat, №${unit.unit_number} xonadon (${unit.rooms} xona, ${unit.area} m²) ga qiziqyapman.`; window.scrollTo({ top: 0, behavior: 'smooth' });" class="mt-auto w-full py-2.5 md:py-3 rounded-xl border border-white/20 text-white font-mono text-[10px] md:text-xs tracking-widest uppercase hover:bg-white hover:text-black transition-colors focus:ring-2 focus:ring-white">
                                    Qiziqish bildirish
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT SIDE: LEAD FORM (Sticky) -->
                <div class="w-full lg:w-[420px] xl:w-[450px] shrink-0 lg:sticky lg:top-8 h-fit glass-panel p-5 md:p-8 rounded-3xl border border-white/10 shadow-[0_0_50px_rgba(0,0,0,0.8)] relative overflow-hidden">
                    <!-- Decorative glow -->
                    <div class="absolute -top-20 -right-20 w-40 h-40 bg-neon-cyan/20 blur-[60px] rounded-full pointer-events-none"></div>

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
                        <div class="text-center mb-6 md:mb-8">
                            <h2 class="text-2xl md:text-3xl font-display font-bold text-transparent bg-clip-text bg-gradient-to-r from-neon-cyan to-white mb-2 tracking-wide">
                                {{ promoSettings?.promo_form_title || (promoSettings?.promo_title ? 'Ro\'yxatdan O\'tish' : 'Maxsus Taklif!') }}
                            </h2>
                            <p class="text-text-muted text-xs md:text-sm font-light">{{ promoSettings?.promo_form_subtitle || 'Telefon raqamingizni qoldiring va bepul konsultatsiyaga ega bo\'ling.' }}</p>
                        </div>

                        <form @submit.prevent="submitLead" class="space-y-4 md:space-y-5">
                            
                            <div v-if="errorMessage" class="p-3 bg-neon-red/10 border border-neon-red/50 text-neon-red text-xs font-mono rounded-xl">
                                {{ errorMessage }}
                            </div>

                            <div>
                                <label class="block text-[9px] md:text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1 md:mb-1.5 ml-1">Ismingiz</label>
                                <input 
                                    type="text" 
                                    v-model="form.full_name" 
                                    required 
                                    placeholder="Masalan: Azizbek"
                                    class="w-full bg-[#1e293b] border border-gray-600 rounded-xl p-3 md:p-4 text-white font-mono text-xs md:text-sm focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all shadow-inner"
                                >
                            </div>

                            <div>
                                <label class="block text-[9px] md:text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1 md:mb-1.5 ml-1">Telefon Raqamingiz</label>
                                <input 
                                    type="tel" 
                                    v-model="form.phone" 
                                    required 
                                    placeholder="+998 90 123 45 67"
                                    class="w-full bg-[#1e293b] border border-gray-600 rounded-xl p-3 md:p-4 text-white font-mono text-xs md:text-sm focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all shadow-inner"
                                >
                            </div>

                            <div class="grid grid-cols-2 gap-3 md:gap-4">
                                <div>
                                    <label class="block text-[9px] md:text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1 md:mb-1.5 ml-1">Viloyat</label>
                                    <select 
                                        v-model="form.region" 
                                        class="w-full bg-[#1e293b] border border-gray-600 rounded-xl p-3 md:p-4 text-white font-mono text-xs md:text-sm focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all shadow-inner"
                                    >
                                        <option value="" disabled>Tanlang</option>
                                        <option v-for="region in uzbekistanRegions" :key="region" :value="region">{{ region }}</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-[9px] md:text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1 md:mb-1.5 ml-1">Tuman</label>
                                    <input 
                                        type="text" 
                                        v-model="form.district" 
                                        placeholder="Tuman nomi"
                                        class="w-full bg-[#1e293b] border border-gray-600 rounded-xl p-3 md:p-4 text-white font-mono text-xs md:text-sm focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all shadow-inner"
                                    >
                                </div>
                            </div>

                            <div>
                                <label class="block text-[9px] md:text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1 md:mb-1.5 ml-1">Qo'shimcha Izoh</label>
                                <textarea 
                                    v-model="form.comment" 
                                    rows="2"
                                    placeholder="Xarid maqsadi yoki boshqa ma'lumotlar..."
                                    class="w-full bg-[#1e293b] border border-gray-600 rounded-xl p-3 md:p-4 text-white font-mono text-xs md:text-sm focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all shadow-inner resize-none"
                                ></textarea>
                            </div>

                            <button 
                                type="submit" 
                                :disabled="isSubmitting" 
                                class="w-full mt-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan py-3 md:py-4 rounded-xl font-display font-bold tracking-widest uppercase hover:bg-neon-cyan hover:text-black hover:shadow-[0_0_30px_rgba(0,240,255,0.5)] transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <span v-if="isSubmitting" class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                                {{ isSubmitting ? 'Yuborilmoqda...' : (promoSettings?.promo_button_text || 'Yuborish') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <p class="mt-8 text-[10px] font-mono text-white/30 tracking-widest uppercase">
                &copy; {{ new Date().getFullYear() }} {{ $page.props.branding?.app_name || 'Sher-Dor ERP System' }}
            </p>
        </main>
    </div>
</template>

<style scoped>
.glass-panel {
    background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
}
</style>
